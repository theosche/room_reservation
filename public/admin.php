<?php
namespace Theosche\RoomReservation;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/exceptionHandler.php';

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

$query_status = ['INIT','PREBOOKED','CONFIRMED'];
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'cancelled') {
    	$query_status = ['CANCELLED'];
    } elseif ($_GET['status'] == 'closed') {
    	$query_status = ['CLOSED'];
    }
}

$limit = 10; // Number of entries per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page, defaults to 1
$offset = ($page - 1) * $limit; // Calculate offset

Reservation::initDBOnly();
$totalEntries = Reservation::count(['status' => $query_status]);
$totalPages = ceil($totalEntries / $limit);
$reservations = Reservation::loadFromDB(['status' => $query_status],$limit,$offset);

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
    <meta name="viewport" content="width=device-width, initial-scale=0.8, maximum-scale=1.6">
    <link rel="stylesheet" href="style.css">
    <title>Admin - Liste des Réservations</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script>
    	function reminder_res(id) {
    		const nb_reminders = Number(document.getElementById('reminders_' + id).textContent) + 1;
    		const nb_reminders_str = nb_reminders > 1 ? nb_reminders + "ème " : "";
    		if (!confirm("Voulez-vous vraiment envoyer un " + nb_reminders_str + "rappel ?")) return;
    		sendRequest(id,'remind');
    	}
    	function close_res(id) {
    		if (!confirm("Voulez-vous vraiment clôturer la réservation (facture payée) ?")) return;
    		sendRequest(id,'close');
    	}
    	function delete_res(id) {
    		sendRequest(id,'delete');
    	}
		
    	async function sendRequest(_id,_res_action,info=null) {
    		document.getElementById("loaderModal").style.display = "block";
    		document.querySelector('table').style.opacity = 0.4;
			const formData = new URLSearchParams();
			formData.append("csrf_token", "<?= $_SESSION['csrf_token'] ?>");
			formData.append("id", _id);
			formData.append("res_action", _res_action);
			if (info) {
				formData.append("info_demandeur", info);
			}

			const response = await fetch("admin-form.php", {
				method: "POST",
				body: formData,
				headers: {
					"Content-Type": "application/x-www-form-urlencoded"
				}
			});
			if (!response.ok) {
				throw new Error('Erreur lors de l\'envoi au serveur.');
			}
			const result = await response.json();
			if (result.success) {
				window.location.reload();
			} else {
				console.log(result);
				document.getElementById("loaderModal").style.display = "none";
			}
    	}
    	document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('tr.res-entry').forEach(row => {
				row.addEventListener('click', function (event) {
					// Vérifier si le clic est sur un lien ou un bouton
					if (event.target.tagName === 'A' || event.target.tagName === 'BUTTON' || event.target.tagName === 'I' ||
						event.target.closest('a') || event.target.closest('button') || event.target.closest('i')) {
						return; // Ne pas ouvrir la ligne détaillée
					}
					const detailRow = row.nextElementSibling.querySelector("div.details");
					if (detailRow) {
						detailRow.classList.toggle('hidden');
					}
				});
			});
			const cancelModal = document.getElementById('cancelModal');
			const cancelReason = document.getElementById('cancelReason');
			const cancelModalClose = document.getElementById('cancelModalClose');
			const cancelModalConfirm = document.getElementById('cancelModalConfirm');

			let currentReservationId = null;

			// Affiche la modale
			function showCancelModal(reservationId) {
				currentReservationId = reservationId;
				cancelReason.value = ""; // Réinitialise le champ
				cancelModalConfirm.disabled = true; // Désactive le bouton
				cancelModal.style.display = 'block';
			}

			// Masque la modale
			function closeCancelModal() {
				cancelModal.style.display = 'none';
				currentReservationId = null;
			}

			// Active le bouton confirmer si le champ est rempli
			cancelReason.addEventListener('input', () => {
				cancelModalConfirm.disabled = cancelReason.value.trim() === "";
			});

			// Annuler la réservation avec raison
			cancelModalConfirm.addEventListener('click', () => {
				if (currentReservationId && cancelReason.value.trim()) {
					sendRequest(currentReservationId, 'refuse', cancelReason.value.trim());
					closeCancelModal();
				}
			});

			// Ferme la modale sans action
			cancelModalClose.addEventListener('click', closeCancelModal);

			// Ajoutez cette fonction au bouton d'annulation
			window.cancel_res = showCancelModal;
		});
    </script>
