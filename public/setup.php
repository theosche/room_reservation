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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		echo "CSRF Token error";
		http_response_code(403);
		exit;
	}

    // Get user inputs
    $mysqlHost = DBHOST;
    $mysqlUser = $_POST['mysql_user'];
    $mysqlPassword = $_POST['mysql_password'];
    $dbName = DBNAME;
    $dbUser = DBUSER;
    $dbUserPassword = DBPASS;

    try {
        // Connect to MySQL with root user
        $pdo = new \PDO("mysql:host=$mysqlHost", $mysqlUser, $mysqlPassword);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // Check if the database already exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '$dbName'");
        if ($stmt->fetch()) {
            throw new \Exception("La DB '$dbName' existe déjà.<br>");
        }

        // Create the database
        echo "CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci<br>";
        $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Create the new user and grant privileges
        try {
        	echo "CREATE USER '$dbUser'@'$mysqlHost' IDENTIFIED BY '$dbUserPassword'<br>";
        	$pdo->exec("CREATE USER '$dbUser'@'$mysqlHost' IDENTIFIED BY '$dbUserPassword'");
        } catch (\Throwable $e) {
        	echo "Unable to create user. Maybe it already exists";
        } 
		echo "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES ON `$dbName`.* TO '$dbUser'@'$mysqlHost'";
        $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES ON `$dbName`.* TO '$dbUser'@'$mysqlHost'");
		echo "FLUSH PRIVILEGES<br>";
        $pdo->exec("FLUSH PRIVILEGES");
        echo "L'utilisateur '$dbUser' a été créé et a reçu les permissions utiles.<br>";
		echo "USE `$dbName`<br>";
        // Connect to the new database
        $pdo->exec("USE `$dbName`");

        // Create the reservations table
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
        echo "Table 'reservations' created successfully.<br>";

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
        echo "Table 'events' created successfully.<br>";
        echo "<strong>Base de données créée avec succès</strong><br>";
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage();
    }
    
	echo "Mise à jour de la classe Reservation<br>";
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
		echo "La classe Reservation est déjà à jour<br>";
	} else {
		$ReservationClassContents = preg_replace($pattern, $replacement, $ReservationClassContents);
		if (!is_writable($ReservationClassFile)) {
			echo "Impossible de mettre à jour la classe Reservation (pas d'accès en écriture à src/Reservation.php)<br>";
			echo "Donner les accès en écriture à l'utilisateur du serveur pour Reservation.php et utiliser update-structure.php<br>";
		} else {
			file_put_contents($ReservationClassFile, $ReservationClassContents);
			echo "<strong>Classe Reservation mise à jour avec succès</strong>";
		}
	}
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Création de la base de données et mise à jour de la classe Reservations</title>
</head>
<body>
	<h1>Création de la base de données et mise à jour de la classe Reservations</h1>

<p>Ce script crée une nouvelle base de données avec un utilisateur, puis ajoute les tables nécessaires, 
selon la configuration dans config.php. Pour cela, un accès root à mysql est nécessaire.
(ou autre utilisateur qui a les permissions pour créer une nouvelle DB)</p>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
	<label>
		MySQL root user:
		<input type="text" value="root" name="mysql_user" required>
	</label><br>
	<label>
		MySQL root password:
		<input type="password" name="mysql_password" required>
	</label><br>
	<button type="submit">Créer la base de données</button>
</form>