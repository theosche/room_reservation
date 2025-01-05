<?php
namespace Theosche\RoomReservation;
require __DIR__ . '/../config.php';

session_start();

if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    header('Location: admin.php');
    exit;
}

// Traitement du formulaire
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === ADMIN && password_verify($password, HASH)) {
        $_SESSION['is_admin'] = true;
        $redirectTo = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'admin.php';
        unset($_SESSION['redirect_after_login']);
        header("Location: $redirectTo");
        exit;
    } else {
        $error = 'Identifiant ou mot de passe incorrect.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administration</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
	<div class="login-form">
    <h1 id="main-title">Connexion Administration</h1>

    <?php if ($error): ?>
        <p id="incorrect-password"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
    	<div class="form-group">
    	    <label for="username">Identifiant :</label>
	        <input type="text" id="username" name="username" required>
		</div>
		<div class="form-group">
	        <label for="password">Mot de passe :</label>
    	    <input type="password" id="password" name="password" required>
    	</div>

        <button type="submit">Se connecter</button>
    </form>
    </div>
</body>
</html>