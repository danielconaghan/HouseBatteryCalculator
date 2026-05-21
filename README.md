# Solar Energy Optimisation

A residential solar battery charging optimisation system.
Each evening it decides whether to charge the home battery from the grid on the cheap overnight tariff, or whether tomorrow's forecast solar generation makes that unnecessary.

**Installation:** SolaX X1-HYBRID-6.0-D (6kW) inverter · 2× SolaX T-BAT H 5.8 (11.6kWh total, 8.1kWh usable) · 19 panels / 7.6kWp · Nailsworth, Gloucestershire

---

## Architecture

```
energy-ui  (React SPA)
    │
    │  HTTP  (auth via Sanctum)
    ▼
energy-bff  (Laravel — aggregation + auth only, no business logic)
    │
    │  HTTP
    ▼
energy-service  (Laravel — optimisation engine, all external integrations)
    │
    ├── SolaxCloud API      (battery state, inverter telemetry)
    ├── Octopus Energy API  (tariff rates, consumption history)
    ├── Forecast.solar API  (generation forecast per array plane)
    └── Open-Meteo API      (weather / cloud cover)
```

**Rule:** The frontend never talks to energy-service directly. The BFF contains no business logic.

---

## Services and Ports

| Service        | Description                    | Local Port |
|----------------|--------------------------------|------------|
| energy-service | Optimisation engine            | 8001       |
| energy-bff     | Backend for Frontend           | 8002       |
| energy-ui      | React SPA (dev server)         | 3000       |
| MySQL 8        | Databases (energy_service, energy_bff) | 3307 |
| Redis 7        | Cache and queues               | 6379       |

---

## Getting Started

### Prerequisites

- Docker Desktop ≥ 4.x
- Docker Compose v2

### 1. Clone and configure environment

```bash
git clone <repo-url> solar
cd solar

# Copy and fill in credentials for each service
cp energy-service/.env.example energy-service/.env
cp energy-bff/.env.example      energy-bff/.env
cp energy-ui/.env.example       energy-ui/.env
```

Edit `energy-service/.env` and supply your API keys:

| Variable | Where to get it |
|---|---|
| `SOLAX_API_KEY` | SolaxCloud portal → Account → API |
| `SOLAX_TOKEN_ID` | SolaxCloud portal |
| `SOLAX_SERIAL_NUMBER` | Inverter label or SolaxCloud |
| `OCTOPUS_API_KEY` | Octopus dashboard → API access |
| `OCTOPUS_ACCOUNT_NUMBER` | Your Octopus account page |
| `OCTOPUS_MPAN` | Your electricity meter |

Edit `energy-bff/.env` and generate an app key:

```bash
# You can run this once the containers are up — see step 3
php artisan key:generate
```

### 2. Build and start

```bash
docker compose up --build
```

This starts all five containers. MySQL runs its init script on first start, creating both databases.

### 3. Initialise Laravel services

In two separate terminals (or use `docker compose exec`):

```bash
# energy-service
docker compose exec energy-service php artisan key:generate
docker compose exec energy-service php artisan migrate

# energy-bff
docker compose exec energy-bff php artisan key:generate
docker compose exec energy-bff php artisan migrate
```

### 4. Open the UI

Visit [http://localhost:3000](http://localhost:3000).

---

## Development

### Running tests

```bash
# energy-service unit and feature tests
docker compose exec energy-service php artisan test

# energy-bff unit and feature tests
docker compose exec energy-bff php artisan test

# energy-ui type checking and tests
docker compose exec energy-ui npm run type-check
docker compose exec energy-ui npm run test
```

### Linting

```bash
docker compose exec energy-service vendor/bin/pint
docker compose exec energy-bff     vendor/bin/pint
docker compose exec energy-ui      npm run lint
```

---

## Directory Structure

```
solar/
├── docker-compose.yml
├── docker/
│   └── mysql/
│       └── init.sql          # Creates both databases on first boot
├── energy-service/           # Optimisation engine (Laravel 11)
│   ├── app/
│   │   ├── Actions/          # Single-purpose action classes
│   │   ├── DTOs/             # Typed data transfer objects (spatie/laravel-data)
│   │   ├── Enums/            # PHP 8.1 backed enums
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   ├── Requests/     # Form Request validation
│   │   │   └── Resources/    # API Resource responses
│   │   ├── Providers/
│   │   └── Services/
│   │       └── Contracts/    # Interfaces for external API clients
│   ├── config/
│   │   ├── battery.php       # Capacity, ceiling %, charge rate
│   │   ├── octopus.php       # Tariff rates, API config
│   │   ├── solar.php         # Panel arrays, location
│   │   └── solax.php         # Inverter API config
│   └── routes/api.php        # /api/v1/...
├── energy-bff/               # Backend for Frontend (Laravel 11)
│   ├── app/
│   │   ├── DTOs/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   ├── Middleware/
│   │   │   └── Resources/
│   │   ├── Providers/
│   │   └── Services/         # energy-service HTTP client
│   ├── config/
│   │   └── services.php      # Upstream service URLs
│   └── routes/api.php        # /api/v1/...
└── energy-ui/                # React SPA (Vite + TypeScript)
    └── src/
        ├── api/              # Typed API client — components never call fetch
        ├── components/       # Shared UI components
        ├── features/         # Co-located feature modules
        ├── hooks/            # Custom hooks (logic lives here, not in components)
        └── types/            # Shared TypeScript types
```

---

## Key Design Decisions

- **Battery charge ceiling is 70%** (`BATTERY_CHARGE_CEILING_PCT`). This is a longevity decision to extend cell life — not a technical limit. It is always a named constant, never a magic number.
- **Conservative by default.** If forecast data is missing or confidence is low, the system recommends charging to ceiling. A missed solar day costs ~£0.50; a flat battery is worse.
- **Versioned API from day one.** All endpoints are under `/api/v1/` even though v2 does not yet exist.
- **No cross-service database access.** Each service owns its own database schema entirely.

---

## Tariff

Intelligent Octopus Go:

| Period | Time | Rate |
|--------|------|------|
| Cheap | 23:30–05:30 | ~7.5p/kWh |
| Day | 05:30–23:30 | ~24p/kWh |

Rates are configuration values (`OCTOPUS_CHEAP_RATE_PENCE`, `OCTOPUS_DAY_RATE_PENCE`), never hardcoded.
