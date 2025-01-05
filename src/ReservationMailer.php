<?php
namespace Theosche\RoomReservation;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ReservationMailer
{
    private $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->mail->CharSet = "UTF-8";
        $this->configureSMTP();
    }

    private function configureSMTP()
    {
        try {
            $this->mail->isSMTP();
            $this->mail->Host       = MAIL_HOST;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = ORGANIZATION_EMAIL;
            $this->mail->Password   = MAIL_PASS;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mail->Port       = MAIL_PORT;

            $this->mail->setFrom(ORGANIZATION_EMAIL, ORGANIZATION);
        } catch (Exception $e) {
        	error_log("Mailer configuration error: {$this->mail->ErrorInfo}");
            throw $e;
        }
    }
    
	public function sendNewReservation($res)
    {
        try {
            $this->mail->addAddress(ORGANIZATION_EMAIL, ORGANIZATION);
            $this->mail->Subject = ucfirst(ROOM) . " - Nouvelle demande de réservation à contrôler";
            $this->mail->isHTML(true);

            // Compose the email body
            $this->mail->Body = $this->composeNewReservationEmail($res);
            $this->mail->AltBody = strip_tags($this->mail->Body);

            $this->mail->send();
        } catch (Exception $e) {
            throw new Exception("Could not send newReservation email: {$this->mail->ErrorInfo}");
        }
    }
    
    public function sendConfirmation($res,$complement)
    {
        try {
            $this->mail->addAddress($res->email, $res->prenom . " " . $res->nom);
            $this->mail->addCC(ORGANIZATION_EMAIL, ORGANIZATION);
            $this->mail->Subject = ucfirst(ROOM) . " - Confirmation de réservation et facture (mail automatique)";
            $this->mail->isHTML(true);

            // Compose the email body
            $this->mail->Body = $this->composeConfirmationEmail($res,$complement);
            $this->mail->AltBody = strip_tags($this->mail->Body);

            $this->mail->send();
        } catch (Exception $e) {
            throw new Exception("Could not send confirmation email: {$this->mail->ErrorInfo}");
        }
    }

    public function sendCancellation($res,$complement)
    {
        try {
            $this->mail->addAddress($res->email, $res->prenom . " " . $res->nom);
            $this->mail->addCC(ORGANIZATION_EMAIL, ORGANIZATION);
            $this->mail->Subject = ucfirst(ROOM) . " - Annulation de votre réservation (mail automatique)";
            $this->mail->isHTML(true);

            // Compose the email body
            $this->mail->Body = $this->composeCancellationEmail($res,$complement);
            $this->mail->AltBody = strip_tags($this->mail->Body);

            $this->mail->send();
        } catch (Exception $e) {
            throw new Exception("Could not send cancellation email: {$this->mail->ErrorInfo}");
        }
    }
    
    public function sendReminder($res)
    {
        try {
        	$rappelnb = $res->nb_reminders > 0 ? ($res->nb_reminders+1) . 'ème ' : "";
            $this->mail->addAddress($res->email, $res->prenom . " " . $res->nom);
            $this->mail->addCC(ORGANIZATION_EMAIL, ORGANIZATION);
            $this->mail->Subject = "Location " . OF_ROOM . " - Facture à payer (" . $rappelnb . "rappel)";
            $this->mail->isHTML(true);

            // Compose the email body
            $this->mail->Body = $this->composeReminderEmail($res);
            $this->mail->AltBody = strip_tags($this->mail->Body);

            $this->mail->send();
        } catch (Exception $e) {
            throw new Exception("Could not send reminder email: {$this->mail->ErrorInfo}");
        }
    }

	private static function getHeader() {
	return "
	<head>
			<meta charset='UTF-8'>
			<meta name='viewport' content='width=device-width, initial-scale=1.0'>
			<style>
				body {
					font-family: Arial, sans-serif;
					color: #333;
					line-height: 1.6;
					background-color: #f9f9f9;
					margin: 0;
					padding: 0;
				}
				.email-container {
					max-width: 600px;
					margin: 20px auto;
					background: #ffffff;
					padding: 20px;
					border: 1px solid #ddd;
					border-radius: 5px;
					box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
				}
				h1 {
					color: #0056b3;
					font-size: 22px;
					text-align: center;
				}
				p {
					margin: 10px 0;
				}
				ul {
					padding-left: 20px;
				}
				ul li {
					margin-bottom: 5px;
				}
				a {
					color: #0056b3;
					text-decoration: none;
				}
				a:hover {
					text-decoration: underline;
				}
				.footer {
					font-size: 12px;
					color: #777;
					text-align: center;
					margin-top: 20px;
				}
			</style>
		</head>";
	}

	private function composeNewReservationEmail($res)
    {
        $email =  "
		<!DOCTYPE html>
		<html lang='fr'>"
		. self::getHeader() .
		"
		<body>
			<div class='email-container'>
				<h1>Nouvelle demande de réservation " . OF_ROOM . "</h1>
				<p>Une demande de réservation " . OF_ROOM . " a été faite par {$res->prenom} {$res->nom} pour l'événement/activité <em>{$res->nom_evenement}</em> " . 
				(count($res->events) > 1 ? "aux dates suivantes" : "à la date suivante") . ":</p>
				<ul>";
				foreach ($res->events as $ev) {
					$icslink = SHOW_ICS_LINKS ? (' (<a href="https://' . $_SERVER['HTTP_HOST'] . '/getevent.php?id=' . $res->id . '&uid=' . $ev['uid'] . '">lien ics</a>)') : '';
					$email .= "<li>" . $ev['start_time']->format('d.m.Y') . ' - ' . $ev['start_time']->format('H:i')
					. ' à ' . $ev['end_time']->format('H:i') . $icslink . '</li>';
				}
				$link = "https://" . $_SERVER['HTTP_HOST'] . "/admin-single.php?id=" . $res->id;
				$email .= "</ul>
				<p>Merci de contrôler la demande pour la confirmer ou la refuser: <a href='" . $link . "'>" . $link . "</a></p>
				<p><strong>" . ORGANIZATION . "</strong><br>" . ORGANIZATION_ADDRESS . "<br>" . ORGANIZATION_NPA . " " . ORGANIZATION_CITY . "</p>
				<div class='footer'>" . SYSTEM_NAME . "</div>
			</div>
		</body>
		</html>";

        return($email);
    }
	
    private function composeConfirmationEmail($res,$complement=null)
    {
        $email =  "
		<!DOCTYPE html>
		<html lang='fr'>"
		. self::getHeader() .
		"
		<body>
			<div class='email-container'>
				<h1>Confirmation de votre réservation et facture</h1>
				<p>Bonjour,</p>
				<p>Votre réservation " . OF_ROOM . " pour l'événement/activité <em>{$res->nom_evenement}</em> a été confirmée pour " . 
				(count($res->events) > 1 ? "les dates suivantes" : "la date suivante") . ":</p>
				<ul>";
				foreach ($res->events as $ev) {
					$icslink = SHOW_ICS_LINKS ? (' (<a href="https://' . $_SERVER['HTTP_HOST'] . '/getevent.php?id=' . $res->id . '&uid=' . $ev['uid'] . '">lien ics</a>)') : '';
					$email .= "<li>" . $ev['start_time']->format('d.m.Y') . ' - ' . $ev['start_time']->format('H:i')
					. ' à ' . $ev['end_time']->format('H:i') . $icslink . '</li>';
				}
				
				$email .= "</ul><p>Vous trouverez une <strong>facture</strong> sur le lien suivant : <a href='" . $res->invoice_link . "'>" . $res->invoice_link . "</a>.";
				$email .= $res->special_red > 0 ? (" Une réduction spéciale de " . number_format($res->special_red, 2, '.', '') . " " . CURRENCY . " vous a été accordée.") : "";
				if (defined_local("INVOICE_DUE_DAYS_BEFORE_RES") && INVOICE_DUE_DAYS_BEFORE_RES >= 0) {
					if (INVOICE_DUE_DAYS_BEFORE_RES == 0) $str_days = "le jour de";
					elseif (INVOICE_DUE_DAYS_BEFORE_RES == 1) $str_days = "un jour avant";
					else $str_days = INVOICE_DUE_DAYS_BEFORE_RES . " jours avant";
					$email .= " Nous vous remercions de régler la facture au plus tard " . $str_days . " la " . (count($res->events) > 1 ? "première date de " : "") . "réservation.";	
				}
				$email .= "</p>";
				$email .= defined_local("SPECIFIC_MSG") ? ("<p>" . SPECIFIC_MSG . "</p>") : "";
				if (USE_SECRET) {
					$codes = $res->getCodesLink();
				 	$email .= "<p>" . SECRET_MSG . " Comme ces informations sont changées occasionnellement, nous vous invitons à les vérifier sur le lien peu avant " . 
					(count($res->events) > 1 ? "les utilisations" : "l'utilisation") . ": <a href='" . $codes . "'>" . $codes . "</a></p>";
				}

		if (!empty($complement)) {
			$email .= "<p>Complément d'information : <em>" . $complement . "</em></p>";
		} 

		$email .= "
				<p>Pour toute question, n'hésitez pas à nous contacter en réponse à cet email.</p>
				<p>Avec nos meilleures salutations,</p>
				<p><strong>" . ORGANIZATION . "</strong><br>" . ORGANIZATION_ADDRESS . "<br>" . ORGANIZATION_NPA . " " . ORGANIZATION_CITY . "</p>
				<div class='footer'>" . SYSTEM_NAME . "</div>
			</div>
		</body>
		</html>";

        return($email);
    }

    private function composeCancellationEmail($res,$complement)
    {
        $email =  "
		<!DOCTYPE html>
		<html lang='fr'>"
		. self::getHeader() .
		"
		<body>
			<div class='email-container'>
				<h1>Annulation de votre réservation</h1>
				<p>Bonjour,</p>
				<p>Vous avez pré-réservé le " . lcfirst(ROOM) . " pour l'événement/activité <em>{$res->nom_evenement}</em> " . (count($res->events) > 1 ? "aux dates suivantes" : "à la date suivante") . ":</p>
				<ul>";
				foreach ($res->events as $ev) {
					$email .= "<li>" . $ev['start_time']->format('d.m.Y') . ' - ' . $ev['start_time']->format('H:i') .
					' à ' . $ev['end_time']->format('H:i') . '</li>'; 
				}
				$email .="</ul>
					<p>Cette pré-réservation a été contrôlée et doit être annulée pour la raison suivante: <em>" . $complement . "</em></p>"; 
				$email .= "<p>Nous vous remercions pour votre compréhension. Pour toute question, n'hésitez pas à nous contacter en réponse à cet email.</p>
				<p>Avec nos meilleures salutations</p>
				<p><strong>" . ORGANIZATION . "</strong><br>" . ORGANIZATION_ADDRESS . "<br>" . ORGANIZATION_NPA . " " . ORGANIZATION_CITY . "</p>
				<div class='footer'>" . SYSTEM_NAME . "</div>
			</div>
		</body>
		</html>";
        return($email);
    }
    
    private function composeReminderEmail($res)
    {
    	$rappelnb = $res->nb_reminders > 0 ? ($res->nb_reminders+1) . 'ème ' : "";
        $email =  "
		<!DOCTYPE html>
		<html lang='fr'>"
		. self::getHeader() .
		"
		<body>
			<div class='email-container'>
				<h1>Location " . OF_ROOM . " - Facture à payer (" . $rappelnb . "rappel)</h1>
				<p>Bonjour,</p>
				<p>Vous avez loué le " . lcfirst(ROOM) . " pour l'événement/activité <em>{$res->nom_evenement}</em> " . 
				(count($res->events) > 1 ? "aux dates suivantes" : "à la date suivante") . ":</p>
				<ul>";
				foreach ($res->events as $ev) {
					$icslink = SHOW_ICS_LINKS ? (' (<a href="https://' . $_SERVER['HTTP_HOST'] . '/getevent.php?id=' . $res->id . '&uid=' . $ev['uid'] . '">lien ics</a>)') : '';
					$email .= "<li>" . $ev['start_time']->format('d.m.Y') . ' - ' . $ev['start_time']->format('H:i')
					. ' à ' . $ev['end_time']->format('H:i') . $icslink . '</li>';
				}
				
				$email .= "</ul><p>Une confirmation et une facture vous sont parvenues par mail le " . $res->created_at->format('d.m.Y') . "."; 
				if (defined_local("INVOICE_DUE_DAYS_BEFORE_RES") && INVOICE_DUE_DAYS_BEFORE_RES >= 0) {
					if (INVOICE_DUE_DAYS_BEFORE_RES == 0) $str_days = "le jour de";
					elseif (INVOICE_DUE_DAYS_BEFORE_RES == 1) $str_days = "un jour avant";
					else $str_days = INVOICE_DUE_DAYS_BEFORE_RES . " jours avant";
					$email .= " En principe, les factures sont à régler au plus tard " . $str_days . " la " . (count($res->events) > 1 ? "première date de " : "") . "réservation.";	
				}	
				$email .= " Selon nos informations, la facture n'a pas encore été payée. " . 
				"Nous nous permettons donc de vous la retransmettre : <a href='" . $res->invoice_link . "'>" . $res->invoice_link . "</a>.</p>" .
				"<p>Nous vous remercions d'avance pour votre paiement. Pour toute question, n'hésitez pas à nous contacter en réponse à cet email.</p>
				<p>Avec nos meilleures salutations,</p>
				<p><strong>" . ORGANIZATION . "</strong><br>" . ORGANIZATION_ADDRESS . "<br>" . ORGANIZATION_NPA . " " . ORGANIZATION_CITY . "</p>
				<div class='footer'>" . SYSTEM_NAME . "</div>
			</div>
		</body>
		</html>";

        return($email);
    }
}