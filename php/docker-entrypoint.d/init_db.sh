#!/bin/env sh

PHP=$(which php)

cd /var/www/fusionpbx
${PHP} core/upgrade/init_database.php
