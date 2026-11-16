# 🏫 Bridges Note - MINEDU ESCALE Bridge API

Microservicio y API REST en **Laravel 13** y **PHP 8.3+** diseñado para sincronizar, estructurar y servir el catálogo oficial de instituciones educativas (colegios) y la división política/geográfica (regiones, provincias y distritos) del Perú, consumiendo directamente la API pública oficial de **MINEDU / ESCALE** (*Estadística de la Calidad Educativa*).

> 🔄 **Nota sobre este fork / modernización:** Este repositorio corresponde a la **migración integral** de la versión original (construida en **Laravel 7** / **PHP 7.4–8.0**) hacia un stack moderno en **Laravel 13** y **PHP 8.3+**, conservando intactos los endpoints REST, la estructura de base de datos y el comando `php artisan sync:locations`.

---

## 📋 Tabla de Contenidos

1. [Propósito del Proyecto](#-propósito-del-proyecto)
2. [Evolución Tecnológica (Antes vs. Actualmente)](#-evolución-tecnológica-antes-vs-actualmente)
3. [Arquitectura y Flujo de Datos](#-arquitectura-y-flujo-de-datos)
4. [Requisitos del Sistema](#-requisitos-del-sistema)
5. [Instalación y Puesta en Marcha](#-instalación-y-puesta-en-marcha)
6. [Configuración de Entorno (.env)](#-configuración-de-entorno-env)
7. [Sincronización de Datos (Consola Artisan)](#-sincronización-de-datos-consola-artisan)
8. [Documentación de la API REST](#-documentación-de-la-api-rest)
9. [Pruebas y Calidad de Código](#-pruebas-y-calidad-de-código)
10. [Estructura del Proyecto](#-estructura-del-proyecto)
11. [Solución de Problemas Frecuentes](#-solución-de-problemas-frecuentes)

---

## 🎯 Propósito del Proyecto

Servir como un **puente de datos optimizado** para aplicaciones cliente (sistemas de admisión universitaria, plataformas de matrícula escolar, intranets, etc.), permitiéndoles autocompletar ubigeos y consultar colegios mediante consultas locales ultrarrápidas, sin depender de la latencia o disponibilidad de los servidores estatales.

---

## 🔄 Evolución Tecnológica (Antes vs. Actualmente)

El proyecto original partió de un esqueleto **Laravel 7** que ya había quedado sin soporte y no podía ejecutarse en PHP ≥ 8.1. Esta versión reestructura la aplicación sobre **Laravel 13** y **PHP 8.3+** aplicando buenas prácticas actuales sin romper compatibilidad con los clientes existentes:

| Área | Cómo estaba construido antes (Original) | Cómo está construido actualmente (Migrado) |
|---|---|---|
| **PHP** | `^7.2.5 \| ^8.0` (recomendado `7.4`; fallaba en `8.1+` por cambios en `ArrayAccess`). Sin tipado estricto en métodos ni constantes. | `^8.3` (verificado en `8.3.36`, compatible con `8.4`/`8.5`), con tipos en parámetros y retornos, constantes tipadas y `#[\Override]`. |
| **Framework** | **Laravel `7.30.7`** (`^7.29`). | **Laravel `13.42.0`** (`^13.0`). |
| **Estructura base** | Arquitectura clásica de L7 con `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, `RouteServiceProvider`, middlewares por defecto, `App\User` y un `app/Region.php` duplicado. | Arranque unificado en `bootstrap/app.php` (`withRouting`, `throttleApi` de `60/min` en `AppServiceProvider`) y `bootstrap/providers.php`; modelos consolidados en `App\Models\`. |
| **Dependencias PHP** | Paquetes externos hoy abandonados o absorbidos por el core: `fideloper/proxy`, `fruitcake/laravel-cors`, `facade/ignition`, `collision ^4.3`, `phpunit ^8.5\|^9.3`. | Proxies y CORS nativos de Laravel, `guzzlehttp/guzzle ^7.9`, `tinker ^3.0`, `collision ^8.6`, `phpunit ^12.5` y `laravel/pint ^1.27`. |
| **Consultas y BD** | `database/seeds` por `classmap`, factories con closures, migraciones con clases nombradas y filtros SQL `LEFT(col, n) = ?` dependientes de MySQL. | `database/seeders` PSR-4, factories basadas en clases, migraciones anónimas (`: void`) y filtrado por prefijo con `LIKE` (portable entre MySQL/SQLite y apto para índices). |
| **Sincronización ESCALE** | `SyncLocations` repetía el manejo de objeto único vs. lista en cada nivel, sin timeout HTTP explícito y sin tolerancia cuando faltaban claves. | Helpers compartidos (`fetchJson`, `normalizeRecords`), timeout de `30` s por petición y manejo seguro de payloads vacíos o errores parciales. |
| **Rutas API** | Incluía ruta heredada `GET /api/user` protegida con `auth:api` (guard inexistente en el servicio). | Mantiene exclusivamente los 4 endpoints públicos de ubigeos y colegios, con soporte CORS y límite de `60` req/min. |
| **Frontend / Assets** | `laravel-mix 5` + Webpack + `sass` (`webpack.mix.js`). | **Vite 8** + `laravel-vite-plugin` (`vite.config.js`) con salida en raíz. |
| **Pruebas y CI** | Solo `ExampleTest` por defecto; sin automatización ni verificación de estilo. | Suite **PHPUnit 12** (`LocationApiTest` y `SyncLocationsCommandTest` con SQLite en memoria y `Http::fake()`), **Laravel Pint** y pipeline en **GitHub Actions** (`.github/workflows/ci.yml`). |

> 📄 El detalle paso a paso de las fases ejecutadas durante la actualización puede consultarse en [docs/PLAN_MIGRACION_PHP83.md](docs/PLAN_MIGRACION_PHP83.md).

---

## 🔄 Arquitectura y Flujo de Datos

```
[ API MINEDU / ESCALE ]
 (escale.minedu.gob.pe)
          │
          ▼  (Comando: php artisan sync:locations)
[ Base de Datos Local MySQL (escalebridge) ]
  ├── regions
  ├── provinces
  ├── districts
  └── schools (con ubigeo / idDistrito)
          │
          ▼  (LocationController)
[ Endpoints API REST Local / Producción ]
  ├── GET /api/regiones
  ├── GET /api/provincias/{idRegion}
  ├── GET /api/distritos/{idProvince}
  └── GET /api/colegios/{ubigeo}
          │
          ▼
[ Clientes / Aplicaciones Externas ] (ej. admisionweb)
```

> 📌 **Despliegue desde la raíz:** Este proyecto mantiene el front controller (`index.php`, `.htaccess` y `web.config`) en la raíz del repositorio en lugar de `public/`, configurado en `bootstrap/app.php` con `$app->usePublicPath($app->basePath())`. Tanto `.htaccess` (Apache) como `web.config` (IIS) bloquean el acceso HTTP directo a directorios internos (`.env`, `app/`, `config/`, `vendor/`, etc.).

---

## 💻 Requisitos del Sistema

- **PHP:** `8.3` o superior (compatible con PHP `8.3`, `8.4` y `8.5`), con las extensiones `openssl`, `mbstring`, `curl`, `fileinfo`, `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `intl`, `zip` y `xml`.
- **Laravel Framework:** `13.x` (`^13.0`)
- **Gestor de Paquetes:** Composer 2.x
- **Base de Datos:** MySQL 8.0+, MariaDB 10.6+ o SQLite 3
- **Node.js / Vite (opcional, compilación de assets):** Node.js LTS + npm
- **Servidor Web Local:** `php artisan serve`, Laragon o XAMPP con **PHP 8.3 (Thread Safe - TS)**.

---

## 🚀 Instalación y Puesta en Marcha

### 1. Clonar el repositorio
```bash
git clone https://github.com/ComoCuandoQue/brigesnote.git
cd brigesnote
```

### 2. Instalar dependencias de Composer
```bash
composer install
```

### 3. Configurar variables de entorno
Copia el archivo de ejemplo y genera la clave de cifrado:
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Crear la Base de Datos
Crea una base de datos vacía en tu gestor MySQL (ej. HeidiSQL o phpMyAdmin):
- **Nombre:** `escalebridge`
- **Cotejamiento:** `utf8mb4_unicode_ci`

### 5. Ejecutar las Migraciones
Crea las tablas en la base de datos:
```bash
php artisan migrate
```

> 💡 Alternativamente, los pasos 2, 3 y 5 se pueden ejecutar de una vez con `composer run setup` (después de crear la base de datos o configurar `.env`).

### 6. Sincronizar el catálogo escolar
Descarga la información oficial desde MINEDU:
```bash
php artisan sync:locations
```

### 7. Iniciar el servidor
```bash
php artisan serve
# o mediante Composer:
composer run dev
```
La aplicación quedará disponible en `http://127.0.0.1:8000` (health check integrado en `GET /up`).

---

## ⚙️ Configuración de Entorno (.env)

Asegúrate de tener estos valores configurados en tu archivo `.env` (basado en `.env.example`):

```env
APP_NAME="Bridges Note"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Conexión MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=escalebridge
DB_USERNAME=root
DB_PASSWORD=

# Sesion, cache y colas sin tablas extra en la BD
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

---

## 📥 Sincronización de Datos (Consola Artisan)

El comando central del sistema (`App\Console\Commands\SyncLocations`) es:

```bash
php artisan sync:locations
```

### ¿Cómo opera la sincronización?
1. **Regiones (`/padron/rest/regiones`):** Descarga los 25 departamentos del país con sus nombres, coordenadas (`pointX`, `pointY`) y nivel de `zoom`.
2. **Provincias (`/padron/rest/provincias?idRegion={id}`):** Por cada región, descarga sus provincias.
3. **Distritos (`/padron/rest/distritos?idProvincia={id}`):** Por cada provincia, descarga los distritos correspondientes.
4. **Colegios (`/padron/rest/instituciones?estados=1&ubigeo={idDistrito}`):** Por cada distrito, descarga las instituciones educativas activas y las almacena en la tabla `schools` vinculando el código modular (`codMod`) como llave primaria y asociando el `idDistrito`.

- **Idempotencia y tolerancia:** Las inserciones usan `updateOrCreate`, normalizan respuestas de ESCALE que llegan como objeto único o como lista, y aplican un timeout de `30` segundos por petición HTTP.
- ⏱️ **Nota:** La sincronización total recorre más de 1,800 distritos y 80,000 colegios, por lo que puede tomar varios minutos según la velocidad de conexión y respuesta del servidor del MINEDU.

---

## 📡 Documentación de la API REST

Todos los endpoints retornan respuestas en formato JSON bajo el prefijo `/api/` (con límite de `60` peticiones por minuto por IP definido en `AppServiceProvider` y CORS configurado en `config/cors.php` para `api/*`). Los modelos Eloquent incluyen además los campos `created_at` y `updated_at`.

### 1. Listar Regiones / Departamentos
Obtiene todas las regiones registradas en la base de datos (devuelve `200 OK` con `data: []` si la tabla está vacía).

- **URL:** `/api/regiones`
- **Método:** `GET`
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idRegion": "15",
      "nombreRegion": "LIMA",
      "pointX": "-12.0463740",
      "pointY": "-77.0427930",
      "zoom": 10
    }
  ]
}
```

---

### 2. Listar Provincias por Región
Obtiene las provincias pertenecientes a una región (mediante el prefijo de 2 dígitos del código de departamento).

- **URL:** `/api/provincias/{idRegion}`
- **Método:** `GET`
- **Ejemplo:** `/api/provincias/15` (Provincias de Lima)
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idProvincia": "1501",
      "nombreProvincia": "LIMA",
      "pointX": "-12.0463740",
      "pointY": "-77.0427930"
    }
  ]
}
```
- **Sin resultados o prefijo inválido (404 Not Found):**
```json
{
  "status": "error",
  "message": "No se encontraron provincias para esta región."
}
```

---

### 3. Listar Distritos por Provincia
Obtiene los distritos de una provincia (mediante el prefijo de 4 dígitos de ubigeo provincial).

- **URL:** `/api/distritos/{idProvince}`
- **Método:** `GET`
- **Ejemplo:** `/api/distritos/1501` (Distritos de la provincia de Lima)
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idDistrito": "150101",
      "nombreDistrito": "LIMA"
    },
    {
      "idDistrito": "150102",
      "nombreDistrito": "ANCON"
    }
  ]
}
```
- **Sin resultados o prefijo inválido (404 Not Found):**
```json
{
  "status": "error",
  "message": "No se encontraron distritos para esta provincia."
}
```

---

### 4. Listar Colegios por Distrito (Ubigeo)
Obtiene todas las instituciones educativas registradas en un distrito según su ubigeo de 6 dígitos.

- **URL:** `/api/colegios/{ubigeo}`
- **Método:** `GET`
- **Ejemplo:** `/api/colegios/150101`
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "codMod": "0273572",
      "idDistrito": "150101",
      "nombreCenEdu": "JUAN JIMENEZ PIMENTEL",
      "cenPob": "LIMA",
      "dirCen": "JR. LAMPA 240",
      "director": "PEDRO PEREZ",
      "dis_crecer": null,
      "dis_juntos": null
    }
  ]
}
```
- **Sin resultados (404 Not Found):**
```json
{
  "status": "error",
  "message": "No se encontraron escuelas para este distrito."
}
```

---

## 🧪 Pruebas y Calidad de Código

La suite de pruebas usa **PHPUnit 12** con SQLite en memoria (configurado en `phpunit.xml`) y `Http::fake()` para el comando de sincronización:

```bash
# Ejecutar todas las pruebas
php artisan test
# o mediante Composer:
composer run test

# Verificar formato de código con Laravel Pint
php vendor/bin/pint --test
# o mediante Composer:
composer run lint

# Compilar assets con Vite (opcional)
npm ci && npm run build
```

Además, el workflow [.github/workflows/ci.yml](.github/workflows/ci.yml) valida automáticamente en cada `push` y `pull_request` sobre `main`: `composer validate --strict`, sintaxis (`php -l`), Pint, PHPUnit en PHP 8.3 y build de Vite.

---

## 📂 Estructura del Proyecto

```
brigesnote/
├── .github/workflows/
│   └── ci.yml                       # Pipeline CI con PHP 8.3 y Vite
├── app/
│   ├── Console/Commands/
│   │   └── SyncLocations.php        # Comando 'sync:locations' (API ESCALE)
│   ├── Http/Controllers/
│   │   └── LocationController.php   # Controlador de endpoints /api/
│   ├── Models/
│   │   ├── District.php             # Modelo Distritos
│   │   ├── Province.php             # Modelo Provincias
│   │   ├── Region.php               # Modelo Regiones
│   │   ├── School.php               # Modelo Colegios / Escuelas
│   │   └── User.php                 # Modelo Usuario
│   └── Providers/
│       └── AppServiceProvider.php   # Rate limiter 'api' (60 req/min)
├── bootstrap/
│   ├── app.php                      # Configuración principal de Laravel 13
│   └── providers.php                # Registro de providers
├── config/
│   └── cors.php                     # Reglas CORS para rutas api/* (entre otros configs)
├── database/
│   ├── factories/UserFactory.php
│   ├── migrations/                  # Migraciones (clases anónimas)
│   └── seeders/DatabaseSeeder.php
├── docs/
│   └── PLAN_MIGRACION_PHP83.md      # Plan de migración a PHP 8.3 / Laravel 13
├── resources/
│   ├── css/app.css
│   ├── js/
│   └── views/welcome.blade.php
├── routes/
│   ├── api.php                      # Rutas REST bajo /api/
│   ├── console.php                  # Comandos de consola
│   └── web.php                      # Ruta web base
├── tests/
│   ├── Feature/
│   │   ├── ExampleTest.php
│   │   ├── LocationApiTest.php      # Pruebas de endpoints REST
│   │   └── SyncLocationsCommandTest.php # Pruebas del comando sync:locations
│   └── Unit/ExampleTest.php
├── .env.example                     # Plantilla de variables de entorno
├── .gitignore                       # Reglas de exclusión para Git
├── .htaccess                        # Reglas Apache y bloqueo de archivos internos
├── composer.json                    # Dependencias PHP y scripts (setup, dev, test, lint)
├── index.php                        # Front controller en la raíz
├── package.json                     # Dependencias Node / Vite
├── phpunit.xml                      # Configuración de PHPUnit 12
├── vite.config.js                   # Configuración de Vite (salida en raíz)
└── web.config                       # Reglas IIS y segmentos ocultos
```

---

## 🛠️ Solución de Problemas Frecuentes

### 1. Error por extensiones faltantes en PHP 8.3 (`could not find driver` o `Class ... not found`)
- **Causa:** En instalaciones limpias de PHP 8.3 en Windows, las extensiones vienen comentadas o sin `php.ini` activo.
- **Solución:** Habilita en tu `php.ini` las extensiones `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `curl`, `fileinfo`, `intl` y `zip`.

### 2. Error: `Service Apache can not start ... fcgid.conf` en Laragon
- **Causa:** Descargaste la versión `nts` (Non-Thread Safe) de PHP en Windows.
- **Solución:** Descarga e instala en Laragon la versión **PHP 8.3 Thread Safe (TS)** desde [windows.php.net](https://windows.php.net/downloads/releases/).

### 3. Error: `Base table or view not found: 1146 Table '...sessions' doesn't exist`
- **Causa:** En el archivo `.env` está configurado `SESSION_DRIVER=database` o `CACHE_STORE=database` sin haber migrado esas tablas.
- **Solución:** Mantén `SESSION_DRIVER=file` y `CACHE_STORE=file` en `.env` (como en `.env.example`).

---

## 📄 Licencia

Este proyecto es de código abierto bajo la licencia MIT (declarada en `composer.json`).
