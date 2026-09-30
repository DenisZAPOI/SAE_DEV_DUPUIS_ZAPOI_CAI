<?php
require_once __DIR__ . '/TestCase.php';

/** Tests de structure : syntaxe, routage, secrets, CSRF, cohérence avec docs/API.md. */
class ProjetTest extends TestCase {
    private static function fichiersPhp(): array {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(RACINE . '/Git', FilesystemIterator::SKIP_DOTS));
        $liste = [];
        foreach ($it as $f) {
            if ($f->getExtension() === 'php') {
                $liste[] = $f->getPathname();
            }
        }
        sort($liste);
        return $liste;
    }

    public function testTousLesFichiersPhpOntUneSyntaxeValide(): void {
        $erreurs = [];
        foreach (self::fichiersPhp() as $fichier) {
            exec('php -l ' . escapeshellarg($fichier) . ' 2>&1', $sortie, $code);
            if ($code !== 0) {
                $erreurs[] = $fichier . ' : ' . implode(' ', $sortie);
            }
            $sortie = [];
        }
        $this->assertSame([], $erreurs);
    }

    public function testChaqueRouteDeIndexPointeVersUnModuleExistant(): void {
        $index = file_get_contents(RACINE . '/Git/index.php');
        preg_match_all('#include_once\s+"(modules/[^"]+)"#', $index, $m);
        $this->assertGreaterThanOrEqual(9, count($m[1]));
        foreach ($m[1] as $chemin) {
            $this->assertFileExists(RACINE . '/Git/' . $chemin);
        }
    }

    public function testChaqueModuleDeIndexADesFichiersMvcComplets(): void {
        $index = file_get_contents(RACINE . '/Git/index.php');
        preg_match_all('#include_once\s+"modules/([^/]+)/module_([^"]+)\.php"#', $index, $m, PREG_SET_ORDER);
        foreach ($m as [, $dossier, $nom]) {
            $this->assertFileExists(RACINE . "/Git/modules/$dossier/controleur_$nom.php");
            $this->assertFileExists(RACINE . "/Git/modules/$dossier/vue_$nom.php");
        }
    }

    /** Règle « jamais de secret » : seul connexion.php contient encore des identifiants (dette connue, à externaliser). */
    public function testAucunSecretEnDurHormisConnexionPhp(): void {
        $suspects = [];
        foreach (self::fichiersPhp() as $fichier) {
            $code = file_get_contents($fichier);
            if (preg_match('/\$password\s*=\s*[\'"][^\'"]+[\'"]|ghp_[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}/', $code)) {
                $suspects[] = substr($fichier, strlen(RACINE) + 1);
            }
        }
        $this->assertSame(['Git/utils/connexion.php'], $suspects, 'Nouveau secret en dur, ou dette résolue : mettre ce test à jour.');
    }

    public function testEnvEstIgnoreParGit(): void {
        $this->assertMatchesRegularExpression('/^\.env$/m', file_get_contents(RACINE . '/.gitignore'));
        $this->assertFileExists(RACINE . '/.env.example');
    }

    /** Tout contrôleur qui écrit des données doit vérifier le jeton CSRF ; seul « stock » ne le fait pas encore. */
    public function testControleursEcrivantSansVerificationCsrf(): void {
        $lectureSeule = ['module_acceuil', 'module_accueil', 'module_historique', 'recapJournee'];
        $sansCsrf = [];
        foreach (glob(RACINE . '/Git/modules/*/controleur_*.php') as $fichier) {
            $module = basename(dirname($fichier));
            if (!in_array($module, $lectureSeule, true) && strpos(file_get_contents($fichier), 'check_csrf') === false) {
                $sansCsrf[] = $module;
            }
        }
        $this->assertSame(['module_stock'], $sansCsrf, 'Nouveau contrôleur sans CSRF, ou dette résolue : mettre ce test à jour.');
    }

    /** Chaque action listée dans docs/API.md existe dans le switch du module (et inversement). */
    public function testApiMdEstCoherenteAvecLesModules(): void {
        $doc = RACINE . '/docs/API.md';
        if (!file_exists($doc)) {
            $this->markTestSkipped('docs/API.md absent (PR dédiée non fusionnée).');
        }
        $sections = preg_split('/^## /m', file_get_contents($doc));
        $verifiees = 0;
        foreach ($sections as $section) {
            $module = strtok(strtok($section, "\n"), ' '); // « stock (gestion…) » -> « stock »
            $fichiers = glob(RACINE . "/Git/modules/{module_$module,$module}/module_$module.php", GLOB_BRACE);
            if (!$fichiers) {
                continue;
            }
            $code = file_get_contents($fichiers[0]);
            preg_match_all('/^\| ([^|]*`[^|]*) \|/m', $section, $lignes); // première cellule des lignes d'action
            foreach ($lignes[1] as $cellule) {
                preg_match_all('/`([^`]+)`/', $cellule, $actions);
                foreach ($actions[1] as $action) {
                    $this->assertMatchesRegularExpression('/[\'"]' . preg_quote($action, '/') . '[\'"]/', $code, "Action « $action » du module $module introuvable.");
                    $verifiees++;
                }
            }
        }
        $this->assertGreaterThanOrEqual(45, $verifiees, 'Trop peu d\'actions vérifiées : le format de docs/API.md a-t-il changé ?');
    }
}
