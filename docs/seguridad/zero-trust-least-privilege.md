# Zero Trust & Least Privilege — CRM Barberías / CyberJARB

**Principio rector:** *"Never trust, always verify"* + *"Assume breach"*.
Cada petición se autentica y autoriza; cada componente tiene **solo** los privilegios
que necesita; se minimiza el radio de explosión si algo se compromete.

---

## 1. Usuarios de base de datos con mínimo privilegio

### Regla de oro
El usuario de **runtime de la app NUNCA debe ser root** ni tener DDL (`DROP/ALTER/CREATE`),
ni `GRANT/FILE/SUPER/PROCESS`. Así, un fallo de la app (SQLi/RCE) **no puede** destruir
ni escalar — solo leer/escribir filas dentro de su propio esquema.

### Dos usuarios, dos momentos

| Usuario | Cuándo se usa | Privilegios |
|---|---|---|
| **`crm_app`** | Runtime (la app, los backups) | `SELECT, INSERT, UPDATE, DELETE` |
| **`crm_migrate`** | Solo en deploy (migraciones) | DDL del esquema (CREATE/ALTER/DROP/INDEX/REFERENCES) |

### SQL (probado 2026-06-14)

```sql
-- 1) Usuario de RUNTIME — el que va en .env de producción (DB_USERNAME=crm_app)
CREATE USER 'crm_app'@'localhost' IDENTIFIED BY 'GENERAR: openssl rand -base64 24';
GRANT SELECT, INSERT, UPDATE, DELETE ON crm_barberia.* TO 'crm_app'@'localhost';

-- 2) Usuario de MIGRACIONES — solo se usa al desplegar (php artisan migrate)
CREATE USER 'crm_migrate'@'localhost' IDENTIFIED BY 'GENERAR: openssl rand -base64 24';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
    ON crm_barberia.* TO 'crm_migrate'@'localhost';

FLUSH PRIVILEGES;
```

> **Ni un privilegio de más:** sin `GRANT` (no puede dar permisos), sin `FILE` (no puede
> escribir webshells), sin `SUPER/PROCESS` (no ve otras sesiones), sin acceso a otras BD.

### Por qué el backup funciona con solo SELECT
`mysqldump` de MySQL 8.0 exige por defecto `LOCK TABLES` y `PROCESS`. El comando
`seguridad:backup` usa **`--single-transaction --no-tablespaces`**, lo que:
- hace un snapshot consistente de InnoDB **sin bloquear** (no necesita `LOCK TABLES`),
- evita la consulta de tablespaces (no necesita `PROCESS`).

→ Verificado: con un usuario de solo `SELECT/INSERT/UPDATE/DELETE`, el backup completo
se genera correctamente (caso B del test). Sin esos flags, falla con `error 1044`.

### Deploy seguro de migraciones
```bash
# Migrar con el usuario privilegiado (solo en este paso):
DB_USERNAME=crm_migrate DB_PASSWORD=... php artisan migrate --force
# La app queda corriendo con crm_app (mínimo privilegio) en su .env normal.
```

---

## 2. Mapeo Zero Trust — qué ya está y qué falta

### Verify explicitly (autenticar y autorizar SIEMPRE)
| Control | Estado |
|---|---|
| MFA/TOTP obligatorio en paneles (prod) | ✅ `SEC_MFA_REQUIRED` |
| Contraseñas fuertes (12+, anti-pwned, historial) | ✅ `config/security.php` |
| Account lockout + rate limiting + reCAPTCHA + honeypot | ✅ |
| Autorización por política en **cada** recurso (RBAC) | ✅ Policies |
| Aislamiento de tenant en **cada** query | ✅ `BelongsToTenant` + tests |
| API: key por tenant (SHA-256) + gating por plan en cada llamada | ✅ `AutenticarTenantApi` |

### Least privilege (mínimo acceso)
| Control | Estado |
|---|---|
| Usuario MySQL runtime sin root/DDL/GRANT/FILE | ✅ §1 (este doc) |
| Backups con solo SELECT | ✅ §1 |
| Roles de app con permisos acotados | ✅ RBAC |
| Registros financieros inmutables (no DELETE) | ✅ `PagoResource` |

### Assume breach (minimizar radio de explosión)
| Control | Estado |
|---|---|
| Cifrado en reposo de campos sensibles (incl. salud) | ✅ `HasEncryptedFields` |
| Cifrado en tránsito (TLS forzado, HSTS) | ✅ |
| Backups cifrados (AES-256) y **restaurables** (probado) | ✅ `seguridad:restore --verificar` |
| Payloads de jobs cifrados | ✅ `ShouldBeEncrypted` |
| Auditoría inmutable (quién/qué/cuándo) | ✅ `AuditLog` |
| Detección de anomalías | ✅ `DetectarAnomalias` |
| DB solo en localhost; firewall 80/443; SSH por clave + fail2ban | ✅ documentado (DEPLOY) |

### Pendiente / mejoras Zero Trust (opcional, no bloqueante)
- [x] **Step-up auth** ✅: reconfirmación de contraseña antes de acciones críticas (export GDPR, anonimizar, borrar cliente/tenant). Helper `app/Filament/Support/StepUpAuth.php`, test `StepUpAuthTest`.
- [x] **IP allowlist** del panel `/super` ✅: middleware `RestrictSuperPanelIp` (IPs/CIDR), config `SEC_SUPER_IP_ALLOWLIST`, test `SuperPanelIpRestrictionTest`.
- [x] **Idle timeout de sesión** ✅: cierre por inactividad + re-login (`EnforceIdleTimeout`, `SEC_SESSION_IDLE_TIMEOUT`, test `SessionIdleTimeoutTest`).
- [x] **Rotación de API keys de tenant** ✅: comando `tenant:api-key` (genera/rota, solo guarda SHA-256).
- [ ] **Secrets manager** (Vault/Doppler) en vez de `.env` plano para APP_KEY/DB_PASSWORD.
- [ ] **Rotación** programada de API keys de tenant y de credenciales DB.
- [ ] **Copia de backup offsite/inmutable** (otro proveedor/bucket WORM).

---

## 3. La verdad honesta sobre "Zero Trust"
Zero Trust no es un interruptor, es una **arquitectura y una mentalidad**. A nivel de
**aplicación**, este CRM ya implementa la gran mayoría de sus principios (identidad
verificada en cada request, mínimo privilegio, cifrado, segmentación por tenant,
asunción de brecha). El Zero Trust *completo* abarca también red, dispositivos e
identidad corporativa, que exceden una sola app y se construyen con la infraestructura.
Para un SaaS de un fundador, el estado actual es **sólido y defendible ante un asegurador**.

_Stand: 2026-06-14 · Version 1.0_
