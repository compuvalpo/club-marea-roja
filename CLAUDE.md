# CLAUDE.md — Reglas de programación del proyecto «Marea Roja»

Este archivo orienta a Claude (y a cualquier persona del equipo) al modificar el código de `web/`.
Léelo completo antes de proponer cambios. Ante conflicto, **la seguridad y las pruebas mandan**.

## 1. Contexto

- Sistema de administración del **Club Deportivo Marea Roja**: finanzas (mensualidades, pagos, morosos,
  ingresos/egresos, gastos fijos), prácticas regulares (calendario), eventos ocasionales y estadísticas.
- Roles: `admin`, `coach` (entrenador), `guardian` (apoderado), `athlete` (alumno).
- Documentación funcional en `../PMO/` (Word y Excel): SRS, Design Doc, historias de usuario, DoD, backlog.

## 2. Arquitectura (no romperla)

```
web/
├── backend/   API REST Laravel 13 (PHP 8.3+, Eloquent, firebase/php-jwt) — /api/v1
└── frontend/  SPA JavaScript ES2022 (Vite, FullCalendar, Chart.js) — consume la API
```

- **Backend Laravel en capas**: `Http\Controllers` (delgados: validan con `$request->validate()`, autorizan con
  `Gate::authorize()`, delegan y responden con `Http\Resources`) → `Services` (reglas de negocio y transacciones)
  → `Models` (Eloquent, relaciones y *scopes*). La lógica de negocio no va en controladores ni en modelos.
- **Rutas** sólo en `backend/routes/api.php`. La autorización **por permiso** se declara ahí con el middleware
  `can:practices.manage` (matriz en `app/Auth/Permissions.php`); la autorización **por fila** va en `app/Policies` (`PracticePolicy`, `AthletePolicy`) y el
  filtrado de listas en `Services\AccessScope`.
- **Respuestas**: siempre a través de `JsonResource` (`{"data": ...}`); los errores los formatea el manejador de
  excepciones de `bootstrap/app.php`. Para conflictos de estado lanzar `ConflictHttpException` (409); para errores de
  dominio por campo, `ValidationException::withMessages()` (422).
- **Frontend**: una vista por archivo en `src/views/`, helpers reutilizables en `src/ui/`, acceso a la API sólo vía
  `src/api/client.js`. Rutas y roles permitidos en `src/routes.js`.
- Frontend y backend se comunican **sólo por JSON** bajo `/api/v1`. Respuestas exitosas: `{ "data": ... }`;
  errores: `{ "error": { "message": "...", "errors": { "campo": ["..."] } } }`.

## 3. Reglas de seguridad (obligatorias)

1. **JWT**: access token HS256 de 15 min con `typ: at+jwt`, `iss`, `aud`, `sub`, `sid`, `exp`, `jti`; sólo en memoria del
   navegador. **Nunca** en `localStorage`/`sessionStorage`. Verificar siempre con `JwtService::verify()` (algoritmo fijo).
2. **Sesiones revocables**: cada login crea una fila en `auth_sessions`; `AuthenticateJwt` valida en cada petición que la
   sesión del claim `sid` siga vigente (revocación inmediata). Inactividad `JWT_IDLE_TTL` y duración máxima
   `JWT_SESSION_TTL`. Una sesión por usuario (`JWT_SINGLE_SESSION`). Al cambiar rol, correo, contraseña o estado de un
   usuario, llamar a `AuthService::revokeAllFor()`.
   **Refresh token**: aleatorio de 256 bits, en cookie `HttpOnly; SameSite=Strict; Path=/api/v1/auth` (`Secure` en
   producción). En BD sólo se guarda su **hash SHA-256**. Se rota en cada uso; la reutilización cierra la sesión.
   El frontend coordina la renovación entre pestañas (Web Locks) y sincroniza login/logout (BroadcastChannel).
3. `JWT_SECRET` ≥ 32 caracteres (`php artisan jwt:secret`), sólo vía variables de entorno. Nunca commitear `.env`.
4. **Consultas** con Eloquent / Query Builder (parametrizadas). Prohibido `DB::raw`/`whereRaw` con datos del usuario.
   Asignación masiva sólo con la propiedad `$fillable` explícita (no usar los atributos `#[Fillable]`/`#[Table]`: según la versión instalada de Laravel no se aplican) y con el resultado de `validate()` (lista blanca).
5. Toda entrada se valida en el servidor (`$request->validate()`, mensajes en `lang/es/validation.php`). No confiar en
   validaciones del frontend.
6. **RBAC**: los permisos se definen sólo en `app/Auth/Permissions.php`. Cada endpoint nuevo declara su permiso en
   `routes/api.php` (`->middleware('can:permiso')`) **y** verifica la propiedad del recurso con una Policy
   (`Gate::authorize`) cuando aplique (p. ej. un apoderado sólo opera sobre sus pupilos). El frontend usa los mismos
   permisos (`can('permiso')` de `api/session.js` y `permission` en `routes.js`); nunca comparar `user.role` para
   decidir una acción. Los usuarios referenciados (`coach_id`, `guardian_id`, `user_id`) se validan con su rol.
7. Frontend: construir DOM con `h()` / `textContent`. **Prohibido `innerHTML`** con datos (lo bloquea ESLint).
8. Contraseñas con el cast `hashed` de Eloquent (bcrypt/argon), mínimo 12 caracteres. Login bloqueado 15 min tras 5
   intentos fallidos por cuenta, límite por IP y tiempo de respuesta igual exista o no la cuenta.
