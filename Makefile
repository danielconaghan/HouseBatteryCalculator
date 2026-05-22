.PHONY: up down down-clean build restart logs ps migrate fresh test worker-logs install key-bff user fetch-battery fetch-forecast fetch-consumption fetch-all

# ─── Stack lifecycle ──────────────────────────────────────────────────────────

up:
	docker compose up -d

down:
	docker compose down

down-clean:
	docker compose down -v

build:
	docker compose up --build -d
	$(MAKE) install

install:
	docker compose exec energy-service composer install --no-interaction
	docker compose exec energy-bff composer install --no-interaction

restart:
	docker compose restart

# ─── Logs / status ───────────────────────────────────────────────────────────

logs:
	docker compose logs -f

worker-logs:
	docker compose logs -f worker

ps:
	docker compose ps

# ─── Database ─────────────────────────────────────────────────────────────────

migrate:
	docker compose exec energy-bff php artisan migrate
	docker compose exec energy-service php artisan migrate

fresh:
	docker compose exec energy-bff php artisan migrate:fresh
	docker compose exec energy-service php artisan migrate:fresh

# ─── First-run setup ──────────────────────────────────────────────────────────

key-bff:
	docker compose exec energy-bff php artisan key:generate

# Usage: make user EMAIL=you@example.com PASSWORD=secret NAME="Your Name"
NAME     ?= Admin
EMAIL    ?= admin@example.com
PASSWORD ?= changeme

user:
	docker compose exec energy-bff php artisan tinker --execute=" \
	App\Models\User::firstOrCreate( \
	    ['email' => '$(EMAIL)'], \
	    ['name' => '$(NAME)', 'password' => bcrypt('$(PASSWORD)')] \
	); \
	echo 'User ready: $(EMAIL)';"

# ─── Tests ───────────────────────────────────────────────────────────────────

test:
	docker compose exec energy-service php artisan test
	docker compose exec energy-bff php artisan test
	docker compose exec energy-ui npm run test

# ─── Data collection (manual triggers for debugging) ─────────────────────────

fetch-battery:
	docker compose exec energy-service php artisan tinker --execute="dispatch(new App\Jobs\FetchBatteryStateJob())"

fetch-forecast:
	docker compose exec energy-service php artisan tinker --execute="dispatch(new App\Jobs\FetchSolarForecastJob(totalKwp: 7.6))"

fetch-consumption:
	docker compose exec energy-service php artisan tinker --execute="dispatch(new App\Jobs\FetchConsumptionJob())"

fetch-all: fetch-battery fetch-forecast fetch-consumption
