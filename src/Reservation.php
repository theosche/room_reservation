<?php
namespace Theosche\RoomReservation;
class ReservationException extends \Exception{}

class Reservation {
	public $entite, $nom, $adresse, $npa, $localite, $email, $telephone;
	public $nom_evenement, $description_evenement, $events, $don;
	public $status = 'INIT';
	public $special_red = 0;
	public $price;
	public $prebook_link = null;
	public $invoice_link = null;
	public $invoice_date = null;
	public $nb_reminders = 0;
	public $last_reminder_date = null;
	public $id = null;
	public $created_at = null;
	private static $reservationDB = null;
    private static $CALDAV = null;
    private static $uploader = null;
    private $codesLink = null;
    // Do not remove / change comments below
    // START OF DYNAMIC CLASS PROPERTIES //
	public $is_private, $is_member, $is_volunteer = 0;
	// END OF DYNAMIC CLASS PROPERTIES //
	public static function initAll() {
		self::initDBOnly();
		self::initCal();
		self::$uploader = new WebdavUploader();
	}
    public static function initDBOnly() {
        if (self::$reservationDB === null) {
            self::$reservationDB = new ReservationDB();
        }
    }
    private static function initCal() {
		if (self::$CALDAV === null) {
            self::$CALDAV = new ReservationCaldavClient();
        }
    }
    public static function count($conditions = NULL) {
    	return(self::$reservationDB->count($conditions));
    }
    
    public function updateFromPost() {
    	$this->entite = htmlspecialchars($_POST['association']);
		$this->prenom = htmlspecialchars($_POST['prenom']);
		$this->nom = htmlspecialchars($_POST['nom']);
		$this->adresse = htmlspecialchars($_POST['adresse']);
		$this->npa = htmlspecialchars($_POST['npa']);
		$this->localite = htmlspecialchars($_POST['localite']);
		$this->email = htmlspecialchars($_POST['email']);
		$this->telephone = htmlspecialchars($_POST['telephone']);
		if (defined_local("ENTITYTYPES")) {
			foreach(ENTITYTYPES as $type) {
				$this->{$type['dbkey']} =  (array_key_exists($type['dbkey'], $_POST) && $_POST[$type['dbkey']] == "on") ? 1 : 0;
			}
		}
		$this->nom_evenement = htmlspecialchars($_POST['nom_evenement']);
		$this->description_evenement = htmlspecialchars($_POST['description_evenement']);
		$this->events = $this->getEventsFromPost();
		$this->don = (isset($_POST['don']) && htmlspecialchars($_POST['don']) >= 0) ? htmlspecialchars($_POST['don']) : 0;
		$this->special_red = (isset($_POST['special_red']) && ctype_digit($_POST['special_red'])) ? abs(intval($_POST['special_red'])) : 0;
		$this->price = $this->total_price();
    }
	
	public function initializeFromPost() {
		$this->created_at = new \DateTime('now');
		$this->updateFromPost();
		$this->special_red = 0; // Just in case
	}
	
	public static function loadFromDB($conditions = NULL,$limit=NULL,$offset=0) {
		$res_arrays = self::$reservationDB->load($conditions,$limit,$offset);

		$reservations = [];
		foreach ($res_arrays as $res) {
			$temp = new Reservation();
			$temp->initializeFromDB($res);
			$reservations[] = $temp;
		}
		return($reservations);
	}
	
	public function initializeFromDB($res) {
		foreach($res as $key => $value) {
			$this->$key = $value;
		}
		$this->invoice_date = empty($this->invoice_date) ? null : new \DateTime("$this->invoice_date 0:0:0");
		$this->last_reminder_date = empty($this->last_reminder_date) ? null : new \DateTime("$this->last_reminder_date 0:0:0");
		$this->created_at = new \DateTime($this->created_at);
		foreach($this->events as &$ev) {
			$ev['start_time'] = new \DateTime($ev['start_time']);
			$ev['end_time'] = new \DateTime($ev['end_time']);
		}
		unset($ev);
	}
	
