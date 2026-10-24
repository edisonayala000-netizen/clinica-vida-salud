# Base de datos — PostgreSQL

Base `clinica_vida_salud`: 24 tablas normalizadas (3FN/FNBC) con llaves primarias y foráneas, restricciones `CHECK`,
`UNIQUE` y `EXCLUDE`, 15 funciones de trigger (34 triggers), 8 vistas y datos de prueba.

**PERSONA** es la generalización de **PACIENTE** y **MEDICO** (y de todo usuario): los datos comunes (CI, nombres,
apellidos, teléfono, correo...) se guardan una sola vez en `persona`, y cada subtipo usa como llave primaria la misma
llave de su persona (`id_paciente = id_persona`, `id_medico = id_persona`).

## Instalar (Windows)

**Opción 1 — doble clic en `instalar_bd.bat`.** Pide el puerto (Enter = 5432) y la contraseña del usuario `postgres`,
borra la base si ya existía, la vuelve a crear y ejecuta `instalar.sql`. Busca `psql.exe` en el PATH o en
`C:\Program Files\PostgreSQL\<versión>\bin`.

**Opción 2 — terminal** (desde esta carpeta):

```bat
psql -U postgres -h localhost -c "CREATE DATABASE clinica_vida_salud ENCODING 'UTF8' TEMPLATE template0"
psql -U postgres -h localhost -d clinica_vida_salud -f instalar.sql
```

`TEMPLATE template0` evita el error de codificación cuando PostgreSQL se instaló en Windows con WIN1252. Los scripts
fijan `client_encoding = UTF8`, así que las tildes y la ñ se guardan bien. Si PostgreSQL no usa el puerto 5432,
agregue `-p <puerto>` a los comandos.

Si la contraseña de `postgres` no es `postgres` (o el puerto no es 5432), indíquelo al sistema en
`backend/config/config.local.php` (ver [backend/README.md](../../backend/README.md)).

## Scripts (los ejecuta `instalar.sql` en este orden)

| Archivo | Contenido |
|---|---|
| `01_esquema.sql` | Extensión `btree_gist`, función `fn_normalizar_texto`, secuencias de códigos (PAC-0001, MED-001, CM-0001, REC-0001) y las 24 tablas con todas sus restricciones |
| `02_funciones_triggers.sql` | Triggers: fecha de actualización, datos obligatorios según el subtipo de la persona, validación de citas, transiciones de estado (leídas del catálogo), historial de cada cita, prohibición de borrar citas y registros clínicos, validación usuario–rol, bitácora inalterable, auditoría de datos, uso único del token de recuperación, atención solo de citas atendidas y programación de recordatorios por correo |
| `03_vistas.sql` | `v_paciente`, `v_medico`, `v_usuario` (cada subtipo unido a su persona), `v_cita_detalle`, `v_medico_especialidades`, `v_resumen_citas_dia`, `v_signo_vital` (con el IMC) y `v_receta_detalle` |
| `04_datos_iniciales.sql` | Roles, 26 permisos, permisos por rol, estados de la cita y sus transiciones, 5 especialidades, 32 medicamentos y el usuario `admin` |
| `05_datos_prueba.sql` | Médicos, horarios, días no laborables, 20 pacientes, personal de recepción, usuarios de demostración, citas alrededor de la fecha de instalación y 2 atenciones con signos vitales y receta |
| `06_diccionario.sql` | Diccionario de datos: descripción de cada tabla y columna (`COMMENT ON`), visible en pgAdmin y base del diccionario de [NORMALIZACION.md](../NORMALIZACION.md) |
| `99_reiniciar.sql` | Borra todo el contenido (luego se vuelve a ejecutar `instalar.sql`) |

Todos los usuarios de demostración tienen la contraseña `Clinica2026*`. Los correos de los pacientes de prueba usan el
dominio reservado `example.com`, que no existe: el sistema nunca escribe a personas reales.

## Tablas

| Paquete | Tablas |
|---|---|
| Personas | `persona`, `paciente`, `contacto_emergencia` |
| Médicos | `medico`, `especialidad`, `medico_especialidad`, `horario_atencion`, `dia_no_laborable` |
| Seguridad | `rol`, `permiso`, `rol_permiso`, `usuario`, `token_recuperacion`, `bitacora` |
| Citas | `estado_cita`, `estado_cita_transicion`, `cita`, `cita_historial` |
| Atención | `atencion`, `signo_vital`, `medicamento`, `receta`, `receta_detalle` |
| Notificaciones | `notificacion` |

Reglas que garantiza la propia base (aunque alguien se salte la aplicación):

