<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests de non-régression issus de l'audit du domaine Socle (docs/AUDIT.md).
 * Chaque test affirme le comportement SÉCURISÉ attendu : il échoue tant que la
 * faille n'est pas corrigée, et deviendra vert une fois le correctif appliqué.
 */
class AuditSecuriteTest extends TestCase
{
    private function git(): string { return dirname(__DIR__) . '/Git'; }

    /** SEC-01 : aucun mot de passe en clair dans connexion.php */
    public function testSec01AucunSecretEnClair(): void
    {
        $src = file_get_contents($this->git() . '/utils/connexion.php');
        $this->assertDoesNotMatchRegularExpression(
            "/\\\$password\s*=\s*'[^']+'/",
            $src,
            "SEC-01 : un mot de passe est écrit en clair dans connexion.php"
        );
    }

    /** AUTH-05 : le fichier ne doit émettre aucun octet avant <?php */
    public function testAuth05PasDeSortieAvantPhp(): void
    {
        $octets = file_get_contents($this->git() . '/html_spe_char.php');
        $this->assertStringStartsWith(
            '<?php',
            $octets,
            "AUTH-05 : html_spe_char.php commence par un octet parasite (headers already sent)"
        );
    }

    /** AUTH-01 : le socle doit régénérer l'ID de session au moins une fois */
    public function testAuth01RegenerationSession(): void
    {
        $trouve = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->git()));
        foreach ($it as $f) {
            if ($f->getExtension() === 'php'
                && strpos(file_get_contents($f->getPathname()), 'session_regenerate_id') !== false) {
                $trouve++;
            }
        }
        $this->assertGreaterThan(
            0,
            $trouve,
            "AUTH-01 : session_regenerate_id() n'est appelé nulle part (fixation de session)"
        );
    }

    /** INJ-01 : le DSN doit fixer le jeu de caractères */
    public function testInj01DsnCharset(): void
    {
        // On ne garde que les lignes de code effectives (hors commentaires //).
        $lignes = array_filter(
            file($this->git() . '/utils/connexion.php', FILE_IGNORE_NEW_LINES),
            fn($l) => strpos($l, 'new PDO') !== false && !preg_match('/^\s*\/\//', $l)
        );
        $actif = array_values(array_filter($lignes, fn($l) => strpos($l, 'charset') !== false));
        $this->assertNotEmpty(
            $actif,
            "INJ-01 : le DSN PDO actif ne précise pas charset (émulation des requêtes préparées)"
        );
    }

    /** XSS-01 : un helper d'URL sûre doit exister dans le socle */
    public function testXss01HelperUrlSure(): void
    {
        $src = file_get_contents($this->git() . '/html_spe_char.php');
        $this->assertMatchesRegularExpression(
            '/function\s+(url|safe_url|h_url)\s*\(/',
            $src,
            "XSS-01 : aucun helper d'URL sûre ; h() laisse passer javascript:"
        );
    }
}
