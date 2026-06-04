#!/usr/bin/env sh
set -eu

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -d ../core ]; then
    rm -f composer.smoke.lock
    cp composer.json composer.smoke.json
    COMPOSER=composer.smoke.json composer config repositories.fulcrum-core '{"type":"path","url":"../core","options":{"symlink":false,"versions":{"fulcrum/core":"0.1.0"}}}'
    COMPOSER=composer.smoke.json composer install --no-interaction --ignore-platform-req=ext-mongodb
else
    composer install --no-interaction
fi

mkdir -p storage/app storage/cache storage/logs
find storage/app storage/cache storage/logs -type d -exec chmod 0777 {} +

docker compose up -d --build
docker compose exec -T php ./vendor/bin/fulcrum migrate

preview_response="$(docker compose exec -T nginx wget -qO- --timeout=5 http://127.0.0.1/ || true)"
if ! printf '%s' "$preview_response" | grep -q '"mode":"headless"'; then
    docker compose logs
    exit 1
fi

for attempt in $(seq 1 60); do
    response="$(docker compose exec -T nginx wget -qO- \
        --header='Content-Type: application/json' \
        --post-data='{"query":"{ health }"}' \
        --timeout=5 \
        http://127.0.0.1/graphql || true)"

    if printf '%s' "$response" | grep -q '"health":"ok"'; then
        printf '%s\n' "$response"
        exit 0
    fi

    sleep 2
done

docker compose logs
exit 1
