# Solar Energy Optimisation — Project Intelligence

## Tech Stack

| Layer          | Technology                                      |
|----------------|-------------------------------------------------|
| energy-service | Laravel 12, PHP 8.5                             |
| energy-bff     | Laravel 12, PHP 8.5                             |
| energy-ui      | React 18, TypeScript 5 (strict), Vite 5         |
| Testing (PHP)  | PHPUnit 11, spatie/laravel-data 4               |
| Testing (UI)   | Vitest 2, React Testing Library                 |
| Runtime        | MySQL 8, Redis 7, Node 20                       |

---

## What This Is

An internet-facing solar battery charging optimisation service for a
residential installation. It runs continuously, collects data from
external APIs on a schedule, and serves a real-time dashboard and
charge recommendation to authenticated users.

Three components:
- **energy-service** — the brain. All external integrations,
  scheduled data collection, and optimisation logic.
- **energy-bff** — Backend for Frontend. Aggregates
  energy-service, handles auth, serves the frontend only.
- **energy-ui** — React SPA. Talks only to the BFF. Never
  directly to the microservice.

---

## System Specification

### Installation
- **Inverter:** SolaX X1-HYBRID-6.0-D (6kW)
- **Battery:** 2x SolaX T-BAT H 5.8 = 11.6kWh total
- **Usable capacity:** 70% of total = 8.1kWh
  (70% ceiling is a longevity decision, not a technical limit —
  it is a named constant `BATTERY_CHARGE_CEILING_PCT = 70`,
  never a magic number)
- **Location:** 51.7°N, 2.2°W — Nailsworth, Gloucestershire
- **Tariff:** Intelligent Octopus Go
  - Cheap rate: 23:30–05:30 at ~7.5p/kWh
  - Day rate: ~24p/kWh
  - These are config values, not hardcoded

### Panel Arrays
All panels: Eurener MEPV 400W, Tilt 35°

| Group | Panels | kWp  | Azimuth | Direction |
|-------|--------|------|---------|-----------|
| 1     | 2      | 0.8  | 181°    | South     |
| 2     | 7      | 2.8  | 90°     | East      |
| 3     | 3      | 1.2  | 270°    | West      |
| 4     | 6      | 2.4  | 181°    | South     |
| 5     | 1      | 0.4  | TBC     | Unknown   |

Total: 19 panels, 7.6kWp

---

## External APIs

| Service         | Purpose                              | Auth          |
|----------------|--------------------------------------|---------------|
| SolaxCloud      | Battery state, generation, inverter  | tokenId header |
| Octopus Energy  | Tariff rates, consumption history    | Basic auth    |
| Open-Meteo      | Solar radiation + weather forecast   | None          |

All API keys via environment variables. Never in code.
Never in version control. Always in `.env` (gitignored).

**Forecast.solar is NOT used.** Solar generation is derived from
Open-Meteo radiation data using the PV physics formula. This gives
no rate limits, no auth, and no third-party dependency in production.

### SolaxCloud API (v2) — Implementation Notes

- **Method:** POST `{baseUrl}/api/v2/dataAccess/realtimeInfo/get`
- **Base URL:** `https://global.solaxcloud.com` (not the old proxyApp URL)
- **Auth:** `tokenId` placed in **HTTP request Headers** — not query string, not Basic auth
- **Request body:** `{ "wifiSn": "..." }` — this is the **Wi-Fi dongle** serial number,
  not the inverter serial number (`SOLAX_WIFI_SN` env var)
- **Response envelope:** `{ "success": bool, "exception": string, "result": {}, "code": int }`
  — check `success === true` AND `code === 0` before trusting `result`
- **`soc` is nullable** — returns `null` when the inverter cannot read the battery
  (e.g. during startup or communication loss). Treat as a transient failure; throw,
  do not silently default to 0.
- **`batPower` is nullable** — positive = charging (W), negative = discharging (W)
- **`batStatus`:** `"0"` = normal, `"1"` = fault, `"2"` = disconnected
- **`inverterStatus`:** string-encoded integer (e.g. `"102"` = Normal). Full mapping
  in `App\Enums\InverterStatus`.
