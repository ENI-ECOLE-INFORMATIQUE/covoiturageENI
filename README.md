# ENI Covoiturage

Plateforme de covoiturage réservée à la communauté ENI (étudiants, formateurs,
personnel), pour les trajets vers les campus de Quimper, Rennes, Nantes et CEL.

Spécification technique : [docs/specification-technique.md](docs/specification-technique.md).

## Stack

- PHP 8.2+ / Symfony 7.4 (LTS)
- MySQL 8 (WAMP64 en local) via Doctrine ORM + migrations
- Twig + Bootstrap 5
- PHPUnit (tests fonctionnels sur SQLite)

## Démarrage en local (WAMP64)

```bash
composer install
# Base de données : adapter DATABASE_URL dans .env.local si besoin
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate
bin/console doctrine:fixtures:load
```

L'envoi d'emails est neutralisé en local (`MAILER_DSN=null://null`) : les
messages de vérification sont consultables dans le profiler (barre de debug,
onglet Emails). Pour un envoi réel, configurer Mailtrap dans `.env.local`.

Compte administrateur issu des fixtures : `admin@campus-eni.fr` / `admin1234`.

## Tests

```bash
php bin/phpunit
```

## Règles métier clés

- Inscription restreinte aux adresses `@campus-eni.fr` (contrainte de
  validation dédiée, `App\Validator\CampusEniEmail`).
- Connexion refusée tant que l'adresse email n'est pas vérifiée
  (`App\Security\MembreUserChecker`).
- Chaque membre est rattaché à un campus ; la liste des campus est éditable
  (back-office prévu au jalon M4).

## Jalons

- M1 — Squelette, entités, migrations, auth + vérification email, campus
  initiaux : **terminé**
- M2 — Trajets (publication, recherche, réservation modérée, règles métier
  1 à 5) : **terminé**
- M3 — Avis, profils, notifications : **terminé**
- M4 — Back-office, modération, statistiques
- M5 — Durcissement, mise en production

## M3 en bref

- **Avis (règle métier 6)** : un avis par participation acceptée, 1 h après
  le départ, note 1 à 5 + commentaire ; le passager note le conducteur et
  inversement (premier à laisser son avis).
- **Profils publics** (`/membre/{id}`) : statut, campus, moyenne des notes,
  avis reçus, prochains trajets proposés.
- **Notifications email asynchrones** (Messenger, transport Doctrine) :
  nouvelle demande, acceptation, refus, annulation de trajet. Les messages
  attendent dans la table `messenger_messages`.

### Envoyer réellement les emails

```bash
# 1. Configurer MAILER_DSN dans .env.local (ex. Mailtrap)
#    MAILER_DSN="smtp://utilisateur:motdepasse@sandbox.smtp.mailtrap.io:2525"
# 2. Lancer le consommateur (dans un terminal dédié) :
bin/console messenger:consume async
```

Sans consommateur, les messages restent en file (aucune perte) ; les
échecs partent dans la file `failed` (transport `failed`).

## M2 en bref

- **Véhicules** : chaque membre gère ses véhicules (`/vehicules`) ; les places
  offertes d'un trajet suivent le véhicule choisi.
- **Recherche** sur la page d'accueil : campus, sens, date, ville libre —
  seuls les trajets ouverts avec places libres apparaissent.
- **Réservation modérée** : le passager demande, le conducteur accepte ou
  refuse ; trajet complet automatiquement quand toutes les places sont
  acceptées.
- **Règles métier** : pas d'auto-réservation, capacité contrôlée à
  l'acceptation, annulation du trajet = participations annulées, réservation
  fermée 2 h avant le départ (paramètre `app.delai_reservation_heures`).
- Comptes de démonstration (fixtures) : `camille.legoff@campus-eni.fr` et
  `hugo.martin@campus-eni.fr` (`MotDePasse123`).
