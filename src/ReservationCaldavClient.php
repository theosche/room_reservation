<?php
namespace Theosche\RoomReservation;

class ReservationCaldavClient extends \SimpleCalDAVClient {
	private static $davURL = DAVURL;
	private static $davCal = DAVCAL;
	private static $davUser = DAVUSER;
	private static $davPass = DAVPASS;
	
	public function __construct()
    {
        // parent::__construct(); --> the parent has no constructor
		$this->connect(rtrim(self::$davURL,'/') . '/' . self::$davUser, self::$davUser, self::$davPass);
		$arrayOfCalendars = $this->findCalendars();
		$this->setCalendar($arrayOfCalendars[self::$davCal]);
    }
    public function new_event($title,$description,$start_time,$end_time) {
    	[$ical,$uid] = self::get_ical_data($title,$description,$start_time,$end_time);
    	$this->create($ical);
    	return($uid);
    }
    public static function getFullURL() {
    	return (rtrim(self::$davURL,'/') . '/' . self::$davUser . '/' . self::$davCal.'/');
    }
    private static function uuidv4()
	{
	  $data = random_bytes(16);
	  $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
	  $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
	  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
	public static function get_ical_data($title,$description,$start_time,$end_time,$uid=null,$created_at=null) {
		$title = html_entity_decode($title);
		$description = html_entity_decode($description);
		$now = new \DateTime("now", new \DateTimeZone("UTC"));
		$uid = $uid ?? self::uuidv4();
		$created_at = is_null($created_at) ? $now->format('Ymd\THis') : $created_at->format('Ymd\THis');
		$ical = "BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
PRODID:-//" . SYSTEM_NAME . "//FR
BEGIN:VEVENT
CREATED:{$created_at}
DTSTAMP:{$created_at}
LAST-MODIFIED:{$created_at}
SEQUENCE:0
UID:$uid
DTSTART;TZID=Europe/Zurich:{$start_time->format('Ymd\THis')}
DTEND;TZID=Europe/Zurich:{$end_time->format('Ymd\THis')}
STATUS:CONFIRMED
SUMMARY:$title
DESCRIPTION:$description
END:VEVENT
BEGIN:VTIMEZONE
TZID:Europe/Zurich
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
END:VCALENDAR";

		return([$ical,$uid]);
	}
}
?>