9. Errores 5xx no exponen detalles en producción (`APP_DEBUG=false`).
10. CORS con lista blanca explícita (`CORS_ORIGINS`), nunca `*`. Los endpoints con cookie usan el middleware `origin`.

## 4. Estilo de código

### PHP / Laravel
- PHP ≥ 8.3 y Laravel 13. `declare(strict_types=1);` en todos los archivos.
- Estilo **Laravel Pint** (preset `laravel` + reglas de `pint.json`): `composer lint` sin diferencias.
- Convenciones Laravel: modelos en singular (`Practice`), tablas en plural, controladores `XxxController`,
  recursos `XxxResource`, políticas `XxxPolicy`, migraciones con fecha. Propiedades `$fillable`, `$hidden` y `$table` en los modelos.
- Tipos explícitos en parámetros/retornos; PHPDoc genérico en relaciones (`@return BelongsTo<User, $this>`).
- **Larastan (PHPStan) nivel 6** sin errores. No usar `@phpstan-ignore` salvo justificación en el PR.
- Montos en **pesos chilenos (CLP) como enteros**. Nunca `float` para dinero.
- Fechas: `Y-m-d`, horas `H:i`, períodos `Y-m`. Zona horaria `America/Santiago` (`config/app.php`); usar `now()`/`today()`
  para que las pruebas puedan congelar el tiempo con `Carbon::setTestNow()`.
- Migraciones portables (SQLite/MySQL/PostgreSQL): sólo el *Schema Builder*, sin SQL específico de un motor.

### JavaScript
- ES Modules, `const`/`let`, funciones flecha, `async/await`. Sin jQuery ni frameworks pesados.
- **Prettier** (`printWidth` 140, comillas simples, coma final) + **ESLint** (flat config). Sin warnings.
- Nombres en inglés para código; **textos de interfaz en español de Chile**.
- Selectores para pruebas: atributos `data-testid` (no depender de clases CSS ni textos cambiantes).
- Accesibilidad: etiquetas `<label for>`, `aria-*` en controles sin texto, foco visible, contraste AA.
- **Iconos**: sólo mediante `icon('nombre')` de `src/ui/icons.js` (Lucide, ISC). Para uno nuevo, importarlo y
  registrarlo en el mapa `ICONS`. No usar emojis como iconos de interfaz.
- **Identidad visual**: logos en `public/brand/` (usar `brandLogo()` de `ui/layout.js`); colores sólo vía tokens CSS
  (`--brand`, `--brand-600`, `--brand-50`), nunca valores fijos en las vistas.

### General
- `.editorconfig`: UTF-8, LF, 2 espacios (4 en PHP).
- Commits: **Conventional Commits** en español (`feat(finanzas): registrar pago parcial`).
- Ramas: `main` (estable), `develop`, `feature/HU-XX-descripcion`, `fix/...`.

## 5. Pruebas (obligatorio)

| Nivel | Herramienta | Ubicación | Comando |
|-------|-------------|-----------|---------|
| Unitarias backend | PHPUnit 12 | `backend/tests/Unit` | `composer test:unit` |
| Integración API | PHPUnit + pruebas HTTP de Laravel (SQLite `:memory:`, `RefreshDatabase`) | `backend/tests/Feature` | `composer test:feature` |
| Unitarias frontend | Vitest + jsdom | `frontend/tests` | `npm test` |
| E2E | Cypress | `frontend/cypress/e2e` | `npm run e2e` |

- Toda regla de negocio nueva → prueba unitaria. Todo endpoint nuevo → prueba Feature con caso feliz **y** caso 403/422.
- Todo flujo de usuario nuevo → spec Cypress.
- Las pruebas congelan el tiempo en `Tests\TestCase` (`Carbon::setTestNow`) y cargan `DemoSeeder`: no deben depender
  del día en que se ejecutan.
- No se hace merge con pruebas fallando ni con cobertura de reglas de negocio en descenso.

## 6. Flujo para cambios

1. Identificar la HU/caso de uso en `../PMO/06_Historias_de_Usuario.docx` / `../PMO/07_Casos_de_Uso.docx`.
2. Backend: migración (`php artisan make:migration`) → modelo → servicio → policy → resource → controlador → ruta → pruebas.
3. Frontend: vista/componente → `routes.js` (roles) → pruebas Vitest/Cypress.
4. Ejecutar antes de entregar:
   ```bash
   cd backend  && composer lint && composer analyse && composer test
   cd frontend && npm run lint && npm run format:check && npm test && npm run build
   ```
5. Verificar la **Definición de Terminado** (`../PMO/08_Definicion_de_Terminado.docx`).

## 7. Qué NO hacer

- No agregar dependencias sin justificar licencia libre (MIT/BSD/Apache) y necesidad real.
- No exponer `password_hash`, tokens ni datos de otros apoderados en respuestas.
- No mezclar lógica de negocio en vistas JS: el backend es la fuente de verdad (p. ej. cálculo de morosidad).
- No modificar datos financieros históricos: los ingresos por mensualidad se anulan desde su pago, no borrándolos.
- No hardcodear URLs: el frontend usa rutas relativas `/api/v1` (proxy de Vite en desarrollo).
