#!/usr/bin/env sh
set -a
. ../../.env

docker build --no-cache -t fpbx-fs:${FREESWITCH_VERSION} --build-arg DEBIAN_VERSION=${DEBIAN_VERSION} --build-arg FREESWITCH_VERSION=${FREESWITCH_VERSION} .
