<?php
// Les fichiers de l'application utilisent des include relatifs à Git/ : on place nos stubs en premier.
define('RACINE', dirname(__DIR__));
set_include_path(__DIR__ . '/stubs' . PATH_SEPARATOR . RACINE . '/Git' . PATH_SEPARATOR . get_include_path());

require_once 'html_spe_char.php';
require_once 'utils/token_csrf.php';
