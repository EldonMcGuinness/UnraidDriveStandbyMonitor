#!/bin/bash

SCRIPT_NAME="DriveStandbyMonitor"
LOG_LOCATION="/tmp/${SCRIPT_NAME}"
PID="/var/run/${SCRIPT_NAME}.pid"

# Remove the previous log
echo "" > "${LOG_LOCATION}/monitor.log"

# Create pid file
echo $$ > "$PID"

for l in /dev/sd?; do 
        DATE=$(date +%s)

        # Get standby status info
        i=$(echo $l | awk '{print substr($1,8)}'); 
        STANDBY_STATUS=0

        SMART=$(smartctl -i -n never /dev/sd$i)

        # Check for Unknown USB Bridge, this will be things that do not support smart over USB
        echo "$SMART" | grep "Unknown USB bridge" > /dev/null
        if [ $? -eq 0 ]; then  # IF it matches
          continue;
        fi

        MODEL=$(echo "$SMART" | grep "Device Model" | awk '{$1=$2=""; print $0}' | sed 's/^\s*//g' | sed 's/\s/_/g')
        SERIAL=$(echo "$SMART" | grep "Serial Number" | awk '{$1=$2=""; print $0}' | sed 's/^\s*//g' | sed 's/\s/_/g')

        DRIVE=${MODEL}_${SERIAL}
        echo "$SMART" | grep STANDBY > /dev/null
        if [ $? -eq 1 ]; then  #IF it does not match
          STANDBY_STATUS=1
        fi
 
        sqlite3 "/boot/config/plugins/DriveStandbyMonitor/monitor.db" "INSERT INTO 'standby' ('drive', 'state', 'date') VALUES ('$DRIVE', $STANDBY_STATUS, $DATE);"
done;

rm "$PID"
exit 0;