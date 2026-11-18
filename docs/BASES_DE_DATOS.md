# PostgreSQL y MongoDB · Administración de Base de Datos

Este documento explica cómo están organizadas y administradas las bases del sistema, propone prácticas y define evidencias para el alumno. La correspondencia académica se basa en los temas implementados; el docente debe ajustarla al sílabo oficial.

## 1. Responsabilidad de cada motor

| Motor | Información | Criterio |
|---|---|---|
| PostgreSQL | Clientes, técnicos, catálogos, equipos, tickets, asignaciones, diagnósticos, servicios, repuestos y auditoría | Relaciones, integridad, transacciones y reglas SQL |
| MongoDB | Actividades, observaciones y fotos | Documentos con arrays y contenido BSON |

El diagnóstico formal está en PostgreSQL; MongoDB conserva la actividad que lo describe y las fotos asociadas. La auditoría SQL registra estados; el historial documental registra actividades. No son copias idénticas.

## 2. Modelo relacional PostgreSQL

| Esquema | Tabla | Responsabilidad |
|---|---|---|
| personas | cliente | Datos del propietario; DNI único |
| personas | tecnico | Responsable y disponibilidad |
| catalogo | tipo_equipo, marca | Catálogos referenciados por el equipo |
| soporte | equipo | Relación con cliente, tipo y marca; serie única |
| soporte | ticket | Problema, prioridad, estado y fechas |
| soporte | asignacion | Historial de técnicos por atención |
| soporte | diagnostico | Hallazgos y recomendación |
| soporte | servicio_realizado | Trabajo y costo de mano de obra |
| soporte | repuesto_utilizado | Repuesto, cantidad y costo unitario por servicio |
| auditoria | auditoria_ticket | Cambios de estado y cuenta de conexión |

```text
cliente 1 ── N equipo 1 ── N ticket
tipo_equipo / marca 1 ── N equipo
ticket 1 ── N asignacion N ── 1 tecnico
ticket 1 ── N diagnostico
ticket 1 ── N servicio_realizado 1 ── N repuesto_utilizado
ticket 1 ── N auditoria_ticket
```

Las PK identifican registros y las FK impiden referencias inexistentes. CHECK limita estados, prioridades, costos y cantidades. UNIQUE impide duplicar DNI/serie y un índice parcial limita a una asignación ACTIVA por ticket. Los importes SQL son NUMERIC; PHP valida el texto decimal antes de enviarlo al motor.

La función de costos suma mano de obra y repuestos por separado para evitar multiplicar costos mediante JOIN. Los triggers validan y auditan; los procedimientos coordinan asignación, estados y disponibilidad. Todo está definido en `database/postgresql/01_modelo.sql` y `03_logica.sql`.

## 3. Modelo documental MongoDB

`historial_ticket` tiene un índice único en `ticket_id` y un validador que exige identificador positivo y arrays `historial`/`observaciones`. El validador no comprueba las relaciones con SQL ni cada propiedad interna de las actividades.

```js
{
  ticket_id: 1,
  historial: [{fecha: ISODate('2026-01-01T12:00:00Z'),
    accion: 'DIAGNOSTICO', detalle: 'Cable defectuoso', usuario: 'Alumno',
    id_diagnostico: 3, fotos: ['identificador_ObjectId_como_texto']}],
  observaciones: ['Revisar cargador']
}
```

`evidencias` guarda `_id`, `ticket_id`, `id_diagnostico`, nombre, MIME, fecha, usuario y `contenido` BSON binario. Tiene un índice por `ticket_id`. Cada foto está limitada a 5 MB por la aplicación; no se usa GridFS. Sus relaciones se verifican desde PHP, no mediante una FK MongoDB.

Los historiales crecen en un único documento por ticket. El ejemplo no implementa archivado ni segmentación; para grandes volúmenes habría que revisar el crecimiento y el límite BSON de 16 MiB por documento.

## 4. Abrir las herramientas de los motores

PostgreSQL, desde la terminal del proyecto:

```sh
docker compose exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
```

MongoDB, en otra terminal:

```sh
docker compose exec mongodb sh -c 'mongosh --username "$MONGO_INITDB_ROOT_USERNAME" --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin "$MONGO_INITDB_DATABASE"'
```

Estos comandos usan las cuentas administradoras para la práctica. La web utiliza `soporte_web` en PostgreSQL y `MONGO_APP_USER` en MongoDB. También puede usar clientes gráficos con los datos de conexión indicados arriba.

## 5. Esquemas, relaciones y consultas SQL

Ejecute dentro de psql o del editor SQL:

