# Plan de trabajo: migrar `brigesnote` a PHP 8.3

Repo clonado en `brigesnote` (rama `main`, HEAD `e947779 iniciando migracion a 8.3`). Tienes permisos de push con la cuenta `ComoCuandoQue`.

## 1. Diagnóstico del estado actual

| Aspecto | Estado actual | Problema con PHP 8.3 |
|---|---|---|
| Framework | Laravel **7.30** (`composer.json`: `laravel/framework ^7.29`) | Laravel 7 **no arranca en PHP ≥ 8.1** (error de firmas `ArrayAccess`; el propio README lo dice). Además ya no tiene soporte. |
| Restricción PHP | `"php": "^7.2.5\|^8.0"` | No incluye 8.3 de forma explícita |
| Paquetes obsoletos | `fideloper/proxy`, `fruitcake/laravel-cors`, `facade/ignition`, `nunomaduro/collision ^4` | Abandonados o ya incluidos en el framework desde Laravel 9 |
| Estructura | Esqueleto L7: `Http/Kernel`, `Console/Kernel`, `Exceptions/Handler`, `database/seeds`, factories con closures, `App\User` | Formas antiguas que ya no se usan |
| Código de la app | `SyncLocations` (comando), `LocationController`, 4 modelos, 4 migraciones, `routes/api.php` | Sin tipos; uso de `LEFT()` (solo MySQL); `app/Region.php` duplicado y sin uso; `Commands\SyncLocations` registrado por error en `Http\Kernel` |
| Frontend | `laravel-mix 5` (sin usar: es una API) | Obsoleto |
| Tests | Solo los `ExampleTest` por defecto | No cubren los endpoints |

> [!IMPORTANT]
> Cambiar solo la versión de PHP no basta: para que funcione en PHP 8.3 **hay que actualizar Laravel**. Laravel 10 es la versión más antigua que acepta 8.3, pero ya no recibe soporte. La propuesta es **Laravel 13** (pide PHP ≥ 8.3, tiene parches de seguridad hasta marzo de 2028).

## 2. Estrategia

La app es pequeña (unos 6 archivos propios), así que **no conviene subir de versión en versión** (7→8→…→13). Es más limpio:

1. Tomar el **esqueleto oficial de Laravel 13** como base (`bootstrap/app.php` simplificado, configs nuevas).
2. **Pasar el código propio** (modelos, controlador, comando, migraciones, rutas, `config/cors.php`) aplicando mejoras de PHP 8.3.
3. Comprobar que la API responde **igual**: mismas rutas, mismo JSON y mismos códigos HTTP.

## 3. Fases y commits

Cada fase termina en su propio commit (Conventional Commits, en español).

| # | Fase | Commit |
|---|---|---|
| 0 | **Antes de empezar:** guardar este plan en `docs/PLAN_MIGRACION_PHP83.md`, hacer commit y **push** | `docs: plan de trabajo para migracion a PHP 8.3 / Laravel 13` |
| 1 | **Dependencias:** `composer.json` con `php ^8.3`, `laravel/framework ^13`, `guzzle ^7`, `tinker`; quitar `fideloper/proxy`, `fruitcake/laravel-cors` y `facade/ignition`; subir `collision`, `phpunit` y `mockery`; agregar `laravel/pint`; regenerar `composer.lock` | `build: actualizar dependencias a PHP 8.3 y Laravel 13` |
| 2 | **Esqueleto:** nuevo `bootstrap/app.php` (rutas `api` con prefijo `/api` y throttle de 60/min, CORS nativo, proxies); borrar `Http/Kernel`, `Console/Kernel`, `Exceptions/Handler`, middlewares y providers por defecto que sobran; actualizar `config/*`, `public/` o `index.php` raíz, `artisan` y `.env.example` | `refactor: adoptar estructura de aplicacion de Laravel 13` |
| 3 | **Código propio:** `App\User` → `App\Models\User`; borrar `app/Region.php`; `database/seeds` → `database/seeders` (con namespace); factory basada en clases; migraciones como clases anónimas; quitar la ruta `auth:api /user` (el guard `api` ya no existe en el esqueleto y no se usa) | `refactor: migrar modelos, seeders, factories y migraciones` |
| 4 | **PHP 8.3:** tipos de propiedades, parámetros y retornos (`JsonResponse`, `int`), constantes tipadas (`private const string BASE_URL`), `#[\Override]`; en `SyncLocations`, juntar en un helper la lógica repetida de "un registro o una lista"; cambiar `LEFT(col, n) = ?` por `LIKE 'xx%'` (funciona en cualquier BD y aprovecha el índice) | `refactor: modernizar codigo con caracteristicas de PHP 8.3` |
| 5 | **Tests:** tests de feature para los 4 endpoints (casos 200 y 404) con SQLite en memoria, y test del comando `sync:locations` con `Http::fake()` | `test: cubrir endpoints de ubicaciones y comando de sincronizacion` |
| 6 | **Frontend y CI:** cambiar `laravel-mix` por Vite (como en el esqueleto); GitHub Actions con PHP 8.3 (`composer install`, `pint --test`, `php artisan test`) | `ci: agregar pipeline con PHP 8.3` |
| 7 | **Documentación:** README con requisitos (PHP 8.3, Laravel 13), instalación actualizada y sin el problema de `ArrayAccess` en la sección de errores | `docs: actualizar README para PHP 8.3 / Laravel 13` |
| 8 | **Comprobación final y push** | (push de todos los commits) |

## 4. Cómo voy a comprobarlo

- `php -l` en todos los `.php` con PHP 8.3
- `composer validate` y `composer install` sin errores
- `php artisan about` (debe mostrar PHP 8.3.x y Laravel 13.x) y `php artisan route:list` (las 4 rutas `/api/...`)
- `php artisan migrate` en SQLite y `php artisan test`: todo en verde
- `vendor/bin/pint --test`

## 5. Decisiones tomadas

> [!NOTE]
> **Entorno listo (sin Docker):** PHP 8.3.36 (winget) con `php.ini` y las extensiones `openssl`, `mbstring`, `curl`, `fileinfo`, `pdo_sqlite`, `sqlite3`, `pdo_mysql`, `zip` e `intl`; Composer 2.11.2; Git 2.56; Node.js 26.13.1.

| Tema | Decisión |
|---|---|
| Entorno | PHP 8.3 y Composer instalados directamente en Windows (sin Docker) |
| Versión de Laravel | **Laravel 13** |
| Rama de push | **Directo a `main`** |
| Fase 6 (Vite + CI) | **Se incluye** |