	public function prebook() {
		// Check if caldav calendar is free
		foreach ($this->events as &$event) {
			$utcStart = clone $event['start_time'];
			$utcStart->setTimezone(new \DateTimeZone('UTC'));
			$utcEnd = clone $event['end_time'];
			$utcEnd->setTimezone(new \DateTimeZone('UTC'));
			$caldav_events = self::$CALDAV->getEvents($utcStart->format('Ymd\THis\Z'),$utcEnd->format('Ymd\THis\Z'));
			if ($caldav_events) {
				throw new ReservationException('La salle est déjà réservée.');
			}
			$event['uid'] = self::$CALDAV->new_event($this->nom_evenement . ' - A CONFIRMER', $this->entite .' - ' . $this->description_evenement, $event['start_time'], $event['end_time']);;
		}
		unset($event);
		
		$this->id = self::$reservationDB->newEntry($this); // We need to do it now to get an ID (pdf file naming)
		
		$pdf = new ReservationPDF();
		$pdf->prebookPDF($this);
		$pdfContents = $pdf->Output('S');
		$remotePath = $this->getPrebookPdfPath();
		self::$uploader->uploadFileContents($pdfContents, $remotePath);
		$this->prebook_link = $this->prebook_link ?? self::$uploader->createPublicShare($remotePath);
		$this->status = 'PREBOOKED';
		self::$reservationDB->updatePrebook($this);
	}
	
	public function cleanPrebook() {
		foreach ($this->events as &$ev) {
			self::deleteEvent($ev['uid']);
			$ev['uid'] = null;
		}
		unset($ev);
	}
	
	public function book() {
		// Check if caldav calendar is free
		foreach ($this->events as &$event) {
			$utcStart = clone $event['start_time'];
			$utcStart->setTimezone(new \DateTimeZone('UTC'));
			$utcEnd = clone $event['end_time'];
			$utcEnd->setTimezone(new \DateTimeZone('UTC'));
			$caldav_events = self::$CALDAV->getEvents($utcStart->format('Ymd\THis\Z'),$utcEnd->format('Ymd\THis\Z'));
			if ($caldav_events) {
				throw new ReservationException('La salle est déjà réservée.');
			}
			$event['uid'] = self::$CALDAV->new_event($this->nom_evenement . ' - CONFIRMÉ', $this->entite .' - ' . $this->description_evenement, $event['start_time'], $event['end_time']);
		}
		unset($event);
		$this->invoice_date = new \DateTime('now');
		$this->last_reminder_date = $this->invoice_date;
				
		$pdf = new ReservationPDF();
		$pdf->invoicePDF($this);
		$pdfContents = $pdf->Output('S');
		$remotePath = $this->getInvoicePdfPath();
		self::$uploader->uploadFileContents($pdfContents, $remotePath);
		$this->invoice_link = $this->invoice_link ?? self::$uploader->createPublicShare($remotePath);
		$this->status = 'CONFIRMED';
		self::$reservationDB->updateEntry($this);
	}
	
	public function getCodesLink() {
		if (is_null($this->codesLink)) {
			$remotePath = "codes.md";
			$endTimes = array_column($this->events, 'end_time');
			$latest = max($endTimes);
			$expireDate = $latest->modify('+1day')->format('Y-m-d');
			$this->codesLink = self::$uploader->createPublicShare($remotePath,expireDate:$expireDate);
		}
		return($this->codesLink);
	}
	
	public function cancel() {
		foreach ($this->events as &$ev) {
			self::deleteEvent($ev['uid']);
			$ev['uid'] = null;
		}
		unset($ev);
		if ($this->status == "CONFIRMED") {
			try {
				self::$uploader->delete($this->getInvoicePdfPath());
			} catch (UploaderFileNotFoundException $e) {
				unset($e);
			}
			$this->invoice_link = null;
			$this->invoice_date = null;
			$this->nb_reminders = 0;
			$this->last_reminder_date = null;
		}
		$this->status = 'CANCELLED';
		self::$reservationDB->updateEntry($this);
	}
	
	public function delete() {
		foreach ($this->events as &$ev) {
			self::deleteEvent($ev['uid']);
		}
		unset($ev);
		try {
			self::$uploader->delete($this->getInvoicePdfPath());
		} catch (UploaderFileNotFoundException $e) {
			unset($e);
		}
		try {
			self::$uploader->delete($this->getPrebookPdfPath());
		} catch (UploaderFileNotFoundException $e) {
			unset($e);
		}
		self::$reservationDB->deleteEntry($this);
	}
	
	public function close() {
		$this->status = 'CLOSED';
		self::$reservationDB->updateStatus($this);
	}
	
	public function reminder_sent() {
		$this->nb_reminders++;
		$this->last_reminder_date = new \DateTime('now');
		self::$reservationDB->updateReminders($this);
	}
	
