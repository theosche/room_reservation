<?php
namespace Theosche\RoomReservation;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/exceptionHandler.php';

session_start();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		http_response_code(403);
		exit;
	}

	if (!ctype_digit($_POST['id'])) {
		http_response_code(404);
		exit;
	}
	$id = $_POST['id'];
	Reservation::initAll();
	$res = Reservation::loadFromDB(['id' => $id])[0];
	
	if ($_POST['res_action'] == 'refuse') {
		$res->cancel();
		if (!(defined_local("DISABLE_MAILER") && DISABLE_MAILER)) {
			$mailer = new ReservationMailer();
			$mailer->sendCancellation($res, $_POST['info_demandeur']);
		}
		echo json_encode(['success'=>true]);
		exit;
	} elseif ($_POST['res_action'] == 'confirm') {
		$res->cleanPrebook();
		$res->updateFromPost();
		$res->book();
		$infos = $_POST['info_demandeur'] ?? null;
		if (!(defined_local("DISABLE_MAILER") && DISABLE_MAILER)) {
			$mailer = new ReservationMailer();
			$mailer->sendConfirmation($res, $infos);
		}
		echo json_encode(['success'=>true]);
		exit;
	} elseif ($_POST['res_action'] == 'close') {
		$res->close();
		echo json_encode(['success'=>true]);
		exit;
	} elseif ($_POST['res_action'] == 'remind') {
		if (!(defined_local("DISABLE_MAILER") && DISABLE_MAILER)) {
			$mailer = new ReservationMailer();
			$mailer->sendReminder($res);
		}
		$res->reminder_sent();
		echo json_encode(['success'=>true]);
		exit;
	} elseif ($_POST['res_action'] == 'delete') {
		$res->delete();
		echo json_encode(['success'=>true]);
		exit;
	}
}