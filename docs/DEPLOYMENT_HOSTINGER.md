# Guide de Déploiement - Altered Tournament Tools sur Hostinger VPS

> **Note importante** : Cette application utilise PostgreSQL comme base de données. L'hébergement mutualisé Hostinger ne supporte que MySQL. Un **VPS Hostinger** est donc requis pour ce déploiement.

---

## Prérequis

### Côté Hostinger
- Hébergement **VPS** (requis pour PostgreSQL - l'hébergement mutualisé ne supporte que MySQL)
- Accès SSH activé
- PostgreSQL installé
- Certificat SSL (Let's Encrypt gratuit)

### Côté Local
- Git installé
- Composer installé
- Accès au code source de l'application

---

## Étape 1 : Préparation de l'Hébergement Hostinger

### 1.1 Connexion au Panel Hostinger
1. Connectez-vous à [hpanel.hostinger.com](https://hpanel.hostinger.com)
2. Sélectionnez votre hébergement

### 1.2 Installation et Configuration PHP (VPS)

#### Installation de PHP 8.2 et extensions
```bash
# Connexion SSH au VPS
ssh root@votre-ip-vps

# Ajouter le repository PHP (Ubuntu/Debian)
apt install software-properties-common
add-apt-repository ppa:ondrej/php
apt update

# Installation PHP 8.2 et extensions requises
apt install php8.2 php8.2-fpm php8.2-pgsql php8.2-intl php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-cli php8.2-common
```

#### Configuration PHP
```bash
# Éditer php.ini
nano /etc/php/8.2/fpm/php.ini
```

Modifiez les paramètres suivants :
```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 64M
post_max_size = 64M
```

Redémarrez PHP-FPM :
```bash
systemctl restart php8.2-fpm
```

### 1.3 Installation et Configuration de PostgreSQL (VPS uniquement)

#### Installation de PostgreSQL
```bash
# Connexion SSH au VPS
ssh root@votre-ip-vps

# Installation PostgreSQL (Ubuntu/Debian)
apt update
apt install postgresql postgresql-contrib

# Démarrage du service
systemctl start postgresql
systemctl enable postgresql
```

#### Création de la base de données
```bash
# Connexion en tant qu'utilisateur postgres
sudo -u postgres psql

# Créer l'utilisateur
CREATE USER altered_user WITH PASSWORD 'votre_mot_de_passe_fort';

# Créer la base de données
CREATE DATABASE altered_tournament OWNER altered_user;

# Accorder les privilèges
GRANT ALL PRIVILEGES ON DATABASE altered_tournament TO altered_user;

# Quitter
\q
```

#### Configuration de l'accès distant (si nécessaire)
```bash
# Éditer pg_hba.conf
nano /etc/postgresql/15/main/pg_hba.conf

# Ajouter la ligne (pour accès local uniquement - plus sécurisé)
local   all   altered_user   md5

# Redémarrer PostgreSQL
systemctl restart postgresql
```

Notez ces informations pour le fichier `.env` :
- **Hôte** : `localhost` (ou `127.0.0.1`)
- **Port** : `5432`
- **Base** : `altered_tournament`
- **Utilisateur** : `altered_user`
- **Mot de passe** : celui que vous avez défini

### 1.4 Installation de Nginx
```bash
# Installation
apt install nginx

# Démarrage
systemctl start nginx
systemctl enable nginx
```

### 1.5 Installation de Composer
```bash
# Télécharger Composer
curl -sS https://getcomposer.org/installer | php

# Installer globalement
mv composer.phar /usr/local/bin/composer

# Vérifier l'installation
composer --version
```

### 1.6 Accès SSH au VPS Hostinger
1. Dans hPanel, allez dans **VPS** → **Paramètres**
2. Notez l'adresse IP du VPS
3. Utilisez le mot de passe root défini lors de la création du VPS
4. Connexion : `ssh root@VOTRE_IP_VPS`

---

## Étape 2 : Préparation des Fichiers Locaux

### 2.1 Configuration de l'environnement de production
Créez le fichier `.env.prod.local` (ne jamais commiter ce fichier) :

```bash
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=votre_secret_tres_long_et_aleatoire_32_caracteres

DATABASE_URL="postgresql://altered_user:MOT_DE_PASSE@localhost:5432/altered_tournament?serverVersion=15&charset=utf8"

# Mailer (optionnel)
MAILER_DSN=smtp://user:pass@smtp.hostinger.com:587
```

### 2.2 Génération de l'APP_SECRET
Générez un secret aléatoire :
```bash
php -r "echo bin2hex(random_bytes(16));"
```

### 2.3 Installation des dépendances de production
```bash
composer install --no-dev --optimize-autoloader
```

### 2.4 Compilation des assets
```bash
php bin/console asset-map:compile
```

### 2.5 Vidage du cache en mode prod
```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

---

## Étape 3 : Transfert des Fichiers

### Option A : Via Git + SSH (Recommandé)

#### 3.1 Connexion SSH
```bash
ssh root@VOTRE_IP_VPS
```

#### 3.2 Clonage du repository
```bash
cd /var/www/altered-tournament
git clone https://github.com/votre-repo/alteredTournamentTools.git .
```

#### 3.3 Configuration de l'environnement
```bash
# Créer le fichier .env.local
nano .env.local
```

Collez le contenu suivant :
```
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=votre_secret_genere
DATABASE_URL="postgresql://altered_user:MOT_DE_PASSE@localhost:5432/altered_tournament?serverVersion=15&charset=utf8"
```

#### 3.4 Installation des dépendances
```bash
composer install --no-dev --optimize-autoloader
```

#### 3.5 Permissions
```bash
chown -R www-data:www-data /var/www/altered-tournament
chmod -R 755 /var/www/altered-tournament
chmod -R 775 /var/www/altered-tournament/var
```

### Option B : Via SFTP

1. Utilisez FileZilla ou un client SFTP similaire
2. Connectez-vous avec :
   - **Hôte** : VOTRE_IP_VPS
   - **Port** : 22
   - **Utilisateur** : root
   - **Mot de passe** : votre mot de passe root
3. Uploadez tous les fichiers dans `/var/www/altered-tournament/`
4. **Excluez** les dossiers suivants de l'upload :
   - `.git/`
   - `var/cache/`
   - `var/log/`
   - `node_modules/` (si présent)

---

## Étape 4 : Configuration du Serveur Web (Nginx)

### 4.1 Structure des dossiers recommandée
```
/var/www/altered-tournament/
├── bin/
├── config/
├── public/                # Document root Nginx
│   ├── assets/
│   └── index.php
├── src/
├── templates/
├── translations/
├── var/
│   ├── cache/
│   └── log/
├── vendor/
├── .env.local
└── composer.json
```

### 4.2 Création du dossier et permissions
```bash
# Créer le dossier
mkdir -p /var/www/altered-tournament

# Définir le propriétaire (www-data pour Nginx)
chown -R www-data:www-data /var/www/altered-tournament
```

### 4.3 Configuration Nginx
Créez le fichier de configuration :
```bash
nano /etc/nginx/sites-available/altered-tournament
```

Contenu du fichier :
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name votre-domaine.com www.votre-domaine.com;

    # Redirection HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name votre-domaine.com www.votre-domaine.com;

    # Certificats SSL (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/votre-domaine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votre-domaine.com/privkey.pem;

    # Document root - pointe vers le dossier public de Symfony
    root /var/www/altered-tournament/public;
    index index.php;

    # Logs
    access_log /var/log/nginx/altered-tournament.access.log;
    error_log /var/log/nginx/altered-tournament.error.log;

    # Taille max upload
    client_max_body_size 64M;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Symfony routing
    location / {
        try_files $uri /index.php$is_args$args;
    }

    # PHP-FPM
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    # Bloquer l'accès aux autres fichiers PHP
    location ~ \.php$ {
        return 404;
    }

    # Cache des assets statiques
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Sécurité - bloquer les fichiers cachés
    location ~ /\. {
        deny all;
    }
}
```

### 4.4 Activer le site
```bash
# Créer le lien symbolique
ln -s /etc/nginx/sites-available/altered-tournament /etc/nginx/sites-enabled/

# Supprimer le site par défaut (optionnel)
rm /etc/nginx/sites-enabled/default

# Tester la configuration
nginx -t

# Recharger Nginx
systemctl reload nginx
```

---

## Étape 5 : Migration de la Base de Données

### 5.1 Via SSH
```bash
cd /var/www/altered-tournament
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5.2 Création d'un administrateur (si nécessaire)
```bash
php bin/console app:create-admin admin@example.com mot_de_passe
```

---

## Étape 6 : Permissions des Dossiers

### 6.1 Configuration des permissions
```bash
cd /var/www/altered-tournament

# Propriétaire www-data (utilisateur Nginx/PHP-FPM)
chown -R www-data:www-data .

# Permissions pour var/
chmod -R 775 var/
chmod -R 775 var/cache/
chmod -R 775 var/log/

# Si vous utilisez des uploads
mkdir -p public/uploads
chmod -R 775 public/uploads/
```

---

## Étape 7 : Configuration SSL avec Let's Encrypt

### 7.1 Installation de Certbot
```bash
apt install certbot python3-certbot-nginx
```

### 7.2 Obtention du certificat SSL
```bash
# Arrêter temporairement Nginx (si config SSL déjà présente)
# systemctl stop nginx

# Obtenir le certificat
certbot --nginx -d votre-domaine.com -d www.votre-domaine.com
```

### 7.3 Renouvellement automatique
Le certificat se renouvelle automatiquement. Vérifiez avec :
```bash
certbot renew --dry-run
```

### 7.4 Configuration du renouvellement automatique (Cron)
```bash
# Le cron est généralement ajouté automatiquement, vérifiez :
cat /etc/cron.d/certbot
```

---

## Étape 8 : Configuration du Domaine

### 8.1 DNS (si domaine externe)
Configurez les enregistrements DNS :
```
Type    Nom    Valeur
A       @      IP_HOSTINGER
CNAME   www    votre-domaine.com
```

---

## Étape 9 : Tâches Planifiées (Cron Jobs)

Si l'application nécessite des tâches planifiées :

```bash
# Éditer le crontab
crontab -e
```

Ajoutez les tâches nécessaires :
```bash
# Exemple : Nettoyage du cache tous les jours à 3h
0 3 * * * cd /var/www/altered-tournament && php bin/console cache:clear --env=prod

# Exemple : Commande personnalisée toutes les heures
0 * * * * cd /var/www/altered-tournament && php bin/console app:ma-commande

# Messenger (si utilisé) - traiter les messages en file d'attente
* * * * * cd /var/www/altered-tournament && php bin/console messenger:consume async --time-limit=60
```

---

## Étape 10 : Vérification Finale

### 10.1 Checklist de déploiement

- [ ] PHP 8.2+ configuré avec les bonnes extensions
- [ ] Base de données créée et migrée
- [ ] Fichier `.env.local` configuré avec les bonnes valeurs
- [ ] `APP_ENV=prod` et `APP_DEBUG=0`
- [ ] Cache vidé et réchauffé
- [ ] Permissions correctes sur `var/`
- [ ] SSL activé et forcé
- [ ] `.htaccess` en place
- [ ] Test de connexion réussi
- [ ] Test de création de tournoi réussi

### 10.2 Test de l'application
1. Accédez à `https://votre-domaine.com`
2. Testez la connexion/inscription
3. Testez la création d'un tournoi
4. Vérifiez les traductions (FR/EN)

---

## Dépannage

### Erreur 500
```bash
# Vérifier les logs Symfony
tail -f /var/www/altered-tournament/var/log/prod.log

# Vérifier les logs Nginx
tail -f /var/log/nginx/altered-tournament.error.log

# Vérifier les permissions
chown -R www-data:www-data /var/www/altered-tournament
chmod -R 775 /var/www/altered-tournament/var/
```

### Page blanche
```bash
# Activer temporairement le debug
# Dans .env.local, mettre APP_DEBUG=1
# Recharger la page pour voir l'erreur
# REMETTRE APP_DEBUG=0 après correction
```

### Erreur de base de données PostgreSQL
```bash
# Vérifier que PostgreSQL est lancé
systemctl status postgresql

# Tester la connexion manuelle
psql -U altered_user -d altered_tournament -h localhost

# Tester via Symfony
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:validate

# Si erreur "SQLSTATE[08006]" - vérifier pg_hba.conf
sudo nano /etc/postgresql/15/main/pg_hba.conf
# Assurez-vous que la ligne pour altered_user est présente
sudo systemctl restart postgresql
```

### Assets non chargés
```bash
# Recompiler les assets
php bin/console asset-map:compile
php bin/console cache:clear --env=prod
```

### Erreur "Class not found"
```bash
# Régénérer l'autoloader
composer dump-autoload --optimize
```

---

## Mise à Jour de l'Application

### Procédure de mise à jour
```bash
# 1. Connexion SSH
ssh root@VOTRE_IP_VPS

# 2. Aller dans le dossier
cd /var/www/altered-tournament

# 3. Activer le mode maintenance (optionnel)
touch public/maintenance.html

# 4. Récupérer les mises à jour
git pull origin main

# 5. Installer les dépendances
composer install --no-dev --optimize-autoloader

# 6. Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# 7. Vider le cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# 8. Compiler les assets
php bin/console asset-map:compile

# 9. Rétablir les permissions
chown -R www-data:www-data /var/www/altered-tournament

# 10. Désactiver le mode maintenance
rm public/maintenance.html
```

---

## Contacts et Support

- **Documentation Symfony** : https://symfony.com/doc/current/deployment.html
- **Support Hostinger** : https://www.hostinger.fr/support
- **Issues du projet** : [Lien vers votre repository]

---

*Document généré le 16/01/2026 pour Altered Tournament Tools*
