# ENI Covoiturage

Plateforme de covoiturage réservée à la communauté ENI (étudiants, formateurs,
personnel), pour les trajets vers les campus de **Quimper, Rennes, Nantes,
Niort** et le **campus en ligne (CEL)**.

Spécification technique : [docs/specification-technique.md](docs/specification-technique.md).
Déploiement : [docs/deploiement.md](docs/deploiement.md).

## Stack

- PHP 8.2+ / Symfony 7.4 (LTS)
- MySQL 8 (WAMP64 en local) via Doctrine ORM + migrations
- Twig + Bootstrap 5 (CDN), assets servis par **AssetMapper** (`assets/` → `/assets/`, URLs fingerprintées)
- Carte interactive **Leaflet + OpenStreetMap** (tuiles OSM, géocodage Nominatim côté navigateur)
- Emails asynchrones via Messenger (transport Doctrine), Mailer
- PHPUnit (tests fonctionnels sur SQLite) + PHPStan niveau 6

## Démarrage en local (WAMP64)

```bash
composer install
# Base de données : adapter DATABASE_URL dans .env.local si besoin
# (par défaut : mysql://root:@127.0.0.1:3306/covoiturage_eni)
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate
bin/console doctrine:fixtures:load
php -S 127.0.0.1:8000 -t public   # ou symfony serve
```

L'envoi d'emails est neutralisé en local (`MAILER_DSN=null://null`) : les
messages de vérification sont consultables dans le profiler (barre de debug,
onglet Emails). Pour un envoi réel, configurer Mailtrap dans `.env.local` puis
`bin/console messenger:consume async`.

Comptes issus des fixtures :

- admin : `admin@campus-eni.fr` / `admin1234`
- démo : `camille.legoff@campus-eni.fr` et `hugo.martin@campus-eni.fr` (`MotDePasse123`)

## Fonctionnalités

### Accueil (`/`)

- Hero de bienvenue (bannière `assets/img/banner-ecologique_conviviale.jpg`), logo ENI dans le header.
- 4 avantages (économique, écologique, convivial, pratique).
- **Nos campus** : cartes (max 3 par ligne) avec photo, adresse, et boutons
  **Détails** → `/campus/{id}` et **Covoiturer** → recherche préfiltrée sur le campus.
- **Page campus** `/campus/{id}` : photo, adresse, site web, présentation
  (champ `description` du campus), annonces de covoiturage du campus.
- **Recherche de trajets** : campus, sens, date, ville.
- **Carte interactive OpenStreetMap** sous les trajets : chaque trajet est tracé
  entre sa ville de départ et d'arrivée (géocodage Nominatim), avec popup
  (date, sens, places, prix, lien) et **filtre par campus**.
- Liste des trajets disponibles (ouverts, à venir, avec places libres).

### Trajets

- Publication (`/trajet/nouveau`) réservée aux membres avec véhicule ; les places
  offertes suivent le véhicule choisi. Campus obligatoire (champ `site` non null).
- Recherche multicampus, réservation modérée (demande → acceptation/refus),
  trajet « complet » automatique, annulation avec notification des participants.
- Règles métier : pas d'auto-réservation, capacité contrôlée à l'acceptation,
  annulation du trajet = participations annulées + emails, réservation fermée
  `app.delai_reservation_heures` (2 h) avant le départ, avis possible
  `app.delai_avis_heures` (1 h) après le départ (règle 6).

### Membres, avis, profils

- Inscription restreinte aux adresses `@campus-eni.fr` (`App\Validator\CampusEniEmail`),
  connexion refusée tant que l'email n'est pas vérifié (`MembreUserChecker`).
- Chaque membre est rattaché à un campus ; véhicules personnels (`/vehicules`).
- Avis 1–5 + commentaire, un par participation acceptée, passager ↔ conducteur.
- Profils publics `/membre/{id}` : statut, campus, moyenne des notes, avis reçus,
  prochains trajets.
- Notifications email asynchrones (nouvelle demande, acceptation, refus,
  annulation) via Messenger.

### Back-office admin (`/admin`, ROLE_ADMIN)

- Dashboard statistiques (membres, campus, trajets, réservations acceptées,
  note moyenne, trajets ouverts à venir par campus).
- Campus : créer/modifier (nom, adresse, ville, CP, site web, présentation),
  activer/désactiver (un campus inactif disparaît des recherches, de
  l'inscription et de l'accueil).
- Membres : activer/désactiver un compte. Avis : consultation et suppression.

## Routes principales

| Route | Description |
| --- | --- |
| `/` | Accueil : hero, campus, recherche, carte OSM, trajets |
| `/campus/{id}` | Page détail d'un campus + ses annonces |
| `/trajet/nouveau`, `/trajet/{id}`, `/trajet/{id}/modifier`, `/trajet/{id}/annuler` | Trajets |
| `/trajet/{id}/reserver`, `/participation/{id}/accepter|refuser|annuler` | Réservations |
| `/participation/{id}/avis` | Avis |
| `/mes-trajets` | Mes trajets conduits + participations |
| `/vehicules`, `/vehicule/nouveau`, … | Mes véhicules |
| `/membre/{id}` | Profil public |
| `/inscription`, `/connexion`, `/verification-email` | Auth + vérification email |
| `/admin` | Back-office (dashboard, campus, membres, avis) |

## Images

`assets/img/` : `logo_eni.png` (header), `banner-ecologique_conviviale.jpg`
(hero + repli campus), `poster_thumb_*.jpg` (cartes campus, choisies selon le
nom du site via `App\Service\ImageCampus`).

## Tests et qualité

```bash
composer test   # PHPUnit (SQLite en mémoire fichier, 35 tests fonctionnels)
composer stan   # PHPStan niveau 6 (src/ + tests/, extension Doctrine)
composer lint   # lint:container, lint:twig, lint:yaml
```

## Jalons

- M1 — Squelette, entités, migrations, auth + vérification email : **terminé**
- M2 — Trajets (publication, recherche, réservation modérée, règles 1 à 5) : **terminé**
- M3 — Avis (règle 6), profils publics, notifications email asynchrones : **terminé**
- M4 — Back-office admin (campus, membres, avis, statistiques) : **terminé**
- M5 — Durcissement, PHPStan 6, préparation production : **terminé**
- Itération UI — Accueil façon landing ENI (hero, avantages, cartes campus 3/ligne,
  page campus `/campus/{id}`, logo ENI, campus de Niort, carte Leaflet/OSM avec
  filtre campus) : **terminé**
