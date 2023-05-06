#!/usr/bin/env sh
set -a
. ../.env

docker build -t fpbx-php:${PHP_VERSION} --build-arg PHP_VERSION=${PHP_VERSION} --build-arg XDEBUG_VERSION=${XDEBUG_VERSION} .