```sql
-- Deben aparecer once tablas operativas distribuidas en cuatro esquemas.
SELECT table_schema, table_name
FROM information_schema.tables
WHERE table_schema IN ('personas','catalogo','soporte','auditoria')
  AND table_type = 'BASE TABLE'
ORDER BY table_schema, table_name;

-- Vista que relaciona ticket, cliente, equipo y responsable.
SELECT id_ticket, cliente, equipo, tecnico, estado
FROM soporte.vw_ticket_operativo ORDER BY id_ticket;

SELECT prioridad, estado, count(*) AS cantidad
FROM soporte.ticket GROUP BY prioridad, estado ORDER BY prioridad, estado;

SELECT te.nombres, count(*) AS asignaciones
FROM soporte.asignacion a JOIN personas.tecnico te USING(id_tecnico)
GROUP BY te.id_tecnico HAVING count(*) >= 1;
```

Identifique las claves primarias y foráneas en `database/postgresql/01_modelo.sql`. Explique por qué se separan cliente, equipo, ticket y asignación, y por qué el catálogo evita repetir nombres.

## 6. Funciones, procedimientos y triggers

```sql
SELECT soporte.fn_total_tickets();
SELECT soporte.fn_costo_ticket(1);
SELECT soporte.fn_ticket_json(1);
SELECT * FROM soporte.diagnostico WHERE id_ticket = 1;
SELECT * FROM auditoria.auditoria_ticket WHERE id_ticket = 1 ORDER BY fecha;
```

En `database/postgresql/03_logica.sql`, revise `sp_asignar_tecnico`, `sp_cambiar_estado`, `fn_validar_cierre` y `auditoria.fn_auditar_ticket`. Ejecute la asignación y los cambios de estado desde la web y vuelva a consultar sus resultados. Distinga procedimiento (operación), función (valor devuelto) y trigger (ejecución automática).

## 7. Transacciones: ROLLBACK y COMMIT

```sql
SELECT prioridad FROM soporte.ticket WHERE id_ticket = 1;
BEGIN;
CALL soporte.sp_cambiar_prioridad(1, 'BAJA');
SELECT prioridad FROM soporte.ticket WHERE id_ticket = 1;
ROLLBACK;
SELECT prioridad FROM soporte.ticket WHERE id_ticket = 1;
```

Compare el valor antes, durante y después. Repita el bloque usando COMMIT para guardar la prioridad. **COMMIT modifica el registro elegido.** Relacione esta práctica con `transaccion()` y `registrar_cliente()` en PHP: si el equipo tiene una serie duplicada, se revierte también el cliente.

## 8. Índices y plan de consulta

```sql
SELECT schemaname, tablename, indexname, indexdef
FROM pg_indexes WHERE schemaname IN ('personas','catalogo','soporte','auditoria')
ORDER BY schemaname, tablename;

EXPLAIN SELECT * FROM soporte.ticket WHERE estado = 'ABIERTO';
```

Explique el índice parcial que permite una sola asignación ACTIVA por ticket. Un plan puede usar lectura secuencial en tablas pequeñas aunque exista un índice; no significa que el índice esté mal.

## 9. Roles, mínimo privilegio y privacidad

```sql
SELECT rol,
       has_table_privilege(rol,'soporte.ticket','SELECT') AS puede_leer,
       has_table_privilege(rol,'soporte.ticket','DELETE') AS puede_borrar
FROM (VALUES ('rol_consulta'),('rol_tecnico'),('soporte_web')) r(rol);

SELECT * FROM personas.vw_cliente_protegido;

-- Como administrador, asumir temporalmente el rol web para comprobar permisos.
SET ROLE soporte_web;
DELETE FROM soporte.ticket WHERE FALSE; -- Debe rechazar DELETE, sin borrar filas.
RESET ROLE;

-- Demostración criptográfica; no cifra automáticamente los datos del sistema.
SELECT crypt('Ejemplo de clase', gen_salt('bf')) AS hash;
SELECT pgp_sym_decrypt(
    pgp_sym_encrypt('Texto de prueba','clave-didactica'),
    'clave-didactica'
) AS texto_recuperado;
```

La cuenta web puede provocar auditoría mediante el trigger, pero no insertarla directamente. Revise `SECURITY DEFINER`, `search_path` y `session_user` en esa función. `rol_consulta` y `rol_tecnico` son roles NOLOGIN para prácticas, no usuarios web.

## 10. MongoDB: documentos e interoperabilidad

Ejecute dentro de mongosh, sobre el ticket de práctica:

