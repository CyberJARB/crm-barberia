<?php

namespace Tests\Unit;

use App\Rules\SecurePassword;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * CWE-521 — Weak Password Requirements
 * NIST SP 800-63B — Password policy enforcement
 */
class SecurePasswordRuleTest extends TestCase
{
    private function validate(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => [new SecurePassword]]
        )->passes();
    }

    public function test_acepta_password_fuerte(): void
    {
        $this->assertTrue($this->validate('Test#Clave2026!Seguro'));
    }

    public function test_rechaza_password_corta(): void
    {
        $this->assertFalse($this->validate('Abc@1'));
    }

    public function test_rechaza_sin_mayuscula(): void
    {
        $this->assertFalse($this->validate('admin@2026!seguro'));
    }

    public function test_rechaza_sin_numero(): void
    {
        $this->assertFalse($this->validate('Admin@Seguro!'));
    }

    public function test_rechaza_sin_especial(): void
    {
        $this->assertFalse($this->validate('Admin2026Seguro'));
    }

    public function test_rechaza_contrasena_comun(): void
    {
        $this->assertFalse($this->validate('Password123!'));
    }
}