	private function total_price() {
		$price = 0;
		foreach ($this->events as $ev) {
			$price += $ev['price'];
		}
		$finalPrice = $price;
		if (defined_local("ENTITYTYPES")) {
			foreach(ENTITYTYPES as $type) {
				if ($this->{$type['dbkey']}) {
					$finalPrice += $type['price']*$price;
				}
			}
		}
		$finalPrice -= $this->special_red;
		$finalPrice += $this->don;
		return($finalPrice);
	}
	
	private static function deleteEvent($uid) {
		if (is_null($uid)) {
			return;
		}
		$href = ReservationCaldavClient::getFullURL() . $uid . '.ics';
		try {
			self::$CALDAV->delete($href,"*");
		} catch (\CalDAVException $e) {
			unset($e);
		}
	}
	
	private function getPrebookPdfPath() {
		$entite = preg_replace("/[^\w]/u", '', $this->entite);
		return($this->created_at->format('Y') . '/Pré-réservations/' . $this->id . '_' . $entite . '_Pré-réservation_' . ROOM_SHORT . '.pdf');
	}
	private function getInvoicePdfPath() {
		$entite = preg_replace("/[^\w]/u", '', $this->entite);
		return($this->created_at->format('Y') . '/Factures/' . $this->id . '_' . $entite . '_Facture_' . ROOM_SHORT . '.pdf');
	}
	
	private function getEventsFromPost() {
		$start_time = $_POST['start_time'];
		$end_time = $_POST['end_time'];
		$events = array();
		foreach ($_POST['reservation_date'] as $index => $value){
			$events[] = array(
				"start_time" => new \DateTime(htmlspecialchars($value) . 'CET' . htmlspecialchars($start_time[$index])),
				"end_time" => new \DateTime(htmlspecialchars($value) . 'CET' . htmlspecialchars($end_time[$index])),
			);
			if (defined_local("OPTIONS")) {
				foreach(OPTIONS as $opt) {
					$events[$index][$opt['dbkey']] = ($_POST[$opt['dbkey']][$index] == "on") ? 1 : 0;
				}
			}
		}
		// Check if start time is before end time, check if event is in the past
		$now = new \DateTime('now');
		foreach ($events as $event) {
			if ($event['end_time'] <= $event['start_time']) {
				throw new ReservationException('Données invalides (end_time < start_time)');
			} elseif ($event['start_time'] < $now) {
				throw new ReservationException('Un événement est dans le passé');
			}
		}
		// Check for overlap between events
		$count = count($events);
		for ($i = 0; $i < $count; $i++) {
			for ($j = $i + 1; $j < $count; $j++) {
				$occ1 = $events[$i];
				$occ2 = $events[$j];
				if (
					$occ1['start_time'] < $occ2['end_time'] &&
					$occ1['end_time'] > $occ2['start_time']
				) {
					throw new ReservationException('Plusieurs réservations se chevauchent');
				}
			}
		}
		// Compute cost and summary text for each event
		foreach ($events as &$event) {
			$interval = $event['start_time']->diff($event['end_time']);
			$duree = ($interval->days * 24) + $interval->h + ($interval->i / 60);
			$startHour = (int)$event['start_time']->format('H');
			if ($duree <= MAX_HOURS_SHORT || $startHour >= ALWAYS_SHORT_AFTER) {
				$text = 'Réservation courte durée (<'. MAX_HOURS_SHORT .'h) ou soirée (dès ' . ALWAYS_SHORT_AFTER .'h)';
				$price = PRICE_SHORT;
			} else {
				$text = 'Réservation journée entière';
				$price = PRICE_FULL_DAY;
			}
			if (defined_local("OPTIONS")) {
				$enabledOptions = [];
				foreach(OPTIONS as $opt) {
					if ($event[$opt['dbkey']]) {
						$price += $opt['price'];
						$enabledOptions[] = $opt['text_short'];
					}
				}
				if (count($enabledOptions) == 1) {
					$text .= " avec " . $enabledOptions[0];
				} elseif (count($enabledOptions) > 1) {
					$text .= " avec ";
					$text .= implode(", ", array_slice($enabledOptions, 0, count($enabledOptions)-1, true)) .
							 " et "  . end($enabledOptions);
				}
			}
			$event['text'] = $text;
			$event['price'] = $price;
		}
		unset($event);
		return $events;
	}
}

?>