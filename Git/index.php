<?php
// Toute la page est mise en tampon : les en-têtes (cookie de session renouvelé,
// redirections) peuvent être envoyés jusqu'à la fin de la requête.
ob_start();

require_once "utils/session.php";
Session::demarrer(); // AUTH-01 : mode strict, jeton CSRF, renouvellement de l'identifiant

require_once "html_spe_char.php";
require_once "utils/token_csrf.php";





$module = $_GET['module'] ?? 'accueil';
switch($module) {
    case 'connexion':
        include_once "modules/module_connexion/module_connexion.php";
        $mod = new Mod_connexion();
        $moduleContent = $mod->affiche();
        break;
    case 'solde':
        include_once "modules/module_solde/module_solde.php";
        $mod = new Mod_solde();
        $moduleContent = $mod->affiche();
        break;
    case 'accueil':
        include_once "modules/module_accueil/module_accueil.php";
        $mod = new Mod_accueil();
        $moduleContent = $mod->affiche();
        break;
    case 'stock':
        include_once "modules/module_stock/module_stock.php";
        $mod = new Mod_stock();
        $moduleContent = $mod->affiche();
        break;
    case 'recapJournee':
        include_once "modules/recapJournee/module_recapJournee.php";
        $mod = new module_recapJournee();
        $moduleContent=$mod->affiche();
        break;
    case 'commande':
	    include_once "modules/module_commande/module_commande.php";
	    $mod = new Mod_commande();
	    $moduleContent=$mod->affiche();
	    break;
	case 'historique':
        include_once "modules/module_historique/module_historique.php";
        $mod = new Mod_historique();
        $moduleContent=$mod->affiche();
        break;
    case 'restock':
        include_once "modules/module_restock/module_restock.php";
        $mod = new Mod_restock();
        $moduleContent=$mod->affiche();
        break;
    case "staff":
        include_once "modules/module_staff/module_staff.php";
        $mod = new Module_staff();
        $moduleContent=$mod->affiche();
        break;
    default:
        echo "<p>Module inconnu.</p>";
        break;
}


include_once "template.php";

?>