- **Error codes:** 1001 = Unauthorized, 1002 = Validation failed,
  1003 = Data Unauthorized, 2002 = Data not found

### Octopus Energy API — Implementation Notes

- **Auth:** HTTP Basic auth — API key as username, empty string as password
- **Consumption endpoint:**
  `GET /v1/electricity-meter-points/{mpan}/meters/{serial}/consumption/`
- **Params:** `period_from`, `period_to` (ISO 8601), `page_size=100`, `order_by=period`
- **Paginated:** follow `next` URL in response until `null`. Cap at 50 pages.
- **Reading fields:** `consumption` (kWh), `interval_start`, `interval_end` (ISO 8601 UTC)

### Open-Meteo API — Implementation Notes

- **Endpoint:** `GET https://api.open-meteo.com/v1/forecast`
- **Key params:** `latitude`, `longitude`,
  `daily=shortwave_radiation_sum,cloud_cover_mean`,
  `hourly=shortwave_radiation,direct_radiation`,
  `start_date=YYYY-MM-DD`, `end_date=YYYY-MM-DD`, `timezone=Europe/London`
- **Response:** `daily.shortwave_radiation_sum[0]` (MJ/m²),
  `daily.cloud_cover_mean[0]` (%)
- **Solar generation derivation:**
  ```
  radiation_kwh_m2 = shortwave_radiation_sum_MJ_m2 / 3.6
  estimated_kwh    = system_kwp * radiation_kwh_m2 * performance_ratio
  ```
  Performance ratio for this installation: **0.78**
  (accounts for inverter efficiency, wiring losses, temperature derating)
- This is calculated **per array group** using each group's kWp, then summed.
- Cloud cover > 60% degrades confidence in the forecast.

---

## Data Collection Architecture

**External APIs are NEVER called during a web request.**

All external data is collected by scheduled background jobs and stored
in the database. The recommendation engine reads from the database only.

```
Scheduled Jobs (Laravel Scheduler)          Web Requests
──────────────────────────────────          ────────────────────────
FetchBatteryStateJob   — every 15 min  →  battery_readings table
FetchSolarForecastJob  — daily @ 06:00 →  solar_forecasts table
FetchConsumptionJob    — every hour    →  consumption_readings table
                                               ↓
                                          CalculateChargeRecommendationAction
                                          reads latest cached values only —
                                          zero external calls per request
```

### Stored data models
- **`battery_readings`** — soc, charge_kwh, bat_power_w, inverter_status, fetched_at
- **`solar_forecasts`** — forecast_date, estimated_kwh, radiation_kwh_m2,
  cloud_cover_pct, generated_at
- **`consumption_readings`** — interval_start, interval_end, consumption_kwh

### Staleness handling
If the latest reading is older than its expected refresh interval, the
recommendation engine **degrades confidence** rather than failing.
Thresholds:
- Battery state stale after **30 minutes** → confidence penalty
- Solar forecast stale after **25 hours** → confidence penalty
- Consumption stale after **3 hours** → confidence penalty

It never throws a 503 because a scheduled job hasn't run yet —
it returns a recommendation with a lower confidence score and includes
a `stale_data` factor in the reasoning.

---

## Coding Standards — Read This Before Writing Anything

This project is held to production standards throughout.
There is no "we'll clean it up later." Write it right first time.

### General Principles

- **Clarity over cleverness.** Code is read far more than it
  is written. If a line needs a comment to be understood,
  rewrite the line.
- **Explicit over implicit.** No magic. No convention-over-
  configuration surprises that aren't documented.
- **Fail loudly.** Exceptions should surface immediately in
  development. Never swallow errors silently.
- **Small, focused units.** Functions do one thing. Classes
  have one responsibility. If you're writing "and" in a
  docblock, split the unit.
- **No premature abstraction.** Don't create interfaces,
  repositories or base classes until there are two concrete
  implementations that need them.

