#!/bin/bash
set -e

MAGENTO_ROOT=/var/www/html

if [ "$(id -u)" = "0" ]; then
    command -v git >/dev/null 2>&1 || (apt-get update -qq && apt-get install -y -qq git)
    COMPOSER_TMP=$(mktemp -d)
    cp /var/www/.composer/auth.json "$COMPOSER_TMP/" 2>/dev/null || true
    chown -R www-data:www-data "$COMPOSER_TMP"
    exec su -s /bin/bash www-data -c "COMPOSER_HOME='$COMPOSER_TMP' exec /bin/bash /init.sh"
fi

# Running as www-data from here

if [ ! -f "$MAGENTO_ROOT/bin/magento" ]; then
    echo "[init] Downloading Magento 2.4.8..."
    composer create-project \
        --repository-url=https://repo.magento.com/ \
        --no-interaction \
        --no-audit \
        --no-dev \
        --prefer-source \
        magento/project-community-edition "$MAGENTO_ROOT" 2.4.8
fi

if ! grep -qE "'install'\s*=>" "$MAGENTO_ROOT/app/etc/env.php" 2>/dev/null; then
    echo "[init] Installing Magento..."
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" setup:install \
        --base-url=http://localhost \
        --db-host=db \
        --db-name=magento \
        --db-user=magento \
        --db-password=magento \
        --admin-firstname=Admin \
        --admin-lastname=User \
        --admin-email=admin@example.com \
        --admin-user=admin \
        --admin-password=Admin1234! \
        --search-engine=opensearch \
        --opensearch-host=opensearch \
        --language=pt_BR \
        --currency=BRL \
        --timezone=America/Sao_Paulo \
        --backend-frontname=admin

    echo "[init] Disabling 2FA (dev environment)..."
    php "$MAGENTO_ROOT/bin/magento" module:disable Magento_AdminAdobeImsTwoFactorAuth Magento_TwoFactorAuth

    echo "[init] Enabling Olist_Envios module..."
    php "$MAGENTO_ROOT/bin/magento" module:enable Olist_Envios
    php -d memory_limit=-1 "$MAGENTO_ROOT/bin/magento" setup:upgrade
    php "$MAGENTO_ROOT/bin/magento" deploy:mode:set developer
    php "$MAGENTO_ROOT/bin/magento" maintenance:disable

    echo "[init] Configuring dynamic base URL..."
    php "$MAGENTO_ROOT/bin/magento" config:set web/unsecure/base_url "{{base_url}}"
    php "$MAGENTO_ROOT/bin/magento" config:set web/secure/base_url "{{base_url}}"

    php "$MAGENTO_ROOT/bin/magento" cache:flush
fi

echo "[init] Done."