</head>
<body>
	<div class="page-container">
  	<div class="content">
    <h1 id="main-title"><?=ROOM?> - Liste des Réservations</h1>
    <div id="filters">
        <a href="admin.php" class="<?= !(isset($_GET['status']) && ($_GET['status'] == 'cancelled' || $_GET['status'] == 'closed')) ? 'active' : '' ?>">Actives</a>
        <a href="admin.php?status=cancelled" class="<?= isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'active' : '' ?>">Annulées</a>
        <a href="admin.php?status=closed" class="<?= isset($_GET['status']) && $_GET['status'] == 'closed' ? 'active' : '' ?>">Terminées</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Entité</th>
                <th>Événement</th>
                <th>Contact</th>
                <th>Prix (CHF)</th>
                <th>État</th>
                <th>Date de Création</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reservations as $res): ?>
                <tr class="res-entry">
                    <td><?= $res->id ?></td>
                    <td class="entity">
                        <?= $res->entite ?>
                        	<?php
                        	if (defined_local("ENTITYTYPES")) {
								foreach(ENTITYTYPES as $type) {
									if ($res->{$type['dbkey']}) {?>
										<span class="entity-icon" data-tooltip="<?=$type['type_short']?>"><?= $type['symbol'] ?></i></span>
									<?php	
									}
								}
							}
                        	?>
                    </td>
                    <td><?= $res->nom_evenement ?></td>
                    <td>
                        <div class="contact-icons">
                            <?= $res->prenom . ' ' . $res->nom ?>
                            <a href="mailto:<?= $res->email ?>"><i class="fas fa-envelope"></i></a>
                            <a href="tel:<?= $res->telephone ?>"><i class="fas fa-phone"></i></a>
                        </div>
                    </td>
                    <td class="column-price"><?= number_format($res->price, 2, ',', ' ') ?></td>
                    <td><span class="status status-<?= $res->status ?>"><?= $status_fr[$res->status] ?></span></td>
                    <td><?= $res->created_at->format("d/m/Y") ?></td>
                    <td class="column-actions"><?php if ($res->status == 'PREBOOKED' || $res->status == 'CANCELLED'):?>
                        <a class="action-button" href="admin-single.php?id=<?= $res->id ?>">Contrôler</a>
                        <?php endif;?>
                        <?php if ($res->status == 'PREBOOKED' || $res->status == 'CONFIRMED'):?>
                        <a class="action-button" onclick="cancel_res(<?= $res->id ?>)">Annuler</a>
                        <?php endif;?>
                        <?php if ($res->status == 'CONFIRMED'):?>
                        <a class="action-button" onclick="reminder_res(<?= $res->id ?>)">Rappel</a>
                        <a class="action-button" onclick="close_res(<?= $res->id ?>)">Clôturer</a>
                        <?php endif;?>
                        <?php if ($res->status == 'INIT'):?>
                        <a class="action-button" onclick="delete_res(<?= $res->id ?>)">Supprimer</a>
                        <?php endif;?>
                    </td>
                </tr>
                <tr id="details-<?= $res->id ?>" class="details">
                    <td colspan="8">
                    	<div class="details hidden">
                        	<div class="details-column">
								<strong>Description :</strong>
								<p><?= $res->description_evenement ?></p>
							</div>
							<div class="details-column">
								<strong>Dates de Réservation :</strong>
								<ul>
									<?php
									if ($res->status != 'CANCELLED' && SHOW_ICS_LINKS) {
										foreach ($res->events as $event): ?>
											<li class="icslink"><span class="event-date" data-tooltip="<?= $event['text'] ?>"><?=$event['start_time']->format('d/m/Y H:i') ?> - <?= $event['end_time']->format('H:i') ?></span><a class="icslink" href="getevent.php?id=<?=$res->id?>&uid=<?=$event['uid']?>"><i class="fas fa-calendar"></i></a></li>
										<?php endforeach;
									} else {
										foreach ($res->events as $event): ?>
											<li><span class="event-date" data-tooltip="<?= $event['text'] ?>"><?=$event['start_time']->format('d/m/Y H:i') ?> - <?= $event['end_time']->format('H:i') ?></span></li>
										<?php endforeach;
									}
									?>
								</ul>
							</div>
							<div class="details-column documents-details">
								<strong class="document-title">Documents :</strong>
								<a href="<?= $res->prebook_link ?>" target="_blank">Pré-réservation</a>
								<?php if ($res->status === 'CONFIRMED' || $res->status === 'CLOSED'): ?>
									<a href="<?= $res->invoice_link ?>" target="_blank">Facture</a>
								<?php endif; ?>
								<br>
								<?php if ($res->don > 0):?>
									<br><strong>Don :</strong> <?= $res->don ?> <?= CURRENCY ?>
								<?php endif;?>
								<?php if ($res->special_red > 0):?>
									<br><strong>Réduction spéciale :</strong> -<?= $res->special_red ?> <?= CURRENCY ?>
								<?php endif;?>
								
							</div>
							<?php if ($res->status === 'CONFIRMED'): ?>
								<div class="details-column invoice-details">
									<strong>Date Facture :</strong> <?= $res->invoice_date->format('d/m/Y') ?><br>
									<?php if ($res->nb_reminders > 0):?>
									<strong>Dernier Rappel :</strong> <?= $res->last_reminder_date->format('d/m/Y') ?><br>
									<?php endif;?>
									<strong>Rappels :</strong> <span id="reminders_<?= $res->id ?>"><?= $res->nb_reminders ?></span>
								</div>
							<?php else: ?>
								<div class="details-column invoice-details">
										<strong style="visibility: hidden;">Date Facture :</strong>
								</div>
							<?php endif;?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
	<div class="pagination">
		<?php
		$range = 2;
		$secondary_range = 10;
		$showFirst = 1;
		$showLast = $totalPages;
		$stat = isset($_GET['status']) ? ("&status=" . $_GET['status']) : "";

		echo '<ul class="pagination">';

		// "First" link
		if (max($showFirst, $page - $range) > 1) {
			echo '<li><a href="admin.php?page=1' . $stat . '">1</a></li>';
		}
		
		// Secondary range
		if ($page - $secondary_range > 2) {
			echo '<li><a>...</a></li>';
		}
		if ($page - $secondary_range > 1) {
			echo '<li><a href="admin.php?page=' . $page - $secondary_range . $stat . '">' . $page - $secondary_range . '</a></li>';
		}
		
		// Range links
		if (max($showFirst, $page - $range) > 2) {
			echo '<li><a>...</a></li>';
		}
		if ($totalPages > 1) {
			for ($i = max($showFirst, $page - $range); $i <= min($showLast, $page + $range); $i++) {
				if ($i == $page) {
					echo '<li><a class="current-page">' . $i . '</a></li>';
				} else {
					echo '<li><a href="admin.php?page=' . $i . $stat . '">' . $i . '</a></li>';
				}
			}
		}
		if (min($showLast, $page + $range) < $totalPages-1) {
			echo '<li><a>...</a></li>';
		}
		
		// Secondary range
		if ($page + $secondary_range < $totalPages) {
			echo '<li><a href="admin.php?page=' . $page + $secondary_range . $stat . '">' . $page + $secondary_range . '</a></li>';
		}
		if ($page + $secondary_range < $totalPages-1) {
			echo '<li><a>...</a></li>';
		}
		
		// "Last" link
		if (min($showLast, $page + $range) < $totalPages) {
			echo '<li><a href="admin.php?page=' . $totalPages . $stat . '">' . $totalPages . '</a></li>';
		}

		echo '</ul>';
		?>
	</div>
	</div>
	<div id="loaderModal" class="modal">
		<div id="loader"></div>
	</div>
	<div id="cancelModal" class="modal">
		<div class="modal-content">
			<h2>Annulation de la réservation</h2>
			<p>Merci de compléter le champ ci-dessous pour expliquer les raisons de l'annulation. Ces informations seront transmises au demandeur/à la demandeuse.</p>
			<textarea id="cancelReason" rows="4" placeholder="Indiquez la raison ici..."></textarea>
			<div class="modal-actions">
				<button id="cancelModalClose" class="btn-secondary">Annuler</button>
				<button id="cancelModalConfirm" class="btn-primary" disabled>Confirmer</button>
			</div>
		</div>
	</div>
</body>
</html>