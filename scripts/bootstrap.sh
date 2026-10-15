#!/bin/sh
# Instala Laravel y superpone los archivos de este repositorio SIN pisarlos.
# Requiere PHP 8.3+ y Composer 2. Con Docker (sin instalar PHP):
#   docker run --rm -v "$PWD":/work -w /work composer:2 sh scripts/bootstrap.sh
set -eu
cd "$(dirname "$0")/.."

if [ -f artisan ]; then echo "Laravel ya está instalado. Nada que hacer."; exit 0; fi

TMP=".skeleton-tmp"
composer create-project laravel/laravel "$TMP" --prefer-dist --no-interaction
cp -Rn "$TMP"/. .          # -n: nuestros archivos tienen prioridad sobre los del esqueleto
rm -rf "$TMP"
rm -f database/migrations/*_create_users_table.php tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php
rm -f .env && cp .env.example .env     # el .env del esqueleto usa SQLite: se reemplaza
printf '\n/build\n/frankenphp\n/.skeleton-tmp\n' >> .gitignore

composer config --no-plugins allow-plugins.pestphp/pest-plugin true
composer config --no-plugins allow-plugins.infection/extension-installer true
composer require laravel/octane spatie/laravel-permission spatie/laravel-activitylog --no-interaction
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan deptrac/deptrac infection/infection --no-interaction -W

php artisan key:generate --no-interaction
php artisan vendor:publish --tag=octane-config --no-interaction || true
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction || true
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag=activitylog-migrations --no-interaction || true

echo ""
echo "Listo. Siguientes pasos:"
echo "  1) docker compose up -d db        (PostgreSQL con roles y BD de pruebas)"
echo "  2) php artisan migrate            (crea todo el esquema)"
echo "  3) vendor/bin/pint                (formatea una vez el código antes del primer commit)"
echo "  4) vendor/bin/pest                (pruebas unitarias, de arquitectura e integración)"
