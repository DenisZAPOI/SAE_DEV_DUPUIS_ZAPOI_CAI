<?php
/**
 * Lecture de la configuration de l'application (SEC-01).
 *
 * Ordre de priorité pour chaque clé :
 *   1. variable d'environnement du serveur (getenv), utile en production ;
 *   2. fichier .env à la racine du projet, hors de la racine web Git/,
 *      donc jamais servi par le serveur HTTP. Ce fichier est ignoré par Git
 *      (voir .gitignore) ; .env.example documente les clés attendues.
 *
 * Aucune valeur par défaut n'est fournie pour les secrets : une clé absente
 * provoque une erreur explicite plutôt qu'un repli sur un identifiant codé en dur.
 */
class Config {
    /** @var array<string, string>|null valeurs lues dans le fichier .env */
    private static ?array $valeurs = null;

    /** Charge (ou recharge) le fichier .env ; un fichier absent donne une configuration vide. */
    public static function charger(?string $fichier = null): void {
        $fichier ??= dirname(__DIR__, 2) . '/.env';
        self::$valeurs = is_readable($fichier) ? self::lireFichier($fichier) : [];
    }

    /** Retourne la valeur de $cle ; lève une RuntimeException si elle n'est définie nulle part. */
    public static function get(string $cle): string {
        $env = getenv($cle);
        if ($env !== false) {
            return $env;
        }
        if (self::$valeurs === null) {
            self::charger();
        }
        if (!array_key_exists($cle, self::$valeurs)) {
            // Le message nomme la clé, jamais une valeur.
            throw new RuntimeException("Configuration manquante : $cle (copier .env.example en .env et le renseigner).");
        }
        return self::$valeurs[$cle];
    }

    /** Format accepté : CLE=valeur, lignes vides et commentaires « # » ignorés, guillemets englobants retirés. */
    private static function lireFichier(string $fichier): array {
        $valeurs = [];
        foreach (file($fichier, FILE_IGNORE_NEW_LINES) as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '' || $ligne[0] === '#' || strpos($ligne, '=') === false) {
                continue;
            }
            [$cle, $valeur] = explode('=', $ligne, 2);
            $cle = trim($cle);
            $valeur = trim($valeur);
            if (strlen($valeur) >= 2 && ($valeur[0] === '"' || $valeur[0] === "'") && substr($valeur, -1) === $valeur[0]) {
                $valeur = substr($valeur, 1, -1);
            }
            $valeurs[$cle] = $valeur;
        }
        return $valeurs;
    }
}
