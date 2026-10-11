# 🩺 Diagnóstico y Estado del Proyecto — CRM Barberías / CyberJARB

> **Documento vivo.** Registra el estado de salud del proyecto y un changelog de cambios.
> Regla: **antes de tocar el código se consulta este archivo; después de cada cambio se actualiza aquí.**
> Así no hay que re-diagnosticar desde cero cada vez.

---

## 1. Estado de salud (último diagnóstico)

| Métrica | Resultado | Fecha |
|---|---|---|
| **Tests (PHPUnit)** | ✅ 209/209 (479 assertions) | 2026-06-14 |
| **Análisis estático (PHPStan/Larastan)** | ✅ 0 errores | 2026-06-14 |
| **Estilo (Pint)** | ✅ Limpio (formateado) | 2026-06-14 |
| **Vulnerabilidades deps (composer audit)** | ✅ 0 advisories | 2026-06-14 |
| **Entorno** | PHP 8.5.7 · Composer 2.9.5 · MySQL | 2026-06-14 |

**Veredicto:** sin bugs funcionales. Base estable y lista para operar en local/demo.

---

## 2. Cómo re-diagnosticar (comandos)

Ejecutar desde la raíz del proyecto:

```bash
php artisan test                                      # Suite completa (DB: crm_barberia_test en MySQL)
vendor/bin/phpstan analyse --no-progress --memory-limit=512M   # Análisis estático
vendor/bin/pint --test                                # Estilo (solo comprobar)
vendor/bin/pint                                       # Estilo (auto-fix)
composer audit --locked                               # Vulnerabilidades de dependencias
```

**Requisitos del entorno de test:** MySQL corriendo con base `crm_barberia_test` (user `root`, sin password — ver `phpunit.xml`).

---

## 3. Pendientes conocidos (no bloquean operar, sí antes de producción/seguro)

| # | Tarea | Prioridad | Referencia |
|---|---|---|---|
| ~~1~~ | ~~Forzar MFA obligatorio~~ ✅ **HECHO**: `SEC_MFA_REQUIRED` (default ON en producción) en ambos paneles | — | `config/security.php` (`mfa.required`) |
| 2 | **Aislamiento de tenant**: ✅ tests añadidos (Cliente, Lead, herencia, fail-open documentado). Pendiente opcional: migrar a fail-closed para modelos de tenant en contextos de cliente | Media | `BelongsToTenant`, `TenantIsolationTest` |
| 3 | **Test de restore**: ✅ **HECHO** — comando `seguridad:restore --verificar` (restaura en BD desechable y valida). Probado real 2026-06-14: 27 tablas OK. Pendiente: copia **offsite/inmutable** (infra) | Media | `RestaurarBackup`, `BackupRestoreTest` |
| 4 | Rellenar placeholders `[...]` en `docs/datenschutz/` con datos reales antes del seguro | Media | `docs/datenschutz/README.md` |
| 5 | **Producción: usuario MySQL de mínimo privilegio** (NO root). ✅ Definido y **probado** (`crm_app`=SELECT/INSERT/UPDATE/DELETE; `crm_migrate`=DDL solo en deploy). Backup ajustado para funcionar con permisos mínimos. Ver `docs/seguridad/zero-trust-least-privilege.md` | Alta | `docs/seguridad/zero-trust-least-privilege.md` |
| 6 | **Zero Trust — mejoras opcionales** (no bloquean): ✅ step-up auth, ✅ IP allowlist /super, ✅ idle timeout sesión, ✅ rotación API key (`tenant:api-key`). Quedan: secrets manager, rotación de credenciales DB, backup offsite inmutable | Baja | `docs/seguridad/zero-trust-least-privilege.md` §2 |

---

## 4. Changelog de cambios

> Cada cambio en el proyecto se registra aquí: qué se tocó, por qué, y resultado de los tests.

