# Requiere make (Linux/macOS/WSL). En Windows (cmd) use los comandos "docker compose run --rm tools ..." del README.
T = docker compose run --rm tools

.PHONY: up down migrate fresh test unit arch lint fix stan deptrac mutate

up:       ; docker compose up -d --build
down:     ; docker compose down
migrate:  ; $(T) php artisan migrate
fresh:    ; $(T) php artisan migrate:fresh
test:     ; $(T) php vendor/bin/pest
unit:     ; $(T) php vendor/bin/pest --testsuite=Unit
arch:     ; $(T) php vendor/bin/pest --testsuite=Architecture
lint:     ; $(T) php vendor/bin/pint --test
fix:      ; $(T) php vendor/bin/pint
stan:     ; $(T) php vendor/bin/phpstan analyse --memory-limit=1G
deptrac:  ; $(T) php vendor/bin/deptrac analyse
mutate:   ; $(T) php vendor/bin/infection --threads=4 --only-covered
