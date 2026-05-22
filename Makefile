.PHONY: up down build restart logs ps migrate fresh

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose up --build -d

restart:
	docker compose restart

logs:
	docker compose logs -f

ps:
	docker compose ps

migrate:
	docker compose exec energy-bff php artisan migrate
	docker compose exec energy-service php artisan migrate

fresh:
	docker compose exec energy-bff php artisan migrate:fresh
	docker compose exec energy-service php artisan migrate:fresh
