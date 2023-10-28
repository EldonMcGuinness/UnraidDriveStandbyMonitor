<?
exec('sqlite3 "/boot/config/plugins/DriveStandbyMonitor/monitor.db" "DELETE FROM standby;"');

// Add some error checking and perhaps a key to make sure this is not accidentally called
$data = array( "result" => 0 );
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
?>
