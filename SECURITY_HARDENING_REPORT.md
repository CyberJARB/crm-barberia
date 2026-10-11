# Security Hardening Report — CRM Barberías

**Fecha:** 2026-06-10 · **Alcance:** Hardening de producción sobre auditoría 20/20 previa
**Stack:** Laravel 13.15 · PHP 8.5 · Filament 5.6 · Livewire 4.3 · MySQL
**Validación:** `php -l` OK · `config:cache` OK · 52/52 tests (63 assertions) pasando

---

## 1. Fixes aplicados

### Cabeceras HTTP (`app/Http/Middleware/SecurityHeaders.php`)
| Fix | Referencia |
|---|---|
| `Cross-Origin-Opener-Policy: same-origin` (anti XS-Leaks/Spectre) | OWASP A05 |
| `Cross-Origin-Resource-Policy: same-origin` | CWE-1275 |
| `X-Permitted-Cross-Domain-Policies: none` | OWASP Secure Headers Project |
| `X-XSS-Protection: 0` (recomendación moderna: desactivar auditor legacy) | CWE-79 |
| `Permissions-Policy` ampliada (usb, sensores, midi, fullscreen) | OWASP A05 |
| CSP: añadido `object-src 'none'` y `upgrade-insecure-requests` (prod) | CWE-79 / CWE-319 |
| CSP ahora configurable desde `config/security.php` (toggle `strict_scripts` para futura build CSP de Alpine) | OWASP A05 |
| **CSP corregida para reCAPTCHA v3**: `script-src`/`frame-src`/`connect-src` incluyen dominios de Google — antes la CSP bloqueaba el script externo de reCAPTCHA | OWASP A03 |

### Rate limiting (`bootstrap/app.php`, `AppServiceProvider.php`)
| Fix | Referencia |
|---|---|
| **Bug corregido:** `throttleWithRedis('login')` forzaba el throttler Redis con `CACHE_STORE=database` → el rate limiting habría fallado/petado en producción. Sustituido por `ThrottleRequests` estándar | CWE-1188 |
| Limiter `web` global aplicado a TODO el grupo web: cubre todas las mutaciones POST de Livewire/Filament (`/livewire/update`), no solo login. Lecturas 300/min, escrituras 180/min por usuario/IP | OWASP A07 / CWE-770 |
| Límites de login/api/web centralizados y ajustables por env (`SEC_*`) | OWASP A05 |

### Bug crítico de arranque (`AppServiceProvider.php`)
| Fix | Referencia |
|---|---|
| `validarVariablesEntorno()` usaba `env()` en runtime: con `php artisan config:cache` activo (obligatorio en prod) `env()` devuelve `null` → la app habría abortado con 500 en cada request. Migrado a `config()` | CWE-1188 |
| `DB::prohibitDestructiveCommands()` en producción — bloquea `migrate:fresh`, `db:wipe` | CWE-693 |
| `URL::forceScheme('https')` en producción (defensa en profundidad con `ForceHttps`) | OWASP A02 / CWE-319 |

### Validación de input (Resources de Filament)
| Fix | Referencia |
|---|---|
| `CitaResource`: `notas` maxLength(5000); `total` minValue(0)/maxValue; `hora_fin->after('hora_inicio')` | CWE-1284 / CWE-79 |
| `ServicioResource`: regex en `nombre`/`categoria`; `duracion` 5–480 min; `precio` 0–99.999,99 €; `descripcion` maxLength(2000) | CWE-20 / CWE-1284 |
| `PersonalResource`: regex de nombre y de teléfono | CWE-20 |
| `PagoResource`: `monto` 0,01–99.999,99 € (sin negativos); `referencia` regex alfanumérico; `notas` maxLength(2000) | CWE-1284 |
| `ClienteResource`: regex de teléfono | CWE-20 |

### Autorización RBAC (OWASP A01)
| Fix | Referencia |
|---|---|
| `AuditLogResource::canViewAny()/canView()` — antes solo se ocultaba la navegación: cualquier usuario autenticado podía acceder a `/admin/audit-logs` por URL directa | OWASP A01 |
| `PagoResource::canDelete()/canDeleteAny() = false` — registros financieros inmutables | GoBD / GDPR Art. 5(1)(f) |
| `DeleteAction`/`DeleteBulkAction` visibles solo para admin/propietario en Citas, Servicios y Personal (consistente con Clientes y refuerzo de las Policies) | OWASP A01 |
| Exportación GDPR de cliente restringida a admin/propietario | GDPR Art. 32 |

### Jobs de cola (`app/Jobs/*`)
| Fix | Referencia |
|---|---|
| `ShouldBeEncrypted` en los 3 jobs de email — el payload en la tabla `jobs` queda cifrado | GDPR Art. 32 / CWE-312 |
| `$timeout = 60` — evita workers colgados por SMTP lento (DoS de cola) | CWE-400 |

### Sesión y cookies (`config/session.php`)
| Fix | Referencia |
|---|---|
| `secure` por defecto `true` cuando `APP_ENV=production` (antes: null si faltaba la env var) | CWE-614 |
| `encrypt` por defecto `true` | CWE-312 |

### Information disclosure
| Fix | Referencia |
|---|---|
| Páginas de error genéricas en alemán: 403, 404, 419, 429, 500, 503 (`resources/views/errors/`) — sin firma del framework ni stack traces | OWASP A05 / CWE-209 |
| Ruta `/` redirige a `/admin` — eliminada la welcome page de Laravel (fingerprinting de versión) | CWE-200 |

