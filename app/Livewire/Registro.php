<?php

namespace App\Livewire;

use App\Mail\BienvenidaMail;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\SecurePassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Registro extends Component
{
    public int $paso = 1;

    // === Paso 1: Datos de la barbería ===
    #[Validate]
    public string $nombre_tienda = '';

    #[Validate]
    public string $tipo = 'barberia';

    #[Validate]
    public string $ciudad = '';

    #[Validate]
    public string $telefono_tienda = '';

    #[Validate]
    public string $email_tienda = '';

    // === Paso 2: Cuenta del propietario ===
    #[Validate]
    public string $nombre_usuario = '';

    #[Validate]
    public string $email_usuario = '';

    #[Validate]
    public string $password = '';

    #[Validate]
    public string $password_confirmation = '';

    public bool $acepta_terminos = false;

    protected function rules(): array
    {
        return [
            'nombre_tienda' => ['required', 'string', 'min:2', 'max:100'],
            'tipo' => ['required', 'in:barberia,salon,unas,spa,otro'],
            'ciudad' => ['required', 'string', 'min:2', 'max:100'],
            'telefono_tienda' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s+\-()\/]+$/'],
            'email_tienda' => ['nullable', 'email', 'max:255'],
            'nombre_usuario' => ['required', 'string', 'min:2', 'max:100'],
            'email_usuario' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:12', new SecurePassword],
            'password_confirmation' => ['required'],
            'acepta_terminos' => ['accepted'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre_tienda.required' => 'Der Name des Geschäfts ist erforderlich.',
            'nombre_tienda.min' => 'Der Name muss mindestens 2 Zeichen haben.',
            'tipo.required' => 'Bitte wählen Sie einen Typ.',
            'ciudad.required' => 'Die Stadt ist erforderlich.',
            'telefono_tienda.regex' => 'Bitte geben Sie eine gültige Telefonnummer ein.',
            'email_tienda.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'nombre_usuario.required' => 'Ihr Name ist erforderlich.',
            'email_usuario.required' => 'Ihre E-Mail-Adresse ist erforderlich.',
            'email_usuario.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'email_usuario.unique' => 'Diese E-Mail-Adresse ist bereits registriert.',
            'password.required' => 'Ein Passwort ist erforderlich.',
            'password.min' => 'Das Passwort muss mindestens 12 Zeichen haben.',
            'password.confirmed' => 'Die Passwörter stimmen nicht überein.',
            'acepta_terminos.accepted' => 'Sie müssen die Nutzungsbedingungen akzeptieren.',
        ];
    }

    public function siguientePaso(): void
    {
        if ($this->paso === 1) {
            $this->validateOnly('nombre_tienda');
            $this->validateOnly('tipo');
            $this->validateOnly('ciudad');
            if ($this->telefono_tienda) {
                $this->validateOnly('telefono_tienda');
            }
            if ($this->email_tienda) {
                $this->validateOnly('email_tienda');
            }
        }

        $this->paso++;
    }

    public function pasoAnterior(): void
    {
        $this->paso = max(1, $this->paso - 1);
    }

    public function registrar(): void
    {
        $this->validate();

        // OWASP A03 — todo en una transacción para consistencia
        DB::transaction(function () {
            $tenant = Tenant::create([
                'nombre' => strip_tags(trim($this->nombre_tienda)),
                'tipo' => $this->tipo,
                'slug' => $this->generarSlug($this->nombre_tienda),
                'ciudad' => strip_tags(trim($this->ciudad)),
                'telefono' => $this->telefono_tienda ?: null,
                'email' => $this->email_tienda ?: null,
                'pais' => 'DE',
                'moneda' => 'EUR',
                'zona_horaria' => 'Europe/Berlin',
                'plan' => 'basico',
                'trial_ends_at' => now()->addDays(14),
                'activo' => true,
            ]);

            $user = User::create([
                'name' => strip_tags(trim($this->nombre_usuario)),
                'email' => strtolower(trim($this->email_usuario)),
                'password' => Hash::make($this->password),
                'tenant_id' => $tenant->id,
                'rol' => 'propietario',
                'activo' => true,
                'debe_cambiar_password' => false,
            ]);

            // Enviar email de bienvenida (DSGVO Art.6(1)(b) — ejecución del contrato)
            try {
                Mail::to($user->email, $user->name)->send(new BienvenidaMail($tenant, $user));
            } catch (\Throwable) {
                // El fallo de email no cancela el registro
            }

            Auth::login($user, remember: false);
        });

        $this->redirect('/admin', navigate: false);
    }

    private function generarSlug(string $nombre): string
    {
        $base = Str::slug($nombre);
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function render(): View
    {
        return view('livewire.registro')
            ->layout('layouts.publico', ['title' => '14 Tage kostenlos testen — CRM Barberías']);
    }
}
