<?php
namespace Theosche\RoomReservation;

	//PERSONNALISATION
	newconst("PRICE_SHORT", 80);			// Prix des réservations courtes
	newconst("PRICE_FULL_DAY", 140);		// Prix pour une journée entière
	newconst("MAX_HOURS_SHORT", 5);			// Limite entre réservations courtes / journée entière
	// Permet d'accorder le tarif "court" pour les réservations du soir à partir d'une certaine heure
	newconst("ALWAYS_SHORT_AFTER", 17);
	
	// FACULTATIF: Définition de types d'entités qui ont droit à des réductions ou augmentations
	// Clés: text, price_str, type_short, price,  dbkey, symbol
	// ATTENTION: LA BASE DE DONNEES DOIT ETRE MISE A JOUR SI CES PARAMETRES SONT MODIFIES
	// (VOIR update-structure.php et setup.php)
	newconst("ENTITYTYPES", [
		[
			"text" => "Privé / Entreprise (prix majorés)",
			"price_str" => "Majoration location privée",
			"type_short" => "Entité privée",
			"price" => 1,
			"dbkey" => "is_private",
			"symbol" => '<i class="fas fa-user"></i>',
		],
		[
			"text" => "Association membre",
			"price_str" => "Réduction membre",
			"type_short" => "Membre",
			"price" => -0.3,
			"dbkey" => "is_member",
			"symbol" => '<i class="fas fa-star"></i>',
		],
		[
			"text" => "Petite association entièrement bénévole",
			"price_str" => "Réduction petite association",
			"type_short" => "Petite association",
			"price" => -0.2,
			"dbkey" => "is_volunteer",
			"symbol" => '<i class="fas fa-hands-helping"></i>',
		],
	]);
	
	// FACULTATIF: Définition d'options à cocher pour chaque événement
	// Clés: text, text_short, price, dbkey
	// ATTENTION: LA BASE DE DONNEES DOIT ETRE MISE A JOUR SI CES PARAMETRES SONT MODIFIES
	// (VOIR update-structure.php et setup.php)
	newconst("OPTIONS", [
		[
			"text" => "Accès à la cuisine",		
			"text_short" => "cuisine",
			"price" => 40,
			"dbkey" => "kitchen",
		],
		[
			"text" => "Beamer",		
			"text_short" => "beamer",
			"price" => 20,
			"dbkey" => "beamer",
		],
	]);
	newconst("FUTURE_RESERVATION_LIMIT", 12);				// Limite les réservations trop à l'avance (mois)
	newconst("USE_SPECIAL_RED", true); 						// Permet à l'admin d'accorder des rédactions spéciales
	newconst("USE_DONATION", true); 						// Propose au demandeur / à la demandeuse d'ajouter un don
	newconst("ORGANIZATION", "Association XYZ");			// Nom de l'organisazion
	newconst("THE_ORGANIZATION", "l'Association XYZ"); 		// Même chose avec le / la / l'
	newconst("ORGANIZATION_EMAIL", "email@exemple.ch");
	newconst("ORGANIZATION_ADDRESS", "Rue des oiseaux 32");
	newconst("ORGANIZATION_NPA", 1400);
	newconst("ORGANIZATION_CITY", "Gotham City");
	newconst("ROOM", "Le Foyer"); 							// Nom de la salle
	newconst("ROOM_SHORT", "Foyer"); 						// Version courte sans espace (nom de fichiers)
	newconst("OF_ROOM", "du Foyer");						// avec "de la" / "du"
	// FACULTATIF (sinon commenter): Lien vers une charte à accepter
	newconst("CHARTE_LINK", "https://wiki.sports-5.ch/index.php?title=Foyer#Charte_du_Foyer");
	// FACULTATIF (sinon commenter): Facture doit dans tous les cas être réglée au plus tard 
	// x jours avant les réservations. Nombre >= 0
	newconst("INVOICE_DUE_DAYS_BEFORE_RES", 5);
	// Délai de paiement normal de factures
	newconst("INVOICE_NORMAL_DUE_DELAY", 30);
	newconst("CURRENCY","CHF");						// Monnaie
	// FACULTATIF (sinon commenter): Message personnalisé transmis avec les confirmations/factures (HTML)
	newconst("SPECIFIC_MSG", 						
	"Merci d'avance de respecter <a href='https://wiki.sports-5.ch/index.php?title=Foyer#Charte_du_Foyer'>la charte</a> et les indications disponibles sur place.
	La clé du Foyer se trouve dans un boîtier à clé situé dans la boîte aux lettres jaune à droite de l'entrée. Pour limiter les risques de perte, nous vous recommandons
	de remettre la clé dans le boîtier directement après avoir déverrouillé/verrouillé."
	);
	// Chemin vers un pdf bulletin QR (intégré en page 2 des factures)
	newconst("BANK_QR_PDF","document.pdf");	
	// Option pour envoyer info "secrète" avec lien de partage qui expire après la location
	newconst("USE_SECRET", true);
	newconst("SECRET_MSG", "Les informations secrètes (code du boîtier à clé et code Wi-Fi) se trouvent sur le lien suivant.");
	
	// CONFIGURATION TECHNIQUE
	
	// Serveur Caldav (par exemple Nextcloud calendar)
	newconst("DAVURL",'https://mynextcloud.xyz/remote.php/dav/calendars');
	newconst("DAVCAL",'nomcalendrier');			// Calendrier sur lequel ajouter les réservations
	newconst("DAVUSER", 'username');
	newconst("DAVPASS", 'password');
	
	newconst("SHOW_ICS_LINKS", true); // Intègre des liens ics sur la vue admin et dans les emails
	// FACULTATIF - Lien public d'intégration (pour afficher l'agenda sur la page de réservation):
	// Attention: il faut un lien qui permet l'intégration. Sur nextcloud, créer un lien public,
	// choisir "copier le code d'intégration" et extraire uniquement l'url
	newconst("DAVEMBED_URL", 'https://mynextcloud.xyz/index.php/apps/calendar/embed/abcdefghijk');
	newconst("DAVEMBED_USER", false);		// Inclure le calendrier de la salle sur le formulaire
	newconst("DAVEMBED_ADMIN", true);		// Inclure le calendrier sur le formulaire admin (contrôle des événements)

	// Configuration Webdav et OCS (liens de partage)
	newconst("WEBDAVUSER", 'username');
	newconst("WEBDAVPASS", 'password');
	newconst("WEBDAV_ENDPOINT", 'https://mynextcloud.xyz/remote.php/dav/files/username');
	newconst("OCS_ENDPOINT", 'https://mynextcloud.xyz/ocs/v2.php/apps/files_sharing/api/v1/shares');
	// Dossier racine utilisé par le système pour stocker les documents générés
	// Le dossier doit exister au préalable
	newconst("WEBDAVSAVEPATH", 'chemin/sauvegarde/documents');

	// Base de données
	newconst("DBHOST", "localhost");
	newconst("DBNAME", "dbname");
	newconst("DBUSER", "dbuser");
	newconst("DBPASS", "password");
	
	// Altcha (pour éviter les bots) - FACULTATIF (mais très fortement conseillé)
	// Voir ici: https://altcha.org/fr/docs/api/free_api_keys/
	newconst("ALTCHA_CHALLENGEURL", "https://eu.altcha.org/api/v1/challenge?apiKey=key_1jmld7oeuKNMLMtCj8FpB");
	newconst("ALTCHA_HMACKEY", '08e05c32b859f143185fb928');
	
	// Email (paramètres d'envoi)
	newconst("MAIL_HOST", "mail.exemple.ch");
	newconst("MAIL_PORT", 465);
	newconst("MAIL_PASS", 'password');
	newconst("DISABLE_MAILER", false); // Pour debug;
	
	// Système
	newconst("SYSTEM_NAME", "Système de réservation du Foyer");
	// Utilisateur pour accéder aux vues admin
	newconst("ADMIN","admin");
	// Hash du mot de passe à générer avec password_hash($password, PASSWORD_DEFAULT);
	// Par défaut, hash du mot de passe "password" à mettre à jour !
	newconst("HASH", '$2y$10$E3pZxSYriktGQfw.MLbfGuJVZKCB03JPfHz6uVCTsP5YAlY1Nn3vO');
	// Activer uniquement pour utiliser setup.php ou update-structure.php
	newconst("ALLOW_ALTER_STRUCTURE", false);

	// Pour limiter les constantes au Namespace
	function newconst($key,$value) {
		define(__NAMESPACE__ . '\\' . $key, $value);
	}
	function defined_local($str) {
		return(defined(__NAMESPACE__ . '\\' . $str));
	}
?>