### Dependencias y secretos
| Fix | Referencia |
|---|---|
| `composer.json`: `filament/filament` fijado a `^5.6` (antes `*` — riesgo de supply chain / major upgrade accidental) | OWASP A06 |
| `.gitignore`: añadidos `/storage/app/gdpr/`, `/storage/backups/`, `*.sql`, `*.sqlite*`, `.env.local`, `.env.*.local` | GDPR Art. 32 |
| `.env.example` verificado: sin secretos reales ✔ | OWASP A05 |
| Creado `.env.production.example` con valores seguros comentados | OWASP A05 |

### Configuración centralizada
| Fix | Referencia |
|---|---|
| Nuevo `config/security.php`: rate limits, lockout, política de contraseñas, CSP, límites de input, retención de logs — todo ajustable por env sin tocar código | ISO 27001 A.14 |
| Account lockout del modelo `User` lee ahora de `config/security.php` | OWASP A07 |

---

## 2. Checklist de producción (antes del deploy)

### Aplicación
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`
- [ ] `php artisan key:generate` en el servidor (nunca reutilizar la APP_KEY de dev)
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `php artisan event:cache && php artisan icons:cache` (Filament)
- [ ] `composer install --no-dev --optimize-autoloader` (**sin** dev-dependencies: tinker/pail/faker no deben estar en prod)
- [ ] `npm run build` y desplegar solo `public/build`
- [ ] `php artisan storage:link` NO necesario (no se usa disco public para datos)
- [ ] Verificar `RECAPTCHA_*`, `STRIPE_WEBHOOK_SECRET` configurados

### Base de datos
- [ ] Usuario MySQL dedicado, permisos mínimos: `SELECT, INSERT, UPDATE, DELETE` (sin `DROP/FILE/SUPER/GRANT`)
- [ ] MySQL escuchando solo en localhost/red privada, TLS si es red compartida
- [ ] Backups cifrados verificados (`seguridad:backup` ya programado a las 02:00) + **test de restore**

### Infraestructura (no automatizable desde el código)
- [ ] TLS ≥ 1.2 (ideal 1.3), certificado vía Let's Encrypt/ACME con renovación automática
- [ ] Servidor web: ocultar versión (`server_tokens off;` en nginx / `ServerSignature Off` en Apache)
- [ ] `client_max_body_size`/`LimitRequestBody` ≤ 10 MB
- [ ] PHP-FPM: `expose_php=Off`, `display_errors=Off`, `open_basedir` al directorio de la app
- [ ] Document root apuntando a `public/` exclusivamente
- [ ] Firewall: solo 80/443 públicos; SSH con clave + fail2ban
- [ ] Queue worker como servicio systemd con usuario sin privilegios: `php artisan queue:work --max-time=3600 --tries=3`
- [ ] Cron: `* * * * * php artisan schedule:run` (usuario de la app, no root)
- [ ] Logs externos/centralizados si hay más de un servidor (NIS2)

### Rotación de APP_KEY (guía)
La APP_KEY cifra: sesiones, cookies, campos `HasEncryptedFields`, `smtp_password` de tenants y payloads de jobs. **No rotar sin plan:**
1. Programar ventana de mantenimiento (`php artisan down`)
2. Re-cifrar todos los campos `Crypt`/`encrypted` con la clave nueva (script de migración: descifrar con clave vieja vía `APP_PREVIOUS_KEYS`, cifrar con la nueva)
3. Laravel ≥ 11 soporta `APP_PREVIOUS_KEYS` para rotación gradual de sesiones/cookies
4. Invalidar sesiones activas y vaciar la cola antes de rotar

---

## 3. Riesgos residuales / no automatizables

| Riesgo | Mitigación pendiente |
|---|---|
| CSP usa `unsafe-inline`/`unsafe-eval` en `script-src` (requisito de Livewire/Alpine) | Cuando se adopte la build CSP de Alpine, activar `SEC_CSP_STRICT_SCRIPTS=true`. El riesgo XSS está mitigado por el escapado de Blade (no hay `{!! !!}` en ninguna vista — verificado) y los maxLength/regex de inputs |
| `X-Frame-Options`/`frame-ancestors` en `SAMEORIGIN`/`'self'` | Correcto para Filament; no reducir |
| Rate limiting con store `database` añade 1 query/request | Si hay carga alta, migrar `CACHE_STORE=redis` y entonces sí activar `throttleWithRedis()` |
| El honeypot y reCAPTCHA dependen de configuración externa | Verificar claves reCAPTCHA reales antes del go-live |
| Seguridad física/SO del servidor, parches del kernel, DSGVO organizativo (AVV/DPA con hosting alemán o UE, registro de actividades de tratamiento Art. 30) | Procedimiento operativo, fuera del alcance del código |
| `composer audit` debe ejecutarse en cada deploy (ya hay SBOM + ZAP en CI) | Añadir `composer audit --locked` como gate del pipeline si aún no lo es |

---

## 4. Archivos modificados/creados

**Modificados:** `app/Http/Middleware/SecurityHeaders.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `config/session.php`, `composer.json`, `.gitignore`, `routes/web.php`, `app/Models/User.php`, `app/Filament/Resources/{Cliente,Cita,Servicio,Personal,Pago,AuditLog}Resource.php`, `app/Jobs/{EnviarConfirmacionCita,EnviarRecordatorioCita,EnviarFollowUpCita}.php`

**Creados:** `config/security.php`, `.env.production.example`, `resources/views/errors/{layout,403,404,419,429,500,503}.blade.php`
