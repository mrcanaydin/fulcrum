#!/usr/bin/env sh
set -eu

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -d ../core ]; then
    rm -f composer.smoke.lock
    rm -rf vendor/fulcrum/core
    rm -f vendor/bin/fulcrum
    cp composer.json composer.smoke.json
    COMPOSER=composer.smoke.json composer config repositories.fulcrum-core '{"type":"path","url":"../core","options":{"symlink":false,"versions":{"fulcrum/core":"0.1.0"}}}'
    COMPOSER=composer.smoke.json composer install --no-interaction
else
    composer install --no-interaction
fi

mkdir -p storage/app storage/cache storage/logs
find storage/app storage/cache storage/logs -type d -exec chmod 0777 {} + 2>/dev/null || true

docker compose up -d --build --force-recreate
docker compose exec -T php php fulcrum migrate
docker compose exec -T php php fulcrum db:seed
docker compose exec -T php php fulcrum api-data:fetch --sort=new
docker compose exec -T php php fulcrum queue:work --max-jobs=1 --sleep=0

preview_response="$(docker compose exec -T nginx wget -qO- --timeout=5 http://127.0.0.1/ || true)"
if ! printf '%s' "$preview_response" | grep -q '"mode":"headless"'; then
    docker compose logs
    exit 1
fi

for attempt in $(seq 1 60); do
    live_response="$(docker compose exec -T nginx wget -qO- --timeout=5 http://127.0.0.1/health/live || true)"
    ready_response="$(docker compose exec -T nginx wget -qO- --timeout=5 http://127.0.0.1/health/ready || true)"
    response="$(docker compose exec -T nginx wget -qO- \
        --header='Content-Type: application/json' \
        --post-data='{"query":"{ users(first: 1) { nodes { id name email email_verified_at banned_at } } }"}' \
        --timeout=5 \
        http://127.0.0.1/graphql || true)"

    if printf '%s' "$response" | grep -q '"errors"'; then
        printf '%s\n' "$response"
        docker compose logs
        exit 1
    fi

    if printf '%s' "$live_response" | grep -q '"status":"ok"' \
        && printf '%s' "$ready_response" | grep -q '"status":"ok"' \
        && printf '%s' "$response" | grep -q '"users"'; then
        printf '%s\n' "$ready_response"
        printf '%s\n' "$response"
        exit 0
    fi

    sleep 2
done

docker compose logs
exit 1
