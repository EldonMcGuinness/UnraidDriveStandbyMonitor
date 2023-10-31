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
        smartctl -i -n never /dev/sd$i | grep STANDBY > /dev/null
        if [ $? -eq 1 ]; then 
          STANDBY_STATUS=1
        fi

        TEMPERATURE=$(smartctl -A /dev/sd$i | grep Temperature_Celsius | awk {'print $10'})
        if [ "$TEMPERATURE" == "" ]; then
                TEMPERATURE=0
        fi

        sqlite3 "/boot/config/plugins/DriveStandbyMonitor/monitor.db" "INSERT INTO 'standby' ('drive', 'state', 'temperature', 'date') VALUES ('sd$i', $STANDBY_STATUS, $TEMPERATURE, $DATE);"
done;

rm "$PID"
exit 0;


