#!/bin/bash

FILE=DriveStandbyMonitor.$(date +%Y.%m.%d)-build.txz

cd src
tar -cJf ../$FILE usr
cd ..
md5sum $FILE
