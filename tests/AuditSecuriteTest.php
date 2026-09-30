<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests de non-régression issus de l'audit du domaine Socle (docs/AUDIT.md).
 * Chaque test affirme le comportement SÉCURISÉ attendu : il échoue tant que la
 * faille n'est pas corrigée. Les tests sont repris de la branche
 * tests/non-regression-audit au fil des PR de correctif.
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
}
