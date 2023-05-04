ARG PHP_VERSION=8.0.0-fpm
FROM php:${PHP_VERSION}

# install the php-extensions script to simplify dockerfile
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

ARG XDEBUG_VERSION=3.2.1
# Install Postgres extension
RUN chmod +x /usr/local/bin/install-php-extensions && \
 install-php-extensions \
 xdebug-^${XDEBUG_VERSION} \
 bcmath \
 bz2 \
 @composer \
 event \
 gd \
 imagick \
 imap \
 ldap \
 mcrypt \
 oauth \
 odbc \
 pcntl \
 pdo_odbc \
 pdo_pgsql \
 pgsql \
 soap \
 sockets \
 snmp \
 shmop \
 zip

# Install Supervisor
RUN apt-get update && apt-get -y install supervisor && mkdir /etc/supervisor.d

# Create user and group
RUN addgroup -q --gid 1000 fusionpbx && adduser --shell /bin/bash -q --no-create-home --uid 1000 --gid 1000 fusionpbx

# Create workdir
RUN mkdir /etc/fusionpbx && chown fusionpbx:fusionpbx /etc/fusionpbx && mkdir -p /var/www/fusionpbx && touch /var/www/fusionpbx/docker-volume-not-mounted && chown fusionpbx:fusionpbx /var/www/fusionpbx
WORKDIR /var/www/fusionpbx

COPY ./docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]

# Supervisor will run PHP-FPM and Laravel queue workers
CMD ["supervisord", "--nodaemon", "--configuration", "/etc/supervisord.conf"]
