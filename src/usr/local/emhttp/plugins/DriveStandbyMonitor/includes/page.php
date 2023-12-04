<?
#Pull some default data
libxml_use_internal_errors(true); # Suppress any warnings from xml errors.
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$pluginRoot = "$docroot/plugins/$scriptName";
$defaults = @parse_ini_file("$docroot/plugins/DriveStandbyMonitor/default.cfg") ?: [];
$DB_FILE = $defaults["DB_LOCATION"];

# Pull in data about the disks
$disks = @parse_ini_file("$docroot/state/disks.ini", true);
$disks = array_merge_recursive(
    @parse_ini_file("$docroot/state/disks.ini",true)?:[], 
    @parse_ini_file("$docroot/state/devs.ini",true)?:[]
);

# Pull in some defaults
$defaults = @parse_ini_file("$docroot/plugins/DriveStandbyMonitor/default.cfg") ?: [];

# Used to strip out unused devices and limit the returned data
class DisksInfo {

    private $disks;
    private $date;
    private $disksBySerial = [];
       
    # Helper function used to clean the disk name up a bit
    private function cleanName( $name = "" ) {
        $chars = "";
        $digits = "";

        list($chars, $digits) = DSMTools::splitName($name);

        $chars = ucfirst($chars);

        if ( $digits === 0 ) $digits = "";

        return $chars." ".$digits;
        
    }

  
    public function __construct( $disks = [] ){
        $this->date = time();

        foreach ($disks as $key => &$val){

            if ( $val["type"] == "Flash" ) {
                unset($disks[$key]);
            }

            if ( $val["device"] == "" ) {
                unset($disks[$key]);
            }

            $val["type"] = $val["type"] ?? "Dev";

            $val = [
                "id" => $val["id"],
                "name" => $this->cleanName($val["name"]),
                "device" => $val["device"],
                "transport" => $val["transport"],
                "type" => $val["type"],
                "rotational" => $val["rotational"],
                "spundown" => ($val["spundown"]) ? 0 : 1, #TODO Look into removing this negation
                "date" => time()
            ];

            $this->disksBySerial[ $val["id"] ] = &$val;
        }

        $this->disks = $disks;

    }

    # Getter to return the disks
    public function getDisks() {
        return $this->disks;
        
    }

    # Getter to convert a serial number into the disk name
    public function getDeviceName( $serial = '' ){
        return $this->disksBySerial[ $serial ]['name'];

    }

    # Getter to convert a serial number into the device
    public function getDevice( $serial = '' ){
        return $this->disksBySerial[ $serial ]['device'];
    }

    # Getter to convert a serial number into the device type
    public function getDeviceType( $serial = '' ){
        return $this->disksBySerial[ $serial ]['type'];
    }
}

# Used when working with the DB
class DSMDB extends SQLite3 {

    # Pointer variables used to hold the current position in the sqlite3 fetchArray functions
    private $getStandbyDisksResults = false;
    private $getLiveDisksResults = false;
    private $getRawDataResults = false;
    private $getRawDataDisksResults = false;

    public function __construct( $dbname ){
        $this->open( $dbname );
        $this->createTable();

    }

    # Helper function to create the table if needed
    private function createTable(){
        $this->exec("CREATE TABLE IF NOT EXISTS 'standby' ( id INTEGER PRIMARY KEY AUTOINCREMENT, drive VARCHAR(32), state INTEGER, date INTEGER );");
    }

    # Get a count of the standby logs for the drives
    public function getStandbyDisks() {
        if ( $this->getStandbyDisksResults === false ) {
            $sql = "SELECT 'standby'.'drive', count('standby'.'state') AS count from 'standby' where 'standby'.'state' == 0 group by 'standby'.'drive';";
            $this->getStandbyDisksResults = $this->query( $sql );
        }

        $row = $this->getStandbyDisksResults->fetchArray(SQLITE3_ASSOC);

        if ( $row === false ) {
            $this->getStandbyDisksResults = false;
        }

        return $row;

    }

    # Get a count of the non-standby logs for the drives
    public function getLiveDisks(){
        if ( $this->getLiveDisksResults === false ) {
            $sql = "SELECT 'standby'.'drive', count('standby'.'state') AS count from 'standby' where 'standby'.'state' == 1 group by 'standby'.'drive';";
            $this->getLiveDisksResults = $this->query( $sql );
        }

        $row = $this->getLiveDisksResults->fetchArray(SQLITE3_ASSOC);

        if ( $row === false ) {
            $this->getLiveDisksResults = false;
        }

        return $row;
        
    }