### PHP / Laravel (energy-service, energy-bff)

- **Strict types everywhere.**
  `declare(strict_types=1);` at the top of every PHP file.
- **Type hints on everything.** Parameters, return types,
  class properties. No untyped code.
- **Use Laravel's service container properly.** Bind
  interfaces in service providers. Inject via constructor.
  Never `app()->make()` inside business logic.
- **DTOs for data transfer.** Never pass raw arrays between
  layers. Use typed Data Transfer Objects (consider
  `spatie/laravel-data`).
- **Form Requests for validation.** Never validate in
  controllers.
- **Repository pattern for data access.** Controllers know
  nothing about Eloquent. Repositories return domain objects
  or DTOs, not Eloquent models.
- **Actions for business logic.** Single-purpose action
  classes for each discrete operation (e.g.
  `CalculateChargeRecommendationAction`). Not fat controllers,
  not fat models.
- **API resources for responses.** Every API response goes
  through a Laravel Resource or Resource Collection. No
  `->toArray()` hacks, no `response()->json($model)`.
- **Consistent error responses.** All errors return a
  structured JSON envelope:
  ```json
  {
    "error": {
      "code": "FORECAST_UNAVAILABLE",
      "message": "Human-readable description",
      "context": {}
    }
  }
  ```
- **No N+1 queries.** Eager load always. Use
  `withCount`, `with` — never lazy load in loops.
- **Migrations are immutable.** Never edit a migration after
  it has been run. Write a new one.
- **Enums for fixed sets of values.** Not strings, not
  constants in a class — backed PHP 8.1 enums.
- **Jobs for anything async.** Never block a request for an
  external API call if it can be queued.
- **Log with context.**
  `Log::info('Recommendation calculated', ['recommendation' => $dto])`
  not `Log::info('Done')`.

### React / TypeScript (energy-ui)

- **TypeScript strict mode.** No `any`. No type assertions
  unless unavoidable and documented.
- **Co-locate by feature.** Not by type. Components, hooks,
  types, and tests for a feature live together.
- **Custom hooks for logic.** No business logic in components.
  Components render, hooks think.
- **No prop drilling beyond two levels.** Use context or a
  state manager for shared state.
- **API layer is isolated.** All fetch calls go through a
  typed API client module. Components never call `fetch`
  directly.
- **Handle all states explicitly.** Every async operation has
  loading, error, and empty states rendered meaningfully — not
  just the happy path.
- **Accessibility is not optional.** Semantic HTML. ARIA where
  needed. Keyboard navigable. Colour contrast AA minimum.

### Testing

- **Test behaviour, not implementation.** Tests should survive
  a refactor that doesn't change behaviour.
- **Every action class has a unit test.**
- **Every API endpoint has a feature test** covering: success,
  validation failure, external API failure, auth failure.
- **Use factories for test data.** Never hardcode IDs or magic
  values in tests.
- **Mock external APIs in tests.** Never hit real APIs in the
  test suite. Use Http::fake() for Laravel HTTP client calls.
- **Test the sad path first.** Write the failure case test
  before the success case.

### API Design (between services)

- **RESTful and predictable.** Resources are nouns.
  HTTP verbs have their standard meaning.
- **Versioned from day one.** `/api/v1/...` — even if v2
  never exists, it signals intent and prevents future pain.
- **Consistent envelope format for all responses:**
  ```json
  {
    "data": {},
    "meta": {},
    "links": {}
  }
  ```
- **Use HTTP status codes correctly.** 200 is not a universal
  response. 422 for validation. 503 for external dependency
  failures. 404 when a resource genuinely doesn't exist.
- **Document with OpenAPI / Swagger.** Generate from code
  attributes, not written by hand.

### Git & Commits

- **Conventional Commits.**
  `feat:`, `fix:`, `refactor:`, `test:`, `chore:`, `docs:`
- **One logical change per commit.** If the commit message
  needs "and", split the commit.
