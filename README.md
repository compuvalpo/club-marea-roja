<p align="center">
  <img src="frontend/public/brand/logo-horizontal.svg" alt="Marea Roja · Club Deportivo" width="320">
</p>

# Marea Roja · Sistema de administración deportiva

Aplicación web para administrar un club deportivo
Principales funcionalidades:
**Finanzas**: registro de ingresos, egresos, gastos fijos y mensualidades (pagadas y pendientes).
**Prácticas**: registro regulares de prácticas, que permite calendarizar las actividades.
**Eventos**: se pueden agregar eventos ocasionales en distintos recintos.
**Estadísticas**: permite registrar y ver las estadisticas de los alumnos.
**Roles**: Administrador, Entrenador, Apoderado y Alumno.

Esta guía explica cómo instalar todo lo necesario para poner el sitio en marcha en local, **para Windows 10/11**,.
No necesita experiencia previa: cada comando se muestra tanto para **CMD** como para **PowerShell**.

---

## Índice

1. [Qué se va a instalar y por qué](#1-qué-se-va-a-instalar-y-por-qué)
2. [Conceptos básicos antes de empezar](#2-conceptos-básicos-antes-de-empezar)
3. [Instalar PHP](#3-instalar-php)
4. [Habilitar extensiones en `php.ini`](#4-habilitar-extensiones-en-phpini)
5. [Instalar Composer](#5-instalar-composer)
6. [Instalar Node.js](#6-instalar-nodejs)
7. [Verificar todas las versiones instaladas](#7-verificar-todas-las-versiones-instaladas)
8. [Instalar el proyecto](#8-instalar-el-proyecto)
9. [Ejecutar el sitio en desarrollo](#9-ejecutar-el-sitio-en-desarrollo)
10. [Compilar para producción](#10-compilar-para-producción)
11. [Pruebas y calidad del código](#11-pruebas-y-calidad-del-código)
12. [Estructura del proyecto y API](#12-estructura-del-proyecto-y-api)
13. [Solución de problemas](#13-solución-de-problemas)

---

## 1. Qué se va a instalar y por qué

| Programa | Versión mínima | Para qué sirve en este proyecto |
|----------|----------------|---------------------------------|
| **PHP** | 8.3 (recomendado 8.4) | Lenguaje en que está escrito el *backend* (la API, con el framework Laravel 13). |
| **Composer** | 2.x | Descarga las librerías PHP que usa el backend (Laravel, JWT, PHPUnit…). |
| **Node.js** (incluye **npm**) | 20 o superior (recomendado la versión **LTS**) | Ejecuta las herramientas del *frontend* (Vite, ESLint, Cypress) y descarga sus librerías. |
| Navegador | Chrome, Edge o Firefox actual | Para usar el sitio. |

Todo es software libre y gratuito. Necesitará conexión a Internet y unos **2 GB** libres en disco.

| Capa | Tecnología |
|------|------------|
| Backend (API REST) | **Laravel 13** (PHP 8.3+), Eloquent (SQLite / MySQL / PostgreSQL), JWT (`firebase/php-jwt`) |
| Frontend (SPA) | JavaScript moderno, Vite, FullCalendar 6, Chart.js 4, iconos Lucide (licencias MIT/ISC) |
| Calidad | PHPUnit 12, Larastan (PHPStan), Laravel Pint, ESLint, Prettier, Vitest, Cypress |

---

## 2. Conceptos básicos antes de empezar

### 2.1 Abrir una terminal

Una **terminal** es una ventana donde se escriben comandos. Windows trae dos:

| Terminal | Cómo abrirla | Cómo se reconoce |
|----------|--------------|------------------|
| **CMD** (Símbolo del sistema) | Tecla <kbd>Windows</kbd> → escriba `cmd` → <kbd>Enter</kbd> | El texto empieza con `C:\Users\SuNombre>` |
| **PowerShell** | Tecla <kbd>Windows</kbd> → escriba `powershell` → <kbd>Enter</kbd> | El texto empieza con `PS C:\Users\SuNombre>` |

> **Terminal de Windows (Windows 11):** clic derecho en el botón Inicio → *Terminal*. Por defecto abre PowerShell;
> con la flecha ▾ de la barra superior puede abrir una pestaña de CMD.
>
> **Como administrador:** algunos pasos lo requieren. Busque `cmd` o `powershell`, haga **clic derecho →
> Ejecutar como administrador** y acepte el aviso.

En esta guía, cada bloque indica para qué terminal es. Cuando el comando es idéntico en ambas, se muestra una sola vez.
Copie el comando, péguelo en la terminal (clic derecho o <kbd>Ctrl</kbd>+<kbd>V</kbd>) y presione <kbd>Enter</kbd>.

### 2.2 El PATH y por qué hay que cerrar la terminal

Windows busca los programas (como `php` o `node`) en una lista de carpetas llamada **PATH**. Cuando un instalador
agrega una carpeta al PATH, **sólo las terminales abiertas después** lo ven. Regla de oro:

> **Después de instalar cualquier programa, cierre todas las terminales y abra una nueva.**

Si un comando responde *"no se reconoce como un comando interno o externo"* (CMD) o *"The term … is not recognized"*
(PowerShell), casi siempre es porque la terminal se abrió antes de instalar, o porque el programa no quedó en el PATH.

### 2.3 winget (instalador de Windows)

Windows 10 (21H1 o superior) y Windows 11 incluyen **winget**, que instala programas con un comando.
Compruebe si lo tiene:

```bat
winget --version
```

Si muestra un número (p. ej. `v1.9.25200`), puede usar las opciones «con comandos» de esta guía. Si no, instale
*App Installer* desde Microsoft Store o use las opciones «desde la web».

---

## 3. Instalar PHP

Elija **una** de las tres opciones.

### Opción A — Con comandos (winget), recomendada

Funciona igual en **CMD** y **PowerShell**:

```bat
winget install --id PHP.PHP.8.4 -e
```

Acepte los términos si se le pregunta (`Y`). winget instala PHP como aplicación portable y lo agrega al PATH.
**Cierre la terminal y abra una nueva.**

### Opción B — Desde la web (archivo .zip)

1. Instale primero el **Microsoft Visual C++ Redistributable (x64)**, que PHP necesita:
   <https://aka.ms/vs/17/release/vc_redist.x64.exe> → ejecútelo → *Instalar*.
2. Entre a <https://windows.php.net/download/>.
3. En la sección **PHP 8.4** (o 8.3), bajo **VS17 x64 Non Thread Safe**, haga clic en **Zip** para descargarlo.
4. Cree la carpeta `C:\php` y **descomprima** ahí el contenido del zip (debe quedar `C:\php\php.exe`).
5. Agregue `C:\php` al PATH. Puede hacerlo con el mouse **o** con un comando:

   **Con el mouse:** tecla Windows → escriba *variables de entorno* → **Editar las variables de entorno del sistema**
   → botón **Variables de entorno…** → en *Variables de usuario*, seleccione **Path** → **Editar** → **Nuevo** →
   escriba `C:\php` → **Aceptar** en las tres ventanas.

   **Con un comando** (agrega `C:\php` al PATH de su usuario):

   *PowerShell:*
   ```powershell
   [Environment]::SetEnvironmentVariable("Path", [Environment]::GetEnvironmentVariable("Path", "User") + ";C:\php", "User")
   ```

   *CMD:*
   ```bat
   powershell -NoProfile -Command "[Environment]::SetEnvironmentVariable('Path', [Environment]::GetEnvironmentVariable('Path','User') + ';C:\php', 'User')"
   ```

   > Evite `setx PATH "%PATH%;C:\php"`: puede truncar el PATH si es largo.

6. **Cierre la terminal y abra una nueva.**

### Opción C — Con XAMPP

[XAMPP](https://www.apachefriends.org/es/index.html) es un paquete que instala PHP (más Apache y MySQL) con un asistente.
Para este proyecto sólo se necesita PHP, pero es una buena opción si ya usa XAMPP.

1. Entre a <https://www.apachefriends.org/es/index.html> y haga clic en **Descargar para Windows**.
   Elija una versión con **PHP 8.3 o superior** (Laravel 13 no funciona con versiones menores).
2. Ejecute el instalador. Si Windows muestra una advertencia sobre el *Control de cuentas de usuario*, acepte y continúe.
3. En la lista de componentes puede dejar los predeterminados (con **PHP** marcado).
4. Como carpeta de destino use **`C:\xampp`** (la ruta predeterminada). Termine el asistente.
5. Compruebe que existe el archivo `C:\xampp\php\php.exe`.
6. Agregue `C:\xampp\php` al PATH. Puede hacerlo con el mouse **o** con un comando:

   **Con el mouse:** tecla Windows → escriba *variables de entorno* → **Editar las variables de entorno del sistema**
   → botón **Variables de entorno…** → en *Variables de usuario*, seleccione **Path** → **Editar** → **Nuevo** →
   escriba `C:\xampp\php` → **Aceptar** en las tres ventanas.

   **Con un comando** (agrega `C:\xampp\php` al PATH de su usuario):

   *PowerShell:*
   ```powershell
   [Environment]::SetEnvironmentVariable("Path", [Environment]::GetEnvironmentVariable("Path", "User") + ";C:\xampp\php", "User")
   ```

   *CMD:*
   ```bat
   powershell -NoProfile -Command "[Environment]::SetEnvironmentVariable('Path', [Environment]::GetEnvironmentVariable('Path','User') + ';C:\xampp\php', 'User')"
   ```

   > Evite `setx PATH "%PATH%;C:\xampp\php"`: puede truncar el PATH si es largo.

7. **Cierre la terminal y abra una nueva.**
8. Compruebe con `where php`: debe aparecer `C:\xampp\php\php.exe`. Si aparece otra ruta primero, hay otro PHP instalado
   que tiene prioridad; quítelo del PATH o mueva `C:\xampp\php` más arriba en la lista.

> **Notas sobre XAMPP**
> - No es necesario iniciar Apache ni MySQL: el proyecto usa su propio servidor (`php artisan serve`) y SQLite.
> - Su `php.ini` queda en `C:\xampp\php\php.ini`. Suele traer `pdo_sqlite` y `mbstring` activadas, pero confírmelo en la sección 4.
> - Si instaló XAMPP en otra carpeta (por ejemplo `D:\xampp`), reemplace `C:\xampp\php` por esa ruta.

### Verificar PHP

```bat
php -v
```

Debe ver algo como `PHP 8.4.x (cli) …`. Si la versión es **menor a 8.3**, Laravel 13 no funcionará: instale una más nueva.

---

## 4. Habilitar extensiones en `php.ini`

PHP trae funciones opcionales llamadas **extensiones**. Este proyecto necesita, entre otras, **`pdo_sqlite`**
(base de datos SQLite) y **`mbstring`** (textos con tildes y ñ). Se activan en el archivo de configuración **`php.ini`**.

### 4.1 Encontrar (o crear) `php.ini`

```bat
php --ini
```

Fíjese en la línea **`Loaded Configuration File`**:

- Si muestra una ruta (p. ej. `C:\php\php.ini`), ese es su archivo: vaya al paso 4.2.
- Si dice **`(none)`**, el archivo no existe todavía. La línea `Configuration File (php.ini) Path` indica la carpeta
  donde debe crearlo. Ahí hay una plantilla llamada `php.ini-development`; cópiela como `php.ini`:

  *CMD* (reemplace `C:\php` por la carpeta que mostró el comando):
  ```bat
  copy C:\php\php.ini-development C:\php\php.ini
  ```

  *PowerShell:*
  ```powershell
  Copy-Item C:\php\php.ini-development C:\php\php.ini
  ```

> **¿Instaló PHP con winget y no sabe la carpeta?** Ejecute `where php` (CMD) o `Get-Command php` (PowerShell);
> la carpeta es la que contiene `php.exe`. Si `where php` muestra un acceso directo dentro de `...\WinGet\Links`,
> use la ruta que indique `php --ini` en *Configuration File (php.ini) Path*.

### 4.2 Editar `php.ini` a mano (con el Bloc de notas)

1. Abra el archivo (ajuste la ruta si es distinta):

   ```bat
   notepad C:\php\php.ini
   ```

   > Si PHP está en una carpeta protegida (p. ej. `C:\Program Files`), abra la terminal **como administrador**.

2. Pulse <kbd>Ctrl</kbd>+<kbd>F</kbd> y busque cada una de estas líneas. En `php.ini`, una línea que empieza con
   **punto y coma (`;`) está desactivada**. Para activarla, **borre el `;` del inicio**:

   | Busque esta línea | Déjela así |
   |-------------------|------------|
   | `;extension_dir = "ext"` | `extension_dir = "ext"` |
   | `;extension=curl` | `extension=curl` |
   | `;extension=fileinfo` | `extension=fileinfo` |
   | `;extension=mbstring` | **`extension=mbstring`** |
   | `;extension=openssl` | `extension=openssl` |
   | `;extension=pdo_sqlite` | **`extension=pdo_sqlite`** |
   | `;extension=sqlite3` | `extension=sqlite3` |
   | `;extension=zip` | `extension=zip` |

   > Ojo: en la sección de Windows hay **dos** líneas `extension_dir`; descomente sólo la que dice `"ext"`.
   > `curl`, `fileinfo`, `openssl` y `zip` también los usan Laravel y Composer.

3. Guarde con <kbd>Ctrl</kbd>+<kbd>S</kbd> y cierre el Bloc de notas.

### 4.3 Alternativa: activarlas con un solo comando (PowerShell)

Si prefiere no editar a mano, este comando de **PowerShell** quita el `;` de las líneas necesarias.
Cambie la ruta de `$ini` si su archivo está en otra carpeta:

```powershell
$ini = "C:\php\php.ini"
$ext = 'curl','fileinfo','mbstring','openssl','pdo_sqlite','sqlite3','zip'
$c = Get-Content $ini
$c = $c -replace '^;\s*extension_dir\s*=\s*"ext"', 'extension_dir = "ext"'
foreach ($e in $ext) { $c = $c -replace "^;\s*extension=$e\s*$", "extension=$e" }
Set-Content -Path $ini -Value $c -Encoding ascii
```

Desde **CMD**, abra PowerShell escribiendo `powershell` y pegue el bloque anterior; luego escriba `exit` para volver.

### 4.4 Verificar las extensiones

*CMD:*
```bat
php -m | findstr /I "pdo_sqlite mbstring openssl fileinfo curl"
```

*PowerShell:*
```powershell
php -m | Select-String -Pattern "pdo_sqlite|mbstring|openssl|fileinfo|curl"
```

Deben aparecer las cinco. Si falta alguna, revise que la línea no tenga `;` y que `extension_dir = "ext"` esté activo.

---

## 5. Instalar Composer

Composer necesita PHP, así que **instale y verifique PHP primero** (pasos 3 y 4).

### Opción A — Desde la web (instalador), recomendada

1. Entre a <https://getcomposer.org/download/>.
2. En la sección **Windows Installer**, descargue **`Composer-Setup.exe`** y ejecútelo.
3. Elija **Install for all users** (recomendado) o sólo para su usuario.
4. En **Settings Check**, el instalador busca `php.exe`. Verifique que la ruta sea la de su PHP
   (p. ej. `C:\php\php.exe`); si no la encuentra, use **Browse** para elegirla.
   Deje marcada la opción que agrega PHP al PATH si aparece.
5. Si le ofrece configurar un proxy, déjelo en blanco (salvo que su red lo requiera).
6. **Next → Install → Finish.**
7. **Cierre la terminal y abra una nueva.**

### Opción B — Con comandos (winget)

```bat
winget install --id Composer.Composer -e
```

Cierre la terminal y abra una nueva.

### Opción C — Sin instalador (sólo si las anteriores fallan)

Desde la carpeta `C:\php` (*PowerShell*):

```powershell
cd C:\php
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
Set-Content -Path C:\php\composer.bat -Value '@php "%~dp0composer.phar" %*' -Encoding ascii
```

En *CMD* son los mismos comandos `cd` y `php …`; el último reemplácelo por:

```bat
echo @php "%~dp0composer.phar" %*> C:\php\composer.bat
```

Como `C:\php` ya está en el PATH, el comando `composer` quedará disponible.

### Verificar Composer

```bat
composer --version
```

Debe ver `Composer version 2.x.x …`. Si dice que la extensión `openssl` no está disponible, vuelva al paso 4.

---

## 6. Instalar Node.js

Node.js incluye **npm**, el gestor de paquetes de JavaScript.

### Opción A — Desde la web (instalador .msi), recomendada

1. Entre a <https://nodejs.org/>.
2. Descargue la versión marcada como **LTS** (soporte a largo plazo) para **Windows (x64)**, en formato **.msi**.
3. Ejecute el instalador: **Next**, acepte la licencia, deje las opciones por defecto (incluye *npm* y
   *Add to PATH*) → **Install → Finish**.
   La casilla *"Automatically install the necessary tools"* **no** es necesaria para este proyecto.
4. **Cierre la terminal y abra una nueva.**

### Opción B — Con comandos (winget)

```bat
winget install --id OpenJS.NodeJS.LTS -e
```

Cierre la terminal y abra una nueva.

### Opción C — Con NVM (administrador de versiones de Node.js)

**NVM** (*Node Version Manager*) permite instalar varias versiones de Node.js y cambiar entre ellas con un comando.
Es útil si trabaja en otros proyectos que requieren versiones distintas. En Windows se usa **NVM para Windows**.

1. **Desinstale** antes cualquier Node.js instalado con las opciones A o B (*Configuración → Aplicaciones →
   Aplicaciones instaladas → Node.js → Desinstalar*), porque puede entrar en conflicto con NVM.
2. Entre a <https://www.nvmnode.com/es/> y descargue el instalador para Windows (`nvm-setup.exe`).
3. Ejecute el instalador con las opciones por defecto (acepte la licencia y las carpetas propuestas) → **Install → Finish**.
4. **Cierre la terminal y abra una nueva, como administrador** (tecla Windows → escriba *cmd* o *PowerShell* →
   clic derecho → **Ejecutar como administrador**). NVM necesita este permiso para cambiar de versión.
5. Verifique NVM e instale Node.js LTS (funciona igual en **CMD** y **PowerShell**):

   ```bat
   nvm version
   nvm install lts
   nvm use lts
   ```

Si `nvm use lts` no es reconocido por su versión de NVM, use el número exacto que muestra `nvm list available`,
por ejemplo `nvm install 24.11.0` y luego `nvm use 24.11.0`.

6. Comandos útiles de NVM:

| Comando | Qué hace |
|---------|----------|
| `nvm list` | Muestra las versiones de Node.js instaladas (la activa lleva `*`) |
| `nvm list available` | Muestra las versiones disponibles para instalar |
| `nvm install <versión>` | Instala una versión |
| `nvm use <versión>` | Activa una versión |
| `nvm uninstall <versión>` | Desinstala una versión |

> Se requiere Node **20 o superior** para este proyecto.

### Verificar Node.js y npm

```bat
node -v
```

Debe ver, por ejemplo, `v24.x.x`.

### Solo PowerShell: permitir la ejecución de `npm`

Si en PowerShell aparece *"…npm.ps1 no se puede cargar porque la ejecución de scripts está deshabilitada en este
sistema"*, ejecute **una vez**:

```powershell
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
```

Responda `S` (o `Y`) y vuelva a intentar. En CMD este problema no ocurre.

---

## 7. Verificar todas las versiones instaladas

Abra una **terminal nueva** y ejecute (funciona igual en CMD y PowerShell):

```bat
php -v
php -m
composer --version
node -v
npm -v
```

| Comando | Resultado esperado |
|---------|--------------------|
| `php -v` | `PHP 8.3.x` o `PHP 8.4.x` (o superior) |
| `php -m` | La lista incluye `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `curl` |
| `composer --version` | `Composer version 2.x.x` |
| `node -v` | `v20.x.x` o superior (idealmente la LTS) |
| `npm -v` | Un número de versión, p. ej. `10.x.x` u `11.x.x` |

Para ver **dónde** está instalado cada programa:

*CMD:*
```bat
where php
where composer
where node
```

*PowerShell:*
```powershell
Get-Command php, composer, node | Select-Object Name, Source
```

---

## 8. Instalar el proyecto

### Obtener el código fuente (mediante comandos GIT)
Clonar el proyecto con el comando:
```bash
git clone https://github.com/compuvalpo/club-marea-roja.git
```
<https://www.youtube.com/shorts/kRfzz1qk6mM>

En los ejemplos, el proyecto está en `Documentos\git\club-marea-roja`. 
**Si lo tiene en otra carpeta, cambie la ruta.**

> **Tip:** en el Explorador de archivos puede abrir la carpeta, hacer clic en la barra de direcciones, escribir
> `cmd` (o `powershell`) y presionar <kbd>Enter</kbd>: se abre una terminal ya ubicada en esa carpeta.

### Obtener el código fuente (mediante GitHub Desktop)
Puedes utilizar GitHub Desktop para clonar el repositorio
Descargar GitHub Desktop desde <https://desktop.github.com/download/>

Sigue las indicaciones del siguiente video
<https://www.youtube.com/watch?v=2MG-0D-Jyc8>


### 8.1 Backend (API Laravel)

*CMD:*
```bat
cd /d "%USERPROFILE%\Documents\git\club-marea-roja\backend"
composer install
copy .env.example .env
php artisan key:generate
php artisan jwt:secret
type nul > database\database.sqlite
php artisan migrate:fresh --seed
```

*PowerShell:*
```powershell
cd "$HOME\Documents\git\club-marea-roja\backend"
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan jwt:secret
New-Item -ItemType File -Path database\database.sqlite -Force
php artisan migrate:fresh --seed
```

Qué hace cada comando:

| Comando | Explicación |
|---------|-------------|
| `cd …` | Entra a la carpeta del backend. |
| `composer install` | Descarga Laravel y las demás librerías a la carpeta `vendor` (tarda unos minutos la primera vez). Al no venir `composer.lock`, Composer elige versiones compatibles con su PHP y crea ese archivo. |
| `copy .env.example .env` | Crea el archivo de configuración local `.env` a partir de la plantilla. |
| `php artisan key:generate` | Genera la clave de cifrado de Laravel (`APP_KEY`). |
| `php artisan jwt:secret` | Genera el secreto para firmar los tokens de sesión (`JWT_SECRET`). |
| `type nul > …` / `New-Item …` | Crea el archivo vacío de la base de datos SQLite. |
| `php artisan migrate:fresh --seed` | Crea las tablas y carga datos de demostración. |

> Si `composer install` muestra *"the requested PHP extension … is missing"*, active esa extensión (paso 4) y repita.

### 8.2 Frontend (sitio web)

*CMD:*
```bat
cd /d "%USERPROFILE%\Documents\git\club-marea-roja\frontend"
npm install
```

*PowerShell:*
```powershell
cd "$HOME\Documents\git\club-marea-roja\frontend"
npm install
```

`npm install` descarga las librerías de JavaScript a la carpeta `node_modules` (incluye Cypress, unos 500 MB).

---

## 9. Ejecutar el sitio en desarrollo

Se necesitan **dos terminales abiertas al mismo tiempo**: una para la API y otra para el sitio.
No las cierre mientras use el sistema.

**Terminal 1 — API** (queda escuchando en `http://127.0.0.1:8080`):

*CMD:*
```bat
cd /d "%USERPROFILE%\Documents\git\club-marea-roja\backend"
php artisan serve --host=127.0.0.1 --port=8080
```

*PowerShell:*
```powershell
cd "$HOME\Documents\git\club-marea-roja\backend"
php artisan serve --host=127.0.0.1 --port=8080
```

**Terminal 2 — Sitio web** (queda en `http://localhost:5173`):

*CMD:*
```bat
cd /d "%USERPROFILE%\Documents\git\club-marea-roja\frontend"
npm run dev
```

*PowerShell:*
```powershell
cd "$HOME\Documents\git\club-marea-roja\frontend"
npm run dev
```

Abra el navegador en **<http://localhost:5173>**. Cuentas de demostración (contraseña **`Marea2026!`**):

| Rol | Correo |
|-----|--------|
| Administrador | `admin@marea.cl` |
| Entrenador | `entrenador@marea.cl` |
| Apoderado | `apoderado@marea.cl` |
| Alumno | `alumno@marea.cl` |

Para **detener** cualquiera de los dos servidores, vaya a su terminal y presione <kbd>Ctrl</kbd>+<kbd>C</kbd>
(en CMD confirme con `S` si se lo pregunta).

Para **volver a los datos de demostración** iniciales (borra todo lo ingresado), en la carpeta `backend`:

```bat
php artisan migrate:fresh --seed
```

> **VS Code:** si abre la carpeta `web` en Visual Studio Code, puede usar *Terminal → Run Task… → Levantar todo*
> para iniciar ambos servidores. Las extensiones recomendadas se ofrecen automáticamente al abrir la carpeta.

---

## 10. Compilar para producción

**Backend** (en el servidor, dentro de `backend`):

```bat
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan jwt:secret
php artisan migrate --force
php artisan club:create-admin admin@miclub.cl "Nombre Admin"
php artisan config:cache
php artisan route:cache
```

Antes, edite `.env` con los valores de producción: `APP_ENV=production`, `APP_DEBUG=false`, `COOKIE_SECURE=true`,
la URL real en `APP_URL` y `CORS_ORIGINS`, y la base de datos (ver abajo). El comando `club:create-admin` le pedirá
la contraseña.

**Frontend** (dentro de `frontend`):

```bat
npm ci
npm run build
```

El sitio compilado queda en `frontend\dist`: son archivos estáticos que se publican en el servidor web.

### Despliegue sugerido (mismo dominio)

Servir `frontend/dist` como sitio estático y redirigir `/api` al backend (`backend/public`), para que la cookie de
sesión funcione. Ejemplo **Nginx**:

```nginx
server {
    server_name club.example.cl;
    root /var/www/marea/web/frontend/dist;

    location / { try_files $uri /index.html; }

    location /api/ {
        root /var/www/marea/web/backend/public;
        try_files $uri /index.php$is_args$args;
    }
    location ~ ^/index\.php$ {
        root /var/www/marea/web/backend/public;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    }
}
```

Con **Apache** (o IIS con URL Rewrite), apunte la API a `backend/public` (incluye el `.htaccess` de Laravel). Dé
permisos de escritura a `storage\` y `bootstrap\cache\`. Use siempre **HTTPS** (`COOKIE_SECURE=true`).

### Usar MySQL o PostgreSQL en vez de SQLite

En `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marea
DB_USERNAME=marea
DB_PASSWORD=********
```

Active en `php.ini` la extensión correspondiente (`extension=pdo_mysql` o `extension=pdo_pgsql`) y ejecute
`php artisan migrate --force`.

---

## 11. Pruebas y calidad del código

**Backend** (en `backend`, CMD o PowerShell):

```bat
composer test
composer lint
composer format
composer analyse
```

| Comando | Qué hace |
|---------|----------|
| `composer test` | Ejecuta las pruebas PHPUnit (unitarias e integración de la API, con base de datos en memoria). |
| `composer lint` | Revisa el estilo del código PHP con Laravel Pint (no cambia nada). |
| `composer format` | Corrige automáticamente el estilo del código PHP. |
| `composer analyse` | Análisis estático con Larastan (PHPStan) nivel 6. |

**Frontend** (en `frontend`):

```bat
npm test
npm run lint
npm run format:check
npm run e2e
```

| Comando | Qué hace |
|---------|----------|
| `npm test` | Pruebas unitarias de JavaScript (Vitest). |
| `npm run lint` | Revisa el código con ESLint. |
| `npm run format:check` | Revisa el formato con Prettier (`npm run format` lo corrige). |
| `npm run e2e` | Pruebas de punta a punta con **Cypress**: reinicia la base de datos, levanta la API y el sitio, y recorre los flujos de cada rol. |
| `npm run cy:open` | Abre Cypress en modo visual (con los dos servidores ya levantados, paso 9). |

> Cierre los servidores del paso 9 antes de `npm run e2e`, porque usa los mismos puertos (8080 y 5173).
> La primera vez Cypress descarga su programa (~500 MB); si no se descargó, ejecute `npx cypress install`.

---

## 12. Estructura del proyecto y API

```
web/
├── backend/          API REST Laravel (puerto 8080)
│   ├── app/                 Http (Controllers, Middleware, Resources) · Models · Policies · Services · Enums
│   ├── bootstrap/app.php    middleware y formato JSON de errores
│   ├── config/jwt.php       configuración de tokens
│   ├── database/            migraciones y datos de demostración (DemoSeeder)
│   ├── lang/es/             mensajes de validación en español
│   ├── routes/api.php       tabla de rutas y roles · routes/console.php: jwt:secret, club:create-admin
│   └── tests/               Unit + Feature (PHPUnit)
├── frontend/         SPA (puerto 5173)
│   ├── public/brand/        logos del club (SVG/PNG) · favicon
│   ├── src/                 api · ui (incluye icons.js) · views · styles
│   ├── tests/               Vitest
│   └── cypress/             pruebas E2E
├── CLAUDE.md         reglas de programación
└── api.http          ejemplos de peticiones (extensión REST Client de VS Code)
```

Base de la API: `/api/v1`. Autenticación con el encabezado `Authorization: Bearer <access_token>`.

| Método | Ruta | Roles |
|--------|------|-------|
| POST | `/auth/login`, `/auth/refresh`, `/auth/logout` | público (cookie, Origin verificado) |
| GET | `/auth/me`, `/dashboard` | todos |
| GET/POST/PATCH/DELETE | `/users` · POST `/users/{id}/revoke-sessions` | admin |
| GET | `/athletes`, `/athletes/{id}`, `/athletes/{id}/stats` | todos (cada uno ve sólo lo suyo) |
| POST/PATCH/DELETE | `/athletes` | admin |
| GET · POST/PATCH/DELETE | `/venues`, `/groups` | todos · admin |
| GET/POST/PATCH/DELETE · POST | `/schedules` · `/schedules/{id}/generate` | admin |
| GET | `/practices?from&to`, `/practices/{id}` | todos (filtrado) |
| PATCH | `/practices/{id}` | admin, entrenador asignado |
| PUT | `/practices/{id}/attendance` | admin, entrenador asignado |
| POST | `/practices/{id}/measurements`, `/practices/{id}/incidents` | admin, entrenador asignado |
| POST | `/practices/{id}/rsvp` | apoderado (pupilos), alumno (propio) |
| GET · POST/PATCH/DELETE | `/events` | todos · admin |
| POST | `/events/{id}/participants` · `/events/{id}/rsvp` | admin · apoderado/alumno |
| GET | `/me/charges` | apoderado, alumno |
| * | `/finance/charges`, `/finance/charges/generate`, `/finance/payments`, `/finance/delinquents`, `/finance/summary`, `/finance/transactions`, `/finance/fixed-expenses`, `/finance/fixed-expenses/apply` | admin |


### 12.1 Seguridad de sesiones, roles y permisos

Resumen de cómo protege el sistema las sesiones (alineado con OWASP ASVS 5.0, OWASP Session Management, RFC 8725
y RFC 9068 para JWT, y RFC 9700 para tokens de renovación):

| Tema | Cómo funciona |
|------|---------------|
| **Access token** | JWT firmado (HS256, secreto de 256 bits) con `typ: at+jwt`, `iss`, `aud`, `sub`, `sid`, `exp` y `jti`. Dura 15 minutos (`JWT_ACCESS_TTL`) y vive sólo en la memoria de la pestaña (nunca en `localStorage`). |
| **Renovación** | Cuando el token está por vencer o la API responde 401, el sitio pide uno nuevo con el *refresh token* (cookie `HttpOnly; SameSite=Strict`). Cada renovación entrega un refresh token nuevo; reutilizar uno viejo cierra la sesión (posible robo). Las pestañas se coordinan con la Web Locks API para no renovar a la vez. |
| **Sesión en servidor** | Cada ingreso crea una sesión (tabla `auth_sessions`). La API comprueba en **cada petición** que la sesión siga vigente, por lo que un cierre o revocación se aplica de inmediato, sin esperar a que el token expire. |
| **Expiración** | Se cierra tras 30 minutos sin actividad (`JWT_IDLE_TTL`) y, aunque haya actividad, a las 8 horas (`JWT_SESSION_TTL`). Al volver a la pantalla de ingreso se muestra el motivo. |
| **Una sesión por usuario** | Iniciar sesión en otra pestaña u otro equipo cierra la sesión anterior (`JWT_SINGLE_SESSION=true`). Si en el mismo navegador entra otra persona en otra pestaña, la pestaña anterior también se cierra, así las sesiones nunca se cruzan. |
| **Revocación de permisos** | Si el administrador cambia el rol, el correo o la contraseña de un usuario, o lo desactiva o elimina, sus sesiones se cierran al instante. En **Usuarios** el botón **Cerrar sesiones** fuerza el cierre manual. El administrador no puede quitarse su propio rol ni desactivarse. |
| **Roles y permisos** | Una única matriz de permisos (`backend/app/Auth/Permissions.php`) protege las rutas de la API (`can:permiso`). El sitio recibe esos mismos permisos al ingresar y los usa para el menú, las rutas y los botones. Ocultar un botón no es la protección: la API vuelve a validar el permiso y además la pertenencia de cada registro (Policies). |
| **Inyección SQL** | Todas las consultas usan Eloquent / Query Builder con parámetros enlazados y cada entrada se valida (tipos, formatos y listas permitidas). |
| **CORS y CSRF** | CORS con lista blanca (`CORS_ORIGINS`, sin `*`). Los endpoints que usan la cookie rechazan otros orígenes (`Origin` / `Sec-Fetch-Site`). |
| **Fuerza bruta** | Bloqueo de 15 minutos tras 5 intentos fallidos por cuenta, límite por IP y respuesta de igual duración exista o no la cuenta. |

> Para producción: sirva el sitio y la API desde el **mismo dominio con HTTPS**, use `COOKIE_SECURE=true`,
> `APP_DEBUG=false` y configure en el servidor web las cabeceras `Content-Security-Policy` (con `frame-ancestors 'none'`)
> y `Strict-Transport-Security`.

---

## 13. Solución de problemas

| Mensaje o síntoma | Causa y solución |
|-------------------|------------------|
| `"php" no se reconoce como un comando interno o externo` (CMD) / `The term 'php' is not recognized` (PowerShell) | PHP no está en el PATH o la terminal es anterior a la instalación. Cierre y abra la terminal; si sigue, revise el paso 3 (PATH). Lo mismo aplica a `composer`, `node` y `npm`. |
| Al ejecutar `php` aparece un error de `VCRUNTIME140.dll` | Falta el Visual C++ Redistributable: instálelo (paso 3, opción B.1). |
| `could not find driver` | Falta `extension=pdo_sqlite` (o el driver de su base de datos) en `php.ini` (paso 4). |
| `Call to undefined function mb_…` o error de `mbstring` | Falta `extension=mbstring` en `php.ini` (paso 4). |
| `composer install` dice que falta una extensión (`ext-…`) | Actívela en `php.ini` y repita. Verifique con `php -m`. |
| `Your requirements could not be resolved … php >=8.4` | Su PHP es 8.3: está bien, ejecute `composer update` para que elija versiones compatibles. Si es menor a 8.3, actualice PHP. |
| `…npm.ps1 no se puede cargar porque la ejecución de scripts está deshabilitada` | Sólo PowerShell: ejecute `Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned` (paso 6) o use CMD. |
| `JWT_SECRET debe tener al menos 32 caracteres` | Ejecute `php artisan jwt:secret` en `backend`. |
| `no such table: auth_sessions` o error 500 al ingresar tras actualizar el proyecto | Falta la migración nueva de sesiones: en `backend` ejecute `php artisan migrate` (o `php artisan migrate:fresh --seed` para reiniciar los datos de demostración). |
| Al volver a la pantalla de ingreso aparece «Se inició sesión en otra pestaña o dispositivo» | Es el comportamiento esperado: sólo se permite una sesión por usuario. Para permitir varias, use `JWT_SINGLE_SESSION=false` en `.env` y ejecute `php artisan config:clear`. |
| «La sesión se cerró por inactividad» | Pasaron más de 30 minutos sin uso. Ajuste `JWT_IDLE_TTL` (segundos) si lo necesita. |
| «Sus permisos cambiaron. Inicie sesión nuevamente» | Un administrador cambió su rol, correo o contraseña, o lo desactivó. Vuelva a ingresar. |
| `Demasiadas solicitudes` / `Demasiados intentos fallidos` | Se activó el límite de intentos. Espere unos minutos. En desarrollo puede limpiarlo con `php artisan cache:clear`. |
| `Origen no permitido` (403) al ingresar | Entre por la dirección configurada en `CORS_ORIGINS` (por defecto `http://localhost:5173`). |
| `No application encryption key has been specified` | Ejecute `php artisan key:generate` en `backend`. |
| `Database file at path … does not exist` | Cree el archivo SQLite (paso 8.1: `type nul > database\database.sqlite`) y repita la migración. |
| Cambié `.env` y no se refleja | `php artisan config:clear`. |
| La página carga pero no puedo iniciar sesión | La API no está corriendo: revise la Terminal 1 del paso 9. |
| La sesión se pierde al recargar | Entre por `http://localhost:5173` (no por `:8080`). En producción con HTTPS use `COOKIE_SECURE=true`. |
| `Port 5173 is in use` / `Failed to listen on 127.0.0.1:8080` | Ya hay otra copia corriendo: ciérrela con <kbd>Ctrl</kbd>+<kbd>C</kbd> en su terminal, o reinicie el equipo. |
| `The Composer installer script was not successful … OpenSSL failed with a 'certificate verify failed' error` (o `curl error 60: SSL certificate problem`) | Un antivirus (Avast, ESET, Kaspersky…), una VPN o un proxy reemplaza los certificados HTTPS. Vea la sección **13.1** más abajo. |
| Cypress no abre | Ejecute `npx cypress install` en `frontend`. |

### 13.1 Error de certificado SSL al instalar Composer (`certificate verify failed`)

**Mensaje típico** (instalador de Composer, o `composer install` / `composer update`):

```text
The Composer installer script was not successful [exit code 1].

OpenSSL failed with a 'certificate verify failed' error. This indicates a problem with the
Certificate Authority file(s) on your system, which may be out of date.

The "https://getcomposer.org/versions" file could not be downloaded: SSL operation failed with code 1.
error:0A000086:SSL routines::certificate verify failed
Failed to enable crypto
```

**Por qué ocurre.** PHP (también el de XAMPP) comprueba los sitios HTTPS contra su propia lista de certificados
(`cacert.pem`), no contra la de Windows. Algunos antivirus (**Avast**, ESET, Kaspersky, Bitdefender, Norton), VPN y
proxies institucionales *analizan* el tráfico HTTPS y lo vuelven a firmar con su propio certificado. Windows y el
navegador confían en ese certificado porque el antivirus lo instala; PHP no, y por eso falla. Un síntoma típico:
`curl.exe -I https://getcomposer.org` funciona, pero `php` no.

#### Paso 1: usar un `cacert.pem` actualizado (siempre se recomienda)

1. Descargue la lista de certificados (en PowerShell o CMD; `curl.exe` viene con Windows):

   ```powershell
   mkdir C:\xampp\php\extras\ssl
   curl.exe -L -o C:\xampp\php\extras\ssl\cacert.pem https://curl.se/ca/cacert.pem
   ```

   (Si instaló PHP en otra carpeta, por ejemplo `C:\php`, use esa carpeta.)

2. Abra `php.ini` (`php --ini` muestra su ubicación) y deje **activas** (sin `;` al inicio) estas dos líneas con la ruta del
   archivo anterior:

   ```ini
   curl.cainfo="C:\xampp\php\extras\ssl\cacert.pem"
   openssl.cafile="C:\xampp\php\extras\ssl\cacert.pem"
   ```

3. Compruebe que `extension=openssl` esté activa **una sola vez** (si ve el aviso `Module "openssl" is already loaded`,
   comente con `;` la línea duplicada, por ejemplo `;extension=php_openssl.dll`).
4. **Cierre la terminal y abra una nueva.** Pruebe:

   ```powershell
   php -r "echo file_get_contents('https://getcomposer.org/versions') ? 'OK' : 'FALLO';"
   ```

   Si muestra `OK`, vuelva a ejecutar el instalador de Composer. Si sigue `FALLO`, continúe con el paso 2.

#### Paso 2: confirmar quién firma el certificado

Cree el archivo `emisor.php` (puede usar el Bloc de notas: `notepad emisor.php`) con este contenido:

```php
<?php
$c = stream_context_create(["ssl" => ["capture_peer_cert" => true, "verify_peer" => false, "verify_peer_name" => false]]);
$s = stream_socket_client("ssl://getcomposer.org:443", $e, $m, 10, STREAM_CLIENT_CONNECT, $c);
$p = stream_context_get_params($s);
$d = openssl_x509_parse($p["options"]["ssl"]["peer_certificate"]);
echo "Emisor: " . ($d["issuer"]["O"] ?? "") . " / " . ($d["issuer"]["CN"] ?? "") . PHP_EOL;
```

Ejecútelo con `php emisor.php`. Si el emisor es *Let's Encrypt*, *Google Trust Services*, *DigiCert* o *Cloudflare*, no hay
interceptación: revise el paso 1 y la fecha y hora de Windows. Si aparece el nombre de un antivirus (por ejemplo
`Avast Web/Mail Shield Root`), de su institución o de un proxy, elija **una** de las siguientes opciones.

#### Opción A: Desactivar el antivirus por 10 minutos (la más simple)

Sirve para instalar Composer sin tocar certificados. Hágalo sólo mientras dura la instalación.

1. En la bandeja del sistema (junto al reloj; puede estar bajo la flecha ^) haga clic derecho en el ícono del antivirus.
2. **Avast:** *Controla los escudos de Avast* → **Desactivar durante 10 minutos**. Confirme con *Sí*.
   Otros antivirus tienen una opción equivalente (*Pausar protección*, *Desactivar temporalmente*).
3. Vuelva a ejecutar el instalador de Composer (o el comando que falló, por ejemplo `composer install`).
4. Cuando termine, **reactive la protección**: mismo menú → *Reanudar/Activar todos los escudos*. Si no lo hace, Avast la
   reactiva sola al cumplirse los 10 minutos.

> Mientras la protección esté desactivada no abra otros sitios ni descargue archivos. Si trabaja en una red institucional,
> confirme con TI que esto está permitido.

#### Opción B: Agregar el certificado del antivirus a `cacert.pem` (permanente)

Mantiene el antivirus activo y evita repetir el problema con `composer install`, `composer update` y `composer require`.

1. Abra PowerShell y exporte el certificado raíz del antivirus (ejemplo con Avast; cambie el texto entre asteriscos por el
   emisor que mostró `emisor.php`):

   ```powershell
   $cert = Get-ChildItem Cert:\CurrentUser\Root, Cert:\LocalMachine\Root | Where-Object { $_.Subject -like "*Avast Web/Mail Shield Root*" } | Select-Object -First 1
   $cert.Subject
   ```

2. Si se mostró el nombre, agréguelo al final de `cacert.pem` en formato PEM:

   ```powershell
   $b64 = [Convert]::ToBase64String($cert.RawData, 'InsertLineBreaks')
   $pem = "`r`n# Certificado del antivirus`r`n-----BEGIN CERTIFICATE-----`r`n$b64`r`n-----END CERTIFICATE-----`r`n"
   Add-Content C:\xampp\php\extras\ssl\cacert.pem -Value $pem -Encoding ASCII
   ```

   Si no se mostró nada: ejecute `certmgr.msc`, busque el certificado en *Entidades de certificación raíz de confianza →
   Certificados*, clic derecho → *Todas las tareas → Exportar…* → formato **Base-64 (.CER)**, y agréguelo con
   `Get-Content C:\ruta\certificado.cer | Add-Content C:\xampp\php\extras\ssl\cacert.pem -Encoding ASCII`.

3. Cierre la terminal, abra una nueva y repita la prueba del paso 1.

Repita esta opción si descarga un `cacert.pem` nuevo o si el antivirus se reinstala (genera un certificado raíz distinto).

#### Opción C: Desactivar el análisis HTTPS del antivirus

En Avast: *Menú → Configuración → Protección → Escudos principales → Escudo web → Configuración* y desactive
**Habilitar análisis HTTPS** (el nombre puede variar según la versión). Es una configuración permanente, a diferencia de la Opción A.

#### Si `npm` también falla con `UNABLE_TO_VERIFY_LEAF_SIGNATURE`

Es la misma causa. Con el certificado exportado en formato `.cer`/`.pem` ejecute:

```powershell
npm config set cafile C:\ruta\certificado.cer
```

### 13.2 Error 500 al iniciar sesión: `Add [user_id] to fillable property to allow mass assignment`

**Síntoma.** La página abre, pero al ingresar con una cuenta de demostración la petición
`POST /api/v1/auth/login` responde **500 Internal Server Error**. En la pestaña *Network* del navegador (F12), la
respuesta (con `APP_DEBUG=true`) o el archivo `backend\storage\logs\laravel.log` muestra:

```text
Add [user_id] to fillable property to allow mass assignment on [App\Models\RefreshToken].
```

**Causa.** Los modelos declaraban los campos permitidos con atributos PHP (`#[Fillable([...])]`). Según la versión de
Laravel que instaló Composer, esos atributos no se aplican y Laravel bloquea la asignación masiva. Un error parecido
puede aparecer al sembrar la base: `no such table: attendances` (el atributo `#[Table]` tampoco se aplicó).

**Solución.** Use la versión corregida del proyecto, donde todos los modelos de `backend\app\Models` declaran
`protected $fillable`, `protected $hidden` y `protected $table` como propiedades normales (funcionan en cualquier
versión). Después de actualizar los archivos:

1. Detenga la API con <kbd>Ctrl</kbd>+<kbd>C</kbd> en su terminal.
2. En `backend`, limpie la caché y vuelva a iniciar:

   ```bat
   php artisan config:clear
   php artisan serve --host=127.0.0.1 --port=8080
   ```

3. Vuelva a iniciar sesión en <http://localhost:5173>.

**Cómo ver el error real de cualquier 500.** Ejecute en `backend`:

```powershell
Get-Content storage\logs\laravel.log -Tail 40
```

y revise `.env`:

```powershell
Select-String -Path .env -Pattern "^(APP_KEY|JWT_SECRET|COOKIE_SECURE|APP_DEBUG|DB_CONNECTION)="
```

- `JWT_SECRET=` vacío → `php artisan jwt:secret`.
- `APP_KEY=` vacío → `php artisan key:generate`.
- En desarrollo (sin HTTPS) debe existir `COOKIE_SECURE=false`.

Tras cambiar `.env`, ejecute `php artisan config:clear` y reinicie la API.
