<?php
namespace Theosche\RoomReservation;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/exceptionHandler.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Content-Type: application/json');
	if (defined_local("ALTCHA_CHALLENGEURL") && defined_local("ALTCHA_HMACKEY")) {
		$payload = $_POST['altcha'] ?? ''; // Récupérer la solution soumise par le client
		$altcha = validateAltchaWithSpamFilter($payload, ALTCHA_HMACKEY);
		if (!$altcha['verified'] || $altcha['classification'] == 'BAD') {
			throw new \Exception('La vérification Altcha a échoué');
		}
	}
	if (defined_local("CHARTE_URL") && htmlspecialchars($_POST['charte']) != "on") {
		throw new \Exception('Charte non acceptée');
	}
	Reservation::initAll();
	$res = new Reservation();
	$res->initializeFromPost();
	$res->prebook();
	if (!(defined_local("DISABLE_MAILER") && DISABLE_MAILER)) {
		$mailer = new ReservationMailer();
		$mailer->sendNewReservation($res);
	}
	echo json_encode(['success'=>true,'prebook_link'=>$res->prebook_link]);
}

function validateAltchaWithSpamFilter($payload, $hmacKey) {
    // Décoder la charge utile (payload) base64 en JSON
    $decodedPayload = json_decode(base64_decode($payload), true);

    // Si le décodage échoue, retourner une erreur
    if (!$decodedPayload) {
        return ['verified' => false, 'error' => 'Invalid payload format'];
    }

    // Vérifier que l'algorithme est SHA-256
    if (!isset($decodedPayload['algorithm']) || $decodedPayload['algorithm'] !== 'SHA-256') {
        return ['verified' => false, 'error' => 'Unsupported algorithm'];
    }

    // Vérifier que le champ signature et verificationData existent
    if (!isset($decodedPayload['signature'], $decodedPayload['verificationData'])) {
        return ['verified' => false, 'error' => 'Missing signature or verification data'];
    }

    // Calculer le hash SHA-256 de verificationData
    $hash = hash('sha256', $decodedPayload['verificationData'], true); // Note: binaire, pas hex

    // Calculer la signature HMAC en utilisant la clé HMAC
    $calculatedSignature = hash_hmac('sha256', $hash, $hmacKey);

    // Comparer la signature calculée avec celle du payload
    if (!hash_equals($calculatedSignature, $decodedPayload['signature'])) {
        return ['verified' => false, 'error' => 'Signature verification failed'];
    }

    // Décoder les données de vérification (URL-encodées) pour obtenir les détails
    parse_str($decodedPayload['verificationData'], $verificationData);

    // Vérifier que la solution a été marquée comme vérifiée
    if (!isset($verificationData['verified']) || $verificationData['verified'] !== 'true') {
        return ['verified' => false, 'error' => 'Solution not verified'];
    }

    // Retourner les données vérifiées et leur classification
    return [
        'verified' => true,
        'classification' => $verificationData['classification'] ?? null,
        'score' => $verificationData['score'] ?? null,
        'fields' => $verificationData['fields'] ?? null,
        'ipAddress' => $verificationData['ipAddress'] ?? null,
        'reasons' => $verificationData['reasons'] ?? [],
    ];
}