    # Get a lists of data entries to use on StandbyData.page
    public function getRawData(){
        if ( $this->getRawDataResults === false ) {
            $sql = "SELECT * from 'standby' order by 'standby'.'drive', 'standby'.'date' DESC;";
            $this->getRawDataResults = $this->query( $sql );
        }

        $row = $this->getRawDataResults->fetchArray(SQLITE3_ASSOC);

        if ( $row === false ) {
            $this->getRawDataResults = false;
        }

        return $row;
    }

    # Get a lists of disks to use on StandbyData.page
    public function getRawDataDisks(){
        if ( $this->getRawDataDisksResults === false ) {
            $sql = "SELECT 'standby'.'drive' from 'standby' group by 'standby'.'drive' order by 'standby'.'drive' ASC;";
            $this->getRawDataDisksResults = $this->query( $sql );
        }

        $row = $this->getRawDataDisksResults->fetchArray(SQLITE3_ASSOC);

        if ( $row === false ) {
            $this->getRawDataDisksResults = false;
        }

        return $row;
    }

    # Log the current disks' information into the database
    public function logEntries ( $disks = [] ) {
        foreach ( $disks as $disk ){
            $this->exec("INSERT INTO 'standby' ('drive', 'state', 'date') VALUES ('".$disk["id"]."', ".$disk["spundown"].", ".$disk["date"].")");
        }

    }

    # Reset the database
    public function resetDB () {
        $this->exec("DELETE FROM standby;");

    }
    
}


class DSMTools{

    #Sorting helper function, used by DSMTools::driveSort
    private static function alphasort($a, $b){
        $a_name = "";
        $a_id = 0;
        $b_name = "";
        $b_id = 0;
    
    
        list($a_name, $a_id) = DSMTools::splitName($a["name"]);
        list($b_name, $b_id) = DSMTools::splitName($b["name"]);

        $a_name = $a["type"];
        $b_name = $b["type"];
    
        if ( $a_name == "Parity" && $b_name == "Parity" ){
            if ($a_id == $b_id) return 0;
            return ($a_id < $b_id) ? -1 : 1;
    
        }elseif ( $a_name == "Parity" && ( $b_name == "Data" || $b_name == "Cache" || $b_name == "Dev" )){
            return -1;

        }elseif ( $a_name == "Data" && $b_name == "Data" ){
            if ($a_id == $b_id) return 0;
            return ($a_id < $b_id) ? -1 : 1;
    
        }elseif ( $a_name == "Data" && $b_name == "Parity" ){
            return 1;

        }elseif ( $a_name == "Data" && $b_name == "Cache" ){
            return -1;

        }elseif ( $a_name == "Data" && $b_name == "Dev" ){
            return -1;

        }elseif ( $a_name == "Cache" && $b_name == "Cache" ){
            if ($a_id == $b_id) return 0;
            return ($a_id < $b_id) ? -1 : 1;
    
        }elseif ( $a_name == "Cache" && $b_name == "Parity" ){
            return 1;

        }elseif ( $a_name == "Cache" && $b_name == "Data" ){
            return 1;

        }elseif ( $a_name == "Cache" && $b_name == "Dev" ){
            return -1;

        }elseif ( $a_name == "Dev" && $b_name == "Dev" ){
            if ($a_id == $b_id) return 0;
            return ($a_id < $b_id) ? -1 : 1;
    
        }elseif ( $a_name == "Dev" && $b_name == "Parity" ){
            return 1;

        }elseif ( $a_name == "Dev" && $b_name == "Data" ){
            return 1;

        }elseif ( $a_name == "Dev" && $b_name == "Cache" ){
            return 1;

        }
    
        if ($a == $b) return 0;
        return ($a < $b) ? -1 : 1;
    
    }
    
    private static function getDigits( $name = null ){
	    if ($name == null) return null;

	    $digits = null;
	
	    if ( preg_match("/(\d+)$/", $name, $digits) ) {
            return $digits[1];
        }
	
    	return 0;
    }

    private static function getChars( $name = null ){
	    if ($name == null) return null;

	    $chars = null;
	
    	if ( preg_match("/(.+?)\s*(\d+)$/", $name, $chars) ) return $chars[1];
	
        if ( $chars == "" ) return "NoName";

    	return $name;
    }
    
    # Helper function used to split the disk name into text and number
    public static function splitName( $name = "" ) {
        return array(
            ucfirst( DSMTools::getChars( $name ) ),
            DSMTools::getDigits( $name )
        );
        
    }

    # Used to sort disks into order Parity -> Disks -> Cache
    public static function diskSort( &$disks ) {
        uasort($disks, "DSMTools::alphasort");
    }

}


?>