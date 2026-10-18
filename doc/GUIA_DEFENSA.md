# Guía de defensa — Sistema de Citas Médicas «Vida Salud»

Grupo #13 · Ayala Ayala Edison (222153512) · Saldaña Zurita Pablo Andres (223044253)

Esta guía sirve para responder rápido cuando la ingeniera pregunte **«¿dónde está implementado este caso de uso?»**. Cada caso de uso tiene **un archivo propio** en `backend/app/paquetes/<paquete>/casos_uso/` y sus pantallas en `frontend/vistas/<paquete>/`.

## Cómo viaja una petición (explicarlo en 30 segundos)

1. El navegador pide una URL → el `.htaccess` de la raíz la envía a `backend/public/index.php` (controlador frontal).
2. `backend/app/nucleo/Enrutador.php` busca la URL en los `rutas.php` de cada paquete, verifica **sesión**, **permiso** y, si es POST, el **token CSRF**.
3. Ejecuta el método del caso de uso (`casos_uso/CUxx…php`) = clase **CONTROL** del análisis.
4. El caso de uso valida (`Validador`), aplica reglas y llama a los **modelos** (`backend/app/paquetes/<paquete>/modelos/*Modelo.php`) = clases **ENTIDAD**, que usan PDO con consultas preparadas.
5. Devuelve una **vista** (`frontend/vistas/<paquete>/*.php`) = clase **INTERFAZ**. La salida se escapa con `e()` (anti-XSS).
6. PostgreSQL vuelve a garantizar las reglas críticas (EXCLUDE, CHECK, UNIQUE, triggers) aunque alguien se salte PHP.

## Usuarios para la demostración (contraseña de todos: `Clinica2026*`)

| Usuario | Rol |
|---|---|
| admin | Administrador |
| recepcion / recepcion2 | Recepcionista |
| dr.perez, dra.gutierrez, dra.suarez, dr.mendoza | Médico |
| maria.lopez, carlos.vargas | Paciente |

## Paquete seguridad

Acceso, usuarios, roles y permisos.

### CU01 — Iniciar sesión

- **Requisitos:** RF01 · **Actores:** Usuario · **Ciclo:** 1
- **Control:** `backend/app/paquetes/seguridad/casos_uso/CU01IniciarSesion.php` → métodos: `formulario()`, `iniciar()`, `cerrar()`, `formularioClave()`, `cambiarClave()`
- **Modelos:** `UsuarioModelo.php` · **Otros:** `backend/app/nucleo/Autenticacion.php`, `backend/app/nucleo/Sesion.php`
- **Vistas:** `frontend/vistas/seguridad/login.php`, `frontend/vistas/seguridad/cambiar_clave.php`, `frontend/vistas/inicio/panel.php`
- **Rutas:** `GET /login` (público); `POST /login` (público); `POST /salir` (con sesión); `GET/POST /cuenta/clave` (con sesión)
- **Base de datos:** usuario, rol, permiso, rol_permiso
- **Propósito:** Permitir que el usuario ingrese al sistema con su nombre de usuario o correo y su contraseña, identificar su rol, mostrar el menú según sus permisos y cerrar la sesión de forma segura.

### CU02 — Gestionar usuarios y permisos

