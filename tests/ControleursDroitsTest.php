<?php
require_once __DIR__ . '/TestCase.php';
require_once 'modules/module_solde/controleur_solde.php';
require_once 'modules/module_stock/controleur_stock.php';
require_once 'modules/module_staff/controleur_staff.php';
require_once 'modules/module_commande/controleur_commande.php';

/** Scénarios de droits (rôle) et de jeton CSRF sur les contrôleurs, sans accès à la base. */
class ControleursDroitsTest extends TestCase {
    private const REFUS = 'Droit requis non perçu.';

    public static function rolesSansAccesStock(): array {
        return ['barman' => [2], 'client' => [3]];
    }

    public function testSoldeClientPageReservee(): void {
        $_SESSION['role'] = 1;
        $vue = new Vue_solde();
        (new Cont_solde($vue))->afficher_page_solde();
        $this->assertStringContainsString(self::REFUS, $vue->affiche());
    }

    public function testSoldeAssoRefuseAuClient(): void {
        $_SESSION['role'] = 3;
        $vue = new Vue_solde();
        (new Cont_solde($vue))->afficher_soldeAsso();
        $this->assertStringContainsString(self::REFUS, $vue->affiche());
    }

    public function testSoldeRechargementRefuseSansJeton(): void {
        $_SESSION = ['role' => 3, 'token' => 'abc'];
        $_POST = ['solde' => '50'];
        $vue = new Vue_solde();
        (new Cont_solde($vue))->envoyer_formulaire_solde();
        $this->assertStringContainsString('Token invalide', $vue->affiche());
    }

    public function testSoldeRechargementRefuseAuxNonClientsMemeAvecJeton(): void {
        $this->avecJetonValide();
        $_SESSION['role'] = 2;
        $_POST['solde'] = '50';
        $vue = new Vue_solde();
        (new Cont_solde($vue))->envoyer_formulaire_solde();
        $this->assertStringContainsString(self::REFUS, $vue->affiche());
    }

    /** @dataProvider rolesSansAccesStock */
    public function testStockInterditAuClientEtAuBarman(int $role): void {
        $_SESSION['role'] = $role;
        $vue = new Vue_stock();
        (new Cont_stock($vue))->affiche_stock();
        $this->assertStringContainsString(self::REFUS, $vue->affiche());
    }

    public function testStockRefuseAuClient(): void {
        $_SESSION['role'] = 3;
        $vue = new Vue_stock();
        $cont = new Cont_stock($vue);
        $cont->affiche_stock();
        @$cont->creeInventaire(); // @ : header() après la sortie de PHPUnit
        $cont->afficheProduit();
        $sortie = $vue->affiche();
        $this->assertSame(3, substr_count($sortie, self::REFUS));
    }

    public function testStockModificationDuMenuReserveeAuGestionnaire(): void {
        $_SESSION['role'] = 4;
        $_POST['produit'] = ['1'];
        $vue = new Vue_stock();
        (new Cont_stock($vue))->ajouterNewProduit();
        $this->assertStringContainsString(self::REFUS, $vue->affiche());
    }

    public function testStaffPromotionRefuseeSansJeton(): void {
        $_SESSION = ['role' => 1, 'token' => 'abc'];
        $_POST = ['role' => 2, 'choix' => [5]];
        $vue = new Vue_staff();
        (new Cont_staff($vue))->promoteMembre();
        $this->assertStringContainsString('Token invalide', $vue->affiche());
    }

    public function testStaffPromotionRefuseeAuBarman(): void {
        $this->avecJetonValide();
        $_SESSION['role'] = 2;
        $_POST['role'] = 1;
        $_POST['choix'] = [5];
        $vue = new Vue_staff();
        (new Cont_staff($vue))->promoteMembre();
        $this->assertStringContainsString(self::REFUS, $vue->affiche());
    }

    public function testStaffGestionMembreRefuseeAuClient(): void {
        $_SESSION['role'] = 3;
        $vue = new Vue_staff();
        (new Cont_staff($vue))->gestionMembre();
        $this->assertStringContainsString(self::REFUS, $vue->affiche());
    }

    public function testCommandeFinalisationRefuseeAuClient(): void {
        $_SESSION['role'] = 3;
        $vue = new Vue_commande();
        (new Cont_commande($vue))->finaliserCommande();
        $this->assertStringContainsString(self::REFUS, $vue->affiche());
    }
}
