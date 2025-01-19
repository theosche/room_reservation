<?php
namespace Theosche\RoomReservation;

if (!isset($_GET['uid']) || strlen($_GET['uid']) != 36 || !isset($_GET['id']) || !ctype_digit($_GET['id'])) {
	exit;
}
Reservation::initDBOnly();
try {
	$res = (Reservation::loadFromDB(['id' => $_GET['id']]))[0];
	$index = array_search($_GET['uid'], array_map(fn($e) => $e['uid'] , $res->events));
	if ($index === false) throw new \Exception();
	$event = $res->events[$index];
	$title = $res->nom_evenement . ($res->status == 'PREBOOKED' ? ' - A CONFIRMER' : ' - CONFIRMÉ');
	$ics = ReservationCaldavClient::get_ical_data($title, $res->entite .
		' - ' . $res->description_evenement, $event['start_time'], $event['end_time'], $event['uid'], $res->created_at);
	$filename = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $title) . '-' . $event['start_time']->format('Y-m-d') . '.ics';
	header('Content-Type: text/calendar');
	header("Content-Disposition: attachment; filename=\"$filename\"");
	echo $ics[0];
} catch (\Throwable) {
	http_response_code(404);
    echo "<h2>Not Found: The requested event does not exist.</h2>";
    exit;
}
?>
