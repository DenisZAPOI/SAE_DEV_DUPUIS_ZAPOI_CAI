<?php
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {
    protected function setUp(): void {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    /** Session avec jeton CSRF valide + POST portant ce même jeton. */
    protected function avecJetonValide(): void {
        $_SESSION['token'] = 'jeton-de-test';
        $_POST['token_csrf'] = 'jeton-de-test';
    }
}
