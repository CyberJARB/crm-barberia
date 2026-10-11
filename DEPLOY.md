# Despliegue en producción — escalabilidad, balanceo y resiliencia

La aplicación es **stateless por diseño** (sesiones en BD, jobs cifrados en
cola, locks atómicos) — escala horizontalmente añadiendo nodos detrás de un
load balancer sin cambiar código.

## Arquitectura de referencia (Hetzner Cloud, ~30–60 €/mes al inicio)

```
                        ┌─────────────────────┐
   Internet ──TLS──────▶│  Load Balancer (LB11)│   health check: GET /salud
                        └─────────┬───────────┘
                  ┌───────────────┼───────────────┐
                  ▼               ▼               ▼
            ┌──────────┐   ┌──────────┐    ┌──────────┐
            │ App #1   │   │ App #2   │    │ App #N…  │   nginx + php-fpm
            │ (CX22)   │   │ (CX22)   │    │          │   (escala aquí)
            └────┬─────┘   └────┬─────┘    └────┬─────┘
                 └───────┬──────┴────────┬──────┘
                         ▼               ▼
                 ┌──────────────┐ ┌─────────────┐
                 │ MySQL 8      │ │ Redis       │  cache + sesiones + colas
                 │ (gestionado/ │ │ (1 nodo o   │  en producción
                 │  CX32)       │ │  gestionado)│
                 └──────────────┘ └─────────────┘
                         ▲
            ┌────────────┴───────────┐
            │ Worker (CX22)          │  queue:work (×2 procesos, supervisor)
            │ + scheduler (cron 1min)│  schedule:run → onOneServer()
            └────────────────────────┘
            S3/Backblaze: backups cifrados + storage compartido (gdpr/)
```

**Empezar pequeño**: TODO esto cabe en 1 servidor (CX22, ~5 €). La arquitectura
de arriba es el camino de crecimiento, no el día 1.

## Capacidades nativas ya implementadas

| Capacidad | Implementación |
|---|---|
| App stateless | Sesiones en BD (`SESSION_DRIVER=database`) → cualquier nodo atiende cualquier request, sin sticky sessions |
| Load balancer | `trustProxies('*')` en bootstrap — HTTPS e IPs reales detrás del proxy |
| Health check LB | `GET /salud` → verifica BD + cache + cola; devuelve 503 si el nodo está degradado → el LB lo saca de rotación |
| Scheduler multi-nodo | Todas las tareas con `onOneServer()` + `withoutOverlapping()` — sin duplicados al escalar |
| Colas resilientes | Jobs con `tries=3` + backoff exponencial (60s/5m/15m) + cifrados (CWE-312); fallos visibles en /super (SistemaStatusWidget) |
| Concurrencia segura | `lockForUpdate` en canje de vales, venta de productos; revalidación de slots en reserva |
| Rate limiting | Por IP en web, API, login, reservas — protege cada nodo |
| Degradación elegante | El fallo de email no rompe registros/reservas; auditado en `audit_logs` |
| Backups | `seguridad:backup` AES-256 diario — subir a S3/Backblaze con retención 30 días |

## Checklist de despliegue

1. **Servidor**: Ubuntu 24.04, `nginx + php8.5-fpm + mysql-8 + redis + supervisor + certbot`
2. **.env producción** (ver `.env.production.example`):
   ```
   APP_ENV=production  APP_DEBUG=false  APP_URL=https://app.tudominio.de
   CACHE_STORE=redis  SESSION_DRIVER=database  QUEUE_CONNECTION=redis
   FILESYSTEM_DISK=s3            # storage compartido entre nodos
   MENSAJERIA_DRIVER=twilio      # + TWILIO_SID/TOKEN/FROM_*
   STRIPE_KEY/SECRET/WEBHOOK_SECRET, CASHIER_* prices
   CORS_TENANT_ORIGINS=https://web-del-tenant.de
   ```
3. **Optimizar** (en cada deploy): `composer install --no-dev -o && php artisan migrate --force && php artisan config:cache route:cache view:cache filament:cache-components && npm run build`
4. **Supervisor** — workers de cola (resiliencia: reinicio automático):
   ```ini
   [program:crm-worker]
   command=php /var/www/crm/artisan queue:work redis --tries=3 --max-time=3600
   numprocs=2
   autostart=true
   autorestart=true
   stopwaitsecs=70
   ```
5. **Cron** (un solo nodo o todos — onOneServer ya deduplica):
   ```
   * * * * * php /var/www/crm/artisan schedule:run >> /dev/null 2>&1
   ```
6. **Verificar**: `php artisan seguridad:auditar` → 20/20; `curl https://app.../salud` → `{"status":"ok"}`
7. **Stripe**: webhook → `https://app.../stripe/webhook` + firmar AVV de Twilio
8. **Monitoreo**: el LB usa `/salud`; el panel `/super` muestra jobs fallidos, cola y fallos de envío en tiempo real

## Cuándo escalar qué

- **Más tráfico web** → añadir nodos App al LB (son clones; cero config extra)
- **Cola lenta** (avisa el widget en /super) → más `numprocs` o un worker dedicado
- **BD saturada** → subir el tamaño primero; luego read-replicas (Laravel: `read/write` en config/database.php, sin tocar código)
- **>500 tenants** → Redis gestionado + MySQL gestionado (HA con failover automático)