```js
db.historial_ticket.find({ticket_id: 1});
db.evidencias.find({ticket_id: 1}, {contenido: 0});
db.historial_ticket.getIndexes();

db.historial_ticket.updateOne({ticket_id: 1}, {$set: {nota_revision: 'Práctica de clase'}});
db.historial_ticket.updateOne({ticket_id: 1}, {$push: {historial: {
    fecha: new Date(), accion: 'SEGUIMIENTO', detalle: 'Revisión de clase', usuario: 'Alumno'
}}});
db.historial_ticket.updateOne({ticket_id: 1}, {$addToSet: {observaciones: 'Revisar cargador'}});
db.historial_ticket.updateOne({ticket_id: 1}, {$pull: {observaciones: 'Revisar cargador'}});

db.historial_ticket.aggregate([
    {$unwind: '$historial'},
    {$group: {_id: '$ticket_id', actividades: {$sum: 1}}},
    {$sort: {actividades: -1}}
]);
```

Repita `$addToSet` antes de `$pull` y compruebe que no duplica el texto. `nota_revision` es un campo documental de demostración; no cambia la asignación relacional. Las fotos están en documentos BSON separados de hasta 5 MB, sin GridFS.

La relación entre motores es `soporte.ticket.id_ticket` ↔ `historial_ticket.ticket_id`. No hay clave foránea ni transacción compartida entre ambos motores. Para consultar diferencias, desde la terminal del proyecto:

```sh
docker compose exec -T web php verificar_consistencia.php
```

El verificador no repara ni elimina datos. Explique qué ocurriría si MongoDB confirma una escritura y después falla el COMMIT SQL.

## 11. Respaldo, recuperación y mantenimiento

