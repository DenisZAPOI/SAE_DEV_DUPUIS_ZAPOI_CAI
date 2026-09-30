<?php
/**
 * Point d'entrée de test pour `php -S` (utilisé par tests/SessionFixationTest.php).
 *
 * Sert le vrai Git/index.php, mais avec la base SQLite en mémoire du stub
 * (tests/stubs/utils/connexion.php) à la place de MySQL. La base étant recréée
 * à chaque requête, on y réinscrit le compte de test « alice » / « secret123 ».
 * Ce fichier n'émet aucune sortie : les en-têtes restent ceux de l'application.
 */
$racine = dirname(__DIR__, 2);
set_include_path($racine . '/tests/stubs' . PATH_SEPARATOR . $racine . '/Git' . PATH_SEPARATOR . get_include_path());
chdir($racine . '/Git');

require_once 'utils/connexion.php'; // le stub : index.php ne rechargera pas la vraie connexion
Connexion::initConnexion();
Connexion::pdo()
    ->prepare("INSERT INTO compte (nom, mdp, solde) VALUES ('alice', ?, 0)")
    ->execute([password_hash('secret123', PASSWORD_DEFAULT)]);

require $racine . '/Git/index.php';
