<?php

namespace config;

use PDO;
use PDOException;
use MongoDB\Client as MongoClient;

class Database
{
    private static $instance = null;

    private $mysqlConnection;
    private $mongoConnection;

    private $mysqlHost = '127.0.0.1';
    private $mysqlDb = 'basi_di_dati';
    private $mysqlUser = 'root';
    private $mysqlPass = '2002';

    private $mongoHost = '127.0.0.1';
    private $mongoPort = '27017';

    private function __construct()
    {
        $this->connectMySQL();
        $this->connectMongo();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    private function connectMySQL()
    {
        try {
            $dsn = "mysql:host={$this->mysqlHost};dbname={$this->mysqlDb};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->mysqlConnection = new PDO($dsn, $this->mysqlUser, $this->mysqlPass, $options);
        } catch (PDOException $e) {
            die("Errore di connessione MySQL: " . $e->getMessage());
        }
    }

    private function connectMongo()
    {
        try {
            $uri = "mongodb://{$this->mongoHost}:{$this->mongoPort}";
            $client = new MongoClient($uri);

            $this->mongoConnection = $client->centro_medico_logs;
        } catch (\Exception $e) {
            die("Errore di connessione MongoDB: " . $e->getMessage());
        }
    }

    public function getMySQL()
    {
        return $this->mysqlConnection;
    }

    public function getMongo()
    {
        return $this->mongoConnection;
    }
}