- **No committed secrets.** Ever. `.env.example` shows the
  shape, `.env` is gitignored.
- **Branch naming:** `feature/`, `fix/`, `chore/` prefixes.

### Environment & Configuration

- All environment-specific values in `.env`.
- All config accessed via `config()` helper, never `env()`
  directly in application code (only in config files).
- Provide a fully documented `.env.example` alongside every
  service.

---

## Architecture Rules — Never Violate These

1. **The frontend never talks to energy-service directly.**
   All traffic goes through energy-bff.

2. **energy-bff contains no business logic.**
   It aggregates, transforms for the UI, and handles auth.
   Optimisation logic lives in energy-service exclusively.

3. **External API calls are isolated behind interfaces.**
   `SolaxClientInterface`, `OctopusClientInterface` etc.
   Swap implementations without touching business logic.

4. **The optimisation engine is pure.**
   `CalculateChargeRecommendationAction` takes typed inputs
   and returns a typed recommendation. It has no side effects,
   makes no API calls, touches no database. It can be unit
   tested with no infrastructure.

5. **Configuration flows inward.**
   Panel specs, tariff rates, battery ceiling — all injected
   from config. Nothing about the installation is hardcoded
   in logic.

6. **External APIs are never called during a web request.**
   All external data is collected by scheduled background jobs
   and persisted to the database. The recommendation controller
   reads from the database only. This is non-negotiable for an
   internet-facing service.

---

## Optimisation Logic (Domain Knowledge)

The core question, answered on demand from cached data:

> Given the latest battery state, tomorrow's solar generation forecast,
> and expected consumption, should I charge tonight and to what level?

### Inputs (all read from database — never fetched live)
- Latest battery state from `battery_readings` (collected every 15 min)
- Tomorrow's generation forecast from `solar_forecasts` (collected daily)
- Historical consumption by hour/day-of-week from `consumption_readings`
- Current tariff rates from config (Octopus rates are config, not dynamic)

### Decision Logic
```
expected_need = forecast_consumption - forecast_generation
charge_gap = expected_need - current_battery_kwh

if charge_gap <= 0:
    recommendation = DO_NOT_CHARGE
elif charge_gap < (BATTERY_CEILING_KWH * 0.25):
    recommendation = PARTIAL_CHARGE to (current + charge_gap)
else:
    recommendation = CHARGE to BATTERY_CEILING_KWH
```

Confidence is degraded when:
- Cloud cover forecast > 60%
- Any input data is stale (beyond its refresh threshold)
- Consumption history has high variance for this day/season

### Output (typed DTO)
```php
RecommendationDTO {
  action: RecommendationAction (enum: CHARGE|PARTIAL|DO_NOT_CHARGE)
  target_charge_pct: int
  target_charge_kwh: float
  confidence: float (0–1)
  reasoning: ReasoningDTO {
    forecast_generation_kwh: float
    forecast_consumption_kwh: float
    current_battery_kwh: float
    gap_kwh: float
    factors: string[]
  }
  generated_at: Carbon
  valid_until: Carbon
}
```

---

## What Good Looks Like

When Claude Code is doing this well:

- Every new class has a corresponding test file created in
  the same commit.
- No function is longer than 20 lines without a good reason
  documented in a comment.
- PR-level thinking: each task produces a clean, reviewable
  diff with one clear purpose.
- External API failures never crash the recommendation engine
  — they degrade confidence or fall back to conservative
  defaults (charge to ceiling if in doubt).
- The recommendation is always explainable in plain English,
  because the reasoning DTO contains everything needed.
- Scheduled jobs fail loudly (logged, alerted) but
  independently — one job failing does not affect others.

## When In Doubt

- More conservative is better. If forecast data is missing or
  suspicious, recommend charging. A missed solar day is
  cheaper than a flat battery.
- Ask before assuming. If a requirement is ambiguous, surface
  it rather than guess.
- Small, complete steps. Scaffold → review → implement one
  layer → review → next layer. Never more than one logical
  layer per session.
