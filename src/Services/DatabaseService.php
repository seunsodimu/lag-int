<?php

namespace Laguna\Integration\Services;

use PDO;
use PDOException;
use Laguna\Integration\Utils\Logger;

/**
 * Database Service
 * 
 * Provides centralized database connection management
 */
class DatabaseService {
    private static $instance = null;
    private $pdo;
    private $logger;
    private $config;
    
    private function __construct() {
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->initializeDatabase();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase() {
        try {
            $dbConfig = $this->config['database'];
            
            if (!$dbConfig['enabled']) {
                throw new \Exception('Database is not enabled in configuration');
            }
            
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
        } catch (PDOException $e) {
            $this->logger->error('Failed to connect to database', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Acquire a named lock
     */
    public function acquireLock($lockName, $timeout = 10) {
        $stmt = $this->pdo->prepare("SELECT GET_LOCK(?, ?)");
        $stmt->execute([$lockName, $timeout]);
        return (bool)$stmt->fetchColumn();
    }
    
    /**
     * Release a named lock
     */
    public function releaseLock($lockName) {
        $stmt = $this->pdo->prepare("SELECT RELEASE_LOCK(?)");
        $stmt->execute([$lockName]);
        return (bool)$stmt->fetchColumn();
    }
}
