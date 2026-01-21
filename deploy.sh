#!/bin/bash

#######################################
# Altered Tournament Tools - Script de déploiement
# Usage: ./deploy.sh
#######################################

set -e  # Arrête le script en cas d'erreur

# Configuration
APP_DIR="/var/www/altered-tournament-tools/alteredTournamentTools"
WEB_USER="www-data"
BRANCH="main"

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Déploiement Altered Tournament Tools  ${NC}"
echo -e "${GREEN}========================================${NC}"

cd "$APP_DIR" || { echo -e "${RED}Erreur: Impossible d'accéder à $APP_DIR${NC}"; exit 1; }

# 1. Activer le mode maintenance (optionnel)
echo -e "${YELLOW}[1/8] Activation du mode maintenance...${NC}"
if [ -f "public/maintenance.html" ]; then
    touch public/.maintenance
fi

# 2. Récupérer les dernières modifications
echo -e "${YELLOW}[2/8] Récupération des modifications depuis Git...${NC}"
git fetch origin
git reset --hard origin/$BRANCH

# 3. Installer les dépendances
echo -e "${YELLOW}[3/8] Installation des dépendances Composer...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Exécuter les migrations
echo -e "${YELLOW}[4/8] Exécution des migrations de base de données...${NC}"
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Vider le cache
echo -e "${YELLOW}[5/8] Vidage du cache...${NC}"
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

# 6. Compiler les assets
echo -e "${YELLOW}[6/8] Compilation des assets...${NC}"
php bin/console asset-map:compile

# 7. Corriger les permissions
echo -e "${YELLOW}[7/8] Correction des permissions...${NC}"
sudo chown -R $WEB_USER:$WEB_USER var/
sudo chown -R $WEB_USER:$WEB_USER public/assets/
sudo chmod -R 775 var/

# 8. Désactiver le mode maintenance
echo -e "${YELLOW}[8/8] Désactivation du mode maintenance...${NC}"
rm -f public/.maintenance

# Redémarrer PHP-FPM pour vider l'opcache
echo -e "${YELLOW}Redémarrage de PHP-FPM...${NC}"
sudo systemctl reload php8.2-fpm

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Déploiement terminé avec succès !    ${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "Site: https://altered-tournament-tools.com"
