<?php
namespace Theosche\RoomReservation;
use om\IcalParser;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/exceptionHandler.php';

$utcStart = new \DateTime("now");
$utcEnd = (new \DateTime("now"))->modify("+" . FUTURE_RESERVATION_LIMIT . " months");
$utcStart->setTimezone(new \DateTimeZone('UTC'));
$utcEnd->setTimezone(new \DateTimeZone('UTC'));

$CALDAV = new ReservationCaldavClient();
$ICSevents = $CALDAV->getEvents($utcStart->format('Ymd\THis\Z'),$utcEnd->format('Ymd\THis\Z'));
$fullics = "";
foreach ($ICSevents as $ev) {
	$fullics .= $ev->getData() . '
';
}
$parser = new IcalParser();
$parser->parseString($fullics);
$events = (array) ($parser->getEvents());
$dates = array_map(fn($e) => [$e['DTSTART']->format('Y-m-d H:i:s'),$e['DTEND']->format('Y-m-d H:i:s')] , $events);
$uids = array_map(fn($e) => $e['UID'], $events);
header('Content-Type: application/json');
echo json_encode([$dates, $uids]);
?>
