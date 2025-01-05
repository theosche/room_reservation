<?php
namespace Theosche\RoomReservation;

class ReservationDB {
	private $pdo;
	private $host = DBHOST;
	private $dbName = DBNAME;
	private $userName = DBUSER;
	private $pass = DBPASS;
    
    public function __construct() {
		$db = "mysql:host=" . $this->host . ";dbname=" . $this->dbName . ";charset=utf8";
		$this->pdo = new \PDO($db, $this->userName, $this->pass, [
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		]);
    }
    public function get_pdo() {
    	return($this->pdo);
    }
    
    public function updateStatus($res) {
		$data = [
			'status' => $res->status,
			'id' => $res->id
		];
		$this->update($data, false);
	}
	
	public function updateReminders($res) {
		$data = [
			'nb_reminders' => $res->nb_reminders,
			'last_reminder_date' => $res->last_reminder_date->format('Y-m-d'),
			'id' => $res->id
		];
		$this->update($data, false);
	}
	
	public function updatePrebook($res) {
		$data = [
			'prebook_link' => $res->prebook_link,
			'status' => $res->status,
			'id' => $res->id
		];
		$this->update($data, false);
	}
    
	public function newEntry($res) {
		$reservationData = self::get_res_data($res);
		$eventsData = self::get_events_data($res); // Warning: no ID yet
				
		// Préparation de la requête d'insertion dans la table 'reservations'
		$stmt_ins = "";
		$stmt_val = "";
		foreach ($reservationData as $key=>$value) {
			$stmt_ins .= $key . ", ";
			$stmt_val .= ":" . $key . ", ";
		}
		$stmt_ins = rtrim($stmt_ins, ', ');
		$stmt_val = rtrim($stmt_val, ', ');
		$stmt_res = $this->pdo->prepare("INSERT INTO reservations (" . $stmt_ins . ") VALUES (" . $stmt_val . ")");
		$stmt_ev = $this->pdo->prepare($this->events_insert_str($eventsData[0]));

		$this->pdo->beginTransaction();
		$stmt_res->execute($reservationData);	
		$id = $this->pdo->lastInsertId();
	
		foreach ($eventsData as $eventData) {
			$eventData['reservation_id'] = $id; // We add back the ID here
			$stmt_ev->execute($eventData);
		}
		$this->pdo->commit();
		return($id);
	}
	
	public function updateEntry($res) {
		$reservationData = self::get_res_data($res);
		$eventsData = self::get_events_data($res);
		$this->update($reservationData, $eventsData);
	}
	
	public function deleteEntry($res) {
		if (!$res->id) return;
		$this->pdo->exec("DELETE FROM reservations WHERE id={$res->id}");
	}
	
	public function count($conditions = NULL) {
    	if (is_null($conditions)) {
			$condString = "";
		} else {
			$condString = " WHERE";
			foreach($conditions as $key => $cond) {
				$condString .= " $key in ('" . (is_array($cond) ? implode("', '",$cond) : $cond) . "')";
				if ($key != array_key_last($conditions)) {
					$condString .= " AND";
				}
			}		
		}
    	$totalStmt = $this->pdo->query("SELECT COUNT(*) FROM reservations" . $condString);
		return($totalStmt->fetchColumn());
    }
    
    public function load($conditions = NULL,$limit=NULL,$offset=0) {
    	if (is_null($conditions)) {
			$condString = "";
		} else {
			$condString = " WHERE";
			foreach($conditions as $key => $cond) {
				$condString .= " $key in ('" . (is_array($cond) ? implode("', '",$cond) : $cond) . "')";
				if ($key != array_key_last($conditions)) {
					$condString .= " AND";
				}
			}		
		}
		if (is_null($limit)) {
			$limitString = "";
		} else {
			$limitString = " LIMIT $limit OFFSET $offset";
		}
		// Since we have dynamic variables, make sure we only load necessary keys
		$keys = implode(",", self::get_res_keys());
		$stmt_res = $this->pdo->prepare("SELECT " . $keys . " FROM reservations" . $condString . " ORDER BY created_at DESC" . $limitString);
		if (defined_local("OPTIONS")) {
			$stmt_ev = $this->pdo->prepare("SELECT start_time, end_time, " . implode(', ', array_column(OPTIONS, 'dbkey')) . ", uid, text, price FROM events WHERE reservation_id = :id");
		} else {
			$stmt_ev = $this->pdo->prepare("SELECT start_time, end_time, uid, text, price FROM events WHERE reservation_id = :id");
		}
		$this->pdo->beginTransaction();
		$stmt_res->execute();
		$res_arrays = $stmt_res->fetchAll();
		foreach ($res_arrays as &$res) {
			$stmt_ev->execute(['id' => $res['id']]);
			$res['events'] = $stmt_ev->fetchAll();
		}
		unset($res);
		$this->pdo->commit();
		return($res_arrays);
	}
	
