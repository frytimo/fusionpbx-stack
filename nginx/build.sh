#!/usr/bin/env sh
set -a
. ../.env
docker build -t fpbx-nginx:${NGINX_VERSION} --build-arg NGINX_VERSION=${NGINX_VERSION} .
