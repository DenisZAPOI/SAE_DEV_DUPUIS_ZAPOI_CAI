<?php
use PHPUnit\Framework\TestCase;

/**
 * AUTH-01 — fixation de session, testée de bout en bout.
 *
 * Le vrai Git/index.php est servi par `php -S` (tests/fixtures/serveur_app.php,
 * base SQLite du stub, compte « alice » / « secret123 »), et les requêtes HTTP
 * rejouent l'attaque : l'attaquant obtient un identifiant de session valide,
 * l'impose à la victime, la victime se connecte, puis l'attaquant réutilise
 * cet identifiant.
 */
class SessionFixationTest extends TestCase
{
    private const LOGIN = 'alice';
    private const MDP = 'secret123';

    /** @var resource|null */
    private static $serveur = null;
    private static string $base = '';
    private static string $dossierSessions = '';

    public static function setUpBeforeClass(): void
    {
        self::$dossierSessions = sys_get_temp_dir() . '/sae_sessions_' . bin2hex(random_bytes(4));
        mkdir(self::$dossierSessions);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $commande = [
            PHP_BINARY, '-d', 'session.save_path=' . self::$dossierSessions,
            '-d', 'display_errors=0', '-d', 'log_errors=0',
            '-S', "127.0.0.1:$port", dirname(__DIR__) . '/tests/fixtures/serveur_app.php',
        ];
        self::$serveur = proc_open($commande, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $tubes);
        self::$base = "http://127.0.0.1:$port/index.php";

        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', $port)) {
                return;
            }
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serveur) {
            proc_terminate(self::$serveur);
            proc_close(self::$serveur);
        }
        array_map('unlink', glob(self::$dossierSessions . '/*') ?: []);
        @rmdir(self::$dossierSessions);
    }

    protected function setUp(): void
    {
        if (!@file_get_contents(self::$base . '?module=connexion&action=connexion')) {
            $this->markTestSkipped('Serveur PHP intégré indisponible.');
        }
    }

    /**
     * Envoie une requête et retourne [corps, identifiant de session reçu dans Set-Cookie ou null].
     */
    private function requete(string $query, ?string $idSession, array $post = []): array
    {
        $entetes = $idSession !== null ? "Cookie: PHPSESSID=$idSession\r\n" : '';
        $options = ['method' => 'GET', 'header' => $entetes, 'follow_location' => 0, 'ignore_errors' => true];
        if ($post) {
            $options['method'] = 'POST';
            $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $options['content'] = http_build_query($post);
        }
        $corps = file_get_contents(self::$base . '?' . $query, false, stream_context_create(['http' => $options]));

        $nouvelId = null;
        foreach ($http_response_header as $ligne) {
            if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]*)/i', $ligne, $m) && $m[1] !== 'deleted') {
                $nouvelId = $m[1]; // le dernier Set-Cookie l'emporte, comme dans un navigateur
            }
        }
        return [$corps, $nouvelId];
    }

    private function jeton(string $corps): string
    {
        $this->assertMatchesRegularExpression('/name="token_csrf" value = "([0-9a-f]+)"/', $corps, 'Formulaire sans jeton CSRF');
        preg_match('/name="token_csrf" value = "([0-9a-f]+)"/', $corps, $m);
        return $m[1];
    }

    private function afficheFormulaireConnexion(string $corps): bool
    {
        return strpos($corps, 'action=ajout_connexion') !== false;
    }

    /** L'identifiant fixé avant la connexion ne doit plus donner accès à la session authentifiée. */
    public function testAuth01LaConnexionChangeLIdentifiantDeSession(): void
    {
        // 1. L'attaquant obtient un identifiant valide auprès du serveur…
        [$corps, $idFixe] = $this->requete('module=connexion&action=connexion', null);
        $this->assertNotNull($idFixe, 'Le serveur aurait dû créer une session');
        $jetonAvant = $this->jeton($corps);

        // 2. … l'impose à la victime, qui se connecte avec.
        [, $idRecu] = $this->requete('module=connexion&action=ajout_connexion', $idFixe, [
            'login_connexion' => self::LOGIN, 'mdp_connexion' => self::MDP, 'token_csrf' => $jetonAvant,
        ]);
        $idVictime = $idRecu ?? $idFixe;
        $this->assertNotSame($idFixe, $idVictime, "AUTH-01 : l'identifiant de session n'a pas changé à la connexion");

        // 3. La victime reste connectée avec son nouvel identifiant.
        [$corps] = $this->requete('module=connexion', $idVictime);
        $this->assertFalse($this->afficheFormulaireConnexion($corps), 'La victime devrait rester connectée');

        // 4. L'attaquant, avec l'ancien identifiant, n'est pas connecté.
        [$corps] = $this->requete('module=connexion', $idFixe);
        $this->assertTrue($this->afficheFormulaireConnexion($corps), "AUTH-01 : l'attaquant partage la session authentifiée");

        // 5. Le jeton CSRF connu de l'attaquant n'est plus valide pour la victime.
        [$corps] = $this->requete('module=connexion&action=inscription', $idVictime);
        $this->assertNotSame($jetonAvant, $this->jeton($corps), 'AUTH-01 : le jeton CSRF doit changer à la connexion');
    }

    /** Un identifiant inventé par l'attaquant (jamais émis par le serveur) est refusé : session.use_strict_mode. */
    public function testAuth01UnIdentifiantInconnuEstRemplace(): void
    {
        $idInvente = 'idinventeparlattaquant0123456789';
        [, $idRecu] = $this->requete('module=connexion&action=connexion', $idInvente);
        $this->assertNotNull($idRecu, "AUTH-01 : l'identifiant inventé est accepté (use_strict_mode désactivé)");
        $this->assertNotSame($idInvente, $idRecu);
    }

    /** La déconnexion ne doit pas conserver l'identifiant de la session authentifiée. */
    public function testAuth01LaDeconnexionChangeLIdentifiant(): void
    {
        [$corps, $id] = $this->requete('module=connexion&action=connexion', null);
        [, $idRecu] = $this->requete('module=connexion&action=ajout_connexion', $id, [
            'login_connexion' => self::LOGIN, 'mdp_connexion' => self::MDP, 'token_csrf' => $this->jeton($corps),
        ]);
        $idConnecte = $idRecu ?? $id;

        [, $idApres] = $this->requete('module=connexion&action=deconnexion', $idConnecte);
        $this->assertNotNull($idApres);
        $this->assertNotSame($idConnecte, $idApres, "AUTH-01 : la déconnexion garde le même identifiant");
    }
}