	private static function get_res_data($res) {
		$keys = self::get_res_keys();
		$reservationData =  array_combine($keys, array_map(fn($key) => $res->$key, $keys));
		
		// Just a few special cases
		$reservationData['invoice_date'] = is_null($res->invoice_date) ? null : $res->invoice_date->format('Y-m-d');
		$reservationData['last_reminder_date'] = is_null($res->last_reminder_date) ? null : $res->last_reminder_date->format('Y-m-d');
		
		// New reservation
		if (is_null($res->id)) {
			unset($reservationData['id']);
			$reservationData['created_at'] = $res->created_at->format('Y-m-d H:i:s');
		} else {
			unset($reservationData['created_at']); // We don't need to rewrite it
		}
		return($reservationData);
	}
	
	// Keys here define uniquely what will be saved and loaded from reservation table
	// Adding one line 'entry' will attempt to save $res->entry to a column "entry" (and load in the opposite way)
	private static function get_res_keys() {
		$keys = [
			'id',
			'entite',
			'prenom',
			'nom',
			'adresse',
			'npa',
			'localite',
			'email',
			'telephone',
			'nom_evenement',
			'description_evenement',
			'don',
			'special_red',
			'price',
			'prebook_link',
			'invoice_link',
			'status',
			'invoice_date',
			'nb_reminders',
			'last_reminder_date',
			'created_at'
		];
		if (defined_local("ENTITYTYPES")) {
			foreach(ENTITYTYPES as $type) {
				$keys[] = $type['dbkey'];
			}
		}
		return($keys);
	}
	
	private static function get_events_data($res) {
		$eventsData = [];
		foreach ($res->events as $index => $ev) {
			$eventsData[] = [
				'reservation_id' => $res->id,
				'start_time' => $ev['start_time']->format('Y-m-d H:i:s'),
				'end_time' => $ev['end_time']->format('Y-m-d H:i:s'),
				'uid' => $ev['uid'],
				'text' => $ev['text'],
				'price' => $ev['price']
			];
			if (defined_local("OPTIONS")) {
				foreach(OPTIONS as $opt) {
					$eventsData[$index][$opt['dbkey']] = $ev[$opt['dbkey']]; 
				}
			}
		}
		return($eventsData);
	}
	
	private function update($reservationData, $eventsData = false) {		
		$stmt_res_str = "UPDATE reservations SET ";
		foreach ($reservationData as $key=>$value) {
			if ($key == 'id') continue;
			$stmt_res_str .= $key . " = :" . $key . ", ";
		}
		$stmt_res_str = rtrim($stmt_res_str,", ");
		$stmt_res_str .= ' WHERE id = :id';
		
		$stmt_res = $this->pdo->prepare($stmt_res_str);
		$this->pdo->beginTransaction();
		$stmt_res->execute($reservationData);
		
		if ($eventsData) {
			$stmt_ev_del = $this->pdo->prepare(
				"DELETE FROM events WHERE reservation_id= :reservation_id"
			);
			$stmt_ev = $this->pdo->prepare($this->events_insert_str($eventsData[0]));
			$stmt_ev_del->execute(['reservation_id' => $reservationData['id'],]);
			foreach ($eventsData as $eventData) {
				$stmt_ev->execute($eventData);
			}
		}
		$this->pdo->commit();
	}
	
	private function events_insert_str($eventData) {
		$stmt_ins = "";
		$stmt_val = "";
		foreach ($eventData as $key=>$value) {
			$stmt_ins .= $key . ", ";
			$stmt_val .= ":" . $key . ", ";
		}
		$stmt_ins = rtrim($stmt_ins, ', ');
		$stmt_val = rtrim($stmt_val, ', ');
		return("INSERT INTO events (" . $stmt_ins . ") VALUES (" . $stmt_val . ")");
	}
}
?>