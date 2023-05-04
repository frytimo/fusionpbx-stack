#!/bin/bash

#
# get the ip of this container
#
export SWITCH_IP=$(hostname  -I | cut -f1 -d' ')

#
# get the ip of the database container
#
export DB_IP=$(host db | cut -f4 -d' ')

#
# get the credentials for the database from fusionpbx config file
#
export DB_PORT=$(grep "database.0.port" /etc/fusionpbx/config.conf | cut -f3 -d' ')
export DB_PASSWORD=$(grep "database.0.password" /etc/fusionpbx/config.conf | cut -f3 -d' ')
export DB_USER=$(grep "database.0.username" /etc/fusionpbx/config.conf | cut -f3 -d' ')
export DB_NAME=$(grep "database.0.name" /etc/fusionpbx/config.conf | cut -f3 -d' ')
