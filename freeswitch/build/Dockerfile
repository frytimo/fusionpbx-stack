#
# Clone the freeswitch,sofia_sip,spandsp repos in to the folder /usr/local/src/freeswitch, /usr/local/src/sofia_sip,/usr/local/src/spandsp
# folders respectively or set the FREESWITCH_DIR, SPANDSP_DIR, SOFIA-SIP_DIR arguments to location they have been cloned to.
# It is recommended to use 'git checkout -b build' for each of them as to not conflict with the original files. Moving them to a cloned
# repo ahead of time on the host machine is recommended as to not download them from git each time you need them compiled because github
# has a limit set per day for public downloads. To go over this limit would require a paid account.
#

#
#OS PREFRENCE
#
ARG DEBIAN_VERSION="11"

#
# Get the base image
# Changing the base image may require different packages so it is not recommended
#
FROM debian:$DEBIAN_VERSION

ENV VOLUME_FAX=/var/lib/freeswitch/storage/fax
ENV VOLUME_RECORDINGS=/var/lib/freeswitch/recordings
ENV VOLUME_VOICEMAIL=/var/lib/freeswitch/storage/voicemail
#
# Comment out this section if files are not already downloaded and uncomment section 'git clone' below
#
COPY ./freeswitch/ /usr/local/src/freeswitch/
COPY ./sofia-sip/ /usr/local/src/sofia-sip/
COPY ./spandsp/ /usr/local/src/spandsp/
# comment out next line if they are not already downloaded
#COPY ./sounds/freeswitch-sounds-*.tar.gz /usr/local/src/freeswitch/

RUN apt-get update && apt-get install -y locales && rm -rf /var/lib/apt/lists/* \
    && localedef -i en_US -c -f UTF-8 -A /usr/share/locale/locale.alias en_US.UTF-8
ENV LANG en_US.utf8

RUN apt update && apt install -y libtool build-essential gosu yasm autoconf libtool-bin pkg-config wget git bind9-host
WORKDIR /

RUN echo "/usr/lib" | tee /etc/ld.so.conf.d/sofia_sip.so.conf && echo "/usr/lib" | tee /etc/ld.so.conf.d/spandsp.so.conf && ldconfig

RUN apt update && apt install -y libtiff-dev

#
# Setup spandsp
#
RUN cd /usr/local/src/spandsp \
  && git checkout -b build \
  && ./bootstrap.sh \
  && ./configure --prefix=/usr \
  && make -i -j$(nproc) \
  && make install

#
# make the sofia-sip modules
#
RUN apt update && apt install -y libssl-dev

RUN cd /usr/local/src/sofia-sip \
  && git checkout -b build \
  && ./bootstrap.sh \
  && ./configure --prefix=/usr \
  && make -i -j$(nproc) \
  && make install

#
# Update / Install packages for freeswitch compile
#
RUN apt update && apt install -y libmpg123-dev libshout3-dev libsqlite3-dev libcurl4-openssl-dev libpcre3-dev libspeexdsp-dev libldns-dev libedit-dev libavformat-dev libswscale-dev liblua5.3-dev doxygen libluajit-5.1-dev liblualib50-dev libpq-dev unixodbc-dev libopus-dev libsndfile-dev erlang-dev libmp3lame-dev libsnmp-dev python3-dev uuid-dev

#
# Update libraries
#
RUN ldconfig

#
# Switch to a release version
#
ARG FREESWITCH_VERSION=1.10.9
RUN cd /usr/local/src/freeswitch && git checkout tags/v$FREESWITCH_VERSION -b v$FREESWITCH_VERSION

RUN cd /usr/local/src/freeswitch \
  && ./bootstrap.sh

#
# update modules to compile
#
RUN cd /usr/local/src/freeswitch \
  && sed -i "s|applications/mod_signalwire|#applications/mod_signalwire|g" modules.conf \
  && sed -i "s|endpoints/mod_verto|#endpoints/mod_verto|g" modules.conf \
  && sed -i "s|#applications/mod_lcr|applications/mod_lcr|g" modules.conf \
  && sed -i "s|#applications/mod_nibblebill|applications/mod_nibblebill|g" modules.conf \
  && sed -i "s|#event_handlers/mod_snmp|event_handlers/mod_snmp|g" modules.conf \
  && sed -i "s|#applications/mod_avmd|applications/mod_avmd|g" modules.conf \
  && sed -i "s|#applications/mod_callcenter|applications/mod_callcenter|g" modules.conf \
  && sed -i "s|#applications/mod_cidlookup|applications/mod_cidlookup|g" modules.conf \
  && sed -i "s|#applications/mod_curl|applications/mod_curl|g" modules.conf \
  && sed -i "s|#event_handlers/mod_erlang_event|event_handlers/mod_erlang_event|g" modules.conf \
  && sed -i "s|#formats/mod_shout|formats/mod_shout|g" modules.conf \
  && sed -i "s|#formats/mod_pgsql|formats/mod_pgsql|g" modules.conf

#
# configure freeswitch with forced directory structure
#
RUN cd /usr/local/src/freeswitch \
  && ./configure -C \
  --enable-portable-binary \
  --disable-dependency-tracking \
  --prefix=/usr \
  --localstatedir=/var \
  --sysconfdir=/etc \
  --with-openssl \
  --enable-core-pgsql-support \
  --enable-fatal-warnings=no

#
# make and install freeswitch using all cores
#
RUN cd /usr/local/src/freeswitch && make CFLAGS="-Wno-error=format-truncation" CXXFLAGS="-Wno-error=format-truncation" -i -j$(nproc)
RUN cd /usr/local/src/freeswitch && make install

#
# make the sound files seperately
#
RUN rm -rf /usr/share/freeswitch/sounds/music/default && cd /usr/local/src/freeswitch && make cd-sounds-install && make cd-moh-install && make uhd-sounds-install && make uhd-moh-install && make hd-sounds-install && make hd-moh-install && make sounds-install && make moh-install && make cd-sounds-fr-install && make uhd-sounds-fr-install && make hd-sounds-fr-install && make sounds-fr-install && mkdir -p /usr/share/freeswitch/sounds/music/default

#
# Update libraries
#
RUN ldconfig

#
# Clean up the sound files
#
RUN rm -Rf /usr/local/src/freeswitch/*.tar.gz


#
# Install and copy configuration for supervisord
#
RUN apt update && apt install -y supervisor
COPY ./supervisord.conf /etc/supervisord.conf

#
# Clean up
#
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

#
# Create user
#
RUN addgroup -q --gid 1000 fusionpbx && adduser --gecos "" --shell /bin/bash -q --no-create-home --uid 1000 --gid 1000 fusionpbx

# Healthcheck to make sure the service is running
SHELL ["/bin/bash"]
HEALTHCHECK --interval=15s --timeout=5s \
    CMD  fs_cli -x status | grep -q ^UP || exit 1

#
# Copy the external entrypoint file in to the container root folder
#
COPY ./docker-entrypoint.sh /docker-entrypoint.sh

# Copy the Limits Configuration file in to the container
COPY ./freeswitch.limits.conf /etc/freeswitch/security/limits.d/

#
# Fusionpbx specific settings
#
#use workdir to create the directory
WORKDIR /var/cache/fusionpbx
#
WORKDIR /

# Supervisor will run freeswitch
ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["supervisord"]
