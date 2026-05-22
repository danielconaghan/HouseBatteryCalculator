.PHONY: up down build restart logs ps migrate fresh test worker-logs install

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose up --build -d
	$(MAKE) install

install:
	docker compose exec energy-service composer install --no-interaction
	docker compose exec energy-bff composer install --no-interaction

restart:
	docker compose restart

logs:
	docker compose logs -f

worker-logs:
	docker compose logs -f worker

ps:
	docker compose ps

migrate:
	docker compose exec energy-bff php artisan migrate
	docker compose exec energy-service php artisan migrate

fresh:
	docker compose exec energy-bff php artisan migrate:fresh
	docker compose exec energy-service php artisan migrate:fresh

test:
	docker compose exec energy-service php artisan test
	docker compose exec energy-bff php artisan test
	docker compose exec energy-ui npm run test

# Manually trigger a data-collection job for debugging
fetch-battery:
	docker compose exec energy-service php artisan tinker --execute="dispatch(new App\Jobs\FetchBatteryStateJob())"

fetch-forecast:
	docker compose exec energy-service php artisan tinker --execute="dispatch(new App\Jobs\FetchSolarForecastJob(totalKwp: 7.6))"

fetch-consumption:
	docker compose exec energy-service php artisan tinker --execute="dispatch(new App\Jobs\FetchConsumptionJob())"
