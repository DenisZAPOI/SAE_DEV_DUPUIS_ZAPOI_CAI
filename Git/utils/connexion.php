<?php
require_once __DIR__ . '/config.php';

class Connexion {
    protected static PDO $bdd;

    public function __construct() {
    }

    /** Ouvre la connexion avec les identifiants lus dans l'environnement ou le fichier .env (SEC-01). */
    public static function initConnexion() {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            Config::get('DB_HOST'),
            Config::get('DB_PORT'),
            Config::get('DB_NAME')
        );
        self::$bdd = new PDO($dsn, Config::get('DB_USER'), Config::get('DB_PASSWORD'));
    }
}
