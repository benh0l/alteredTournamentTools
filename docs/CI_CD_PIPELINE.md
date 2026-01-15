# Guide CI/CD - Altered Tournament Tools

## Pipeline GitHub Actions vers VPS Hostinger

Ce document détaille la mise en place d'une pipeline CI/CD complète pour automatiser les tests, la validation et le déploiement de l'application sur votre VPS Hostinger.

---

## Table des matières

1. [Architecture de la pipeline](#architecture-de-la-pipeline)
2. [Prérequis](#prérequis)
3. [Configuration du VPS](#configuration-du-vps)
4. [Configuration des secrets GitHub](#configuration-des-secrets-github)
5. [Workflow CI - Tests et validation](#workflow-ci---tests-et-validation)
6. [Workflow CD - Déploiement automatique](#workflow-cd---déploiement-automatique)
7. [Workflow complet CI/CD](#workflow-complet-cicd)
8. [Notifications et monitoring](#notifications-et-monitoring)
9. [Rollback et gestion des erreurs](#rollback-et-gestion-des-erreurs)
10. [Bonnes pratiques](#bonnes-pratiques)

---

## Architecture de la pipeline

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Developer     │     │     GitHub      │     │   VPS Hostinger │
│   Local Dev     │────▶│    Actions      │────▶│   Production    │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                       │                       │
        │ git push              │ CI Pipeline           │ Deploy
        │                       │                       │
        ▼                       ▼                       ▼
   ┌─────────┐           ┌─────────────┐         ┌─────────────┐
   │ Commit  │           │ 1. Lint     │         │ 1. Pull     │
   │ & Push  │           │ 2. Tests    │         │ 2. Composer │
   └─────────┘           │ 3. Security │         │ 3. Migrate  │
                         │ 4. Build    │         │ 4. Cache    │
                         └─────────────┘         └─────────────┘
```

### Flux de travail

1. **Push sur `develop`** → Tests CI uniquement
2. **Pull Request vers `main`** → Tests CI + Review
3. **Merge sur `main`** → Tests CI + Déploiement automatique en production

---

## Prérequis

### Côté GitHub
- Repository GitHub avec le code source
- Accès aux paramètres du repository (Settings)
- GitHub Actions activé (activé par défaut)

### Côté VPS Hostinger
- VPS configuré avec Nginx, PHP 8.2, PostgreSQL
- Application déjà déployée une première fois manuellement
- Accès SSH configuré

---

## Configuration du VPS

### 1. Création d'un utilisateur dédié au déploiement

```bash
# Connexion au VPS
ssh root@VOTRE_IP_VPS

# Créer un utilisateur deploy
adduser deploy
usermod -aG www-data deploy

# Configurer les permissions sudo limitées
visudo
```

Ajoutez cette ligne dans le fichier sudoers :
```
deploy ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx, /bin/systemctl reload php8.2-fpm, /usr/bin/chown -R www-data\:www-data /var/www/altered-tournament*
```

### 2. Configuration des clés SSH

```bash
# Sur votre machine locale, générer une clé SSH dédiée
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy

# Afficher la clé publique
cat ~/.ssh/github_actions_deploy.pub

# Afficher la clé privée (à copier dans GitHub Secrets)
cat ~/.ssh/github_actions_deploy
```

Sur le VPS, ajouter la clé publique :
```bash
# En tant que root
su - deploy
mkdir -p ~/.ssh
chmod 700 ~/.ssh
nano ~/.ssh/authorized_keys
# Coller la clé publique
chmod 600 ~/.ssh/authorized_keys
```

### 3. Configuration du répertoire de déploiement

```bash
# Permissions pour l'utilisateur deploy
chown -R deploy:www-data /var/www/altered-tournament
chmod -R 775 /var/www/altered-tournament

# Créer le dossier pour les releases (stratégie blue-green)
mkdir -p /var/www/altered-tournament-releases
chown deploy:www-data /var/www/altered-tournament-releases
```

### 4. Script de déploiement sur le VPS

Créez le script `/home/deploy/deploy.sh` :

```bash
#!/bin/bash
set -e

# Configuration
APP_DIR="/var/www/altered-tournament"
RELEASES_DIR="/var/www/altered-tournament-releases"
KEEP_RELEASES=5

# Couleurs pour les logs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[DEPLOY]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Timestamp pour le release
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RELEASE_DIR="${RELEASES_DIR}/release_${TIMESTAMP}"

log "Début du déploiement - Release: ${TIMESTAMP}"

# 1. Créer le dossier de release
log "Création du dossier de release..."
mkdir -p "${RELEASE_DIR}"

# 2. Cloner/Copier les fichiers
log "Copie des fichiers..."
cp -r ${APP_DIR}/. ${RELEASE_DIR}/

# 3. Aller dans le dossier
cd "${RELEASE_DIR}"

# 4. Installer les dépendances
log "Installation des dépendances Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Copier le fichier .env.local depuis le dossier partagé
log "Configuration de l'environnement..."
cp /var/www/shared/.env.local ${RELEASE_DIR}/.env.local

# 6. Vider le cache
log "Nettoyage du cache..."
php bin/console cache:clear --env=prod --no-interaction
php bin/console cache:warmup --env=prod --no-interaction

# 7. Exécuter les migrations
log "Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# 8. Compiler les assets
log "Compilation des assets..."
php bin/console asset-map:compile

# 9. Mettre à jour le lien symbolique
log "Activation de la nouvelle release..."
ln -sfn "${RELEASE_DIR}" "${APP_DIR}"

# 10. Corriger les permissions
log "Configuration des permissions..."
sudo chown -R www-data:www-data ${RELEASE_DIR}
chmod -R 755 ${RELEASE_DIR}
chmod -R 775 ${RELEASE_DIR}/var

# 11. Recharger PHP-FPM
log "Rechargement de PHP-FPM..."
sudo systemctl reload php8.2-fpm

# 12. Nettoyer les anciennes releases
log "Nettoyage des anciennes releases..."
cd ${RELEASES_DIR}
ls -dt release_* | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

log "Déploiement terminé avec succès!"
echo "Release: ${TIMESTAMP}"
```

Rendre le script exécutable :
```bash
chmod +x /home/deploy/deploy.sh
```

### 5. Créer le dossier partagé pour les fichiers sensibles

```bash
mkdir -p /var/www/shared
cp /var/www/altered-tournament/.env.local /var/www/shared/
chown deploy:www-data /var/www/shared/.env.local
chmod 640 /var/www/shared/.env.local
```

---

## Configuration des secrets GitHub

### Accéder aux secrets

1. Allez sur votre repository GitHub
2. **Settings** → **Secrets and variables** → **Actions**
3. Cliquez sur **New repository secret**

### Secrets à configurer

| Nom du secret | Description | Exemple |
|---------------|-------------|---------|
| `VPS_HOST` | Adresse IP du VPS | `123.456.789.012` |
| `VPS_USER` | Utilisateur SSH | `deploy` |
| `VPS_SSH_KEY` | Clé privée SSH (contenu complet) | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `VPS_SSH_PORT` | Port SSH (optionnel) | `22` |
| `SLACK_WEBHOOK_URL` | URL webhook Slack (optionnel) | `https://hooks.slack.com/...` |
| `DISCORD_WEBHOOK_URL` | URL webhook Discord (optionnel) | `https://discord.com/api/webhooks/...` |

### Ajouter la clé SSH privée

```bash
# Copier le contenu complet de la clé privée
cat ~/.ssh/github_actions_deploy
```

Copiez **tout** le contenu, y compris :
```
-----BEGIN OPENSSH PRIVATE KEY-----
...contenu de la clé...
-----END OPENSSH PRIVATE KEY-----
```

---

## Workflow CI - Tests et validation

Créez le fichier `.github/workflows/ci.yml` :

```yaml
name: CI - Tests & Validation

on:
  push:
    branches: [develop, main]
  pull_request:
    branches: [main]

env:
  PHP_VERSION: '8.2'

jobs:
  #############################################
  # Job 1: Analyse statique et linting
  #############################################
  lint:
    name: Lint & Static Analysis
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: intl, pdo_pgsql, pgsql, mbstring, xml, ctype, iconv
          tools: composer:v2, php-cs-fixer, phpstan
          coverage: none

      - name: Get Composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-interaction

      - name: Check Symfony requirements
        run: composer check-platform-reqs

      - name: Validate composer.json
        run: composer validate --strict

      - name: Check YAML syntax
        run: php bin/console lint:yaml config/ --parse-tags

      - name: Check Twig templates
        run: php bin/console lint:twig templates/

      - name: Check container
        run: php bin/console lint:container

  #############################################
  # Job 2: Tests unitaires et fonctionnels
  #############################################
  test:
    name: Tests PHP ${{ matrix.php-version }}
    runs-on: ubuntu-latest
    needs: lint

    strategy:
      fail-fast: false
      matrix:
        php-version: ['8.2', '8.3']

    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_USER: test_user
          POSTGRES_PASSWORD: test_password
          POSTGRES_DB: test_altered
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: intl, pdo_pgsql, pgsql, mbstring, xml, ctype, iconv
          tools: composer:v2
          coverage: xdebug

      - name: Get Composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-interaction

      - name: Create test environment file
        run: |
          echo "APP_ENV=test" > .env.test.local
          echo "APP_SECRET=test_secret_for_ci_pipeline_12345" >> .env.test.local
          echo "DATABASE_URL=postgresql://test_user:test_password@localhost:5432/test_altered?serverVersion=15&charset=utf8" >> .env.test.local

      - name: Create database schema
        run: |
          php bin/console doctrine:database:create --env=test --if-not-exists
          php bin/console doctrine:migrations:migrate --env=test --no-interaction

      - name: Run PHPUnit tests
        run: |
          php bin/phpunit --coverage-text --coverage-clover=coverage.xml
        env:
          XDEBUG_MODE: coverage

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v4
        if: matrix.php-version == '8.2'
        with:
          files: ./coverage.xml
          fail_ci_if_error: false

  #############################################
  # Job 3: Analyse de sécurité
  #############################################
  security:
    name: Security Check
    runs-on: ubuntu-latest
    needs: lint

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          tools: composer:v2

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress --no-interaction

      - name: Check for security vulnerabilities
        run: composer audit

      - name: Symfony Security Check
        uses: symfonycorp/security-checker-action@v5

  #############################################
  # Job 4: Build de validation
  #############################################
  build:
    name: Build Validation
    runs-on: ubuntu-latest
    needs: [lint, test, security]

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: intl, pdo_pgsql, pgsql, mbstring, xml, ctype, iconv
          tools: composer:v2

      - name: Install production dependencies
        run: composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

      - name: Compile assets
        run: php bin/console asset-map:compile --env=prod

      - name: Warm up cache
        run: |
          php bin/console cache:clear --env=prod
          php bin/console cache:warmup --env=prod

      - name: Verify Symfony configuration
        run: php bin/console debug:config --env=prod || true

      - name: Build successful
        run: echo "✅ Build completed successfully!"
```

---

## Workflow CD - Déploiement automatique

Créez le fichier `.github/workflows/deploy.yml` :

```yaml
name: CD - Deploy to Production

on:
  push:
    branches: [main]
  workflow_dispatch:
    inputs:
      environment:
        description: 'Environment to deploy to'
        required: true
        default: 'production'
        type: choice
        options:
          - production
          - staging

env:
  PHP_VERSION: '8.2'

jobs:
  #############################################
  # Job 1: Déploiement en production
  #############################################
  deploy:
    name: Deploy to Production
    runs-on: ubuntu-latest
    environment: production

    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Get commit info
        id: commit
        run: |
          echo "sha_short=$(git rev-parse --short HEAD)" >> $GITHUB_OUTPUT
          echo "message=$(git log -1 --pretty=%B | head -1)" >> $GITHUB_OUTPUT
          echo "author=$(git log -1 --pretty=%an)" >> $GITHUB_OUTPUT

      - name: Setup SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.VPS_SSH_KEY }}" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -p ${{ secrets.VPS_SSH_PORT || 22 }} -H ${{ secrets.VPS_HOST }} >> ~/.ssh/known_hosts

      - name: Deploy to VPS
        run: |
          ssh -i ~/.ssh/deploy_key -p ${{ secrets.VPS_SSH_PORT || 22 }} ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }} << 'ENDSSH'
            set -e

            echo "🚀 Starting deployment..."

            # Aller dans le dossier de l'application
            cd /var/www/altered-tournament

            # Activer le mode maintenance
            touch public/maintenance.html

            # Récupérer les dernières modifications
            git fetch origin main
            git reset --hard origin/main

            # Installer les dépendances
            composer install --no-dev --optimize-autoloader --no-interaction

            # Exécuter les migrations
            php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

            # Vider et réchauffer le cache
            php bin/console cache:clear --env=prod
            php bin/console cache:warmup --env=prod

            # Compiler les assets
            php bin/console asset-map:compile

            # Corriger les permissions
            sudo chown -R www-data:www-data /var/www/altered-tournament
            chmod -R 755 /var/www/altered-tournament
            chmod -R 775 /var/www/altered-tournament/var

            # Recharger PHP-FPM
            sudo systemctl reload php8.2-fpm

            # Désactiver le mode maintenance
            rm -f public/maintenance.html

            echo "✅ Deployment completed successfully!"
          ENDSSH

      - name: Verify deployment
        run: |
          echo "Waiting for application to be ready..."
          sleep 10

          # Vérifier que le site répond
          HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://votre-domaine.com || echo "000")

          if [ "$HTTP_STATUS" = "200" ]; then
            echo "✅ Application is responding (HTTP $HTTP_STATUS)"
          else
            echo "⚠️ Application returned HTTP $HTTP_STATUS"
          fi

      - name: Send success notification
        if: success()
        run: |
          echo "Deployment successful!"
          echo "Commit: ${{ steps.commit.outputs.sha_short }}"
          echo "Message: ${{ steps.commit.outputs.message }}"
          echo "Author: ${{ steps.commit.outputs.author }}"

      - name: Send failure notification
        if: failure()
        run: |
          echo "❌ Deployment failed!"
          echo "Please check the logs for more information."

  #############################################
  # Job 2: Smoke tests post-déploiement
  #############################################
  smoke-test:
    name: Post-deployment Smoke Tests
    runs-on: ubuntu-latest
    needs: deploy

    steps:
      - name: Wait for deployment to stabilize
        run: sleep 15

      - name: Check homepage
        run: |
          HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://votre-domaine.com)
          if [ "$HTTP_STATUS" != "200" ]; then
            echo "❌ Homepage check failed (HTTP $HTTP_STATUS)"
            exit 1
          fi
          echo "✅ Homepage OK"

      - name: Check health endpoint (if exists)
        run: |
          HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://votre-domaine.com/health || echo "404")
          if [ "$HTTP_STATUS" = "200" ]; then
            echo "✅ Health endpoint OK"
          else
            echo "ℹ️ Health endpoint not available (HTTP $HTTP_STATUS)"
          fi

      - name: Check login page
        run: |
          HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://votre-domaine.com/login)
          if [ "$HTTP_STATUS" != "200" ]; then
            echo "❌ Login page check failed (HTTP $HTTP_STATUS)"
            exit 1
          fi
          echo "✅ Login page OK"
```

---

## Workflow complet CI/CD

Pour une approche tout-en-un, créez `.github/workflows/ci-cd.yml` :

```yaml
name: CI/CD Pipeline

on:
  push:
    branches: [develop, main]
  pull_request:
    branches: [main]

env:
  PHP_VERSION: '8.2'
  DEPLOY_BRANCH: 'main'

jobs:
  #############################################
  # STAGE 1: Continuous Integration
  #############################################

  lint:
    name: 📝 Lint & Validate
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: intl, pdo_pgsql, mbstring, xml
          tools: composer:v2

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: ${{ runner.os }}-composer-${{ hashFiles('composer.lock') }}

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Lint YAML
        run: php bin/console lint:yaml config/

      - name: Lint Twig
        run: php bin/console lint:twig templates/

      - name: Lint Container
        run: php bin/console lint:container

  test:
    name: 🧪 Tests
    runs-on: ubuntu-latest
    needs: lint

    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
          POSTGRES_DB: test_db
        ports:
          - 5432:5432
        options: --health-cmd pg_isready --health-interval 10s --health-timeout 5s --health-retries 5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: intl, pdo_pgsql, mbstring, xml
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Setup test environment
        run: |
          cp .env.test .env.test.local
          echo "DATABASE_URL=postgresql://test:test@localhost:5432/test_db?serverVersion=15" >> .env.test.local

      - name: Run migrations
        run: |
          php bin/console doctrine:migrations:migrate --env=test --no-interaction

      - name: Run tests
        run: php bin/phpunit --coverage-text

  security:
    name: 🔒 Security
    runs-on: ubuntu-latest
    needs: lint
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Security audit
        run: composer audit

      - name: Symfony security check
        uses: symfonycorp/security-checker-action@v5

  #############################################
  # STAGE 2: Continuous Deployment
  #############################################

  deploy:
    name: 🚀 Deploy
    runs-on: ubuntu-latest
    needs: [lint, test, security]
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'
    environment: production

    steps:
      - uses: actions/checkout@v4

      - name: Setup SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.VPS_SSH_KEY }}" > ~/.ssh/key
          chmod 600 ~/.ssh/key
          ssh-keyscan -H ${{ secrets.VPS_HOST }} >> ~/.ssh/known_hosts

      - name: Deploy
        run: |
          ssh -i ~/.ssh/key ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }} << 'EOF'
            cd /var/www/altered-tournament

            # Mode maintenance
            touch public/maintenance.html

            # Mise à jour
            git pull origin main
            composer install --no-dev --optimize-autoloader --no-interaction
            php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
            php bin/console cache:clear --env=prod
            php bin/console cache:warmup --env=prod
            php bin/console asset-map:compile

            # Permissions
            sudo chown -R www-data:www-data .
            chmod -R 775 var/

            # Reload
            sudo systemctl reload php8.2-fpm

            # Fin maintenance
            rm -f public/maintenance.html

            echo "✅ Deployed successfully!"
          EOF

      - name: Health check
        run: |
          sleep 10
          curl -f https://votre-domaine.com || exit 1

  #############################################
  # Notification finale
  #############################################

  notify:
    name: 📢 Notify
    runs-on: ubuntu-latest
    needs: [deploy]
    if: always() && github.ref == 'refs/heads/main'

    steps:
      - name: Send notification
        run: |
          if [ "${{ needs.deploy.result }}" == "success" ]; then
            echo "✅ Deployment successful!"
          else
            echo "❌ Deployment failed!"
          fi
```

---

## Notifications et monitoring

### Discord Webhook

Ajoutez à la fin de votre workflow :

```yaml
  notify-discord:
    name: Discord Notification
    runs-on: ubuntu-latest
    needs: [deploy]
    if: always()

    steps:
      - name: Notify Discord
        env:
          DISCORD_WEBHOOK: ${{ secrets.DISCORD_WEBHOOK_URL }}
        run: |
          if [ "${{ needs.deploy.result }}" == "success" ]; then
            COLOR=3066993
            TITLE="✅ Déploiement réussi"
            DESC="L'application a été déployée avec succès."
          else
            COLOR=15158332
            TITLE="❌ Déploiement échoué"
            DESC="Le déploiement a échoué. Vérifiez les logs."
          fi

          curl -H "Content-Type: application/json" \
            -d "{
              \"embeds\": [{
                \"title\": \"$TITLE\",
                \"description\": \"$DESC\",
                \"color\": $COLOR,
                \"fields\": [
                  {\"name\": \"Repository\", \"value\": \"${{ github.repository }}\", \"inline\": true},
                  {\"name\": \"Branch\", \"value\": \"${{ github.ref_name }}\", \"inline\": true},
                  {\"name\": \"Commit\", \"value\": \"\`${{ github.sha }}\`\", \"inline\": false}
                ],
                \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
              }]
            }" \
            $DISCORD_WEBHOOK
```

### Slack Webhook

```yaml
  notify-slack:
    name: Slack Notification
    runs-on: ubuntu-latest
    needs: [deploy]
    if: always()

    steps:
      - name: Notify Slack
        env:
          SLACK_WEBHOOK: ${{ secrets.SLACK_WEBHOOK_URL }}
        run: |
          if [ "${{ needs.deploy.result }}" == "success" ]; then
            EMOJI=":white_check_mark:"
            COLOR="good"
            TEXT="Déploiement réussi"
          else
            EMOJI=":x:"
            COLOR="danger"
            TEXT="Déploiement échoué"
          fi

          curl -X POST -H 'Content-type: application/json' \
            --data "{
              \"attachments\": [{
                \"color\": \"$COLOR\",
                \"title\": \"$EMOJI $TEXT\",
                \"fields\": [
                  {\"title\": \"Repository\", \"value\": \"${{ github.repository }}\", \"short\": true},
                  {\"title\": \"Branch\", \"value\": \"${{ github.ref_name }}\", \"short\": true}
                ]
              }]
            }" \
            $SLACK_WEBHOOK
```

---

## Rollback et gestion des erreurs

### Script de rollback manuel

Créez `/home/deploy/rollback.sh` sur le VPS :

```bash
#!/bin/bash
set -e

RELEASES_DIR="/var/www/altered-tournament-releases"
APP_LINK="/var/www/altered-tournament"

echo "📋 Releases disponibles :"
ls -lt ${RELEASES_DIR} | head -10

echo ""
read -p "Entrez le nom du dossier de release pour rollback : " RELEASE_NAME

if [ -d "${RELEASES_DIR}/${RELEASE_NAME}" ]; then
    echo "🔄 Rollback vers ${RELEASE_NAME}..."

    ln -sfn "${RELEASES_DIR}/${RELEASE_NAME}" "${APP_LINK}"

    sudo chown -R www-data:www-data "${RELEASES_DIR}/${RELEASE_NAME}"
    sudo systemctl reload php8.2-fpm

    echo "✅ Rollback terminé!"
else
    echo "❌ Release non trouvée: ${RELEASE_NAME}"
    exit 1
fi
```

### Workflow de rollback GitHub

Créez `.github/workflows/rollback.yml` :

```yaml
name: 🔄 Rollback

on:
  workflow_dispatch:
    inputs:
      commits_back:
        description: 'Number of commits to rollback'
        required: true
        default: '1'
        type: string

jobs:
  rollback:
    name: Rollback Deployment
    runs-on: ubuntu-latest
    environment: production

    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Get rollback commit
        id: rollback
        run: |
          COMMIT=$(git rev-parse HEAD~${{ inputs.commits_back }})
          echo "commit=$COMMIT" >> $GITHUB_OUTPUT
          echo "Rolling back to commit: $COMMIT"

      - name: Setup SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.VPS_SSH_KEY }}" > ~/.ssh/key
          chmod 600 ~/.ssh/key
          ssh-keyscan -H ${{ secrets.VPS_HOST }} >> ~/.ssh/known_hosts

      - name: Execute rollback
        run: |
          ssh -i ~/.ssh/key ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }} << EOF
            cd /var/www/altered-tournament

            touch public/maintenance.html

            git fetch origin
            git checkout ${{ steps.rollback.outputs.commit }}

            composer install --no-dev --optimize-autoloader --no-interaction
            php bin/console cache:clear --env=prod
            php bin/console cache:warmup --env=prod

            sudo chown -R www-data:www-data .
            sudo systemctl reload php8.2-fpm

            rm -f public/maintenance.html

            echo "✅ Rollback to ${{ steps.rollback.outputs.commit }} completed!"
          EOF
```

---

## Bonnes pratiques

### 1. Protection de la branche main

Dans GitHub, allez dans **Settings** → **Branches** → **Add rule** :

- **Branch name pattern** : `main`
- ✅ Require a pull request before merging
- ✅ Require status checks to pass before merging
  - Sélectionnez : `lint`, `test`, `security`
- ✅ Require branches to be up to date before merging
- ✅ Do not allow bypassing the above settings

### 2. Environnements GitHub

Configurez un environnement "production" :

1. **Settings** → **Environments** → **New environment**
2. Nom : `production`
3. Activez **Required reviewers** si vous voulez une approbation manuelle
4. Ajoutez les secrets spécifiques à l'environnement

### 3. Structure des branches

```
main (production)
  └── develop (intégration)
        ├── feature/nouvelle-fonctionnalite
        ├── fix/correction-bug
        └── hotfix/correction-urgente
```

### 4. Workflow recommandé

1. Créer une branche `feature/xxx` depuis `develop`
2. Développer et commiter
3. Ouvrir une PR vers `develop` → CI s'exécute
4. Merger dans `develop`
5. Quand prêt pour production : PR `develop` → `main`
6. Merger déclenche le déploiement automatique

### 5. Variables d'environnement

Ne jamais commiter de secrets. Utilisez toujours :
- GitHub Secrets pour la CI/CD
- Fichiers `.env.local` sur le serveur (hors Git)

### 6. Logs et monitoring

```bash
# Sur le VPS, créer un alias pour voir les logs facilement
echo "alias deploy-logs='tail -f /var/www/altered-tournament/var/log/prod.log'" >> ~/.bashrc
```

---

## Checklist de mise en place

- [ ] Créer l'utilisateur `deploy` sur le VPS
- [ ] Générer et configurer les clés SSH
- [ ] Créer le dossier `/var/www/shared` avec `.env.local`
- [ ] Configurer les secrets GitHub (VPS_HOST, VPS_USER, VPS_SSH_KEY)
- [ ] Créer les fichiers workflow dans `.github/workflows/`
- [ ] Configurer la protection de branche `main`
- [ ] Tester un push sur `develop` (CI uniquement)
- [ ] Tester une PR vers `main` (CI + review)
- [ ] Tester un merge sur `main` (CI + CD)
- [ ] Vérifier les notifications (Discord/Slack)
- [ ] Documenter le processus pour l'équipe

---

## Commandes utiles

```bash
# Voir le statut des GitHub Actions
gh run list

# Voir les logs d'un run spécifique
gh run view <run-id> --log

# Déclencher manuellement le déploiement
gh workflow run deploy.yml

# Rollback manuel via GitHub CLI
gh workflow run rollback.yml -f commits_back=1
```

---

*Document généré le 16/01/2026 pour Altered Tournament Tools*
