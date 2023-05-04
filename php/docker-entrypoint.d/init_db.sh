#!/bin/bash
if [ ! -f /var/www/fusionpbx/core/upgrade/init_database.php ]; then
    ln -s /usr/local/src/upgrade_apps/init_database.php /var/www/fusionpbx/core/upgrade/init_database.php
fi

PHP=$(which php)

cd /var/www/fusionpbx
${PHP} /usr/local/src/upgrade_apps/init_database.php
