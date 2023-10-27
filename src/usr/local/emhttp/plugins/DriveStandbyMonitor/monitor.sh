#!/bin/bash

SCRIPT_NAME="DriveStandbyMonitor"
LOG_LOCATION="/tmp/${SCRIPT_NAME}"

# Remove the previous log
echo "" > "${LOG_LOCATION}/monitor.log"

for l in /dev/sd?; do 
        i=$(echo $l | awk '{print substr($1,8)}'); 
        STATUS=0
        DATE=$(date +%s)
        smartctl -i -n never /dev/sd$i | grep STANDBY > /dev/null
        if [ $? -eq 1 ]; then 
          STATUS=1
        fi
        sqlite3 "/boot/config/plugins/DriveStandbyMonitor/monitor.db" "INSERT INTO 'standby' ('drive', 'state', 'date') VALUES ('sd$i', $STATUS, $DATE);"
done;

exit 0;