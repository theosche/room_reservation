<?php
namespace Theosche\RoomReservation;
require_once '../config.php';

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

if (!ALLOW_ALTER_STRUCTURE) {
	echo "Erreur: ALLOW_ALTER_STRUCTURE doit être activé pour utiliser ce script (config.php)";
	exit;
}

try {
	$db = "mysql:host=" . DBHOST . ";dbname=" . DBNAME . ";charset=utf8";
	$pdo = new \PDO($db, DBUSER, DBPASS, [
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
	]);
} catch (\PDOException $e) {
	die('Erreur de connexion : ' . $e->getMessage());
}    

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		echo "CSRF Token error";
		http_response_code(403);
		exit;
	}
    if (!empty($_POST['add_res_columns'])) {
        foreach ($_POST['add_res_columns'] as $column) {
	        $pdo->exec("ALTER TABLE reservations ADD COLUMN $column BOOLEAN DEFAULT 0");
        }
    }
    if (!empty($_POST['delete_res_columns'])) {
        foreach ($_POST['delete_res_columns'] as $column) {
            $pdo->exec("ALTER TABLE reservations DROP COLUMN $column");
        }
    }
    if (!empty($_POST['add_ev_columns'])) {
        foreach ($_POST['add_ev_columns'] as $column) {
	        $pdo->exec("ALTER TABLE events ADD COLUMN $column BOOLEAN DEFAULT 0");
        }
    }
    if (!empty($_POST['delete_ev_columns'])) {
        foreach ($_POST['delete_ev_columns'] as $column) {
            $pdo->exec("ALTER TABLE events DROP COLUMN $column");
        }
    }
    echo "Base de données mise à jour avec succès !";
    exit;
}

// Reservations table
$resKeys = array_column(ENTITYTYPES, 'dbkey');
$query = $pdo->query("
    SELECT COLUMN_NAME 
	FROM INFORMATION_SCHEMA.COLUMNS 
	WHERE TABLE_NAME = 'reservations' 
	AND TABLE_SCHEMA = '" . DBNAME . "' 
	AND COLUMN_TYPE = 'tinyint(1)'
	AND COLUMN_DEFAULT = '0'
");
$existingColumns = $query->fetchAll(\PDO::FETCH_COLUMN);
$missingResColumns = array_diff($resKeys, $existingColumns);
$excessResColumns = array_diff($existingColumns, $resKeys);

// Events table
$eventsKeys = array_column(OPTIONS, 'dbkey');
$query = $pdo->query("
    SELECT COLUMN_NAME 
	FROM INFORMATION_SCHEMA.COLUMNS 
	WHERE TABLE_NAME = 'events' 
	AND TABLE_SCHEMA = '" . DBNAME . "' 
	AND COLUMN_TYPE = 'tinyint(1)'
	AND COLUMN_DEFAULT = '0'
");
$existingColumns = $query->fetchAll(\PDO::FETCH_COLUMN);
$missingEvColumns = array_diff($eventsKeys, $existingColumns);
$excessEvColumns = array_diff($existingColumns, $eventsKeys);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mise à jour de la base de données et de la classe Reservations</title>
</head>
<body>
	<h1>Mise à jour de la base de données et de la classe Reservations</h1>
	<?php if ($missingResColumns || $excessResColumns || $missingEvColumns || $excessEvColumns): ?>
		<form method="POST">
		<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
	<?php endif; ?>

	<?php if ($missingResColumns): ?>
		<h2>Colonnes manquantes dans la table 'reservations'</h2>
			<p>Les colonnes suivantes doivent être ajoutées:</p>
			<ul>
				<?php foreach ($missingResColumns as $column): ?>
					<li>
						<label>
							<input type="checkbox" name="add_res_columns[]" value="<?= htmlspecialchars($column) ?>" checked>
							<?= htmlspecialchars($column) ?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
	<?php endif; ?>

	<?php if ($excessResColumns): ?>
		<h2>Colonnes en trop</h2>
		<p>Les colonnes suivantes existent dans la table 'reservations' mais ne sont plus requises dans la configuration actuelle. Elles peuvent être supprimées ou conservées au cas où l'option est réactivée plus tard. En cas de suppression, toutes les données liées à cette option sont supprimées:</p>
		<ul>
			<?php foreach ($excessResColumns as $column): ?>
				<li>
					<label>
						<input type="checkbox" name="delete_res_columns[]" value="<?= htmlspecialchars($column) ?>">
						<?= htmlspecialchars($column) ?> (cocher pour supprimer)
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ($missingEvColumns): ?>
		<h2>Colonnes manquantes dans la table 'events'</h2>
			<p>Les colonnes suivantes doivent être ajoutées:</p>
			<ul>
				<?php foreach ($missingEvColumns as $column): ?>
					<li>
						<label>
							<input type="checkbox" name="add_ev_columns[]" value="<?= htmlspecialchars($column) ?>" checked>
							<?= htmlspecialchars($column) ?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
	<?php endif; ?>

	<?php if ($excessEvColumns): ?>
		<h2>Colonnes en trop</h2>
		<p>Les colonnes suivantes existent dans la table 'events' mais ne sont plus requises dans la configuration actuelle. Elles peuvent être supprimées ou conservées au cas où l'option est réactivée plus tard. En cas de suppression, toutes les données liées à cette option sont supprimées:</p>
		<ul>
			<?php foreach ($excessEvColumns as $column): ?>
				<li>
					<label>
						<input type="checkbox" name="delete_ev_columns[]" value="<?= htmlspecialchars($column) ?>">
						<?= htmlspecialchars($column) ?> (cocher pour supprimer)
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ($missingResColumns || $excessResColumns || $missingEvColumns || $excessEvColumns): ?>
		<button type="submit">Update Database</button>
		</form>
	<?php else: ?>
		<p>La base de données est à jour</p>
	<?php endif; ?>
	<?php
	// Update class Reservation
	$ReservationClassFile = __DIR__ . '/../src/Reservation.php';
	$ReservationClassContents = file_get_contents($ReservationClassFile);
	$pattern = '/\/\/\sSTART\sOF\sDYNAMIC\sCLASS\sPROPERTIES\s\/\/[\s\S]*\/\/\sEND\sOF\sDYNAMIC\sCLASS\sPROPERTIES\s\/\//';
	$found = [];
	preg_match($pattern,$ReservationClassContents,$found);
	$replacement = 
	'// START OF DYNAMIC CLASS PROPERTIES //
	';
		if ($resKeys) {
			$replacement .=
	'public $' . implode(', $', $resKeys) . ' = 0;
	';	
	}
	$replacement .= '// END OF DYNAMIC CLASS PROPERTIES //';
	if ($found[0] == $replacement) {
		echo "<p>La classe Reservation est déjà à jour</p>";
	} else {
		$ReservationClassContents = preg_replace($pattern, $replacement, $ReservationClassContents);
		if (!is_writable($ReservationClassFile)) {
			echo "<p>Impossible de mettre à jour la classe Reservation (pas d'accès en écriture à src/Reservation.php)</p>";
		} else {
			file_put_contents($ReservationClassFile, $ReservationClassContents);
			echo "<p>Classe Reservation mise à jour avec succès</p>";
		}
	}
	?>
</body>
</html>
