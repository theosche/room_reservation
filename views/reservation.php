<?php
namespace Theosche\RoomReservation;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation <?=OF_ROOM?></title>
	<link rel="stylesheet" href="/style.css">
	<script type="text/javascript" src="/js.php"></script>
	<script async defer src="https://eu.altcha.org/js/latest/altcha.min.js" type="module"></script>
	<script>
		const review = false;
		let occurrencePrice = []; // Tableau pour stocker l'état des occurrences
        
        let eventsDates = []; // Variable globale pour stocker les réservations existantes
        let eventsUIDs = [];

		// Charger et parser le fichier ICS au chargement de la page
		async function loadCalendar() {
			try {
				const response = await fetch('/availability.php'); // Proxy PHP pour le fichier ICS
				const data = await response.json();
        
				// Access the start and end dates
				eventsDates = data[0].map(line => line.map(date => new Date(date)));

			} catch (error) {
				console.error('Erreur lors du chargement du calendrier :', error);
			}
		}
		
		document.addEventListener('DOMContentLoaded', function() {
			addOccurrence();
			loadCalendar();
			document.getElementById('reservation-form').addEventListener('submit', (event) => {
				// Vérifiez si toutes les occurrences sont valides
				const allValid = occurrencePrice.every(state => state);

				if (!allValid) {
					event.preventDefault();
					alert('Veuillez corriger les erreurs dans les créneaux avant de soumettre.');
				}
			});
			updateTotalCost();
		});
		async function handleFormSubmission(event) {
			event.preventDefault(); // Empêche le rechargement de la page
			document.getElementById("loaderModal").style.display = "block";

			const form = event.target;
			const formData = new FormData(form);

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
				console.log(msg);
				document.getElementById("loaderModal").style.display = "none";
				alert('Une erreur est survenue. Veuillez vérifier et réessayer. N\'hésitez pas à nous contacter. ' + msg.error);
			} else {
				// Clean in case the user comes back
				document.getElementById("loaderModal").style.display = "none";
				const occurrences = document.getElementsByClassName("remove-btn");
				for (let item of occurrences) {
					removeOccurrence(item);
				}
				addOccurrence();
				window.location.href = msg.prebook_link;
			}
		}
    </script>
