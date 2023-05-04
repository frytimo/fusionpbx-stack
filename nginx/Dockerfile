ARG NGINX_VERSION=1.23.2-alpine
FROM nginx:${NGINX_VERSION}

# Create the user and group
RUN addgroup -S -g 1000 fusionpbx && adduser -S -D -u 1000 -G fusionpbx fusionpbx

# Create workdir
RUN mkdir -p /var/www/fusionpbx && touch /var/www/fusionpbx/docker-volume-not-mounted && chown fusionpbx:fusionpbx /var/www/fusionpbx && mkdir -p /var/cache/fusionpbx/ && chown fusionpbx:fusionpbx /var/cache/fusionpbx
WORKDIR /var/www/fusionpbx
