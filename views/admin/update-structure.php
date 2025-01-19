<?php
namespace Theosche\RoomReservation;

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
		require __DIR__ . '../../views/403.html';
		die;
	}
	if (isset($_POST['create_tables']) && $_POST['create_tables'] == "on") {
		 $stmt = "
            CREATE TABLE reservations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entite VARCHAR(100) NOT NULL,
                prenom VARCHAR(50) NOT NULL,
                nom VARCHAR(50) NOT NULL,
                adresse TEXT NOT NULL,
                npa SMALLINT NOT NULL,
                localite VARCHAR(50) NOT NULL,
                email VARCHAR(60) NOT NULL,
                telephone VARCHAR(13) NOT NULL,
                nom_evenement VARCHAR(100) NOT NULL,
                description_evenement TEXT,
                don DECIMAL(6,2) NOT NULL,
                special_red DECIMAL(6,2) DEFAULT 0,
                price DECIMAL(6,2) DEFAULT 0,
                prebook_link VARCHAR(300) DEFAULT NULL,
                invoice_link VARCHAR(300) DEFAULT NULL,
                status ENUM('INIT', 'PREBOOKED', 'CONFIRMED', 'CLOSED', 'CANCELLED'),
                invoice_date DATE DEFAULT NULL,
                nb_reminders TINYINT DEFAULT 0,
                last_reminder_date DATE DEFAULT NULL,
                created_at DATETIME NOT NULL";

		$resKeys = array_column(ENTITYTYPES, 'dbkey');
		foreach($resKeys as $key) {
			$stmt .= ",
				$key BOOLEAN DEFAULT 0";
		} 	
		$stmt .= "
            )
        ";
        $pdo->exec($stmt);
        echo $stmt . "<br>";
        echo "Table 'reservations' créée avec succès.<br>";

        // Create the events table
        $stmt = "
            CREATE TABLE events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reservation_id INT NOT NULL,
                start_time DATETIME NOT NULL,
                end_time DATETIME NOT NULL,
                uid CHAR(36) DEFAULT NULL,
                text TEXT NOT NULL,
                price DECIMAL(6,2) NOT NULL,";
        $eventsKeys = array_column(OPTIONS, 'dbkey');
        foreach($eventsKeys as $key) {
        	$stmt .= "
        		$key BOOLEAN DEFAULT 0,";
        }
        $stmt .= "
                FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
            )
        ";
        echo $stmt . "<br>";
        $pdo->exec($stmt);
        echo "Table 'events' créée avec succès.<br>";
		echo "Tables créées avec succès. La base de données est à jour.";
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

$query = $pdo->query("
	SELECT count(*)
	FROM information_schema.TABLES
	WHERE (TABLE_SCHEMA = '" . DBNAME . "')
	AND (TABLE_NAME = 'reservations')
");
$tableExists = $query->fetchColumn();

if (!$tableExists) {
	$missingResColumns = false;
	$excessResColumns = false;
	$missingEvColumns = false;
	$excessEvColumns = false;
} else {

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
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mise à jour de la base de données et de la classe Reservations</title>
</head>
<body>
	<h1>Mise à jour de la base de données et de la classe Reservations</h1>
	<?php if ($missingResColumns || $excessResColumns || $missingEvColumns || $excessEvColumns || !$tableExists): ?>
		<form method="POST">
		<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
	<?php endif; ?>

	<?php if (!$tableExists): ?>
		<h2>Les tables n'existent pas et vont être créées.</h2>
		<label>
			<input type="checkbox" name="create_tables" value="on" checked>
			Créer les tables 'reservations' et 'events'
		</label><br>
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

	<?php if ($missingResColumns || $excessResColumns || $missingEvColumns || $excessEvColumns || !$tableExists): ?>
		<br><button type="submit">Mettre à jour la base de données</button>
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
			echo "<p>Impossible de mettre à jour la classe Reservation (pas d'accès en écriture à src/Reservation.php). Donner les accès et réessayer.</p>";
		} else {
			file_put_contents($ReservationClassFile, $ReservationClassContents);
			echo "<p>Classe Reservation mise à jour avec succès</p>";
		}
	}
	?>
</body>
</html>
