ARG PHP_VERSION=8.1-fpm-alpine
FROM php:${PHP_VERSION}

# install the php-extensions script to simplify dockerfile
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# These are some packages that contain commands FusionPBX runs in a shell
RUN if [ -z $(which apk) ]; then DEBIAN_FRONTEND=noninteractive apt update && DEBIAN_FRONTEND=noninteractive apt install -y procps uptimed lsb-release wget git; else apk update && apk add --no-cache git wget procps uptimed; fi

WORKDIR /usr/local/src

RUN git clone https://github.com/xdebug/xdebug.git

WORKDIR /usr/local/src/xdebug

# PHP version 8.1 or higher is default
ARG XDEBUG_VERSION=3.3.2

RUN git checkout tags/${XDEBUG_VERSION} -b ${XDEBUG_VERSION}

RUN cd /usr/local/src/xdebug/ && ./rebuild.sh

# Install Postgres extension
RUN chmod +x /usr/local/bin/install-php-extensions && \
 install-php-extensions \
 bcmath \
 bz2 \
 @composer \
 event \
 gd \
 imagick \
 imap \
 inotify \
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
RUN if [ -z $(which apk) ]; then DEBIAN_FRONTEND=noninteractive apt update && DEBIAN_FRONTEND=noninteractive apt install -y supervisor gosu; else apk update && apk add --no-cache supervisor && mkdir /etc/supervisor.d; fi

# Create user and group
RUN if [ -z $(which apk) ]; then addgroup -q --gid 1000 fusionpbx && adduser --shell /bin/bash -q --no-create-home --uid 1000 --gid 1000 fusionpbx; else addgroup -S -g 1000 fusionpbx && adduser -S -D -u 1000 -G fusionpbx fusionpbx; fi

# Create workdir
RUN mkdir /etc/fusionpbx && chown fusionpbx:fusionpbx /etc/fusionpbx && mkdir -p /var/www/fusionpbx && touch /var/www/fusionpbx/docker-volume-not-mounted && chown fusionpbx:fusionpbx /var/www/fusionpbx

#
# Clean up Debian files
#
RUN if [ -z $(which apk) ]; then apt-get clean && rm -rf /var/lib/apt/lists/*; fi

WORKDIR /

COPY ./docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]

# Supervisor will run PHP-FPM and Laravel queue workers
CMD ["supervisord", "--nodaemon", "--configuration", "/etc/supervisord.conf"]
