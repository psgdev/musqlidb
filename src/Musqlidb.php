<?php

namespace Psgdev\Musqlidb;

use mysqli;
use Exception;
use Log;

/**
 * Class Musqlidb
 * Connect to one or more mysql database and run crud
 * @package Psgdev\Musqlidb
 */
class Musqlidb extends mysqli
{

    /**
     * protected var
     */
    protected $primaryKey;
    protected $getEnc;
    protected $setUtf8Uni = false;
    protected $setUtf8mb4Uni = false;
    protected $runSQL = '';

    protected $dbHost = '';
    protected $dbPort = '';
    protected $dbName = '';
    protected $dbUser = '';
    protected $dbPassword = '';

    protected $trackDeleted = false; // need to extend this class to enable log of delete attempt or use non static
    // instantiation only


    /**
     * public var
     */
    public $testStatus = false; // if true, queries will not be executed except select statement!!!!
    public $cntConnection = 0;
    public $response = null;
    public $rows = 0;
    public $data = [];
    public $currentQuery = ''; // last run query, error and affected rows

    /**
     * protected static var
     */
    protected static $instance = null;
    protected static $currentDatabaseConnection = ''; // current database connection from general database config, if new database requested, must switch in static instance call
    protected static $currentDatabaseName = ''; // current database name, if new database requested, must switch in static instance call

    /**
     * Musqlidb constructor
     *
     * @throws Exception exception
     * @param array $connectionArray
     * return object
     */
    public function __construct($connectionArray = []) {

        if(is_array($connectionArray)) {

            $this->dbHost = $connectionArray['host'];
            $this->dbPort = is_numeric($connectionArray['port']) ? $connectionArray['port'] : 3306;
            $this->dbName = $connectionArray['database'];
            $this->dbUser = $connectionArray['username'];
            $this->dbPassword = $connectionArray['password'];

            parent::__construct($this->dbHost, $this->dbUser, $this->dbPassword, $this->dbName, $this->dbPort);

            if ($this->connect_errno > 0) {
                sleep(1);
                $this->cntConnection++;
                $err = "Unable to connect to " . $this->dbName . " on " . $this->dbHost . " : '" . $_SERVER['SERVER_NAME'] . "', msg: " . $this->connect_error;
                $this->errorLog($err);
                self::__construct($connectionArray);
            } else {
                Log::info("Connected to " . $this->dbName . " on " . $this->dbHost);
                $this->cntConnection = 0;
            }


        if ($this->cntConnection > 4) {
            $err = "Unable to connect to " . $this->dbName . " on " . $this->dbHost . " : '" . $_SERVER['SERVER_NAME'] . "', msg: " . $this->connect_error;
            $this->errorLog($err);
            throw new Exception($err);
            die();
        }

//        MOVED to setUTF8Uni and setUTF8mb4Uni respectively
//        if(!empty($connectionArray['charset'])) {
//            try {
//                $this->set_charset($connectionArray['charset']);
//                if($connectionArray['collation']) {
//                    $this->query("SET collation_connection = ".$connectionArray['collation']."");
//                }
//            } catch(Exception $E) {
//                $this->errorLog($E->getMessage());
//            }
//        }

        } else {
            throw new Exception('Missing connection array in constructor.');
            die();
        }


    }

    /**
     * resolveBindingPair
     *
     * @param int $key
     * @param string $val
     * @return string
     */
    protected function resolveBindingPair($key, $val) {

        if (strlen($val) == 0) {
            $bind = "`$key` = NULL";
        } else {

            if (strstr($val, '[dbFunction]')) {
                $val = str_replace('[dbFunction]', '', $val);
                $bind = "`$key` = $val";
            } else {
                $val = $this->escape($val);
                $bind = "`$key` = '$val'";
            }
        }

        return $bind;
    }

    /**
     * setUTF8Uni
     */
    public function setUTF8Uni() {
        $this->setUtf8Uni = true;
        $this->setUtf8mb4Uni = false;
        $this->set_charset('utf8');
        $this->run("SET collation_connection = utf8_unicode_ci");
    }

