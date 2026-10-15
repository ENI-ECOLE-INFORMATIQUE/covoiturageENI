# ENI Covoiturage — Spécification technique

Plateforme de covoiturage réservée aux étudiants, formateurs et membres du
personnel liés à l'ENI.

## 1. Objectif et périmètre

Mettre en relation les membres de la communauté ENI conducteurs et passagers
pour les trajets domicile ⇄ campus, ainsi que les trajets inter-campus ou
vers les lieux de stage/apprentissage.

Fonctionnalités couvertes par la V1 :

- Inscription et connexion, réservées aux adresses `@campus-eni.fr`.
- Publication de trajets (conducteur) et recherche/réservation (passager).
- Gestion des places et du cycle de vie d'un trajet.
- Avis et notes entre utilisateurs.
- Tableau de bord conducteur / passager.

Hors périmètre V1 : paiement en ligne, application mobile native,
messagerie temps réel, gestion des frais réels partagés (calcul automatique).

## 2. Stack technique

| Couche | Choix | Motif |
|---|---|---|
| Langage | PHP 8.2+ (8.4 en local) | Environnement local disponible |
| Framework | Symfony 7.4 (LTS en nov. 2026) | Support long terme ; 6.4 en repli si besoin de PHP 8.1 |
| Base de données | MySQL 8.0 / MariaDB (WAMP) | Standard, bien supporté par Doctrine |
| ORM | Doctrine ORM 3 + Migrations | Migrations versionnées en SQL |
| Front | Twig + Bootstrap 5 + Symfony UX (Turbo) | Rendu serveur, peu de JS à écrire |
| Authentification | SecurityBundle (form login), hasher `sodium`/`bcrypt` | Standard Symfony, robuste |
| Validation | Validator (attributs PHP) | Contraintes métier centralisées |
| Envoi d'emails | Mailer + SMTP (Mailtrap en dev, OVH/Gmail en prod) | Notifications |
| File de travaux | Messenger + transport Doctrine (async simple) | Emails et tâches différées |
| Tests | PHPUnit, WebTestCase, Foundry (fixtures) | Couverture fonctionnelle |
| Qualité | PHPStan niveau 6+, PHP CS Fixer | Constance de code |

Architecture : **monolithe MVC** rendu côté serveur. Pas d'API découplée en V1 ;
le site est le produit. Un futur mobile passerait par API Platform en V2.

### Environnements

- **Dev** : WAMP64 (Apache + MySQL), `.env.local`, Mailtrap, MakerBundle et
  DebugBundle activés.
- **Prod** : hébergement mutualisé PHP 8.2+ (OVH, Infomaniak…) ou VPS ; Apache,
  document root sur `public/`, `APP_ENV=prod`, dump d'autoload optimisé.
- GitHub Pages est abandonné : plateforme à états (auth, base de données),
  incompatible avec l'hébergement statique.

## 3. Rôles et sécurité

### Rôles

| Rôle | Droits |
|---|---|
| `ROLE_USER` | Tout membre inscrit : rechercher, réserver, publier des trajets, noter |
| `ROLE_ADMIN` | Modération des comptes, avis et trajets ; gestion des sites ENI |

Le statut (étudiant / formateur / personnel) est un **attribut** du membre, pas
un rôle : il n'ouvre pas de droits différents, il sert à l'information et aux
statistiques.

### Inscription contrôlée

- Email restreint au domaine unique `@campus-eni.fr` : aucune autre adresse
  ne permet de s'inscrire. C'est le garde-fou qui garantit que la plateforme
  reste réservée à la communauté ENI (étudiants, formateurs, personnel).
- Compte créé **non vérifié** : un email de confirmation avec token signé est
  envoyé ; la connexion est refusée tant que le compte n'est pas vérifié.

### Mesures de sécurité

- Hashage des mots de passe via le hasher par défaut de Symfony
  (` PASSWORD_ARGON2ID ` si disponible, sinon bcrypt).
- Protection CSRF sur tous les formulaires (`disable_csrf: false` par défaut).
- `Voters` pour les autorisations par objet : seul le conducteur modifie son
  trajet, seul le passager annule sa réservation.
- Requêtes paramétrées via Doctrine (pas de SQL concaténé).
- Entités échappées par défaut dans Twig ; `raw` interdit sauf justification.
- Anti-spam : formulaires de trajets limités en fréquence (limiter `RateLimiter`).
- RGPD : export et suppression de compte possibles, données minimales
  (email, nom, statut, numéro de téléphone optionnel).

## 4. Modèle de données (MCD)

### Membre

| Champ | Type | Notes |
|---|---|---|
| id | INT auto | PK |
| email | VARCHAR(180) | UNIQUE, domaine contrôlé |
| mot_de_passe | VARCHAR(255) | hashé |
| nom, prenom | VARCHAR(80) | |
| telephone | VARCHAR(20) | NULL, facultatif |
| statut | ENUM('etudiant','formateur','personnel') | attribut informatif |
| campus_rattachement_id | INT | FK Site — campus de rattachement obligatoire |
| actif | BOOL | désactivation = soft delete |
| email_verifie_le | DATETIME | NULL avant vérification |
| roles | JSON | `ROLE_USER`, `ROLE_ADMIN` |

### Site (campus ENI)

Liste éditable par un administrateur (créer, modifier, activer/désactiver).
Données initiales : **Quimper, Rennes, Nantes, CEL**.

| Champ | Type | Notes |
|---|---|---|
| id | INT auto | PK |
| nom | VARCHAR(80) | ex. « ENI Rennes » |
| actif | BOOL | un campus désactivé disparaît des recherches et des inscriptions |
| adresse, ville, code_postal | VARCHAR | géocodage non requis en V1 |