</head>
<body>
    <h1 id="main-title">Réservation <?=OF_ROOM?></h1>
    <?php if(DAVEMBED_USER && defined_local("DAVEMBED_URL")): ?>
    	<iframe width="100%" height="700" src="<?=DAVEMBED_URL?>"></iframe>
    <?php endif;?>
    <form method="post" action="/form.php" accept-charset="utf-8" onsubmit="handleFormSubmission(event)" id="reservation-form">
        <!-- Informations sur la personne ou association -->
        <div class="form-group">
            <label for="association">Nom de l'association / collectif / entité / personne :</label>
            <input type="text" id="association" name="association" required>
        </div>

        <div class="form-group">
            <label>Personne de contact :</label>
            <div class="name-group">
                <input type="text" aria-label="Prénom" name="prenom" placeholder="Prénom" required>
                <input type="text" aria-label="Nom" name="nom" placeholder="Nom" required>
            </div>
        </div>

        <div class="form-group">
            <label for="adresse">Adresse :</label>
            <textarea maxlength="100" id="adresse" name="adresse" rows="2" required></textarea>
        </div>
        <div class="form-group">
         <label>Localité :</label>
            <div class="name-group">
                <input type="number" aria-label="NPA" style="width:50%;" name="npa" placeholder="NPA" required>
                <input type="text" aria-label="Localité" name="localite" placeholder="Localité" required>
            </div>
        </div>

        <div class="form-group">
            <label for="email">Adresse email :</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="telephone">Numéro de téléphone :</label>
            <input type="tel" id="telephone" name="telephone" pattern="[0-9]{10}" placeholder="Format : 0123456789 (uniquement numéros)" required>
        </div>
        
        <?php if(defined_local("ENTITYTYPES")):?>
        <div class="form-group" id="entity-type-form">
			<label>Nature de l'entité :</label>
			<?php foreach(ENTITYTYPES as $entity):?>
			<label class="entity-type-form-entry">
				<input type="checkbox" id="<?=$entity['dbkey']?>" name="<?=$entity['dbkey']?>">
				<?=$entity['text']?> (<?= ($entity['price'] > 0 ? "+" : "") . round(100*$entity['price']) ?>%)
			</label>
			<?php endforeach;?>
		</div>
		<?php endif;?>

        <!-- Informations sur l'événement -->
        <div class="form-group">
            <label for="nom_evenement">Nom de l'événement/activité :</label>
            <input type="text" id="nom_evenement" name="nom_evenement" required>
        </div>

        <div class="form-group">
            <label for="description_evenement">Description de l'événement/activité et demandes particulières :</label>
            <textarea maxlength="250" id="description_evenement" name="description_evenement" rows="4" required></textarea>
        </div>

        <!-- Occurrences de date et heure -->
        <div id="occurrences">
			<label>Date et heures de réservation :</label>
			<label class="label-precision">Date, heure début, heure fin</label>
        </div>

        <div class="form-group">
            <button type="button" onclick="addOccurrence()">Ajouter une occurrence</button>
        </div>
        <?php if (USE_DONATION):?>
			<div class="form-group">
				<label for="don">Ajouter un don en CHF à <?=THE_ORGANIZATION?> (facultatif)</label>
				<input type="number" id="don" name="don" value="0">
			</div>
        <?php endif;?>
        <p id="total-cost-p" class="cost">Total initial: <span id="total-cost">0</span>.-</p>
        <?php if(defined_local("ENTITYTYPES")):?>
        	<?php foreach(ENTITYTYPES as $entity):?>
        		<p id="<?=$entity['dbkey']?>-text" class="cost"><?=$entity['price_str']?> (<?= ($entity['price'] > 0 ? "+" : "") . round(100*$entity['price']) ?>%): <span id="<?=$entity['dbkey']?>-price"></span>.-</p>
			<?php endforeach;?>
        <?php endif;?>
        <?php if (USE_DONATION):?>
        	<p id="don-cost-p" class="cost">Don supplémentaire: <span id="don-cost">0</span>.-</p>
        <?php endif;?>
        <p id="final-cost-p" class="cost">Total: <span id="final-cost">0</span>.-</p>
		<?php if (defined_local("CHARTE_LINK")):?>
			<div class="form-group">
				<label>
					<input name="charte" type="checkbox" required>
					J'ai pris connaissance de la <a href="<?=CHARTE_LINK?>" target="_blank" rel="noopener noreferrer">charte <?=OF_ROOM?></a>
				</label>
			</div>
		<?php endif;?>
        <?php if (defined_local("ALTCHA_CHALLENGEURL") && defined_local("ALTCHA_HMACKEY")):?>
			<altcha-widget
				challengeurl="<?=ALTCHA_CHALLENGEURL?>"
				spamfilter
				strings='{"ariaLinkLabel":"Visiter Altcha.org","error":"Erreur lors de la vérification. Réessayez plus tard.","expired":"Expiration de la vérification. Réessayez plus tard.","footer":"Protégé par <a href=\"https://altcha.org/\" target=\"_blank\" aria-label=\"Visiter Altcha.org\">ALTCHA</a>","label":"Je ne suis pas un robot","verified":"Vérifié","verifying":"Vérification...","waitAlert":"Vérification... patience."}'
			></altcha-widget>
        <?php endif;?>
        <div class="form-group">
            <button type="submit">Valider la demande de réservation</button>
        </div>
    </form>
    <div id="loaderModal" class="modal">
		<div id="loader"></div>
	</div>
</body>
</html>