    /**
     * setUTF8mb4Uni
     */
    public function setUTF8mb4Uni() {
        $this->setUtf8mb4Uni = true;
        $this->setUtf8Uni = false;
        $this->set_charset('utf8mb4');
        $this->run("SET collation_connection = utf8mb4_unicode_ci");
    }

    /**
     * useTrackDeleted - use log delete action
     *
     * @param bool $use
     */
    public function useTrackDeleted($use = true) {
        if (is_bool($use))
            $this->trackDeleted = $use;
    }

    /**
     * setPrimaryKey
     *
     * @param int $key
     */
    public function setPrimaryKey($key) {
        if ($this->isValidKey($key)) {
            $this->primaryKey = $key;
        } else {
            $this->primaryKey = '';
        }
    }

    /**
     * getPrimaryKey
     *
     * @return int
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
    }

    /**
     * isValidKey
     *
     * @param int $key
     * @return int
     */
    public function isValidKey($key) {
        if (!empty($key) && is_numeric($key) && $key != 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * getInstanceByConfig - return instance or create new instance by db config
     *
     * @throws Exception exception
     * @param string $databaseConfig
     * @return object
     */
    public static function getInstanceByConfig($databaseConfig) {

        if( empty($databaseConfig) ) {
            throw new Exception('Missing database config.');
            die();
        }


        if ( self::$currentDatabaseConnection != $databaseConfig || !self::$instance || (self::$instance && self::$instance->setUtf8mb4 == true) ) {

            self::$currentDatabaseConnection = $databaseConfig;

            $connectionArray = config("database.connections.".$databaseConfig."");

            if(!is_array($connectionArray)) {
                throw new Exception('Missing database connection properties.');
                die();
            }

            self::$instance = new self($connectionArray);
        }
        self::$instance->setPrimaryKey('');
        return self::$instance;
    }

    /**
     * getInstance - return instance or create new instance with array of connection properties
     *
     * @param array $connectionArray
     * @return object
     */
    public static function getInstance($connectionArray = []) {

        if(!is_array($connectionArray) || count($connectionArray) < 4) die('Missing database connection properties.');

        if (self::$currentDatabaseName != $connectionArray['database']  || !self::$instance  || (self::$instance && self::$instance->setUtf8mb4 == true) ) {

            self::$currentDatabaseName = $connectionArray['database'];
            self::$instance = new self($connectionArray);
        }
        self::$instance->setPrimaryKey('');
        return self::$instance;
    }

    /**
     * Query
     *
     * @throws Exception exception
     * @param string $query
     * @param string $action
     * @param bool $reconnected
     * @return object|bool
     */
    public function run($query, $action = 'select', $reconnected = false) {

        // RESET THIS DATA!!!
        $this->rows = 0;
        $this->data = [];
        $this->response = null;

        if ($reconnected == true) {
            $this->errorLog("Reconnected: Try [$query] query to run again");
        }

        $this->runSQL = $query;
        $this->currentQuery = " | db: ".$this->dbName."; query: " . $query . " |";

        $subQuery = strtolower(substr(trim($query), 0, 6));
        $action = strtolower($action);

        if (strstr($subQuery, 'insert') || $action == 'insert') {
            $action = 'insert';
            if ($this->testStatus == true)
                return true;
        }

        if (strstr($subQuery, 'update') || $action == 'update') {
            $action = 'update';
            if ($this->testStatus == true)
                return true;
        }

        if (strstr($subQuery, 'delete') || $action == 'delete') {
            $action = 'delete';
            if ($this->testStatus == true)
                return true;
        }

        if (strstr($subQuery, 'select') || $action == 'select') {
            $action = 'select';
        }

        if ($this->setUtf8mb4Uni == true) {
            try {
                $this->set_charset("utf8mb4");
                $this->real_query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
            } catch(Exception $E) {
                $this->errorLog($E->getMessage());
            }
        }

        if ($this->setUtf8Uni == true) {
            try {
                $this->set_charset("utf8");
                $this->real_query("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
            } catch(Exception $E) {
                $this->errorLog($E->getMessage());
            }
        }

        $this->real_query($query);

        if ($this->errno > 0) {
            $this->errorLog();
            throw new Exception("Database error: ".$this->errno." | ".$this->error);
        }

        try {
            $enc = $this->get_charset();
            $this->getEnc = $enc->charset . "::" . $enc->collation;
        } catch(Exception $E) {
            //Log::warning($E->getMessage()); // get_charset() added in mysql v5.1 but set_charset added earlier in v5.0.5 - minimum requirement is mysql 5.0.5
        }


        $this->currentQuery .= "error: ".$this->errno."::".$this->error."; affRows: ".$this->affected_rows." |";

        // ** RECONNECT AND RUN THE QUERY - THESE ERRORS OCCURS WHEN THE APPLICATION AND DATABASE ARE ON DIFFERENT SERVERS - TIMEOUT ISSUE !!!
        if ($this->errno == '2006' || $this->errno == '2013') {
            $this->errorLog($query);
            $this->close();
            if (self::__construct()) {
                $this->run($query, $action, true);
            }
        }

//print "A".$action."A<br>";
        if ($action == 'insert') {

            $this->setPrimaryKey($this->insert_id);
        }

        if ($this->errno == 0)
            $this->storeResult();

        if ($this->trackDeleted == true) {
            $this->trackDeleteAction($query, $this->errno, $this->error);
        }

        return $this->response;
    }

    /**
     * storeResult
     */
    public function storeResult() {
        $this->response = $this->store_result();
        if (!is_bool($this->response)) {
            $this->rows = $this->response->num_rows;
            // Compatibility with old code
            $this->data = $this->response->fetch_array(MYSQLI_BOTH);
            $this->response->data_seek(0);
        } else {
            $this->rows = 0;
            $this->data = [];
            $this->response = null;
        }
    }

    /**
     * escape - real escape string
     *
     * @param string $str
     * @return string
     */
    public function escape($str) {
        return $this->real_escape_string($str);
    }

    /**
     * result
     *
     * @param bool $onlyAssociative
     * @param bool $returnObject
     * @return array|object
     */
    public function result($onlyAssociative = false, $returnObject = false) {

        if (!$this->isError() && !is_null($this->response) && !is_bool($this->response)) {

            if ($returnObject == true) {
                return $this->response->fetch_object();
            }

            if ($onlyAssociative == false) {
                $this->data = $this->response->fetch_array(MYSQLI_BOTH);
            } else {
                $this->data = $this->response->fetch_assoc();
            }


            return $this->data;

        } else {
            if($returnObject == true) {
                return new stdClass();
            } else {
                return [];
            }
        }
    }

    /**
     * fill
     *
     * @param bool $onlyAssociative
     * @param bool $returnArrayOfObject
     * @return array|object
     */
    public function fill($onlyAssociative = false, $returnArrayOfObject = false) {
        //print "BF:".memory_get_usage()."<br>";
        $ret = [];

        if (!$this->isError() && !is_null($this->response) && !is_bool($this->response)) {

            $this->response->data_seek(0);

            $e = 0;
            while ($data = $this->result($onlyAssociative, $returnArrayOfObject)) {
                $ret[$e] = $data;
                $e++;
            }
            //$this->response->data_seek(0); NOT NEEDED IF FREE MEMORY
            $this->response->free_result();
            //print "AF:".memory_get_usage()."<br>";
        }
        return $ret;
    }

    /**
     * getLastInsertID
     *
     * @throws Exception exception
     * @param bool $silent
     * @return int
     */
    public function getLastInsertID($silent = false) {

        if ($silent != false) {

            if ($this->isError()) {
                $this->setPrimaryKey('');
            }

            return $this->getPrimaryKey();
        } else {

            try {
                if ($this->isError()) {
                    throw new Exception("Missing z_Primary_Key." . $this->getError(true));
                } else {
                    return $this->getPrimaryKey();
                }
            } catch (Exception $E) {
                die($E->getMessage());
            }
        }
    }

    /**
     * isError
     *
     * @return bool
     */
    public function isError() {
        return ($this->errno == 0) ? false : true;
    }

    /**
     * getError
     *
     * @param bool $format
     * @return string
     */
    public function getError($format = false) {
        if ($format == true) {
            $ret = "Error ID:" . $this->errno . "\n Error message:" . $this->error . "\n SQL: " . $this->runSQL . "\n Encoding: " . $this->getEnc;
            return $ret;
        }
        return "Error ID: ".$this->errno . " :: " . $this->error . " :: " . $this->runSQL . " :: " . $this->getEnc;
    }

    /**
     * getErrorArray
     *
     * @return array
     */
    public function getErrorArray() {
        return array($this->errno, $this->error, $this->runSQL);
    }

    /**
     * @param string $field
     * @param array $data
     * @return array
     */
    public function getKeyArray($field = 'z_PRIMARY_KEY', $data = []) {
        $ret = [];

        if (!is_array($data) || $data == '') {

            $this->response->data_seek(0);

            while ($res = $this->result()) {
                $ret[] = $res["$field"];
            }

            $this->response->data_seek(0);
        } else {

            if (count($data) > 0) {
                foreach ($data as $elem) {
                    $ret[] = $elem["$field"];
                }
            }
        }
        return $ret;
    }

    /**
     * errorLog
     *
     * @param string txt
     */
    public function errorLog($txt = '') {

            if ($txt != '') {
                Log::error($txt);
            } else {
                Log::error($this->errno . "::" . $this->error . "::" . $this->runSQL);
            }

    }

    /**
     * insert
     *
     * @param string $table
     * @param int $zPK
     * @return int
     */
    public function insert($table, $zPK) {

        $this->setPrimaryKey('');

        if ($zPK != '') {

            $this->setPrimaryKey(intval($zPK));

            $sql = "INSERT INTO $table SET `z_PRIMARY_KEY` = " . $this->getPrimaryKey() . "";
            $this->run($sql);

            if ($this->isError() || $this->affected_rows == 0) {
                $this->setPrimaryKey('');
            }
        } else {

            $sql = "INSERT INTO $table VALUES ()";
            $this->run($sql);
            $this->setPrimaryKey($this->getLastInsertID());
        }

        return $this->getPrimaryKey();
    }

    /**
     * update
     *
     * @param string $table
     * @param array $variables
     * @param int $primaryKey
     * @param string $extraArgument
     * @return boolean
     */
    public function update($table, $variables = [], $primaryKey, $extraArgument) {

        $sql = "UPDATE $table SET ";

        $fields = '';

        $a = 0;
        $cnt = count($variables);
        foreach ($variables as $key => $val) {

            $val = trim($val);

            $fields .= $this->resolveBindingPair($key, $val);

            if ($a + 1 != $cnt) {
                $fields .= ", ";
            }
            $a++;
        }


        if (!empty($fields)) {

            if (empty($extraArgument)) {
                $fields .= " WHERE `z_PRIMARY_KEY` = $primaryKey";
            } else {
                $fields .= " WHERE `$extraArgument` = '$primaryKey'";
            }

            $sql .= $fields;

            $this->run($sql);

            return !$this->isError();
        } else {
            $this->errorLog('update CANCELLED - Missing update fields');
            return false;
        }
    }

    /**
     * insertUpdate - do insert with update
     *
     * @param string $table
     * @param array $variables
     * @param string $extraArgument
     * @return boolean
     */
    public function insertUpdate($table, $variables = [], $extraArgument) {

        $primaryKey = $this->insert($table);
        return $this->update($table, $variables, $primaryKey, $extraArgument);
    }

    /**
     * updateOnInsert - insert or update if exists
     * @param string $table
     * @param array $variables
     * @param array $unsetSysFields // i.e. created time and user
     * @return boolean
     */
    public function updateOnInsert($table, $variables = [], $unsetSysFields = []) {

        if(count($variables) == 0) {
            $this->errorLog('updateOnInsert CANCELLED - Missing variables');
            return false;
        }

        $this->setPrimaryKey('');

        $sql = "INSERT INTO $table SET ";

        $fields = '';

        $a = 0;
        $cnt = count($variables);
        foreach ($variables as $key => $val) {

            $val = trim($val);

            $fields .= $this->resolveBindingPair($key, $val);

            if ($a + 1 != $cnt) {
                $fields .= ", ";
            }
            $a++;
        }


        $sql .= $fields;
        $sql .= ' ON DUPLICATE KEY UPDATE ';

        if(is_array($unsetSysFields)) {
            foreach($unsetSysFields as $sf) {
                unset($variables["$sf"]);
            }
        }

        $fields = '';

        $a = 0;
        $cnt = count($variables);
        foreach ($variables as $key => $val) {

            $val = trim($val);

            $fields .= $this->resolveBindingPair($key, $val);

            if ($a + 1 != $cnt) {
                $fields .= ", ";
            }
            $a++;
        }

        $sql .= $fields;

        $this->run($sql, 'insert');
        if(!$this->isError()) {
            $this->setPrimaryKey($this->getLastInsertID());
        }

        return !$this->isError();


    }

    /**
     * create - create an autoincrement record
     *
     * @param string $table
     * @param array $variables
     * @return boolean
     */
    public function create($table, $variables = []) {

        if(count($variables) == 0) {
            $this->errorLog('create CANCELLED - Missing variables');
            return false;
        }

        $this->setPrimaryKey('');

        $sql = "INSERT INTO $table SET ";

        $fields = '';

        $a = 0;
        $cnt = count($variables);
        foreach ($variables as $key => $val) {

            $val = trim($val);

            $fields .= $this->resolveBindingPair($key, $val);

            if ($a + 1 != $cnt) {
                $fields .= ", ";
            }
            $a++;
        }


        if (!empty($fields)) {

            $sql .= $fields;

            $this->run($sql, 'insert');
            if(!$this->isError()) {
                $this->setPrimaryKey($this->getLastInsertID());
            }

            return !$this->isError();
        } else {
            $this->errorLog('create CANCELLED - Missing fields');
            return false;
        }
    }

    /**
     * delete
     *
     * @param string $table
     * @param array $key
     * @param string $where
     * @return bool
     */
    public function delete($table, $key = [], $where = '') {

        $where = $where == '' ? "z_PRIMARY_KEY" : $where;

        if (@is_array($key) && count($key) > 0) {

            $toDel = @implode(",", $key);
            $toDel = $this->escape($toDel);
            $sql = "DELETE FROM $table WHERE $where IN($toDel)";
            $this->run($sql);
        } else {

            if (@!is_array($key) && $key != '') {
                $key = $this->escape($key);
                $sql = "DELETE FROM $table WHERE $where = '$key'";
                $this->run($sql);
            }
        }

        return !$this->isError();
    }

    /**
     * addCondition
     *
     * @param string $query
     * @return string
     */
    public function addCondition($query) {
        return strstr($query, "WHERE") ? " AND " : " WHERE ";
    }

    /**
     * buildCountSQL
     *
     * @param string $mainSql
     * @return string
     */
    public function buildCountSQL($mainSql = '') {

        if ($mainSql == '')
            return $mainSql;

        $exp = @explode("FROM", $mainSql);
        $clean = $exp[1];

        if (strstr($clean, "ORDER BY")) {
            $part = @explode("ORDER BY", $clean);
            $clean = $part[0];
        } elseif (strstr($clean, "LIMIT")) {
            $part = @explode("LIMIT", $clean);
            $clean = $part[0];
        }

        return "SELECT COUNT(*) FROM " . $clean;
    }

    /**
     * getQueryPart
     *
     * @param string $query
     * @return array
     */
    public function getQueryPart($query) {

        $sql = '';
        $rest = '';
        $orderby = '';
        $having = '';

        if (strstr($query, "HAVING")) {
            $expl = @explode("HAVING", $query);
            $query = $expl[0];
            $having = " HAVING " . $expl[1];
            ;
        }

        if (strstr($query, "ORDER BY")) {
            $expl = @explode("ORDER BY", $query);
            $query = $expl[0];
            $orderby = " ORDER BY " . $expl[1];
        }

        if (strstr($query, "WHERE")) {
            $expl = @explode("WHERE", $query);
            $sql = $expl[0];
            $rest = $expl[1];
        } else {
            if (strstr($query, "GROUP BY")) {
                $expl = @explode("GROUP BY", $query);
                $sql = $expl[0];
                $rest = " GROUP BY " . $expl[1];
            } else {
                $sql = $query;
            }
        }

        return array("main" => "$sql", "group" => "$rest", "order" => "$orderby", "having" => "$having");
    }

    /**
     * createPrimaryKey - create primary key
     *
     * @param string $table
     * @return int
     */
    protected function createPrimaryKey($table) {

        $firstNumber = 1;
        $keyOK = 'no';

        while ($keyOK != 'yes') {

            mt_srand((double) microtime() * 1000000);
            $random1 = mt_rand(1000, 9999);
            $random1 = sprintf("%04s", $random1);

            $random2 = mt_rand(10000, 99999);
            $random2 = sprintf("%05s", $random2);

            $value = $firstNumber . $random1 . $random2;

            $sql = "SELECT z_PRIMARY_KEY FROM $table WHERE z_PRIMARY_KEY = '$value'";
            $this->real_query($sql);
            $res = $this->store_result();

            if ($this->errno == 0 && $res->num_rows == 0) {
                $keyOK = 'yes';
            }
        }

        return $value;
    }

    /**
     * trackDeleteAction
     *
     * @param string $query
     * @param int $sqlResult
     * @param string $sqlResultText
     */
    protected function trackDeleteAction($query, $sqlResult, $sqlResultText) {
        Log::info($query,'; '.$sqlResult.'; '.$sqlResultText);
    }

    /**
     * @param string $databaseConfig - database config option
     * @param string $query
     * @param boolean $testStatus
     * @return object
     */
    public static function sql_Run($databaseConfig, $query, $testStatus = false) {

        $db = self::getInstanceByConfig($databaseConfig);
        $db->testStatus = $testStatus;
        $db->Run($query);

        return $db;
    }

    /**
     * sql_Insert
     *
     * @param string $databaseConfig - database config option
     * @param string $table
     * @param int $zPK
     * @param boolean $testStatus
     * @return object
     */
    public static function sql_Insert($databaseConfig, $table, $zPK, $testStatus = false) {

        $db = self::getInstanceByConfig($databaseConfig);
        $db->testStatus = $testStatus;
        $db->Insert($table, $zPK);

        return $db;
    }

    /**
     * sql_Update
     *
     * @param string $databaseConfig - database config option
     * @param string $table
     * @param array $variables
     * @param int $primaryKey
     * @param string $extraArgument
     * @param boolean $testStatus
     * @return object
     */
    public static function sql_Update($databaseConfig, $table, $variables = [], $primaryKey, $extraArgument, $testStatus = false) {

        $db = self::getInstanceByConfig($databaseConfig);
        $db->testStatus = $testStatus;
        $db->Update($table, $variables, $primaryKey, $extraArgument);

        return $db;
    }

    /**
     * sql_InsertUpdate - do insert with update
     *
     * @param string $databaseConfig - database config option
     * @param string $table
     * @param array $variables
     * @param string $extraArgument
     * @param boolean $testStatus
     * @return object
     */
    public static function sql_InsertUpdate($databaseConfig, $table, $variables = [], $extraArgument, $testStatus = false) {

        $db = self::getInstanceByConfig($databaseConfig);
        $db->testStatus = $testStatus;
        $db->InsertUpdate($table, $variables, $extraArgument);

        return $db;
    }


    /**
     * sql_Create - create an autoincrement record
     *
     * @param string $databaseConfig - database config option
     * @param string $table
     * @param array $variables
     * @param boolean $testStatus
     * @return object
     */
    public static function sql_Create($databaseConfig, $table, $variables = [], $testStatus = false) {

        $db = self::getInstanceByConfig($databaseConfig);
        $db->testStatus = $testStatus;
        $db->Create($table, $variables);

        return $db;
    }

    /**
     * sql_Delete
     *
     * @param string $databaseConfig - database config option
     * @param string $table
     * @param array/key $key
     * @param string $where
     * @param boolean $testStatus
     * @return object
     */
    public static function sql_Delete($databaseConfig, $table, $key = [], $where = '', $testStatus = false) {

        $db = self::getInstanceByConfig($databaseConfig);
        $db->testStatus = $testStatus;
        $db->Delete($table, $key, $where);

        return $db;
    }
}