| Regla | Objeto |
|---|---|
| Un médico no tiene dos citas que se crucen | `EXCLUDE ex_cita_medico_sin_solape` (las canceladas liberan el horario) |
| Un paciente no tiene dos citas a la misma hora | `EXCLUDE ex_cita_paciente_sin_solape` |
| Los bloques de horario de un médico no se cruzan | `EXCLUDE ex_horario_sin_solape` |
| El CI no se repite entre ninguna persona (paciente, médico o personal) | `UNIQUE uq_persona_ci` |
| Especialidad y medicamento con nombre único sin importar tildes ni mayúsculas | índices sobre `fn_normalizar_texto(nombre)` |
| Un paciente tiene fecha de nacimiento, sexo y teléfono; un médico, teléfono y un correo que no repite otro médico | trigger `fn_validar_datos_persona` |
| Una cuenta con rol médico o paciente pertenece a un médico o paciente registrado | trigger `fn_tg_validar_usuario_rol` |
| El médico atiende la especialidad de la cita | FK compuesta `fk_cita_medico_especialidad` |
| Solo se permiten los cambios de estado del catálogo | tabla `estado_cita_transicion` y trigger `fn_tg_validar_transicion_cita` |
| Una cita cancelada exige motivo | `CHECK ck_cita_cancelada` |
| Las citas, su historial, las atenciones y las recetas no se borran | trigger `fn_tg_impedir_eliminacion`; cada cambio de la cita queda en `cita_historial` |
| La atención solo se registra para una cita atendida | trigger `fn_tg_validar_atencion` |
| Signos vitales en rangos posibles; la presión sistólica es mayor que la diastólica | `CHECK ck_signo_*` |
| La bitácora no se modifica ni se borra | trigger `fn_tg_bitacora_inalterable` (`BEFORE UPDATE OR DELETE`) |
| Todo cambio de datos queda en la bitácora con los valores de antes y de después (sin contraseñas) | trigger `fn_tg_auditoria` |
| Un enlace de recuperación de contraseña vence a los 30 minutos y se usa una sola vez | `token_recuperacion` y trigger `fn_tg_token_un_uso` |
| Recordatorio de la cita 24 h antes; se anula si la cita se cancela | trigger `fn_tg_recordatorio_cita` |
| Un recordatorio por cada toma de un medicamento recetado (máximo 90) | trigger `fn_tg_programar_tomas` |

La aplicación informa a la base qué usuario hace cada cambio y desde qué IP (`app.id_usuario`, `app.ip`); con eso el
historial de la cita y la auditoría saben quién hizo qué.

## Pruebas

```bat
psql -U postgres -h localhost -d clinica_vida_salud -f pruebas/pruebas_bd.sql
```

Ejecuta 25 pruebas: intenta guardar datos inválidos y verifica que PostgreSQL los rechace, y comprueba que los triggers
automáticos funcionen (historial, auditoría con antes y después, recordatorios por correo). Todo ocurre dentro de una
transacción que se revierte al final, así que no deja cambios.

## Modificar la base

Cambie el script que corresponda (por ejemplo, una columna nueva en `01_esquema.sql`), vuelva a instalar con
`instalar_bd.bat` y ejecute las pruebas. Si la columna se usa en el sistema, actualice también el modelo en
`backend/app/paquetes/<paquete>/modelos/` (y la vista `v_paciente`, `v_medico` o `v_usuario` si es un dato de la persona).

## Problemas comunes

| Problema | Solución |
|---|---|
| `psql` no se reconoce | Use la ruta completa `"C:\Program Files\PostgreSQL\16\bin\psql.exe"` (cambie 16 por su versión) o use `instalar_bd.bat`, que la busca solo |
| *new encoding (UTF8) is incompatible with the encoding of the template database* | Cree la base con `TEMPLATE template0`, como en los comandos de arriba |
| Tildes raras (`PediatrÃ­a`) | La base se instaló sin `client_encoding = UTF8`: vuelva a instalarla con `instalar.sql` (ya lo fija) |
| *extension "btree_gist" is not available* | Instale PostgreSQL con el instalador oficial (incluye las extensiones *contrib*) |
| *database is being accessed by other users* al reinstalar | Cierre pgAdmin o detenga Apache un momento y vuelva a ejecutar `instalar_bd.bat` |
| *Connection refused* en el puerto 5432 | Su PostgreSQL usa otro puerto: escríbalo cuando `instalar_bd.bat` lo pregunte y póngalo en `config.local.php` |
| No hay citas «de hoy» en la demostración | Los datos de prueba se generan alrededor del día de la instalación: reinstale la base el día anterior a la defensa |