- **Requisitos:** RF01 · Alcance: administrar usuarios y permisos básicos · **Actores:** Administrador · **Ciclo:** 1
- **Control:** `backend/app/paquetes/seguridad/casos_uso/CU02GestionarUsuariosPermisos.php` → métodos: `listar()`, `nuevo()`, `editar()`, `crear()`, `actualizar()`, `cambiarEstado()`, `permisos()`, `guardarPermisos()`, `bitacora()`
- **Modelos:** `UsuarioModelo.php`, `RolModelo.php`, `BitacoraModelo.php` (`listar()`, `contar()`, `modulos()`, `acciones()`) · **Otros:** `backend/app/nucleo/Bitacora.php` (`registrar()`, `registrarOperacion()`)
- **Vistas:** `frontend/vistas/seguridad/usuarios_lista.php`, `frontend/vistas/seguridad/usuario_form.php`, `frontend/vistas/seguridad/permisos.php`, `frontend/vistas/seguridad/bitacora.php`
- **Rutas:** `GET /usuarios` (usuarios.gestionar); `GET/POST /usuarios/nuevo · /usuarios` (usuarios.gestionar); `GET/POST /usuarios/{id}/editar · /usuarios/{id}` (usuarios.gestionar); `POST /usuarios/{id}/estado` (usuarios.gestionar); `GET/POST /permisos` (usuarios.gestionar); `GET /bitacora?usuario=&modulo=&accion=&desde=&hasta=[&formato=csv]` (bitacora.ver, solo administrador)
- **Base de datos:** usuario, rol, permiso, rol_permiso (trigger fn_tg_validar_usuario_rol), bitacora (trigger fn_tg_bitacora_inalterable)
- **Propósito:** Registrar y administrar las cuentas de usuario, asignarles un rol, vincularlas con el médico o paciente correspondiente, definir los permisos básicos de cada rol y consultar la bitácora de acciones (quién hizo qué, cuándo y desde qué IP), con filtros y exportación a CSV.
- **Demostración de la bitácora:** con `admin` → menú **Bitácora**. Antes, escriba mal la contraseña de `recepcion2` y registre un paciente con `recepcion`: aparecen «Inicio fallido», «Inicio de sesión» y la «Operación» `POST /pacientes → CU03GestionarPacientes::crear · correcto`.

## Paquete pacientes

Registro de pacientes, perfil e historial.

### CU03 — Gestionar pacientes

- **Requisitos:** RF02 · RN01 · RN11 · **Actores:** Recepcionista, Administrador · **Ciclo:** 1
- **Control:** `backend/app/paquetes/pacientes/casos_uso/CU03GestionarPacientes.php` → métodos: `listar()`, `ver()`, `nuevo()`, `editar()`, `crear()`, `actualizar()`, `cambiarEstado()`, `buscarJson()`
- **Modelos:** `PacienteModelo.php` · **Otros:** `backend/app/paquetes/pacientes/ReglasPaciente.php`
- **Vistas:** `frontend/vistas/pacientes/lista.php`, `frontend/vistas/pacientes/formulario.php`, `frontend/vistas/pacientes/ficha.php`
- **Rutas:** `GET /pacientes` (pacientes.ver); `GET/POST /pacientes/nuevo · /pacientes` (pacientes.gestionar); `GET /pacientes/{id}` (pacientes.ver); `GET/POST /pacientes/{id}/editar · /pacientes/{id}` (pacientes.gestionar); `POST /pacientes/{id}/estado` (pacientes.gestionar); `GET /api/pacientes?q=` (citas.programar)
- **Base de datos:** paciente (UNIQUE uq_paciente_ci), contacto_emergencia
- **Propósito:** Registrar, listar, buscar, modificar y desactivar pacientes sin perder su historial, garantizando que no existan dos pacientes con el mismo CI.

### CU04 — Gestionar perfil del paciente

- **Requisitos:** Punto 3.4 Perfil de pacientes · **Actores:** Paciente · **Ciclo:** 2
- **Control:** `backend/app/paquetes/pacientes/casos_uso/CU04GestionarPerfilPaciente.php` → métodos: `formularioRegistro()`, `registrar()`, `ver()`, `guardarDatos()`, `guardarMedica()`, `guardarEmergencia()`, `guardarPreferencias()`
- **Modelos:** `PacienteModelo.php`, `UsuarioModelo.php`, `RolModelo.php`
- **Vistas:** `frontend/vistas/pacientes/registro.php`, `frontend/vistas/pacientes/mi_perfil.php`
- **Rutas:** `GET/POST /registro` (público); `GET /mi-perfil` (portal.perfil); `POST /mi-perfil/personales · /medica · /emergencia · /cuenta` (portal.perfil)
- **Base de datos:** paciente, usuario, rol, contacto_emergencia
- **Propósito:** Permitir que el paciente cree su cuenta en línea y mantenga actualizado su perfil: datos personales, contacto, información médica, contacto de emergencia y preferencias de notificaciones y privacidad.

### CU05 — Consultar historial de citas

