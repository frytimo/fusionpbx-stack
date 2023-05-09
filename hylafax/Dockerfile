FROM ubuntu:18.04

#docker run -v $(pwd):/opt -it --name hylafax ubuntu:18.04 /bin/bash
WORKDIR /usr/local/src

#RUN wget -O hylafax.tar.gz http://git.hylafax.org/HylaFAX?a=snapshot;h=c6cac8d8cd0dbe313689ba77023e12bc5b3027be
COPY HylaFAX.tar.gz .
RUN apt update && apt install -y libtiff-dev build-essential sendmail libtiff-tools
RUN tar -xvzf HylaFAX.tar.gz && cd HylaFAX && ./configure --nointeractive && make && make install
