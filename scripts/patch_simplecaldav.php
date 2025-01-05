<?php

$caldavClientPath = __DIR__ . '/../vendor/wvrzel/simplecaldav/CalDAVClient.php';
$simpleCalDAVClientPath = __DIR__ . '/../vendor/wvrzel/simplecaldav/SimpleCalDAVClient.php';

// Step 1: Modify the visibility of the $headers property in CalDAVClient.php
$caldavClientContents = file_get_contents($caldavClientPath);
$caldavClientContents = str_replace(
    'protected $headers = array();',
    'public $headers = array();',
    $caldavClientContents
);
file_put_contents($caldavClientPath, $caldavClientContents);

// Step 2: Add $this->client->headers = array(); at the beginning of the specified functions in SimpleCalDAVClient.php
$simpleCalDAVClientContents = file_get_contents($simpleCalDAVClientPath);

$functionNames = [
    'create',
    'change',
    'delete',
    'getEvents',
    'getTODOs',
    'getCustomReport',
];

foreach ($functionNames as $functionName) {
    $patternPatched = '/function\s+' . preg_quote($functionName, '/') . '\s*\(.*\)\s*{\s*\$this\-\>client\-\>headers\s*\=\s*array\(\)/';
	if (!preg_match($patternPatched, $simpleCalDAVClientContents)) {
		$pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\(.*\)\s*{/';
		$found = [];
		preg_match($pattern, $simpleCalDAVClientContents,$found);
		$replacement = $found[0] . '
		$this->client->headers = array();';

		// Replace the function declaration and insert the new line
		$simpleCalDAVClientContents = preg_replace($pattern, $replacement, $simpleCalDAVClientContents);
    } else {
    	echo "Patch already applied.\n";
    	exit;
    }
}
// Step 3: allow delete and changes without knowing etag
$simpleCalDAVClientContents = str_replace(
	'// $etag correct?',
	'// Added to allow delete without knowing etag (be cautious !)
		if ($etag == "*") {
			$etag = $result[0]["etag"];
		}
		// $etag correct?',
		$simpleCalDAVClientContents
);

file_put_contents($simpleCalDAVClientPath, $simpleCalDAVClientContents);
echo "Patch applied successfully.\n";

?>
