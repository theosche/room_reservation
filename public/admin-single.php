<?php
namespace Theosche\RoomReservation;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/exceptionHandler.php';
class InvalidUrlException extends \Exception{}

session_start();

// Vérification de l'authentification administrateur
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
	$_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit;
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un jeton sécurisé
}

Reservation::initDBOnly();
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
	$res = Reservation::loadFromDB(['id' => $_GET['id']]);
	if (!$res) {
		throw new InvalidUrlException('Impossible de charger la réservation');
	}
	$res = $res[0];
	if ($res->status != 'PREBOOKED' && $res->status != 'CANCELLED') {
		throw new InvalidUrlException('Statut incompatible');
	}
} else {
	throw new InvalidUrlException('ID invalide');
}

$status_fr = [
	'INIT' => 'Initialisation',
	'PREBOOKED' => 'Pré‑réservé',
	'CONFIRMED' => 'Confirmé',
	'CLOSED' => 'Terminé',
	'CANCELLED' => 'Annulé'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation <?=OF_ROOM?></title>
	<link rel="stylesheet" href="style.css">
	<script type="text/javascript" src="js.php"></script>
	<script>
		<?php
			$strFalse = "";
			for 	($i=0; $i<count($res->events); $i++) {
				$strFalse .= "false,";
			}
			$strFalse = rtrim($strFalse,',');
			echo("let occurrencePrice = [$strFalse];");
		?>
		const review = true;
        
        let eventsDates = []; // Variable globale pour stocker les événements
        let eventsUIDs = [];

		// Charger et parser le fichier ICS au chargement de la page
		async function loadCalendar() {
			try {
				const response = await fetch('availability.php'); // Proxy PHP pour le fichier ICS
				const data = await response.json();
        
				// Access the start and end dates
				eventsDates = data[0].map(line => line.map(date => new Date(date)));
				eventsUIDs = data[1]; // UIDs
			} catch (error) {
				console.error('Erreur lors du chargement du calendrier :', error);
			}
		}
		
		document.addEventListener('DOMContentLoaded', function() {
			document.getElementById("loaderModal").style.display = "block";
			loadCalendar().then(() => {
				document.getElementById("loaderModal").style.display = "none";
				const rows = document.getElementsByClassName('occurrence');
				for (let row of rows) {
    				updateRow(row);
				}
				updateTotalCost();
			});
			updateTotalCost();
		});
		
		function confirmButton(event) {
			event.preventDefault();
			if (!confirm("Voulez-vous vraiment valider la demande de réservation ?")) return;
			const allValid = occurrencePrice.every(state => state);
			if (!allValid) {
				alert('Veuillez corriger les erreurs dans les créneaux avant de soumettre.');
			} else {
				document.getElementById("input-button").value = "confirm";
				handleFormSubmission();
			}
		}
		function refuseButton(event) {
			event.preventDefault();
			if (!document.getElementById('info_demandeur').value) {
				alert("Merci de compléter le champ \"Indication particulière à transmettre au demandeur / à la demandeuse\" pour expliquer les raisons du refus.");
				return;
			}
			if (!confirm("Voulez-vous vraiment refuser la demande de réservation ?")) return;
			document.getElementById("input-button").value = "refuse";
			handleFormSubmission();
		}
		async function handleFormSubmission() {
			document.getElementById("loaderModal").style.display = "block";

			const form = document.querySelector("form"); // Référence au formulaire
			let formData = new FormData(form); // Crée un objet FormData à partir du formulaire
			try {
				const response = await fetch(form.action, {
					method: form.method,
					body: formData,
				});

				if (!response.ok) {
					throw new Error('Erreur lors de l\'envoi du formulaire.');
				}
				const result = await response.json();
				handleResponse(result);
			} catch (error) {
				console.error(error);
				document.getElementById("loaderModal").style.display = "none";
				alert('Une erreur est survenue. Veuillez vérifier et réessayer. N\'hésitez pas à nous contacter');
			}
		}

		function handleResponse(msg) {
			if (!msg.success) {
				document.getElementById("loaderModal").style.display = "none";
				alert('Une erreur est survenue. Veuillez vérifier et réessayer. N\'hésitez pas à nous contacter. ' + msg.error);
			} else {
				document.getElementById("loaderModal").style.display = "none";
				window.location.href = "admin.php";
			}
		}
    </script>
</head>
<body>
    <h1 id="main-title">Réservation <?=OF_ROOM?></h1>
    <?php if(DAVEMBED_ADMIN && defined_local("DAVEMBED_URL")): ?>
	    <iframe src="https://cloud.actionculture.ch/index.php/apps/calendar/embed/HneCPH48gRfsRQr8"></iframe> 
    <?php endif;?>
    <form method="post" action="admin-form.php" accept-charset="utf-8" id="reservation-form">
    	<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    	<input type="hidden" name="id" value="<?= $res->id ?>">
    	<input type="hidden" name="res_action" id="input-button">
    	<div class="form-group"><strong>ADMIN: Contrôler (et modifier si besoin) les données du formulaire complété par le demandeur ou la demandeuse, puis valider ou annuler la réservation.</strong></div>
        <div class="form-group">
            <label for="association">Nom de l'association / collectif / entité / personne :</label>
            <input type="text" value="<?= $res->entite ?>" id="association" name="association" required>
        </div>

        <div class="form-group">
            <label>Personne de contact :</label>
            <div class="name-group">
                <input type="text" value="<?= $res->prenom ?>" aria-label="Prénom" name="prenom" placeholder="Prénom" required>
                <input type="text" value="<?= $res->nom ?>" aria-label="Nom" name="nom" placeholder="Nom" required>
            </div>
        </div>

        <div class="form-group">
            <label for="adresse">Adresse :</label>
            <textarea maxlength="100" id="adresse" name="adresse" rows="2" required><?= $res->adresse ?></textarea>
        </div>
        <div class="form-group">
         <label>Localité :</label>
            <div class="name-group">
                <input type="number" value="<?= $res->npa ?>" aria-label="NPA" style="width:50%;" name="npa" placeholder="NPA" required>
                <input type="text" value="<?= $res->localite ?>" aria-label="Localité" name="localite" placeholder="Localité" required>
            </div>
        </div>

        <div class="form-group">
            <label for="email">Adresse email :</label>
            <input type="email" value="<?= $res->email ?>" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="telephone">Numéro de téléphone :</label>
            <input type="tel"  value="<?= $res->telephone ?>"id="telephone" name="telephone" pattern="[0-9]{10}" placeholder="Format : 0123456789 (uniquement numéros)" required>
        </div>
        
        <?php if(defined_local("ENTITYTYPES")):?>
        <div class="form-group" id="entity-type-form">
			<label>Nature de l'entité :</label>
			<?php foreach(ENTITYTYPES as $entity):?>
			<label class="entity-type-form-entry">
				<input type="checkbox" id="<?=$entity['dbkey']?>" name="<?=$entity['dbkey']?>"<?= $res->{$entity['dbkey']} ? " checked" : "" ?>>
				<?=$entity['text']?> (<?= ($entity['price'] > 0 ? "+" : "") . round(100*$entity['price']) ?>%)
			</label>
			<?php endforeach;?>
		</div>
		<?php endif;?>


        <!-- Informations sur l'événement -->
        <div class="form-group">
            <label for="nom_evenement">Nom de l'événement/activité :</label>
            <input type="text" value="<?= $res->nom_evenement ?>" id="nom_evenement" name="nom_evenement" required>
        </div>

        <div class="form-group">
            <label for="description_evenement">Description de l'événement/activité et demandes particulières :</label>
            <textarea maxlength="250" id="description_evenement" name="description_evenement" rows="4" required><?= $res->description_evenement ?></textarea>
        </div>

        <!-- Occurrences de date et heure -->
        <div id="occurrences">
			<label>Date et heures de réservation :</label>
			<label class="label-precision">Date, heure début, heure fin</label>
			<?php
			foreach ($res->events as $ev) {
				$strOccurrence = '<div class="date-time-group occurrence" data-uid="' . $ev['uid'] . '">
					<span class="remove-btn" onclick="removeOccurrence(this)">✖</span>
					<input type="date" value="' . $ev['start_time']->format('Y-m-d') .'" aria-label="Date" name="reservation_date[]" class="reservationDate" required>
					<input type="time" value="' . $ev['start_time']->format('H:i') . '" aria-label="Heure de début" name="start_time[]" class="startTime" required>
					<input type="time" value="' . $ev['end_time']->format('H:i') . '" aria-label="Heure de fin" name="end_time[]" class="endTime" required>
					<span class="availability-status"></span>';
					if (defined_local("OPTIONS")) {
						$strOccurrence .= '<div class="break"></div>';
						foreach(OPTIONS as $opt) {
							$strOccurrence .=
							"<label class='opt-label'>
							<input type='checkbox' " . ($ev[$opt['dbkey']] ? '' : 'checked ') . " class='opt-hidden opt-{$opt['dbkey']}' name='{$opt['dbkey']}[]' value='off'>
							<input type='checkbox' " . ($ev[$opt['dbkey']] ? 'checked ' : '') . " class='opt opt-{$opt['dbkey']}' name='{$opt['dbkey']}[]' value ='on'>
							{$opt['text']} (+ {$opt['price']}.-)</label>";
						}
					}
					$strOccurrence .= '<div class="break"></div><p class="tarif">' . $ev['text'] . ' : ' . $ev['price'] . '.-</p></div>';
				echo $strOccurrence;
			}
			?>
        </div>

        <div class="form-group">
            <button type="button" onclick="addOccurrence()">Ajouter une occurrence</button>
        </div>
        <?php if (USE_DONATION):?>
			<div class="form-group">
				<label for="don">Ajouter un don en CHF à <?=THE_ORGANIZATION?> (facultatif)</label>
				<input type="number" value="<?= $res->don ?>" id="don" name="don">
			</div>
        <?php endif;?>
        <?php if (USE_SPECIAL_RED):?>
			<div class="form-group">
				<label for="special_red">ADMIN: accorder une réduction spéciale (CHF)</label>
				<input type="number" id="special_red" name="special_red" <?= ($res->special_red > 0) ? $res->special_red : ""?>>
			</div>
        <?php endif;?>

        <p id="total-cost-p" class="cost">Total initial: <span id="total-cost">0</span>.-</p>
        <?php if(defined_local("ENTITYTYPES")):?>
        	<?php foreach(ENTITYTYPES as $entity):?>
        		<p id="<?=$entity['dbkey']?>-text" class="cost"><?=$entity['price_str']?> (<?= ($entity['price'] > 0 ? "+" : "") . round(100*$entity['price']) ?>%): <span id="<?=$entity['dbkey']?>-price"></span>.-</p>
			<?php endforeach;?>
        <?php endif;?>
        <?php if (USE_SPECIAL_RED):?>
    	    <p id="special-red-cost-p" class="cost">Réduction spéciale: <span id="special-red-cost"></span>.-</p>
        <?php endif;?>
        <?php if (USE_DONATION):?>
	        <p id="don-cost-p" class="cost">Don supplémentaire: <span id="don-cost"></span>.-</p>
        <?php endif;?>
        <p id="final-cost-p" class="cost">Total: <span id="final-cost"></span>.-</p>
		<div class="form-group">
            <label for="info_demandeur">ADMIN: Indication particulière à transmettre au demandeur / à la demandeuse :</label>
            <textarea id="info_demandeur" name="info_demandeur" rows="4"></textarea>
        </div>
        <div id="admin-single-confirm-text"><p>Une fois la réservation confirmée ou annulée, le demandeur ou la demandeuse recevra un email, avec notamment les précisions du champ ci-dessus (par ex. réponse à une question / raisons d'un refus de réservation).</p></div>
        <div class="form-group admin-confirm-buttons">
        	<a class="button" href="admin.php">Retour à la liste</a>
        	<?php if ($res->status == "PREBOOKED"):?>
            <button type="submit" onclick="refuseButton(event)">Refuser la demande</button>
            <?php endif;?>
            <button type="submit" onclick="confirmButton(event)">Valider la demande</button>
        </div>
    </form>
    <div id="loaderModal" class="modal">
		<div id="loader"></div>
	</div>
</body>
</html>