#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
if [ ! -f .env ]; then
    umask 077
    while IFS= read -r linea || [ -n "$linea" ]; do
        case "$linea" in
            *=cambiar_*)
                clave=$(od -An -N32 -tx1 /dev/urandom | tr -d ' \n')
                printf '%s=%s\n' "${linea%%=*}" "$clave"
                ;;
            *) printf '%s\n' "$linea" ;;
        esac
    done < .env.example > .env
    echo 'Se creó .env con claves locales aleatorias.'
fi
docker compose up -d --build
echo 'Abra http://localhost:8081 (o el APP_PORT indicado en .env).'
