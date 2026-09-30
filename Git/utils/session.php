<?php
/**
 * Gestion de la session (AUTH-01 : fixation de session).
 *
 * - session.use_strict_mode : un identifiant jamais émis par le serveur est refusé.
 * - Changement d'identité (connexion, déconnexion, choix d'association, rôle) :
 *   nouvel identifiant, ancien supprimé aussitôt. À la connexion et à la
 *   déconnexion, le jeton CSRF est aussi renouvelé.
 * - Rotation périodique (toutes les 30 min) et après une action sensible
 *   (commande au barman, achat ou mise en stock) : nouvel identifiant, l'ancien
 *   reste accepté DELAI_GRACE secondes pour ne pas casser les requêtes AJAX
 *   parties en même temps, puis n'ouvre plus qu'une session vide.
 *
 * Le changement d'identité est détecté par le socle, en fin de requête, en comparant
 * la session avant et après le module : aucun module n'a besoin d'appeler cette classe.
 * La vérification a lieu à l'arrêt du script (register_shutdown_function), donc aussi
 * après les `header('Location: …'); exit;` des modules. Elle suppose que la sortie est
 * mise en tampon (ob_start() en tête de index.php) pour que le cookie puisse partir.
 */
class Session {
    /** Durée (s) au-delà de laquelle l'identifiant est renouvelé. */
    public const DUREE_ROTATION = 1800;

    /** Durée (s) pendant laquelle un identifiant renouvelé par rotation reste accepté. */
    public const DELAI_GRACE = 60;

    /** Actions après lesquelles l'identifiant est renouvelé : module => actions. */
    public const ACTIONS_SENSIBLES = [
        'commande' => ['ajout_produit'],
        'restock'  => ['ajoutAchat', 'ajoutStock'],
    ];

    /** Clés de $_SESSION qui définissent qui est connecté et avec quels droits. */
    private const CLES_IDENTITE = ['connecté', 'idCompte', 'idAsso', 'role'];

    /** @var array<string, string> identité au début de la requête */
    private static array $identiteInitiale = [];

    /** Démarre la session ; à appeler une fois, en tête de index.php. */
    public static function demarrer(): void {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        if (isset($_SESSION['_obsolete_depuis'])
            && time() - $_SESSION['_obsolete_depuis'] > self::DELAI_GRACE) {
            // Ancien identifiant présenté après le délai de grâce : on n'en tire rien.
            self::reinitialiser();
        }

        if (empty($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }
        $_SESSION['_cree_le'] ??= time();

        if (!isset($_SESSION['_obsolete_depuis'])
            && time() - $_SESSION['_cree_le'] >= self::DUREE_ROTATION) {
            self::renouvelerAvecGrace();
        }

        self::$identiteInitiale = self::identite();
        register_shutdown_function([self::class, 'terminer']);
    }

    /** Fin de requête : renouvelle l'identifiant si l'identité a changé ou après une action sensible. */
    public static function terminer(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $avant = self::$identiteInitiale;
        $apres = self::identite();

        if ($avant !== $apres) {
            $compteChange = $avant['connecté'] !== $apres['connecté'] || $avant['idCompte'] !== $apres['idCompte'];
            self::renouveler($compteChange);
        } elseif (self::estActionSensible($_GET['module'] ?? '', $_GET['action'] ?? '')) {
            self::renouvelerAvecGrace();
        }
    }

    /**
     * Nouvel identifiant, ancienne session supprimée immédiatement.
     * Le jeton CSRF n'est renouvelé que sur demande : un formulaire peut déjà avoir été
     * affiché avec l'ancien jeton dans la page en cours.
     */
    public static function renouveler(bool $renouvelerJeton): bool {
        if (!session_regenerate_id(true)) {
            error_log('AUTH-01 : session_regenerate_id() a échoué (en-têtes déjà envoyés ?)');
            return false;
        }
        $_SESSION['_cree_le'] = time();
        if ($renouvelerJeton) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }
        return true;
    }

    public static function estActionSensible(string $module, string $action): bool {
        return in_array($action, self::ACTIONS_SENSIBLES[$module] ?? [], true);
    }

    /**
     * Rotation avec délai de grâce (méthode décrite dans la documentation de
     * session_regenerate_id) : l'ancienne session est conservée mais marquée obsolète,
     * la suite de la requête continue dans une nouvelle session qui reprend les données.
     */
    private static function renouvelerAvecGrace(): void {
        if (headers_sent()) {
            error_log('AUTH-01 : rotation de session impossible, en-têtes déjà envoyés');
            return;
        }
        $donnees = $_SESSION;
        $_SESSION['_obsolete_depuis'] = time();
        session_write_close();

        // Le mode strict refuserait l'identifiant tout juste créé : on le suspend le temps de l'adopter.
        $nouvelId = session_create_id();
        ini_set('session.use_strict_mode', '0');
        session_id($nouvelId);
        session_start(); // demarrer() le réactivera à la prochaine requête

        $_SESSION = $donnees;
        $_SESSION['_cree_le'] = time();
    }

    /** Vide la session et repart d'un identifiant neuf. */
    private static function reinitialiser(): void {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    /** @return array<string, string> identité normalisée (le rôle vient tantôt de la BDD en chaîne, tantôt du code en entier) */
    private static function identite(): array {
        $identite = [];
        foreach (self::CLES_IDENTITE as $cle) {
            $identite[$cle] = (string) ($_SESSION[$cle] ?? '');
        }
        return $identite;
    }
}
