Un système de réservation de salle prévu pour s'intégrer avec un Nextcloud/Owncloud
* Formulaire de réservation
* Liaison avec un calendrier en ligne
* Espace admin pour le suivi des demandes de réservations, leur approbation / annulation
* Génération et envoi par mail de confirmations pdf et de factures
* Possibilité modulable de définir des réductions par type de locataire et d'ajouter des options

# Prérequis
* Un serveur web (testé avec Apache2)
* PHP (testé avec PHP8.3-fpm, devrait fonctionner avec d'autres) - avec cURL
* Mysql
* Composer
* Un compte sur un Nextcloud ou Owncloud (ou système avec webdav, caldav, ocs api)

# Installation
```
git clone https://github.com/theosche/room_reservation.git
cd room_reservation
composer install
cp config.php.example config.php
```
* Diriger un serveur web vers le dossier _public_. En particulier, _config.php_ contient des informations sensibles et ne doit pas être exposé.
* Donner les autorisations en lecture à l'utilisateur du serveur web, et en écriture pour le fichier _src/Reservation.php_ (besoin uniquement pour le setup)
* Ouvrir config.php et mettre à jour les paramètres. En particulier:
  * Éditer les paramètres de la base de données (_DBHOST_, _DBNAME_, _DBUSER_, _DBPASS_). La base de données n'a pas besoin d'être déjà créée. Elle sera générée grâce aux informations fournies
  * Préparer le compte admin: éditer les paramètres _ADMIN_ et _HASH_ (nom d'utilisateur et hash du mot de passe admin)
  * Changer _ALLOW_ALTER_STRUCTURE_ en _true_ pour autoriser le script _setup.php_ à s'exécuter
* Ouvrir _setup.php_ dans un navigateur. Fournir des identifiants mysql root (ou d'un utilisateur pouvant créer une nouvelle DB et de nouveaux utilisateurs). La base de donnée est créée. _ALLOW_ALTER_STRUCTURE_ peut être remis à _false_. Les droits d'écriture peuvent être retirés pour _src/Reservation.php_.
* C'est parti ! Le formulaire public est accessible via _index.php_ et l'espace admin via _admin.php_

# Modification de la structure
* Le système offre la possibilité d'ajouter des réductions et options particulières (_config.php_). Après modification de ces paramètres, la structure doit être mise à jour. Pour cela:
  * _ALLOW_ALTER_STRUCTURE_ doit être réactivé (_true_) et les droits d'écriture redonnés à l'utilisateur web pour _src/Reservation.php_.
  * Ouvrir _update_structure.php_ dans un navigateur et suivre les instructions.
  * Désactiver _ALLOW_ALTER_STRUCTURE_ et retirer les droits d'écriture pour _src/Reservation.php_.
