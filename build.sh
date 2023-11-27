#!/bin/bash

VER=$1

if [ "$1" == "" ]; then
  VER="build"
fi

FILE=DriveStandbyMonitor.$(date +%Y.%m.%d)-$VER.txz

cd src
tar -cJf ../$FILE usr
cd ..
md5sum $FILE