- **Requisitos:** RF12 · RN10 · **Actores:** Recepcionista, Administrador, Paciente · **Ciclo:** 2
- **Control:** `backend/app/paquetes/pacientes/casos_uso/CU05ConsultarHistorialCitas.php` → métodos: `historialPaciente()`, `misCitas()`
- **Modelos:** `PacienteModelo.php`
- **Vistas:** `frontend/vistas/pacientes/historial.php`, `frontend/vistas/pacientes/mis_citas.php`
- **Rutas:** `GET /pacientes/{id}/historial` (pacientes.historial); `GET /mis-citas` (portal.mis_citas)
- **Base de datos:** vista v_cita_detalle (cita + paciente + médico + especialidad)
- **Propósito:** Visualizar el historial de citas de un paciente (fecha y hora, médico, especialidad, estado y fecha de registro), incluidas las pasadas y canceladas, y el seguimiento de sus citas por parte del propio paciente.

## Paquete medicos

Médicos, especialidades y horarios de atención.

### CU06 — Gestionar especialidades

- **Requisitos:** RF04 · **Actores:** Administrador · **Ciclo:** 1
- **Control:** `backend/app/paquetes/medicos/casos_uso/CU06GestionarEspecialidades.php` → métodos: `listar()`, `crear()`, `actualizar()`, `cambiarEstado()`
- **Modelos:** `EspecialidadModelo.php`
- **Vistas:** `frontend/vistas/medicos/especialidades.php`
- **Rutas:** `GET /especialidades` (especialidades.gestionar); `POST /especialidades` (especialidades.gestionar); `POST /especialidades/{id}` (especialidades.gestionar); `POST /especialidades/{id}/estado` (especialidades.gestionar)
- **Base de datos:** especialidad (índice único uq_especialidad_nombre_normalizado sobre fn_normalizar_texto(nombre))
- **Propósito:** Administrar las especialidades que ofrece la clínica (identificador, nombre, descripción opcional y estado) sin permitir nombres repetidos.

### CU07 — Gestionar médicos

- **Requisitos:** RF03 · RN02 · RN04 · RN11 · **Actores:** Administrador, Recepcionista · **Ciclo:** 1
- **Control:** `backend/app/paquetes/medicos/casos_uso/CU07GestionarMedicos.php` → métodos: `listar()`, `ver()`, `nuevo()`, `editar()`, `crear()`, `actualizar()`, `cambiarEstado()`
- **Modelos:** `MedicoModelo.php`, `EspecialidadModelo.php`, `HorarioModelo.php`
- **Vistas:** `frontend/vistas/medicos/medicos_lista.php`, `frontend/vistas/medicos/medico_form.php`, `frontend/vistas/medicos/medico_ficha.php`
- **Rutas:** `GET /medicos` (medicos.ver); `GET /medicos/{id}` (medicos.ver); `GET/POST /medicos/nuevo · /medicos` (medicos.gestionar); `GET/POST /medicos/{id}/editar · /medicos/{id}` (medicos.gestionar); `POST /medicos/{id}/estado` (medicos.gestionar)
- **Base de datos:** medico, medico_especialidad, vista v_medico_especialidades
- **Propósito:** Administrar la información de los médicos (código, registro profesional, nombres y apellidos, teléfono, correo, especialidades y estado) y consultar su agenda.

### CU08 — Gestionar horarios de atención

- **Requisitos:** RF05 · RN05 · RN08 · **Actores:** Administrador · **Ciclo:** 1
- **Control:** `backend/app/paquetes/medicos/casos_uso/CU08GestionarHorarios.php` → métodos: `mostrar()`, `crear()`, `actualizar()`, `cambiarEstado()`, `agregarFeriado()`, `quitarFeriado()`
- **Modelos:** `HorarioModelo.php`, `MedicoModelo.php`
- **Vistas:** `frontend/vistas/medicos/horarios.php`
- **Rutas:** `GET /horarios?medico=` (horarios.gestionar); `POST /horarios` (horarios.gestionar); `POST /horarios/{id} · /horarios/{id}/estado` (horarios.gestionar); `POST /feriados · /feriados/{id}/quitar` (horarios.gestionar)
- **Base de datos:** horario_atencion (CHECK ck_horario_rango, EXCLUDE ex_horario_sin_solape), dia_no_laborable
- **Propósito:** Registrar los horarios semanales de atención de cada médico con la duración de cada cita, y bloquear fechas por feriados o ausencias, para que el sistema calcule los horarios disponibles.

