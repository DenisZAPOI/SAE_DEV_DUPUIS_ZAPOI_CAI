<?php
require_once __DIR__ . '/TestCase.php';
require_once RACINE . '/Git/utils/config.php';

/** SEC-01 : lecture des identifiants depuis .env / l'environnement, sans valeur par défaut. */
class ConfigTest extends TestCase {
    private string $fichier;

    protected function setUp(): void {
        parent::setUp();
        $this->fichier = tempnam(sys_get_temp_dir(), 'env');
    }

    protected function tearDown(): void {
        @unlink($this->fichier);
        putenv('SAE_TEST_CLE');
        Config::charger(); // revient au .env réel du projet (ou à rien)
    }

    private function env(string $contenu): void {
        file_put_contents($this->fichier, $contenu);
        Config::charger($this->fichier);
    }

    public function testLitLesClesDuFichierEnv(): void {
        $this->env("# commentaire\n\nSAE_TEST_CLE=valeur\nSAE_TEST_VIDE=\n");
        $this->assertSame('valeur', Config::get('SAE_TEST_CLE'));
        $this->assertSame('', Config::get('SAE_TEST_VIDE'), 'Un mot de passe vide (root local) doit rester possible.');
    }

    public function testRetireLesGuillemetsEtGardeLesEgalDansLaValeur(): void {
        $this->env("SAE_TEST_CLE=\"a=b#c\"\n");
        $this->assertSame('a=b#c', Config::get('SAE_TEST_CLE'));
    }

    public function testLaVariableDEnvironnementPrimeSurLeFichier(): void {
        $this->env("SAE_TEST_CLE=fichier\n");
        putenv('SAE_TEST_CLE=serveur');
        $this->assertSame('serveur', Config::get('SAE_TEST_CLE'));
    }

    public function testCleAbsenteLeveUneErreurSansRepliCodeEnDur(): void {
        $this->env("AUTRE=1\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SAE_TEST_ABSENTE');
        Config::get('SAE_TEST_ABSENTE');
    }

    public function testFichierEnvAbsentDonneUneConfigurationVide(): void {
        Config::charger('/chemin/inexistant/.env');
        $this->expectException(RuntimeException::class);
        Config::get('SAE_TEST_ABSENTE');
    }

    public function testEnvExampleDeclareToutesLesClesLuesParConnexion(): void {
        $connexion = file_get_contents(RACINE . '/Git/utils/connexion.php');
        preg_match_all("/Config::get\('([A-Z_]+)'\)/", $connexion, $m);
        $this->assertNotEmpty($m[1], 'connexion.php doit lire ses identifiants via Config::get().');
        Config::charger(RACINE . '/.env.example');
        foreach ($m[1] as $cle) {
            $this->assertNotNull(Config::get($cle), "Clé $cle absente de .env.example");
        }
    }
}
