#!/usr/bin/env sh
set -e

chown -R fusionpbx:fusionpbx /var/log/freeswitch
chown -R fusionpbx:fusionpbx /var/run/freeswitch
chown -R fusionpbx:fusionpbx /etc/freeswitch
chown -R fusionpbx:fusionpbx /var/cache/fusionpbx
chown -R fusionpbx:fusionpbx /usr/lib/freeswitch
chown -R fusionpbx:fusionpbx /var/lib/freeswitch
mkdir -p /etc/fusionpbx
mkdir -p /dev/shm/freeswitch
chown -R fusionpbx:fusionpbx /etc/fusionpbx
chown fusionpbx:fusionpbx /dev/shm/freeswitch

# Source docker-entrypoint.sh:
# https://github.com/docker-library/postgres/blob/master/9.4/docker-entrypoint.sh
# https://github.com/kovalyshyn/docker-freeswitch/blob/vanilla/docker-entrypoint.sh

if [ "x$1" = 'xsupervisord' ]; then

    if [ -d /docker-entrypoint.d ]; then
        for f in /docker-entrypoint.d/*.sh; do
            [ -f "$f" ] && . "$f"
        done
    fi

    #
    # execute freeswitch with fusionpbx user and group permissions
    #
    #exec gosu fusionpbx /usr/bin/freeswitch -rp -u fusionpbx -g fusionpbx -nonat -nc
    exec "$@"
fi

if [ "x$1" = "x" ]; then
    exec gosu fusionpbx /usr/bin/fs_cli
fi

if [ "x$1" = "xbash" ]; then
    exec gosu fusionpbx /bin/bash
fi

if [ "x$1" = "x/bin/bash" ]; then
    exec gosu fusionpbx /bin/bash
fi
