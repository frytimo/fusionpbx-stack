#!/bin/bash
docker build -t fpbx-fs:1.10.9 --build-arg DEBIAN_VERSION=11 --build-arg FREESWITCH_VERSION=1.10.9 .
