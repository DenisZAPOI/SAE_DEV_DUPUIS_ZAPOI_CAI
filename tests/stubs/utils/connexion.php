<?php
// Remplace Git/utils/connexion.php pendant les tests : SQLite en mémoire, aucune connexion MySQL.
class Connexion {
    protected static PDO $bdd;

    public static function initConnexion() {
        if (!isset(self::$bdd)) {
            self::reinitialiser();
        }
    }

    /** Recrée une base vide avec le schéma de test (tests/schema.sql). */
    public static function reinitialiser(): PDO {
        self::$bdd = new PDO('sqlite::memory:');
        self::$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$bdd->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
        return self::$bdd;
    }

    public static function pdo(): PDO {
        return self::$bdd;
    }
}
