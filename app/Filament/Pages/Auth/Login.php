<?php

namespace App\Filament\Pages\Auth;

use App\Models\AuditLog;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /** Populated by reCAPTCHA v3 JS before form submission */
    public string $recaptchaToken = '';

    public function authenticate(): ?LoginResponse
    {
        if (config('services.recaptcha.secret')) {
            $this->validateRecaptcha();
        }

        return parent::authenticate();
    }

    private function validateRecaptcha(): void
    {
        if (empty($this->recaptchaToken)) {
            $this->logFallo('token_ausente');
            $this->lanzarError();
        }

        $result = Http::timeout(5)
            ->asForm()
            ->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret'),
                'response' => $this->recaptchaToken,
                'remoteip' => request()->ip(),
            ])
            ->json();

        $score = (float) ($result['score'] ?? 0);

        if (! ($result['success'] ?? false) || $score < 0.5) {
            $this->logFallo($result['error-codes'][0] ?? 'score_bajo', $score);
            $this->lanzarError();
        }
    }

    private function lanzarError(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Sicherheitsüberprüfung fehlgeschlagen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.',
        ]);
    }

    private function logFallo(string $motivo, float $score = 0.0): void
    {
        AuditLog::registrar('RECAPTCHA_FALLO', 'Login', null, null, [
            'ip' => request()->ip(),
            'motivo' => $motivo,
            'score' => $score,
        ], 'bloqueado');
    }
}
