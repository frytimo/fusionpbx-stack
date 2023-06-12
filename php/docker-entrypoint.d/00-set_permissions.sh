#!/usr/bin/env sh
set -e
if [ -d /var/log/freeswitch ]; then
    chown -R fusionpbx:fusionpbx /var/log/freeswitch
fi

if [ -d /etc/freeswitch ]; then
    chown -R fusionpbx:fusionpbx /etc/freeswitch
fi

if [ -d /var/cache/fusionpbx ]; then
    chown -R fusionpbx:fusionpbx /var/cache/fusionpbx
fi

if [ -d /var/lib/freeswitch ]; then
    chown -R fusionpbx:fusionpbx /var/lib/freeswitch
fi

if [ ! -d /etc/fusionpbx ]; then
    mkdir -p /etc/fusionpbx
    chown -R fusionpbx:fusionpbx /etc/fusionpbx
fi

if [ ! -d /dev/shm/freeswitch ]; then
    mkdir -p /dev/shm/freeswitch
fi

chown fusionpbx:fusionpbx /dev/shm/freeswitch
