#!/bin/sh
set -eu
# psql escapa la contraseña como literal SQL; no se concatena en una consulta.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
  --set=web_password="$APP_DB_PASSWORD" <<'SQL'
CREATE ROLE soporte_web LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD :'web_password';
GRANT USAGE ON SCHEMA personas,catalogo,soporte,auditoria TO soporte_web;
GRANT SELECT ON ALL TABLES IN SCHEMA personas,catalogo,soporte,auditoria TO soporte_web;
GRANT INSERT,UPDATE ON ALL TABLES IN SCHEMA personas,catalogo,soporte TO soporte_web;
GRANT USAGE,SELECT ON ALL SEQUENCES IN SCHEMA personas,catalogo,soporte TO soporte_web;
GRANT EXECUTE ON ALL ROUTINES IN SCHEMA soporte TO soporte_web;
-- La auditoría la escribe un trigger; la web no recibe INSERT ni DELETE sobre ella.
SQL
