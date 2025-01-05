<?php
namespace Theosche\RoomReservation;
set_exception_handler(function ($e) {

	$time = date('F j, Y, g:i a e O');

	// show a user-friendly message
	echo json_encode(array(
		'success' => false,
		'error' => array(
			'time' => $time,
			'msg' => $e->getMessage(),
			'code' => $e->getCode(),
		),
	));
	throw($e);
});
?>