<?
#Pull some default data
libxml_use_internal_errors(true); # Suppress any warnings from xml errors.

$scriptName = "DriveStandbyMonitor";
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$pluginRoot = "$docroot/plugins/$scriptName";
include_once( "./plugins/$scriptName/includes/page.php" );

$DB = new DSMDB( $DB_FILE );
$DB->resetDB();

// Add some error checking and perhaps a key to make sure this is not accidentally called
$data = array( "result" => 0 );
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
?>
