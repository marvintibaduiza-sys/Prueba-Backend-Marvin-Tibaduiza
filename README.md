# Products API

[Leer en español](README.es.md)

RESTful API built with Laravel 12 to manage products and their prices in multiple currencies.

- Full CRUD for products and currencies; per-currency prices for each product.
- Token authentication with **Laravel Sanctum** (tokens expire after 60 minutes; logout revokes them).
- Role and permission authorization with **spatie/laravel-permission** (`admin` and `seller` roles, custom roles via the API).
- JSON responses built with **API Resources**; uniform JSON errors.
- Interactive **Swagger / OpenAPI** documentation.
- 254 automated tests.

## Tech stack

| Component | Version |
|---|---|
| PHP | 8.3+ |
| Laravel | 12 |
| MySQL | 8 (tests use SQLite in memory) |
| Laravel Sanctum | 4 |
| spatie/laravel-permission | 8 |
| L5-Swagger (swagger-php) | 11 (7) |

## Installation

```bash
git clone <repository-url> products-api
cd products-api
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`: set the `DB_*` values and the seeded accounts (see below). Then create the database and run:

```bash
php artisan migrate --seed
php artisan serve
```

The API is available at `http://127.0.0.1:8000/api` and the documentation at `http://127.0.0.1:8000/api/documentation`.

## Environment variables

| Variable | Description | Default |
|---|---|---|
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection | — |
| `SANCTUM_EXPIRATION` | Token lifetime in minutes | `60` |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Administrator account created by the seeder | name: `Administrator` |
| `SELLER_NAME`, `SELLER_EMAIL`, `SELLER_PASSWORD` | Seller account created by the seeder | name: `Seller` |

No credentials are stored in the code. An account without email or password is skipped by the seeder.

## Seeded data

| Data | Value |
|---|---|
| Currencies | USD (`$`, base, exchange rate `1`), EUR (`€`, exchange rate `0.89`) |
| Roles | `admin`, `seller` with their permissions |
| Users | Administrator and seller, from the environment variables above |

`exchange_rate` means **units of the currency per 1 USD** (1 USD = 0.89 EUR).

## Authentication

1. `POST /api/auth/login` with `email` and `password` returns a token.
2. Send it in every request: `Authorization: Bearer <token>`.
3. The token expires after 60 minutes. `POST /api/auth/logout` revokes it immediately.

Security measures:

- Every endpoint except login requires a valid token (`401` otherwise).
- Login: 5 attempts per minute per email and IP (`429`). Unknown email and wrong password return the same message and take the same time, so existing accounts are not revealed.
- API: 60 requests per minute per user.
- Changing a user's password revokes that user's other sessions.
- Permission delegation: users can only grant permissions, assign roles or manage accounts within their own permissions.
- Passwords are hashed and never returned. Unexpected errors return a generic message; details go to the log.

## Roles and permissions

| Resource | `admin` | `seller` |
|---|---|---|
| Products | view, create, update, delete | view, create, update |
| Product prices | view, create | view, create |
| Currencies | view, create, update, delete | view |
| Users | view, create, update, delete | — |
| Roles and permissions | view, create, update, delete | — |

Protections: the `admin` role cannot be modified or deleted, nobody can change their own role or delete their own account, and the last administrator cannot be demoted or deleted.

## Endpoints

All paths are relative to `/api`.

| Method | Path | Permission |
|---|---|---|
| POST | `/auth/login` | public |
| POST | `/auth/logout` | authenticated |
| GET | `/auth/me` | authenticated |
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

Lists are paginated: `?page=1&per_page=15` (`per_page` from 1 to 100).

### Business rules

- Product `name`: letters (any language), spaces and hyphens only; digits are rejected.
- Amounts: greater than 0 (costs may be 0), up to 2 decimals, returned as strings (`"19.90"`).
- A currency's `name` is its unique identifier (e.g. `USD`, `EUR`); the base currency (USD) cannot be renamed and its rate is always 1.
- A product's `currency_id` defaults to USD.
- One price per currency per product, never in the product's own base currency.
- Deleting a product deletes its prices. A currency in use, or the base currency, cannot be deleted.

## Errors

Every error is JSON with a `message`; validation errors also include `errors` per field.

| Status | Meaning |
|---|---|
| 401 | Missing, invalid or expired token |
| 403 | Missing permission |
| 404 | Resource or route not found |
| 409 | Conflicts with the current state (e.g. deleting a currency in use) |
| 422 | Invalid data |
| 429 | Too many requests |
| 500 | Unexpected error (details are logged, never returned) |

```json
{ "message": "The name field is required.", "errors": { "name": ["The name field is required."] } }
```

## Tests

```bash
php artisan test
```

254 tests run on SQLite in memory. For every endpoint they cover the success case, `401`, `403`, `404`, each validation rule (`422`), business conflicts (`409`), transaction rollback on errors, token expiration and revocation, rate limiting, permission delegation and that the documentation covers every route.

## API documentation

Swagger UI: `http://127.0.0.1:8000/api/documentation`. Click **Authorize** and paste the token from the login to try the endpoints.

The OpenAPI file is `storage/api-docs/api-docs.json` (it can be imported into Postman or Insomnia). To regenerate it after changing the API:

```bash
php artisan l5-swagger:generate
```
