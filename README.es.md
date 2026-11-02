# API de Productos

[Read in English](README.md)

API RESTful construida con Laravel 12 para gestionar productos y sus precios en múltiples divisas.

- CRUD completo de productos y divisas; precios por divisa para cada producto.
- Autenticación por token con **Laravel Sanctum** (los tokens vencen a los 60 minutos; el logout los revoca).
- Autorización por roles y permisos con **spatie/laravel-permission** (roles `admin` y `seller`, y roles personalizados desde la API).
- Respuestas JSON construidas con **API Resources**; errores en JSON con formato uniforme.
- Documentación interactiva **Swagger / OpenAPI**.
- 254 tests automatizados.

## Tecnologías

| Componente | Versión |
|---|---|
| PHP | 8.3+ |
| Laravel | 12 |
| MySQL | 8 (los tests usan SQLite en memoria) |
| Laravel Sanctum | 4 |
| spatie/laravel-permission | 8 |
| L5-Swagger (swagger-php) | 11 (7) |

## Instalación

```bash
git clone <url-del-repositorio> products-api
cd products-api
composer install
cp .env.example .env
php artisan key:generate
```

Editar `.env`: configurar los valores `DB_*` y las cuentas iniciales (ver más abajo). Luego crear la base de datos y ejecutar:

```bash
php artisan migrate --seed
php artisan serve
```

La API queda disponible en `http://127.0.0.1:8000/api/v1` y la documentación en `http://127.0.0.1:8000/api/documentation`.

## Variables de entorno

| Variable | Descripción | Valor por defecto |
|---|---|---|
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión a la base de datos | — |
| `SANCTUM_EXPIRATION` | Duración del token en minutos | `60` |
| `BASE_CURRENCY` | Código ISO de la divisa base | `USD` |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Cuenta de administrador que crea el seeder | nombre: `Administrator` |
| `SELLER_NAME`, `SELLER_EMAIL`, `SELLER_PASSWORD` | Cuenta de vendedor que crea el seeder | nombre: `Seller` |

El código no contiene credenciales. Si a una cuenta le falta el email o la contraseña, el seeder la omite.

## Datos iniciales

| Dato | Valor |
|---|---|
| Divisas | USD (`$`, base, tasa de cambio `1`), EUR (`€`, tasa de cambio `0.89`) |
| Roles | `admin` y `seller`, con sus permisos |
| Usuarios | Administrador y vendedor, según las variables de entorno anteriores |

`exchange_rate` significa **unidades de la divisa por 1 USD** (1 USD = 0.89 EUR).

## Autenticación

1. `POST /api/v1/auth/login` con `email` y `password` devuelve un token.
2. Enviarlo en cada petición: `Authorization: Bearer <token>`.
3. El token vence a los 60 minutos. `POST /api/v1/auth/logout` lo revoca de inmediato.

Medidas de seguridad:

- Todos los endpoints excepto el login requieren un token válido (`401` en caso contrario).
- Login: 5 intentos por minuto por email e IP (`429`). Un email inexistente y una contraseña incorrecta devuelven el mismo mensaje y tardan lo mismo, de modo que no se revela qué cuentas existen.
- API: 60 peticiones por minuto por usuario.
- Cambiar la contraseña de un usuario revoca sus otras sesiones.
- Delegación de permisos: solo se pueden otorgar permisos, asignar roles o gestionar cuentas dentro de los permisos propios.
- Las contraseñas se guardan hasheadas y nunca se devuelven. Los errores inesperados devuelven un mensaje genérico; el detalle queda en el log.

## Roles y permisos

| Recurso | `admin` | `seller` (vendedor) |
|---|---|---|
| Productos | ver, crear, editar, eliminar | ver, crear, editar |
| Precios de productos | ver, crear | ver, crear |
| Divisas | ver, crear, editar, eliminar | ver |
| Usuarios | ver, crear, editar, eliminar | — |
| Roles y permisos | ver, crear, editar, eliminar | — |

Protecciones: el rol `admin` no se puede modificar ni eliminar, nadie puede cambiar su propio rol ni eliminar su propia cuenta, y el último administrador no puede ser degradado ni eliminado.

## Endpoints

Todas las rutas son relativas a `/api/v1`.