Windows:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/backup.ps1
powershell -ExecutionPolicy Bypass -File scripts/restore.ps1 -Origen backups/FECHA
powershell -ExecutionPolicy Bypass -File scripts/maintenance.ps1
```

Linux/macOS:

```sh
sh scripts/backup.sh
sh scripts/restore.sh backups/FECHA
sh scripts/maintenance.sh
```

Use la carpeta real creada por el respaldo en lugar de `FECHA`. Conserve juntos los dos archivos: incluyen las fotos. El respaldo detiene temporalmente la web; no escriba desde otros clientes mientras se ejecuta.

La recuperación crea `soporte_php_restaurada` y `soporte_php_mongo_restaurada`, conservando las bases originales. Abra esas bases en sus clientes y compare conteos y fotos. Si el destino ya existe, el script se detiene; no borra una recuperación anterior.

```sql
SELECT schemaname, relname, pg_size_pretty(pg_total_relation_size(relid)) AS tamano
FROM pg_stat_user_tables ORDER BY schemaname, relname;
```

El mantenimiento ejecuta VACUUM/ANALYZE. No tiene por qué reducir inmediatamente el tamaño del archivo. Mida duración del respaldo y de la recuperación, y explique RPO (pérdida de datos tolerable) y RTO (tiempo de recuperación tolerable).

## 11. Operación, seguridad y límites administrativos

- Los contenedores no son el almacenamiento permanente: los datos residen en `postgres_data` y `mongo_data`. Los volúmenes no sustituyen un respaldo.
- El orden de instalación SQL es modelo → datos → lógica → permisos; MongoDB crea usuario, colecciones e historiales iniciales. Solo se ejecuta con volúmenes nuevos.
- Mantenga cuentas de aplicación separadas de las administradoras. No distribuya `.env` ni incluya claves en capturas.
- La vista protegida enmascara datos, pero la cuenta web también necesita acceso a las tablas operativas. No constituye aislamiento completo de información personal.
- Los ejemplos criptográficos son demostraciones. El sistema no cifra automáticamente DNI, correos ni fotos; no configura TLS en la red del aula.
- MongoDB confirma por separado de SQL. El verificador detecta historiales, diagnósticos o fotos sin correspondencia y referencias incompletas; no los repara.
- Para diagnosticar, registre error, ticket afectado y estado de ambos motores antes de corregir. No elimine volúmenes como primera medida.

## 12. Verificación del alumno y relación con la unidad didáctica

| Tema de la unidad | Dónde está implementado | Qué debe verificar el alumno | Evidencia |
|---|---|---|---|
| Instalación y configuración | `compose.yaml`, `.env.example` | Tres servicios iniciados, puertos externos e internos y ubicación de los volúmenes | Tabla de configuración sin claves y respuesta de salud |
| Organización y modelado relacional | `01_modelo.sql` | Once tablas, cuatro esquemas, PK, FK, catálogos y relaciones cliente–equipo–ticket | Diagrama elaborado por el alumno y explicación de tres relaciones |
| Integridad de datos | Restricciones SQL e índice `uq_asignacion_activa` | Rechazo de DNI/serie duplicados, costos negativos y más de una asignación activa | Error observado y consulta que demuestra datos consistentes |
| Consultas y vistas | `vw_ticket_operativo`, consultas del README | JOIN, GROUP BY y HAVING sobre datos del caso | Consultas y resultados explicados |
| Funciones y procedimientos | `03_logica.sql` | Asignación de técnico, cambio de estado y cálculo del costo | Archivo y rutina identificados; antes/después de la operación |
| Transacciones | `db.php`, `registrar_cliente()` y SQL | Cliente/equipo atómicos; diferencia entre COMMIT y ROLLBACK | Serie duplicada sin cliente parcial y comparación de prioridades |
| Triggers y auditoría | `fn_validar_cierre`, `fn_auditar_ticket` | Rechazo de cierre prematuro; fecha y auditoría al cerrar correctamente | Estado anterior, error, cierre válido y fila de auditoría |
| Roles y seguridad | `04_permisos.sh`, roles de práctica | Usuario web limitado, DELETE denegado y consultas parametrizadas | Matriz de permisos y ejemplo localizado en PHP |
| Privacidad y criptografía | `vw_cliente_protegido`, extensión pgcrypto | Diferencia entre enmascarado, hash y cifrado reversible | Resultados y explicación; reconocer que el sistema no cifra todo automáticamente |
| Índices y optimización | Índices de `01_modelo.sql`, EXPLAIN | Objetivo de cada índice y lectura del plan, aun con tablas pequeñas | Un plan y explicación de sus nodos principales |
| Modelo documental e interoperabilidad | `mongo.php`, `historial_ticket`, `evidencias` | Mismo identificador lógico en ambos motores; operaciones documentales y fotos BSON | Ticket SQL, documento MongoDB y prueba de observación sin duplicados |
| Respaldo y recuperación | `scripts/backup.*`, `restore.*` | Copia de ambos motores y recuperación en bases separadas | Archivos, conteos comparados y foto recuperada |
| Mantenimiento y continuidad | `maintenance.*`, `verificar_consistencia.php` | VACUUM/ANALYZE, diferencias entre motores y límites de las transacciones | Ejecución, interpretación y propuesta de RPO/RTO |

### Recorrido mínimo y resultados esperados

- [ ] La página de salud informa conexión con ambos motores.
- [ ] El cliente y su equipo aparecen relacionados por `id_cliente`.
- [ ] El ticket existe en PostgreSQL y tiene un único historial documental con su `ticket_id`.
- [ ] La asignación cambia el ticket a ASIGNADO y deja una sola asignación ACTIVA.
- [ ] Reasignar conserva la asignación anterior como FINALIZADA.
- [ ] El diagnóstico aparece en SQL; su actividad y foto se relacionan con él en MongoDB.
- [ ] Mano de obra 25.50 y dos repuestos de 10.25 dan 46.00 mediante la función SQL.
- [ ] Intentar cerrar antes de RESUELTO conserva el estado anterior.
- [ ] Cerrar correctamente completa fecha, finaliza asignaciones y registra auditoría.
- [ ] ROLLBACK conserva la prioridad original; COMMIT guarda el cambio elegido.
- [ ] `$addToSet` no duplica una observación y `$pull` elimina solo el texto indicado.
- [ ] La recuperación tiene los mismos conteos y fotos del momento del respaldo.
- [ ] El verificador no informa referencias incompletas; si las encuentra, se documentan y explican.

### Cómo explicar la implementación

Elija la creación de un cliente, la asignación o el cierre de un ticket y describa:

1. Pantalla y datos que recibe el formulario.
2. Acción correspondiente en `app/public/acciones.php`.
3. Función PHP, consulta o procedimiento que se ejecuta.
4. Tablas/documentos que cambian y las restricciones que intervienen.
5. Momento de confirmación o reversión y respuesta presentada al usuario.

Distinga la validación PHP de una restricción en el motor. Distinga también la auditoría SQL de estados del historial documental de actividades: no son copias idénticas.

### Entregable

Presente un informe breve con:

- Identificador del caso, diagrama de relaciones y matriz de verificaciones completada.
- Consultas utilizadas y evidencias suficientes para demostrar sus conclusiones.
- Una operación explicada desde la pantalla hasta la base de datos.
- Un error provocado de forma controlada y la regla que lo impidió.
- Comparación del respaldo con las bases recuperadas, tiempos medidos y propuesta de RPO/RTO.
- Dos limitaciones detectadas y una mejora justificada desde la administración de bases de datos.

No basta con capturar pantallas: explique qué demuestra cada resultado. No afirme que PostgreSQL y MongoDB comparten una transacción; el proyecto no la implementa. Para este ejemplo se recomienda una atención activa por equipo, pero no existe una restricción que lo imponga.