## Paquete citas

Programación, cambios, estados y agenda de citas.

### CU09 — Programar cita

- **Requisitos:** RF06 · RF07 · RN03–RN09 · RN13 · **Actores:** Recepcionista, Paciente · **Ciclo:** 1
- **Control:** `backend/app/paquetes/citas/casos_uso/CU09ProgramarCita.php` → métodos: `formulario()`, `formularioPaciente()`, `registrar()`, `registrarPaciente()`, `guardar()`, `medicosJson()`, `diasJson()`, `horariosJson()`
- **Modelos:** `CitaModelo.php`, `MedicoModelo.php`, `HorarioModelo.php` · **Otros:** `backend/app/paquetes/citas/servicios/Disponibilidad.php`
- **Vistas:** `frontend/vistas/citas/programar.php`, `frontend/assets/js/citas.js`, `frontend/vistas/citas/detalle.php`
- **Rutas:** `GET /citas/nueva` (citas.programar); `POST /citas` (citas.programar); `GET/POST /reservar` (portal.reservar); `GET /api/medicos?especialidad=` (citas.programar | portal.reservar); `GET /api/disponibilidad/dias?medico=` (citas.programar | portal.reservar); `GET /api/disponibilidad?medico=&fecha=` (citas.programar | portal.reservar)
- **Base de datos:** cita (EXCLUDE ex_cita_medico_sin_solape y ex_cita_paciente_sin_solape, FK compuesta médico–especialidad, trigger fn_tg_validar_cita, trigger fn_tg_historial_cita)
- **Propósito:** Registrar una cita médica eligiendo paciente, especialidad, médico, fecha y una hora disponible, evitando citas duplicadas o superpuestas y generando un código único de cita.

### CU10 — Reprogramar cita

- **Requisitos:** RF08 · RN13 · **Actores:** Recepcionista, Administrador · **Ciclo:** 2
- **Control:** `backend/app/paquetes/citas/casos_uso/CU10ReprogramarCita.php` → métodos: `formulario()`, `guardar()`, `reprogramar()`
- **Modelos:** `CitaModelo.php` · **Otros:** `backend/app/paquetes/citas/servicios/Disponibilidad.php`, `backend/app/paquetes/citas/servicios/EstadoCita.php`
- **Vistas:** `frontend/vistas/citas/reprogramar.php`, `frontend/assets/js/citas.js`
- **Rutas:** `GET /citas/{id}/reprogramar` (citas.reprogramar); `POST /citas/{id}/reprogramar` (citas.reprogramar)
- **Base de datos:** cita, cita_historial (trigger fn_tg_historial_cita registra la reprogramación)
- **Propósito:** Cambiar la fecha y la hora de una cita programada o confirmada, mostrando los nuevos horarios disponibles del mismo médico y manteniendo la integridad y la trazabilidad de la información.

### CU11 — Cancelar cita

- **Requisitos:** RF09 · RN09 · RN10 · **Actores:** Recepcionista, Paciente · **Ciclo:** 2
- **Control:** `backend/app/paquetes/citas/casos_uso/CU11CancelarCita.php` → métodos: `cancelar()`, `cancelarPaciente()`, `ejecutar()`
- **Modelos:** `CitaModelo.php` · **Otros:** `backend/app/paquetes/citas/servicios/EstadoCita.php`
- **Vistas:** `frontend/vistas/citas/detalle.php`, `frontend/vistas/pacientes/mis_citas.php`
- **Rutas:** `POST /citas/{id}/cancelar` (citas.cancelar); `POST /mis-citas/{id}/cancelar` (portal.mis_citas)
- **Base de datos:** cita (CHECK ck_cita_cancelada exige motivo; trigger impide DELETE), cita_historial
- **Propósito:** Cancelar una cita programada o confirmada registrando el motivo, liberando el horario y conservando la cita para consultas históricas.

### CU12 — Registrar asistencia

