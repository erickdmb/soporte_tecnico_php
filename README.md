# Sistema de soporte técnico

## Descripción

Sistema académico para registrar la recepción y atención de equipos: **cliente → equipo → ticket → técnico → diagnóstico → servicio → repuestos → cierre**. Permite estudiar la aplicación de PostgreSQL y MongoDB en la unidad didáctica de Administración de Base de Datos.

## Funcionalidades

- Gestión de clientes, equipos, técnicos, marcas y tipos de equipo.
- Creación de tickets, prioridades, asignación y reasignación de responsables.
- Diagnósticos y recomendaciones con fotos opcionales.
- Registro de servicios y repuestos, con cálculo del costo total.
- Seguimiento de estados, validación del cierre y auditoría.
- Historial documental, observaciones y consulta de evidencias.
- Búsqueda de tickets y comprobación de conexiones mediante `/salud.php`.

## Stack

| Parte | Tecnología |
|---|---|
| Backend | PHP 8.4, Apache y PDO PostgreSQL |
| Frontend | Vistas PHP, HTML, Bootstrap 5.3.9, CSS y JavaScript |
| Base relacional | PostgreSQL 16 y pgcrypto |
| Base documental | MongoDB 7 y extensión PHP MongoDB 2.1.4 |
| Ejecución | Docker y Docker Compose v2 |
| Administración | psql, mongosh y scripts PowerShell/POSIX shell |
| Pruebas | Recorrido PHP con verificaciones SQL, MongoDB y HTTP |

Bootstrap está incluido localmente. No requiere Composer, Node.js ni compilación frontend.

## Puesta en marcha

Necesita Docker iniciado y conexión a Internet para la primera construcción. Abra una terminal en esta carpeta:

**Windows:**

```powershell
powershell -ExecutionPolicy Bypass -File scripts/install.ps1
```

**Linux/macOS:**

```sh
sh scripts/install.sh
```

El instalador crea `.env` con claves aleatorias si no existe, inicia los servicios y conserva los datos en volúmenes. Abra **http://localhost:8081**; compruebe conexiones en **http://localhost:8081/salud.php**. La instalación inicial incluye dos clientes, dos técnicos, dos equipos y dos tickets ficticios.

Para probar el recorrido: registre cliente y equipo, cree un ticket, asigne un técnico, agregue diagnóstico y costos, cambie a EN_PROCESO, RESUELTO y CERRADO, y revise la auditoría.

## Configuración de los servicios

| Servicio | Puerto del servidor | Puerto interno | Persistencia |
|---|---|---|---|
| `web` | `APP_PORT`, por defecto 8081 | 80 | El código está en la imagen PHP |
| `postgres` | `POSTGRES_HOST_PORT`, por defecto 5434 | 5432 | Volumen `postgres_data` |
| `mongodb` | `MONGO_HOST_PORT`, por defecto 27019 | 27017 | Volumen `mongo_data` |

La configuración se define en `.env`, usando `.env.example` como referencia:

- `BIND_ADDRESS`: `0.0.0.0` permite acceso por red; `127.0.0.1` limita al servidor.
- `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`: base y cuenta administradora SQL.
- `APP_DB_PASSWORD`: clave de la cuenta SQL web `soporte_web`.
- `MONGO_DB`, `MONGO_ROOT_USER`, `MONGO_ROOT_PASSWORD`: base y cuenta administradora documental.
- `MONGO_APP_USER`, `MONGO_APP_PASSWORD`: cuenta de aplicación, autenticada en `MONGO_DB`.

Dentro de Docker, PHP utiliza los hosts `postgres` y `mongodb`. Desde otra PC de la misma red use **`http://IP_DEL_SERVIDOR:8081`** y la IP del servidor como host de las bases, con puertos **5434** y **27019**. No use `localhost` de la PC cliente. Para pgAdmin/DBeaver use la base de `POSTGRES_DB`, usuario `soporte_web` y `APP_DB_PASSWORD`; en Compass use `MONGO_APP_USER`, `MONGO_APP_PASSWORD` y `authSource=MONGO_DB`.

Si Windows bloquea el acceso, ejecute `scripts/habilitar-red.ps1` desde PowerShell como administrador: permite los puertos configurados únicamente desde la subred local. Las credenciales son las del servidor al que se conecta, no las de otra instalación.

Después de cambiar puertos, ejecute `docker compose up -d`. Cambiar una clave en `.env` no actualiza la clave guardada en un motor ya instalado. Los SQL/JS de inicialización solo se ejecutan con volúmenes nuevos.

```sh
docker compose stop
docker compose start
docker compose logs --tail 80 web postgres mongodb
# Después de modificar PHP o CSS:
docker compose up -d --build web
```

No use `docker compose down -v` para actualizar: elimina los datos. Comparta el código y `.env.example`; excluya `.env`, respaldos y datos locales.

## Decisiones de diseño y arquitectura

Se utiliza una aplicación PHP con páginas generadas en el servidor, sin framework backend, para facilitar la lectura. PostgreSQL concentra las relaciones y reglas; MongoDB guarda actividades y fotos. Las cuentas web se separan de las administradoras y ambos motores mantienen sus datos en volúmenes. Aplicación y pruebas reutilizan una sola imagen PHP.

La web es un ejemplo para la red del aula: no implementa inicio de sesión de usuarios ni una API REST de negocio. PostgreSQL y MongoDB no comparten una transacción distribuida.

- [Arquitectura](docs/ARQUITECTURA.md): visión general, estructura, endpoints, modelo, flujos, seguridad, frontend, decisiones y pruebas.
- [Bases de datos y Administración de Base de Datos](docs/BASES_DE_DATOS.md): explicación de ambos motores, prácticas, seguridad, mantenimiento, recuperación y verificaciones del alumno.
