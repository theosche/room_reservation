<?php
namespace Theosche\RoomReservation;

class UploaderException extends \Exception{}
class UploaderFileNotFoundException extends UploaderException{}

class WebdavUploader
{
    private $username = WEBDAVUSER;
    private $password = WEBDAVPASS;
    private $basePath = WEBDAVSAVEPATH;
    private $webdavBaseUrl = WEBDAV_ENDPOINT;
    private $ocsEndpoint = OCS_ENDPOINT;
	
	public function uploadFileContents($fileContents, $destinationPath) {
        static $secondTry = false;
        $url = str_replace(' ', '%20', rtrim($this->webdavBaseUrl,'/') . '/' . trim($this->basePath,'/') . '/' . ltrim($destinationPath, '/'));
        $tempStream = fopen('php://temp', 'rw+');
		fwrite($tempStream, $fileContents);
		rewind($tempStream);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $tempStream);
        curl_setopt($ch, CURLOPT_INFILESIZE, strlen($fileContents));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($tempStream);
		
        if ($statusCode === 201 || $statusCode === 204) { // 204 if overwrite
        	$secondTry = false;
            return true;
        } else {
        	if ($secondTry) {
        		$secondTry = false;
	            throw new UploaderException("Failed to upload file. Status code: $statusCode. Response: $response");
	        } else {
	        	$secondTry = true;
	        	$this->newFolder(self::getParent($destinationPath));
	        	$this->uploadFileContents($fileContents, $destinationPath);
	        }
        }
    }
    
    public function uploadFile($filePath, $destinationPath) {
    	static $secondTry = false;
        $url = str_replace(' ', '%20', rtrim($this->webdavBaseUrl,'/') . '/' . trim($this->basePath,'/') . '/' . ltrim($destinationPath, '/'));
        $fileContents = file_get_contents($filePath);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, fopen($filePath, 'r'));
        curl_setopt($ch, CURLOPT_INFILESIZE, strlen($fileContents));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode === 201 || $statusCode === 204) { // 204 if overwrite
        	$secondTry = false;
            return true;
        } else {
        	if ($secondTry) {
        		$secondTry = false;
	            throw new UploaderException("Failed to upload file. Status code: $statusCode. Response: $response");
	        } else {
	        	$secondTry = true;
	        	$this->newFolder(self::getParent($destinationPath));
	        	$this->uploadFile($filePath, $destinationPath);
	        }
        }
    }
    
    public function delete($destinationPath) {
        $url = str_replace(' ', '%20', rtrim($this->webdavBaseUrl,'/') . '/' . trim($this->basePath,'/') . '/' . ltrim($destinationPath, '/'));

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);

		$response = curl_exec($ch);
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($statusCode === 204) {
			return true;
		} elseif ($statusCode === 404) {
			throw new UploaderFileNotFoundException("File or folder not found: $destinationPath");
		} else {
			throw new UploaderException("Failed to delete file or folder. Status code: $statusCode. Response: $response");
		}
	}
	
	public function newFolder($destinationPath) {
        $url = str_replace(' ', '%20', rtrim($this->webdavBaseUrl,'/') . '/' . trim($this->basePath,'/') . '/' . trim($destinationPath, '/') . '/');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);

		$response = curl_exec($ch);
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($statusCode === 201) {
			return true;
		} elseif ($statusCode === 405) {
			throw new UploaderException("Folder already exists: $destinationPath");
		} else {
			$parent = self::getParent($destinationPath);
			if ($parent == "") {
				throw new UploaderException("Could not create folder structure");
			} else {
				$this->newFolder(self::getParent($destinationPath));
				$this->newFolder($destinationPath);
			}
		}
	}

    public function createPublicShare($path, $permissions = 1, $expireDate = null) {
        $data = [
            'path' => $this->basePath . '/' . $path, // Do NOT replace ' ' by %20 !
            'shareType' => 3, // Public link
            'permissions' => $permissions,
        ];
        if ($expireDate) {
            $data['expireDate'] = $expireDate;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, rtrim($this->ocsEndpoint,'/'));
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['OCS-APIRequest: true']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        try {
            $xml = simplexml_load_string($response);
            return (string)$xml->data->url;
        } catch (\Exception $e) {
            throw new UploaderException("Failed to create public share. Error: " . $xml->meta->message);
        }
    }
    private static function getParent($path) {
		$arr = explode('/', trim($path,'/'));
		array_pop($arr);
		return(implode('/',$arr));
	}
}
?>