- **Requisitos:** RF10 · RN12 · **Actores:** Recepcionista, Médico · **Ciclo:** 2
- **Control:** `backend/app/paquetes/citas/casos_uso/CU12RegistrarAsistencia.php` → métodos: `cambiar()`, `ejecutar()`
- **Modelos:** `CitaModelo.php` · **Otros:** `backend/app/paquetes/citas/servicios/EstadoCita.php`
- **Vistas:** `frontend/vistas/citas/detalle.php`, `frontend/vistas/citas/agenda.php`, `frontend/vistas/inicio/citas_hoy.php`
- **Rutas:** `POST /citas/{id}/estado` (citas.confirmar | citas.atender)
- **Base de datos:** cita (trigger fn_tg_validar_transicion_cita), cita_historial
- **Propósito:** Controlar los estados de la cita: confirmar la asistencia, marcarla como atendida o registrar la inasistencia, aplicando solo las transiciones válidas y los permisos de cada rol.

### CU13 — Consultar agenda médica

- **Requisitos:** RF11 · RF12 · **Actores:** Médico, Recepcionista, Administrador · **Ciclo:** 2
- **Control:** `backend/app/paquetes/citas/casos_uso/CU13ConsultarAgendaMedica.php` → métodos: `agenda()`, `agendaSemanal()`, `listado()`, `ver()`
- **Modelos:** `CitaModelo.php` (`agenda()`, `agendaSemana()`), `MedicoModelo.php`, `HorarioModelo.php` · **Otros:** `backend/app/paquetes/citas/servicios/Disponibilidad.php`
- **Vistas:** `frontend/vistas/citas/agenda.php`, `frontend/vistas/citas/agenda_semana.php`, `frontend/vistas/citas/listado.php`, `frontend/vistas/citas/detalle.php`
- **Rutas:** `GET /agenda?medico=&fecha=&estado=` (agenda.ver | agenda.propia); `GET /agenda/semana?medico=&fecha=` (agenda.ver | agenda.propia); `GET /citas?desde=&hasta=&medico=&estado=` (citas.ver); `GET /citas/{id}` (citas.ver | agenda.propia | portal.mis_citas)
- **Base de datos:** vista v_cita_detalle, cita_historial, horario_atencion, dia_no_laborable
- **Propósito:** Consultar la agenda de un médico para una fecha (hora, código de cita, paciente, especialidad, estado y motivo breve) o para toda la semana (lunes a domingo, con los días no laborables marcados), y las citas por fecha, médico, especialidad y estado. El médico solo ve la suya.
- **Demostración de la agenda semanal:** con `dr.perez` → **Agenda médica** → botón **Vista semanal**; flechas para la semana anterior o siguiente. En el celular los días se muestran uno debajo del otro.

## Paquete reportes

Reportes administrativos.

### CU14 — Generar reportes

- **Requisitos:** RF13 · **Actores:** Administrador · **Ciclo:** 2
- **Control:** `backend/app/paquetes/reportes/casos_uso/CU14GenerarReportes.php` → métodos: `generar()`
- **Modelos:** `ReporteModelo.php`
- **Vistas:** `frontend/vistas/reportes/reportes.php`
- **Rutas:** `GET /reportes?tipo=&desde=&hasta=[&formato=csv]` (reportes.ver)
- **Base de datos:** vistas v_cita_detalle, v_resumen_citas_dia; tablas cita y paciente
- **Propósito:** Generar los reportes administrativos: citas programadas por fecha, por médico, por especialidad y por estado, pacientes registrados, cantidad de atendidas, canceladas e inasistencias del período y agenda diaria de cada médico, con filtros por fecha o rango y totales.

## Diagramas de clases (StarUML)

Archivo: `doc/staruml/Clinica_Vida_Salud_Diagramas_Clases.mdj` (abrir con StarUML → File → Open). Contiene 18 diagramas:

| Diagrama | Qué muestra |
|---|---|
| Diagrama de clases — dominio | 13 entidades (una por tabla), 16 asociaciones con multiplicidad y 7 enumeraciones (valores de los CHECK) |
| Diseño — arquitectura del núcleo | Las 13 clases de `backend/app/nucleo` con sus métodos reales y dependencias principales |
| CU01 … CU14 y Panel principal — clases de diseño | La clase «control» del caso de uso, su herencia de `Controlador` y las clases «servicio» y «modelo» que usa |
| Diseño — capa de acceso a datos | Las 8 clases `*Modelo` con todos sus métodos públicos y su dependencia de `BaseDatos` → `PDO` |

