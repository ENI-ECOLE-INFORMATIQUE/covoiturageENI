# Déploiement en production

Checklist pour mettre ENI Covoiturage en ligne sur un hébergeur PHP
(mutualisé type OVH/Infomaniak ou VPS).

## Prérequis serveur

| Élément | Version minimale |
|---|---|
| PHP | 8.2 (extensions : pdo_mysql, intl, mbstring, sodium, xml, curl) |
| Base de données | MySQL 8 ou MariaDB 10.5+ |
| Serveur web | Apache (mod_rewrite) ou Nginx |
| Composer | 2.3+ |

Le document root du domaine doit pointer sur `public/`.
En Apache, le `.htaccess` fourni par le squelette gère le rewrite ;
en Nginx, configurer l'équivalent (toute requête vers `public/index.php`).

## Étapes de mise en ligne

```bash
# 1. Dépendances de production uniquement
composer install --no-dev --optimize-autoloader

# 2. Compiler la configuration (AUCUN secret dans un fichier commité)
#    Renseigner au préalable les variables réelles :
#      APP_SECRET         : générer une nouvelle valeur (bin/console secrets? ou openssl rand -hex 16)
#      DATABASE_URL       : mysql://user:pass@host:3306/covoiturage?serverVersion=8.0&charset=utf8mb4
#      MAILER_DSN        : smtp://... (Mailtrap en test, SMTP réel ensuite)
#      DEFAULT_URI       : https://votre-domaine.tld
composer dump-env prod

# 3. Base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
# NE JAMAIS exécuter doctrine:fixtures:load en production.

# 4. Droits d'écriture sur var/ (cache, logs, sessions)
```

## Premier compte administrateur

Aucun compte admin n'existe en production (les fixtures ne sont pas
chargées). Procédure :

1. Créer un compte normalement via l'inscription (`/inscription`) avec une
   adresse `@campus-eni.fr`, vérifier l'email.
2. Promouvoir ce compte en SQL :

```sql
UPDATE membre
SET roles = '["ROLE_USER","ROLE_ADMIN"]'
WHERE email = 'prenom.nom@campus-eni.fr';
```

## File de notifications (Messenger)

Les emails sont asynchrones : un consommateur doit tourner en permanence.

- **VPS / dédié** : service systemd ou supervisor lançant
  `php bin/console messenger:consume async --time-limit=3600` avec redémarrage automatique.
- **Mutualisé (cron)** : tâche cron toutes les minutes :
  `php bin/console messenger:consume async --time-limit=50 --env=prod`

Les échecs partent dans la file `failed` : surveiller avec
`php bin/console messenger:failed:show`.

## Durcissement en place (M5)

- Cookies de session : `Secure` (auto en HTTPS), `HttpOnly`, `SameSite=Lax`
  (`config/packages/framework.yaml`).
- Déconnexion protégée par CSRF (`enable_csrf: true`, lien `logout_url()`).
- Limitation des tentatives de connexion (5, `login_throttling`).
- Pages d'erreur personnalisées 403/404/générique.
- PHPStan niveau 6 à zéro erreur (`composer stan`).
- Lint container/Twig/YAML à zéro erreur (`composer lint`).

## Checklist sécurité avant l'ouverture

- [ ] HTTPS actif et redirection HTTP → HTTPS (les cookies deviennent `Secure`).
- [ ] `APP_SECRET` de production régénéré (ne pas réutiliser celui du dépôt).
- [ ] Compte admin avec mot de passe fort.
- [ ] Sauvegardes automatiques de la base planifiées.
- [ ] `var/` non accessible depuis le web (le document root dans `public/` le garantit).
- [ ] `composer audit` sans vulnérabilité connue.

## Vérification post-déploiement

1. Page d'accueil + une recherche avec filtres.
2. Inscription complète avec un vrai `@campus-eni.fr` (email reçu).
3. Publication d'un trajet de test, puis suppression/annulation.
4. `php bin/console messenger:stats` — la file doit se vider.
5. Accès à `/admin` refusé pour un compte normal.
