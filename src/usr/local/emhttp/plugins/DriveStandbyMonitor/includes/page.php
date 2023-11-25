<?

/* Adjustments for 6.12 UI changes. */
if (version_compare($version['version'], '6.12.0-beta5', '>')) {
        $title_classid  = "class='title'";
        $new_model              = true;
} else {
        $title_classid  = "id='title'";
        $new_model              = false;
}

#Pull some default data
libxml_use_internal_errors(true); # Suppress any warnings from xml errors.
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$pluginRoot = "$docroot/plugins/$scriptName";
$defaults = @parse_ini_file("$docroot/plugins/DriveStandbyMonitor/default.cfg") ?: [];
$DB_FILE = $defaults["DB_LOCATION"];

# Pull in data about the disks
$disks = @parse_ini_file("$docroot/state/disks.ini", true);

# Pull in some defaults
$defaults = @parse_ini_file("$docroot/plugins/DriveStandbyMonitor/default.cfg") ?: [];


# Used to strip out unused devices and limit the returned data
class DisksInfo {

    private $disks;
    private $date;
    private $disksBySerial = [];
    
    private function cleanName($name) {
        if ( strstr($name, 'disk')  !== false ){
            return rtrim( "Disk ".substr($name, 4) );

        }elseif ( strstr($name, 'cache') !== false ){
            return rtrim( "Cache ".substr($name, 5) );

        }elseif ( strstr($name, 'parity')  !== false ){
            return rtrim( "Parity ".substr($name, 6) );
        }else{
            return $name;
        }
        
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

            $val = [
                "id" => $val["id"],
                "name" => $this->cleanName($val["name"]),
                "device" => $val["device"],
                "transport" => $val["transport"],
                "rotational" => $val["rotational"],
                "spundown" => ($val["spundown"]) ? 0 : 1, #TODO Look into removing this negation
                "date" => time()
            ];

            $this->disksBySerial[ $val["id"] ] = $val;
        }

        $this->disks = $disks;

    }

    public function getDisks() {
        return $this->disks;
        
    }

    public function getDeviceName( $serial = '' ){
        return $this->disksBySerial[ $serial ]['name'];

    }

    public function getDevice( $serial = '' ){
        return $this->disksBySerial[ $serial ]['device'];
    }
}

# Used when working with the DB
class DSMDB extends SQLite3 {

    private $getStandbyDisksResults = false;
    private $getLiveDisksResults = false;
    private $getRawDataResults = false;
    private $getRawDataDisksResults = false;

    public function __construct( $dbname ){
        $this->open( $dbname );
        $this->createTable();

    }

    private function createTable(){
        $this->exec("CREATE TABLE IF NOT EXISTS 'standby' ( id INTEGER PRIMARY KEY AUTOINCREMENT, drive VARCHAR(32), state INTEGER, date INTEGER );");
    }

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

    

    public function logEntries ( $disks = [] ) {
        foreach ( $disks as $disk ){
            $this->exec("INSERT INTO 'standby' ('drive', 'state', 'date') VALUES ('".$disk["id"]."', ".$disk["spundown"].", ".$disk["date"].")");
        }

    }

    public function resetDB () {
        $this->exec("DELETE FROM standby;");

    }
    
}

?>