Los atributos y métodos de los diagramas de diseño se extrajeron del código fuente, así que coinciden exactamente con los archivos PHP.

**Pendiente en los diagramas:** agregar la entidad `Bitacora` (tabla 14, asociada 0..* a 1 con `Usuario`), la clase del núcleo `Bitacora` (`registrar()`, `registrarOperacion()`), `BitacoraModelo` (`listar()`, `contar()`, `modulos()`, `acciones()`), `CU02GestionarUsuariosPermisos::bitacora()`, `CU13ConsultarAgendaMedica::agendaSemanal()` y `CitaModelo::agendaSemana()`.

**¿Por qué el dominio tiene solo 14 clases?** Porque el alcance del examen es la gestión de citas: cada clase del dominio es una tabla (13 del negocio + la bitácora). El sistema completo tiene 41 clases PHP (14 del núcleo, 15 de casos de uso, 9 modelos y 3 servicios), que aparecen en los diagramas de diseño.

## Preguntas frecuentes de la defensa

**¿Cómo evitan dos citas al mismo tiempo para un médico?** Tres capas: (1) la pantalla solo muestra horarios libres (`Disponibilidad::delDia`); (2) al guardar, `CU09ProgramarCita::guardar()` abre una transacción, bloquea al médico con `SELECT … FOR UPDATE` y vuelve a verificar; (3) PostgreSQL tiene `EXCLUDE ex_cita_medico_sin_solape` (btree_gist) que rechaza rangos superpuestos aunque se inserte por fuera del sistema. Prueba 4.

**¿Dónde se guardan las contraseñas?** En `usuario.password_hash` con `password_hash()` (bcrypt). Se comprueban con `password_verify()` en `CU01IniciarSesion::iniciar()`. 5 intentos fallidos bloquean 15 minutos.

**¿Cómo evitan la inyección SQL?** Todas las consultas pasan por `backend/app/nucleo/BaseDatos.php` con sentencias preparadas (`prepare` + parámetros), sin emulación.

**¿Y XSS y CSRF?** Toda salida usa `e()` (`htmlspecialchars`). Todo formulario POST lleva `_csrf`, validado en `Enrutador::despachar()` con `Csrf::validar()`.

**¿Por qué no se borran las citas?** RN10: el trigger `fn_tg_impedir_eliminacion` lo impide; se cancelan con motivo y `cita_historial` guarda cada cambio (trigger `fn_tg_historial_cita`).

**¿Qué pasa si se ingresa a una página sin sesión?** `Enrutador` redirige a `/login` con «Acceso denegado» (Prueba 9). Sin permiso, muestra la página 403.

**¿Cómo se ejecutan las pruebas?** `php backend/tests/pruebas_sistema.php http://localhost/clinica-vida-salud` (10 pruebas del examen) y `psql -U postgres -d clinica_vida_salud -f basededato/sql/pruebas/pruebas_bd.sql` (16 pruebas de BD; no deja cambios).

**¿Hay una bitácora? ¿Qué guarda?** Sí, dentro de CU02. La tabla `bitacora` guarda usuario, acción, módulo, detalle, IP y fecha y hora. `backend/app/nucleo/Bitacora.php` registra inicios de sesión correctos y fallidos (con el usuario escrito), cuentas bloqueadas, cierres de sesión y, desde `Enrutador::despachar()`, **toda petición POST** con su ruta, el caso de uso y el método que la atendió y si terminó bien o con error. Nunca guarda contraseñas ni el contenido de los formularios, y si falla no impide la operación. El trigger `fn_tg_bitacora_inalterable` impide modificarla o borrarla (pruebas 15 y 16 de la BD). Solo el administrador la ve (permiso `bitacora.ver`).

**¿Cómo reinicio los datos antes de la defensa?** Ejecutar de nuevo `basededato/sql/instalar_bd.bat` (borra y recrea la base con datos de prueba con fechas alrededor del día actual).