### Véhicule

| Champ | Type | Notes |
|---|---|---|
| id | INT auto | PK |
| membre_id | INT | FK Membre — propriétaire |
| marque, modele | VARCHAR(80) | |
| couleur | VARCHAR(40) | NULL |
| nb_places | TINYINT | >= 1 (hors conducteur en V1 : place passager) |
| plaque | VARCHAR(15) | masquée aux non-participants |
| fumeur, animaux | BOOL | confort |

### Trajet

| Champ | Type | Notes |
|---|---|---|
| id | INT auto | PK |
| conducteur_id | INT | FK Membre |
| site_id | INT | FK Site — campus d'arrivée ou de départ |
| sens | ENUM('vers_site','depuis_site') | détermine le rôle du campus |
| ville_depart, ville_arrivee | VARCHAR(120) | V1 : texte libre, pas de géocodage |
| adresse_rendez_vous | VARCHAR(255) | NULL |
| date_depart | DATETIME | indexé |
| duree_estimee | SMALLINT (min) | NULL |
| prix | DECIMAL(5,2) | participation, 0 autorisé (gratuit) |
| nb_places_offertes | TINYINT | = places du véhicule choisi |
| statut | ENUM('ouvert','complet','annule','termine') | |
| commentaire | TEXT | NULL |

Le point non-campus du trajet (domicile, gare, ville de stage…) est une
adresse en texte libre : on peut covoiturer **depuis n'importe où vers
n'importe quel campus actif**, et inversement. Le campus lié au trajet
(`site_id` + `sens`) sert de pivot à la recherche. Un trajet inter-campus
(Rennes ⇄ Nantes) est saisi comme `depuis_site` sur le campus de départ ou
`vers_site` sur le campus d'arrivée ; la recherche croise les deux.

### Participation (réservation)

| Champ | Type | Notes |
|---|---|---|
| id | INT auto | PK |
| trajet_id, membre_id | INT | FK, UNIQUE(trajet_id, membre_id) |
| nb_places | TINYINT | par défaut 1 |
| statut | ENUM('en_attente','accepte','refuse','annule') | V1 : auto-accepté ou modéré |
| cree_le | DATETIME | |

Un avis ne peut être laissé que si une participation « terminée » existe
entre les deux membres (garantie d'authenticité).

### Avis

| Champ | Type | Notes |
|---|---|---|
| id | INT auto | PK |
| auteur_id, cible_id | INT | FK Membre |
| participation_id | INT | FK — preuve du trajet partagé |
| note | TINYINT (1..5) | CHECK |
| commentaire | TEXT | modéré par admin |
| cree_le | DATETIME | |

### Tables transverses

- `reset_password` : tokens de réinitialisation (nativement gérable).
- `messenger_messages` : file async Messenger.

### Diagramme relationnel (résumé)

```
Membre 1──n Vehicule
Membre n──1 Site (campus de rattachement)
Membre 1──n Trajet (conducteur)
Site  1──n Trajet
Trajet 1──n Participation n──1 Membre (passager)
Participation 1──1 Avis (facultatif, auteur = passager ou conducteur)
```

## 5. Règles métier principales

1. Un membre ne peut pas réserver son propre trajet.
2. La somme des places réservées (statut accepté) ne peut excéder
   `nb_places_offertes` : au-delà, le trajet passe `complet`.
3. Le conducteur peut refuser une réservation ; le passager est notifié.
4. Annulation d'un trajet par le conducteur : toutes les participations
   deviennent `annule`, notification envoyée.
5. Réservation impossible moins de 2 heures avant le départ
   (paramètre configurable).
6. Un avis n'est possible qu'après la date de départ + 1 h, et une seule
   fois par participation.
7. Aucun échange d'argent sur la plateforme : le prix affiché est une
   participation aux frais réglée en main propre.

## 6. Écrans (V1)

- Accueil / recherche de trajets (filtres : campus, date, sens, ville
  libre, places libres).
- Détail d'un trajet (conducteur, véhicule, places restantes, réserver).
- Publication de trajet (formulaire en 2 étapes : trajet puis véhicule).
- « Mes trajets » : conducteur (gérer, annuler, participants) / passager
  (réservations à venir, historique).
- Profil public : moyenne des notes, derniers avis, trajets à venir.
- Admin : membres, avis signalés, gestion des campus (créer, modifier,
  activer/désactiver — y compris l'ajout de futurs campus ENI).

## 7. Plan de livraison

| Jalon | Contenu | Critère de fin |
|---|---|---|
| M1 — Squelette | Projet Symfony, Doctrine, auth, entités + migrations, campus initiaux (Quimper, Rennes, Nantes, CEL) | Inscription vérifiée fonctionnelle |
| M2 — Trajets | CRUD trajets + recherche + réservation | Règles métier 1-5 testées (PHPUnit) |
| M3 — Confiance | Avis, profils, notifications email | Règle 6 testée, emails vérifiés via Mailtrap |
| M4 — Admin | Back-office, modération, statistiques | Parcours admin complet |
| M5 — Prod | Durcissement, PHPStan, déploiement | Mise en ligne sur hébergeur PHP |

## 8. Décisions ouvertes à trancher avant M1

- Réservations auto-acceptées ou modérées par le conducteur (reco : modérées
  pour la V1, plus simple à changer vers auto qu'inversement).
- Trajet inter-campus : garder un seul `site_id` par trajet (reco, plus
  simple) ou ajouter un second campus optionnel pour les trajets
  campus ⇄ campus.
