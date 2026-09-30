<?php
require_once __DIR__ . '/TestCase.php';
require_once 'modules/module_connexion/modele_connexion.php';

/** Scénarios inscription / connexion / rôles sur une base SQLite en mémoire. */
class ConnexionModeleTest extends TestCase {
    private Modele_connexion $modele;

    protected function setUp(): void {
        parent::setUp();
        Connexion::reinitialiser();
        $this->modele = new Modele_connexion();
    }

    private function inscrire(string $login, string $mdp): string {
        $_POST = ['login_inscription' => $login, 'mdp_inscription' => $mdp];
        return $this->modele->ajout_formulaire_inscription();
    }

    private function connecter(string $login, string $mdp): string {
        $_POST = ['login_connexion' => $login, 'mdp_connexion' => $mdp];
        return @$this->modele->ajout_formulaire_connexion(); // @ : header() après la sortie de PHPUnit
    }

    public function testInscriptionCreeUnCompteAvecMotDePasseHache(): void {
        $this->assertSame('Inscription réussie !', $this->inscrire('alice', 'secret123'));
        $ligne = Connexion::pdo()->query("SELECT nom, mdp, solde FROM compte")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('alice', $ligne['nom']);
        $this->assertNotSame('secret123', $ligne['mdp']);
        $this->assertTrue(password_verify('secret123', $ligne['mdp']));
        $this->assertEquals(0, $ligne['solde']);
    }

    public function testInscriptionRefuseLesChampsManquants(): void {
        $this->assertSame('Champs manquants', $this->inscrire('alice', ''));
        $this->assertSame('Champs manquants', $this->inscrire('', 'secret123'));
        $this->assertSame(0, (int) Connexion::pdo()->query("SELECT COUNT(*) FROM compte")->fetchColumn());
    }

    public function testInscriptionRefuseUnNomDejaPris(): void {
        $this->inscrire('alice', 'secret123');
        $this->assertStringContainsString('déjà un compte', $this->inscrire('alice', 'autre'));
        $this->assertSame(1, (int) Connexion::pdo()->query("SELECT COUNT(*) FROM compte")->fetchColumn());
    }

    public function testConnexionReussieRemplitLaSession(): void {
        $this->inscrire('alice', 'secret123');
        $this->assertSame('Connexion réussie !', $this->connecter('alice', 'secret123'));
        $this->assertSame('alice', $_SESSION['login']);
        $this->assertTrue($_SESSION['connecté']);
        $this->assertSame(1, (int) $_SESSION['idCompte']);
    }

    public function testConnexionRefuseUnMauvaisMotDePasse(): void {
        $this->inscrire('alice', 'secret123');
        $this->assertSame('Login ou mot de passe incorrect.', $this->connecter('alice', 'faux'));
        $this->assertArrayNotHasKey('connecté', $_SESSION);
    }

    public function testConnexionRefuseUnCompteInconnu(): void {
        $this->assertSame('Login ou mot de passe incorrect.', $this->connecter('personne', 'x'));
        $this->assertArrayNotHasKey('login', $_SESSION);
    }

    public function testInjectionSqlDansLeLoginEstSansEffet(): void {
        $this->inscrire('alice', 'secret123');
        $this->assertSame('Login ou mot de passe incorrect.', $this->connecter("' OR '1'='1", "' OR '1'='1"));
        $this->assertArrayNotHasKey('connecté', $_SESSION);
    }

    public function testNouvelUtilisateurEstClientDansLAsso(): void {
        $_SESSION = ['idCompte' => 7, 'idAsso' => 2, 'login' => 'bob'];
        $this->assertFalse($this->modele->existe(7, 2));
        $this->modele->newUtilisateurClient();
        $this->assertTrue($this->modele->existe(7, 2));
        $this->assertFalse($this->modele->existe(7, 3), 'Le rôle est propre à chaque association');
        $this->assertSame(3, (int) Connexion::pdo()->query("SELECT idRole FROM Utilisateur")->fetchColumn());
    }

    public function testGetRoleDependDeLAssociationChoisie(): void {
        $this->inscrire('alice', 'secret123');
        Connexion::pdo()->exec("INSERT INTO Utilisateur VALUES (1, 1, 10), (1, 3, 20)");
        $_SESSION = ['login' => 'alice', 'idAsso' => 10];
        $this->assertSame(1, (int) $this->modele->getRole());
        $_SESSION['idAsso'] = 20;
        $this->assertSame(3, (int) $this->modele->getRole());
    }

    public function testGetRoleSansSessionNeRenvoieRien(): void {
        $this->assertNull($this->modele->getRole());
    }

    public function testGetAssosListeLesAssociations(): void {
        Connexion::pdo()->exec("INSERT INTO association (nomAsso, siege_social, chemin_logo, tresorerie) VALUES ('BDE', 'Montreuil', 'images/bde.png', 15000)");
        $assos = $this->modele->getAssos();
        $this->assertCount(1, $assos);
        $this->assertSame('BDE', $assos[0]['nomAsso']);
    }
}
