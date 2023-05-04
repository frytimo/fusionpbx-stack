#!/bin/bash
docker build -t fpbx-nginx:1.23.2-alpine --build-arg NGINX_VERSION=1.23.2-alpine .
