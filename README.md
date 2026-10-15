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
- M2 — Trajets (CRUD, recherche, réservation)
- M3 — Avis, profils, notifications
- M4 — Back-office, modération, statistiques
- M5 — Durcissement, mise en production
