#!/bin/bash

source /docker-entrypoint.d/globals.sh

FILE="/etc/freeswitch/vars.xml"

#
# Update vars.xml so that it points to the current IP of the db container
#
sed -i -E "s#(<X-PRE-PROCESS cmd=\"set\" data=\"dsn=pgsql://hostaddr=)(.*)( port=${DB_PORT} dbname=${DB_NAME} user=${DB_USER} password=${DB_PASSWORD}\" />)#\1${DB_IP}\3#g" ${FILE}