### 2026-06-14
- **Diagnóstico inicial completo**: 190/190 tests, PHPStan 0 errores, composer audit limpio.
- **Pint auto-fix** aplicado a ~180 archivos (solo formato/estilo, sin cambios de lógica). Re-test: ✅ 190/190.
- **Documentación DSGVO creada** en `docs/datenschutz/` (TOMs, VVT, Subprozessoren, Incident-Response-Plan) — diligencia para asegurabilidad, agnóstica del vertical.
- **Creado este documento** (`docs/DIAGNOSTICO.md`) como registro vivo de estado.
- **MFA obligatorio (pendiente #1)**: añadido `config/security.php → mfa.required` (toggle `SEC_MFA_REQUIRED`, default ON en producción / OFF en local-testing). Activado `isRequired` en `AdminPanelProvider` y `SuperPanelProvider`. Documentado en `.env.production.example`. _Por qué OFF en testing: forzar MFA redirige a setup y rompería los logins de la suite._
- **Tests de aislamiento de tenant (pendiente #2)**: +3 tests en `TenantIsolationTest` (aislamiento de Lead, herencia de tenant al crear, y fail-open documentado como decisión consciente). Total tests: **190 → 193**.
- **Test de restore + BUG corregido (pendiente #3)**:
  - 🐛 **Bug real corregido en `BackupEncriptado`**: `Storage::makeDirectory('backups')` creaba `storage/app/private/backups` (disco por defecto Laravel 11+), pero el comando escribe en `storage/app/backups` → el volcado fallaba con "system cannot find the path". **Los backups nunca habrían funcionado en producción.** Cambiado a `File::ensureDirectoryExists(storage_path('app/backups'))`.
  - ➕ Nuevo comando `seguridad:restore` con `--verificar` (restaura en BD desechable `{db}_restore_check`, valida nº de tablas, la elimina — sin tocar producción).
  - ➕ `BackupRestoreTest` (ciclo backup→restore, guardado por disponibilidad de herramientas).
  - ✔️ **Probado real 2026-06-14**: backup cifrado 101.7 KB → restore verificado → 27 tablas. El backup ES restaurable.
- **Resultado tras cambios**: ✅ **195/195 tests** · PHPStan 0 errores · Pint limpio.
- **Least privilege + Zero Trust (pendientes #5/#6)**:
  - 🐛 **2º bug de backup corregido**: `mysqldump` sin flags fallaba con usuario de permisos mínimos (`error 1044: LOCK TABLES denied` + requería `PROCESS`). Añadido `--single-transaction --no-tablespaces` → backup funciona con solo `SELECT`. **Probado** creando usuario restringido real.
  - ➕ Doc `docs/seguridad/zero-trust-least-privilege.md`: SQL de usuarios `crm_app`/`crm_migrate` (probado), flujo de deploy de migraciones, y mapeo completo Zero Trust (Verify explicitly / Least privilege / Assume breach) con estado actual y mejoras opcionales.
  - Veredicto: a nivel de **aplicación** el CRM ya cumple la mayoría de principios Zero Trust.
- **Step-up auth (mejora Zero Trust)**:
  - ➕ Helper reutilizable `app/Filament/Support/StepUpAuth.php` (campo de reconfirmación de contraseña con regla `current_password`).
  - Aplicado a acciones críticas: export GDPR, anonimizar (derecho al olvido), borrar cliente (individual + masivo), y **borrar tenant** en panel super.
  - ➕ `StepUpAuthTest` (3 tests): password incorrecto bloquea y no registra; correcto procede.
  - 📊 Total tests: **195 → 198**. PHPStan 0 errores · Pint limpio.
- **IP allowlist del panel /super (mejora Zero Trust)**:
  - ➕ Middleware `RestrictSuperPanelIp` (1º en la pila del panel super); soporta IPs y CIDR (Symfony IpUtils); allowlist vacía = sin restricción (no rompe local/tests).
  - ➕ Config `security.super_ip_allowlist` (`SEC_SUPER_IP_ALLOWLIST`) + documentado en `.env.production.example`. Log `SUPER_IP_BLOCKED` en intentos bloqueados.
  - ➕ `SuperPanelIpRestrictionTest` (5 tests): sin allowlist permite, IP no permitida→403, IP/CIDR permitida→200, /admin no afectado.
  - 📊 Total tests: **198 → 203**. PHPStan 0 errores · Pint limpio.
- **Idle timeout de sesión (mejora Zero Trust)**:
  - ➕ Middleware `EnforceIdleTimeout` en `authMiddleware` de admin y super: cierra sesión tras N min de inactividad y exige re-login.
  - ➕ Config `security.session.idle_timeout` (`SEC_SESSION_IDLE_TIMEOUT`, default 30 min en prod / 0 en local-testing). Log `SESSION_IDLE_TIMEOUT`.
  - ➕ `SessionIdleTimeoutTest` (3 tests): inactiva cierra+redirige, activa continúa, desactivado no cierra.
  - 📊 Total tests: **203 → 206**. PHPStan 0 errores · Pint limpio.
  - Nota: rotación de API key de tenant ya existía (`tenant:api-key`).
- **Feature: Umsatz pro Mitarbeiter (ingresos por barbero)**:
  - ➕ Widget `UmsatzProMitarbeiterWidget` en el dashboard: ranking de ingresos del mes por barbero (Pago→Cita→personal_id) + nº de citas.
  - 🔒 Seguridad mantenida: filtrado explícito por tenant (`HasTenantContext`), subqueries con `tenant_id` explícito y `withoutGlobalScopes()` (determinista, OWASP A01).
  - ➕ `UmsatzProMitarbeiterTest`: verifica suma correcta **y aislamiento de tenant** (no filtra ingresos de otro negocio).
  - 📊 Total tests: **206 → 207**. PHPStan 0 errores · Pint limpio. Demo refrescada (citas semana actual; ranking visible: Lena 155€, Murat 116€).
- **Feature: Tasa de no-shows (KPI)**:
  - ➕ Stat "Nicht erschienen (Monat)" en `ResumenWidget`: nº y % de no-shows del mes (estado `no_show`), en rojo, con copy que vende los recordatorios. Filtro explícito por tenant.
  - ➕ `NoShowRateTest`: % correcto + aislamiento de tenant. Demo: 21% (3/14) — gancho para el pitch.
  - 📊 Total tests: **207 → 208**. PHPStan 0 errores · Pint limpio.
- **Feature: Beliebteste Leistungen (servicios más vendidos)**:
  - ➕ Widget `BeliebtesteLeistungenWidget` en dashboard: ranking de servicios por ingresos del mes (pivote `cita_servicios`) + nº de veces; excluye cancelada/no_show.
  - 🔒 Seguridad: Servicio filtrado por tenant explícito; subqueries unen `citas` con `citas.tenant_id` explícito (OWASP A01).
  - ➕ `BeliebtesteLeistungenTest`: ranking correcto + aislamiento de tenant.
  - 📊 Total tests: **208 → 209**. PHPStan 0 errores · Pint limpio. Demo: Färben 130€, Herrenhaarschnitt 112€ (x4)…

---

_Mantener este archivo actualizado en cada sesión de trabajo sobre el proyecto._
