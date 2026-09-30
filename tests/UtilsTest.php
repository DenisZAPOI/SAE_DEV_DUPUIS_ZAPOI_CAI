<?php
require_once __DIR__ . '/TestCase.php';
require_once 'utils/vue_generique.php';

class UtilsTest extends TestCase {
    public function testCsrfAccepteLeBonJeton(): void {
        $this->avecJetonValide();
        $this->assertTrue((new Token_CSRF())->check_csrf());
    }

    public function testCsrfRefuseUnJetonAbsent(): void {
        $_SESSION['token'] = 'jeton-de-test';
        $this->assertFalse((new Token_CSRF())->check_csrf());
    }

    public function testCsrfRefuseUnMauvaisJeton(): void {
        $_SESSION['token'] = 'jeton-de-test';
        $_POST['token_csrf'] = 'autre';
        $this->assertFalse((new Token_CSRF())->check_csrf());
    }

    public function testCsrfRefuseUnJetonVide(): void {
        $_SESSION['token'] = '';
        $_POST['token_csrf'] = '';
        $this->assertFalse((new Token_CSRF())->check_csrf());
    }

    public function testHEchappeLeHtmlEtLesGuillemets(): void {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', h('<script>alert("x")</script>'));
        $this->assertSame('l&#039;asso', h("l'asso"));
    }

    public function testHAccepteNullEtNombres(): void {
        $this->assertSame('', h(null));
        $this->assertSame('42', h(42));
    }

    public function testVueGeneriqueCaptureLaSortie(): void {
        $vue = new VueGenerique();
        echo '<p>bonjour</p>';
        $this->assertSame('<p>bonjour</p>', $vue->getAffichage());
    }
}
