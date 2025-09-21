<?php
require_once 'config.php';

class Database {
    private $host;
    private $user;
    private $pass;
    private $dbname;
    
    private $dbh;
    private $stmt;
    private $error;
    
    public function __construct() {
        // Usar constantes de configuración
        $this->host = DB_HOST;
        $this->user = DB_USER;
        $this->pass = DB_PASS;
        $this->dbname = DB_NAME;
        
        // Set DSN
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname . ';charset=utf8mb4';
        
        // Set options
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        );
        
        // Create PDO instance
        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
            $this->logError('Database connection failed: ' . $this->error);
            
            // En producción, mostrar mensaje genérico
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
                die('Database connection error. Please contact administrator.');
            } else {
                die('Connection Error: ' . $this->error);
            }
        }
    }
    
    /**
     * Log database errors
     */
    private function logError($message) {
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent('database_error', ['message' => $message]);
        }
        
        $log_file = APP_ROOT . '/logs/database.log';
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }
        
        $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    // Prepare statement with query
    public function query($sql) {
        $this->stmt = $this->dbh->prepare($sql);
    }
    
    // Bind values
    public function bind($param, $value, $type = null) {
        if(is_null($type)) {
            switch(true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        
        $this->stmt->bindValue($param, $value, $type);
    }
    
    // Execute the prepared statement
    public function execute() {
        try {
            return $this->stmt->execute();
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
            $this->logError('Query execution failed: ' . $this->error);
            return false;
        }
    }
    
    // Get result set as array of objects
    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll();
    }
    
    // Get single record as object
    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }
    
    // Get row count
    public function rowCount() {
        return $this->stmt->rowCount();
    }
    
    // Get last insert ID
    public function lastInsertId() {
        return $this->dbh->lastInsertId();
    }
    
    // Transactions
    public function beginTransaction() {
        return $this->dbh->beginTransaction();
    }
    
    public function endTransaction() {
        return $this->dbh->commit();
    }
    
    public function cancelTransaction() {
        return $this->dbh->rollBack();
    }
}