| Método | Ruta | Permiso |
|---|---|---|
| POST | `/auth/login` | público |
| POST | `/auth/logout` | autenticado |
| GET | `/auth/me` | autenticado |
| GET | `/products` | `products.view` |
| POST | `/products` | `products.create` |
| GET | `/products/{product}` | `products.view` |
| PUT/PATCH | `/products/{product}` | `products.update` |
| DELETE | `/products/{product}` | `products.delete` |
| GET | `/products/{product}/prices` | `prices.view` |
| POST | `/products/{product}/prices` | `prices.create` |
| GET | `/currencies` | `currencies.view` |
| POST | `/currencies` | `currencies.create` |
| GET | `/currencies/{currency}` | `currencies.view` |
| PUT/PATCH | `/currencies/{currency}` | `currencies.update` |
| DELETE | `/currencies/{currency}` | `currencies.delete` |
| GET | `/users` | `users.view` |
| POST | `/users` | `users.create` |
| GET | `/users/{user}` | `users.view` |
| PUT/PATCH | `/users/{user}` | `users.update` |
| DELETE | `/users/{user}` | `users.delete` |
| GET | `/roles` | `roles.view` |
| POST | `/roles` | `roles.create` |
| GET | `/roles/{role}` | `roles.view` |
| PUT/PATCH | `/roles/{role}` | `roles.update` |
| DELETE | `/roles/{role}` | `roles.delete` |
| GET | `/permissions` | `roles.view` |

Los listados están paginados: `?page=1&per_page=15` (`per_page` entre 1 y 100).

### Reglas de negocio

- `name` del producto: solo letras (de cualquier idioma), espacios y guiones; se rechazan los dígitos.
- Montos: mayores a 0 (los costos pueden ser 0), hasta 2 decimales, devueltos como strings (`"19.90"`).
- El `currency_id` de un producto es USD por defecto.
- Un precio por divisa por producto, nunca en la divisa base del propio producto.
- Eliminar un producto elimina sus precios. No se puede eliminar una divisa en uso ni la divisa base.

## Errores

Todo error es un JSON con `message`; los errores de validación incluyen además `errors` por campo. Los mensajes de la API están en inglés.

| Código | Significado |
|---|---|
| 401 | Token ausente, inválido o vencido |
| 403 | Falta el permiso necesario |
| 404 | Recurso o ruta inexistente |
| 409 | Conflicto con el estado actual (p. ej. eliminar una divisa en uso) |
| 422 | Datos inválidos |
| 429 | Demasiadas peticiones |
| 500 | Error inesperado (el detalle se registra en el log, nunca se devuelve) |

```json
{ "message": "The name field is required.", "errors": { "name": ["The name field is required."] } }
```

## Arquitectura

Cada petición sigue el mismo recorrido:

```
Ruta (agrupada, auth:sanctum, solo los verbos necesarios)
  → Form Request   authorize() mediante la Policy + reglas de validación
  → Controlador    try { DB::beginTransaction() … DB::commit() } catch { DB::rollBack() }
  → API Resource   da forma a la respuesta JSON
```

| Carpeta | Contenido |
|---|---|
| `app/Http/Controllers/Api/V1` | Controladores (versionados como `v1`) |
| `app/Http/Requests` | Form Requests (validación y autorización) |
| `app/Http/Resources` | API Resources |
| `app/Policies` | Verificación de permisos por modelo |
| `app/Enums` | `PermissionName`, `RoleName` |
| `app/Models` | Modelos Eloquent y relaciones |
| `app/OpenApi` | Raíz y esquemas de OpenAPI |
| `database/migrations`, `database/seeders`, `database/factories` | Esquema, datos iniciales, datos de prueba |
| `bootstrap/app.php` | Manejo de errores en JSON |
| `routes/api.php` | Rutas |

## Tests

```bash
php artisan test
```

254 tests sobre SQLite en memoria. Para cada endpoint cubren el caso exitoso, `401`, `403`, `404`, cada regla de validación (`422`), los conflictos de negocio (`409`), el rollback de la transacción ante errores, el vencimiento y la revocación de tokens, el límite de peticiones, la delegación de permisos y que la documentación cubra todas las rutas.

## Documentación de la API

Swagger UI: `http://127.0.0.1:8000/api/documentation` (en inglés). Usar **Authorize** y pegar el token del login para probar los endpoints.

El archivo OpenAPI es `storage/api-docs/api-docs.json` (se puede importar en Postman o Insomnia). Para regenerarlo después de cambiar la API:

```bash
php artisan l5-swagger:generate
```
