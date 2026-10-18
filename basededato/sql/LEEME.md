# Base de datos — PostgreSQL

Base `clinica_vida_salud`: 14 tablas normalizadas (3FN) con llaves primarias y foráneas, restricciones `CHECK`, `UNIQUE`
y `EXCLUDE`, 7 funciones de trigger (13 triggers), 3 vistas y datos de prueba.

## Instalar (Windows)

**Opción 1 — doble clic en `instalar_bd.bat`.** Pide la contraseña del usuario `postgres`, borra la base si ya existía,
la vuelve a crear y ejecuta `instalar.sql`. Busca `psql.exe` en el PATH o en `C:\Program Files\PostgreSQL\<versión>\bin`.

**Opción 2 — terminal** (desde esta carpeta):

```bat
psql -U postgres -h localhost -c "CREATE DATABASE clinica_vida_salud ENCODING 'UTF8' TEMPLATE template0"
psql -U postgres -h localhost -d clinica_vida_salud -f instalar.sql
```

`TEMPLATE template0` evita el error de codificación cuando PostgreSQL se instaló en Windows con WIN1252. Los scripts
fijan `client_encoding = UTF8`, así que las tildes y la ñ se guardan bien.

Si la contraseña de `postgres` no es `postgres`, indíquela al sistema en `backend/config/config.local.php`
(ver [backend/README.md](../../backend/README.md)).

## Scripts (los ejecuta `instalar.sql` en este orden)

| Archivo | Contenido |
|---|---|
| `01_esquema.sql` | Extensión `btree_gist`, función `fn_normalizar_texto`, secuencias de códigos (PAC-0001, MED-001, CM-0001) y las 14 tablas con todas sus restricciones |
| `02_funciones_triggers.sql` | Triggers: fecha de actualización, validación de citas (fecha pasada, médico inactivo, fuera de horario, día no laborable), transiciones de estado, historial automático de cada cita, prohibición de borrar citas y validación usuario–rol |
| `03_vistas.sql` | `v_cita_detalle`, `v_medico_especialidades`, `v_resumen_citas_dia` (las usan los reportes y las agendas) |
| `04_datos_iniciales.sql` | Roles, 21 permisos (incluido `bitacora.ver`, solo para el administrador), permisos por rol, 5 especialidades y el usuario `admin` |
| `05_datos_prueba.sql` | Médicos, horarios, días no laborables, 20 pacientes, usuarios de demostración y citas alrededor de la fecha de instalación |
| `99_reiniciar.sql` | Borra todo el contenido (luego se vuelve a ejecutar `instalar.sql`) |

Todos los usuarios de demostración tienen la contraseña `Clinica2026*`.

## Tablas

`rol`, `permiso`, `rol_permiso`, `usuario`, `paciente`, `contacto_emergencia`, `especialidad`, `medico`,
`medico_especialidad`, `horario_atencion`, `dia_no_laborable`, `cita`, `cita_historial`, `bitacora`.

Reglas que garantiza la propia base (aunque alguien se salte la aplicación):

| Regla | Objeto |
|---|---|
| Un médico no tiene dos citas que se crucen | `EXCLUDE ex_cita_medico_sin_solape` (las canceladas liberan el horario) |
| Un paciente no tiene dos citas a la misma hora | `EXCLUDE ex_cita_paciente_sin_solape` |
| Los bloques de horario de un médico no se cruzan | `EXCLUDE ex_horario_sin_solape` |
| CI único y especialidad con nombre único sin importar tildes ni mayúsculas | `UNIQUE uq_paciente_ci`, índice sobre `fn_normalizar_texto(nombre)` |
| El médico atiende la especialidad de la cita | FK compuesta `fk_cita_medico_especialidad` |
| Una cita cancelada exige motivo | `CHECK ck_cita_cancelada` |
| Las citas no se borran; todo cambio queda en `cita_historial` | triggers `fn_tg_impedir_eliminacion` y `fn_tg_historial_cita` |
| La bitácora de acciones (CU02) no se modifica ni se borra | trigger `fn_tg_bitacora_inalterable` (`BEFORE UPDATE OR DELETE`) |

## Pruebas

```bat
psql -U postgres -h localhost -d clinica_vida_salud -f pruebas/pruebas_bd.sql
```

Ejecuta 16 pruebas que intentan guardar datos inválidos (las 15 y 16 intentan borrar y modificar la bitácora) y verifica que PostgreSQL los rechace. Todo ocurre dentro de una
transacción que se revierte al final, así que no deja cambios.

## Modificar la base

Cambie el script que corresponda (por ejemplo, una columna nueva en `01_esquema.sql`), vuelva a instalar con
`instalar_bd.bat` y ejecute las pruebas. Si la columna se usa en el sistema, actualice también el modelo en
`backend/app/paquetes/<paquete>/modelos/`.

## Problemas comunes

| Problema | Solución |
|---|---|
| `psql` no se reconoce | Use la ruta completa `"C:\Program Files\PostgreSQL\16\bin\psql.exe"` (cambie 16 por su versión) o use `instalar_bd.bat`, que la busca solo |
| *new encoding (UTF8) is incompatible with the encoding of the template database* | Cree la base con `TEMPLATE template0`, como en los comandos de arriba |
| Tildes raras (`PediatrÃ­a`) | La base se instaló sin `client_encoding = UTF8`: vuelva a instalarla con `instalar.sql` (ya lo fija) |
| *extension "btree_gist" is not available* | Instale PostgreSQL con el instalador oficial (incluye las extensiones *contrib*) |
| *database is being accessed by other users* al reinstalar | Cierre pgAdmin o detenga Apache un momento y vuelva a ejecutar `instalar_bd.bat` |
| No hay citas «de hoy» en la demostración | Los datos de prueba se generan alrededor del día de la instalación: reinstale la base el día anterior a la defensa |
