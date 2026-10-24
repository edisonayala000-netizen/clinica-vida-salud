# Backend — PHP 8 (sin frameworks)

Aquí está **toda la lógica del sistema**: recibe cada petición, comprueba sesión y permisos, valida los datos, aplica las
reglas de negocio y lee o guarda en PostgreSQL con PDO (`pdo_pgsql`). Las pantallas están en [`../frontend`](../frontend).

## Estructura

```
backend/
├── public/
│   ├── index.php          ← controlador frontal: TODA petición entra aquí
│   └── router.php         ← solo para el servidor integrado de PHP (php -S)
├── app/
│   ├── nucleo/            ← infraestructura común (sin reglas de negocio)
│   │   ├── Aplicacion.php       arranque, autocargador y configuración
│   │   ├── Enrutador.php        URL → caso de uso; exige sesión, permiso y token CSRF
│   │   ├── Controlador.php      clase base de todos los casos de uso
│   │   ├── BaseDatos.php        conexión PDO, consultas parametrizadas y transacciones
│   │   ├── Autenticacion.php    usuario de la sesión y sus permisos
│   │   ├── Bitacora.php         registra las acciones de los usuarios (CU02): sesiones y toda petición POST
│   │   ├── Sesion.php  Csrf.php  Validador.php  Vista.php  Respuesta.php  Peticion.php
│   │   ├── ManejadorErrores.php traduce errores de PostgreSQL a mensajes claros
│   │   ├── ErrorNegocio.php     error de una regla de negocio (se muestra al usuario)
│   │   └── ayudantes.php        funciones para las vistas: e(), url(), recurso(), icono()…
│   └── paquetes/          ← un paquete por área del negocio
│       └── <paquete>/
│           ├── rutas.php        URL, método del caso de uso y permiso exigido
│           ├── casos_uso/       CONTROL: un archivo por caso de uso (CUxxNombre.php)
│           ├── modelos/         ENTIDAD: solo consultas SQL parametrizadas
│           └── servicios/       reglas reutilizables (solo en citas)
├── config/config.php      ← conexión y parámetros (sobrescribibles con config.local.php)
├── storage/logs/          ← errores técnicos (errores.log)
└── tests/pruebas_sistema.php ← las 10 pruebas del examen
```

## Cómo viaja una petición

1. Apache envía la URL a `public/index.php` (lo hace el `.htaccess` de la raíz del proyecto).
2. `Aplicacion::ejecutar()` carga la configuración, la sesión y las rutas de cada paquete.
3. `Enrutador::despachar()` busca la ruta, exige sesión y permiso y, si es POST, valida el token CSRF. Toda petición
   POST queda en la **bitácora** al terminar (`Bitacora::registrarOperacion()`): ruta, caso de uso y método, usuario, IP
   y si terminó bien o con error. Nunca se guardan contraseñas ni el contenido de los formularios.
4. Se ejecuta el método del caso de uso (`casos_uso/CUxx…php`), que valida con `Validador`, aplica las reglas y usa los
   modelos dentro de `BaseDatos::transaccion()` cuando guarda.
5. El caso de uso muestra una vista de `frontend/vistas/<paquete>/` o redirige con un mensaje.

## Dónde está cada caso de uso

| CU | Archivo (en `app/paquetes/`) | Modelos / servicios | Vistas (en `frontend/vistas/`) |
|---|---|---|---|
| CU01 Iniciar sesión | `seguridad/casos_uso/CU01IniciarSesion.php` | UsuarioModelo | `seguridad/login.php`, `seguridad/cambiar_clave.php` |
| CU02 Gestionar usuarios y permisos (incluye la bitácora) | `seguridad/casos_uso/CU02GestionarUsuariosPermisos.php` | UsuarioModelo, RolModelo, BitacoraModelo · núcleo `Bitacora` | `seguridad/usuarios_lista.php`, `usuario_form.php`, `permisos.php`, `bitacora.php` |
| CU03 Gestionar pacientes | `pacientes/casos_uso/CU03GestionarPacientes.php` | PacienteModelo, ReglasPaciente | `pacientes/lista.php`, `formulario.php`, `ficha.php` |
| CU04 Gestionar perfil del paciente | `pacientes/casos_uso/CU04GestionarPerfilPaciente.php` | PacienteModelo, UsuarioModelo, RolModelo, ReglasPaciente | `pacientes/registro.php`, `mi_perfil.php` |
| CU05 Consultar historial de citas | `pacientes/casos_uso/CU05ConsultarHistorialCitas.php` | PacienteModelo | `pacientes/historial.php`, `mis_citas.php` |
| CU06 Gestionar especialidades | `medicos/casos_uso/CU06GestionarEspecialidades.php` | EspecialidadModelo | `medicos/especialidades.php` |
| CU07 Gestionar médicos | `medicos/casos_uso/CU07GestionarMedicos.php` | MedicoModelo, EspecialidadModelo, HorarioModelo | `medicos/medicos_lista.php`, `medico_form.php`, `medico_ficha.php` |
| CU08 Gestionar horarios de atención | `medicos/casos_uso/CU08GestionarHorarios.php` | HorarioModelo, MedicoModelo | `medicos/horarios.php` |
| CU09 Programar cita | `citas/casos_uso/CU09ProgramarCita.php` | CitaModelo, Disponibilidad, MedicoModelo, EspecialidadModelo, PacienteModelo | `citas/programar.php` + `assets/js/citas.js` |
| CU10 Reprogramar cita | `citas/casos_uso/CU10ReprogramarCita.php` | CitaModelo, Disponibilidad, EstadoCita | `citas/reprogramar.php` |
| CU11 Cancelar cita | `citas/casos_uso/CU11CancelarCita.php` | CitaModelo, EstadoCita | `citas/detalle.php`, `pacientes/mis_citas.php` |
| CU12 Registrar asistencia | `citas/casos_uso/CU12RegistrarAsistencia.php` | CitaModelo, EstadoCita | `citas/detalle.php`, `citas/agenda.php` |
| CU13 Consultar agenda médica (diaria y semanal) | `citas/casos_uso/CU13ConsultarAgendaMedica.php` | CitaModelo, Disponibilidad, EstadoCita, MedicoModelo, EspecialidadModelo, HorarioModelo | `citas/agenda.php`, `citas/agenda_semana.php`, `citas/listado.php`, `citas/detalle.php` |
| CU14 Generar reportes | `reportes/casos_uso/CU14GenerarReportes.php` | ReporteModelo | `reportes/reportes.php` |
| CU15 Recuperar contraseña por correo | `seguridad/casos_uso/CU15RecuperarContrasena.php` | UsuarioModelo, TokenModelo · `notificaciones/servicios/Notificador` · núcleo `Correo` (Brevo) | `seguridad/olvide_clave.php`, `seguridad/restablecer_clave.php`, `correos/recuperar_clave.php` |
| CU16 Registrar atención médica | `atencion/casos_uso/CU16RegistrarAtencion.php` | AtencionModelo, RecetaModelo, MedicamentoModelo · CitaModelo, EstadoCita · Notificador | `atencion/formulario.php`, `atencion/receta.php` |
| CU17 Consultar mis recetas | `atencion/casos_uso/CU17ConsultarRecetas.php` | RecetaModelo, PacienteModelo | `atencion/mis_recetas.php` |
| CU18 Enviar notificaciones | `notificaciones/casos_uso/CU18EnviarNotificaciones.php` y la tarea `tareas/enviar_notificaciones.php` | NotificacionModelo · Notificador, ContenidoCorreo · núcleo `EnlaceFirmado`, `Correo` | `notificaciones/lista.php`, `cita_correo.php`, `baja.php`, `correos/*` |
| CU19 Gestionar medicamentos | `atencion/casos_uso/CU19GestionarMedicamentos.php` | MedicamentoModelo | `atencion/medicamentos.php` |
| Panel principal | `inicio/casos_uso/PanelPrincipal.php` | CitaModelo | `inicio/panel.php`, `inicio/citas_hoy.php` |

**Estado:** los 19 casos de uso y el panel principal están implementados y probados.

El paquete `notificaciones` no tiene pantallas: su servicio `Notificador` arma el correo con una plantilla de
`frontend/vistas/correos/`, lo envía con `App\Nucleo\Correo` y lo registra en la tabla `notificacion`.

Cada archivo de `casos_uso/` empieza con su **ficha**: reglas que programa, paquete, actores, requisitos, tablas y objetos
de la base que usa, rutas, vistas, clases que usa, precondición, postcondición y flujo. Para saber qué hace un caso de uso
basta con abrir su archivo. Los diagramas de clases de cada caso de uso (StarUML) están en `../doc/staruml/`.

## Cómo agregar o cambiar un caso de uso

1. **Ruta** — en `app/paquetes/<paquete>/rutas.php`:
   ```php
   $r->get('/reportes', [CU14GenerarReportes::class, 'generar'], 'reportes.ver');
   //       URL          clase y método que la atienden          permiso: 'codigo', ['a','b'], 'autenticado' o 'publico'
   ```
2. **Caso de uso** — `app/paquetes/<paquete>/casos_uso/CUxxNombre.php`: una clase `final` que `extends Controlador`, con
   un método público por acción. Valida con `Validador::validar()`, lanza `ErrorNegocio` si una regla no se cumple y
   guarda dentro de `BaseDatos::transaccion(fn () => ...)`. Para mostrar una pantalla: `$this->vista('paquete/plantilla', [...])`.
3. **Modelo** — `app/paquetes/<paquete>/modelos/XModelo.php`: solo SQL con parámetros (`:nombre`), usando
   `BaseDatos::todos()`, `uno()`, `valor()` o `ejecutar()`. Nunca concatenar datos del usuario en el SQL.
4. **Vista** — `frontend/vistas/<paquete>/plantilla.php`. Imprimir siempre con `e($valor)` y poner `<?= csrf_campo() ?>`
   en cada formulario POST.
5. **Permiso nuevo** — agregarlo en `basededato/sql/04_datos_iniciales.sql` (tablas `permiso` y `rol_permiso`) y
   reinstalar la base, o asignarlo desde la pantalla *Permisos* con el usuario `admin`.

Las clases se cargan solas: el espacio de nombres `App\Paquetes\Citas\CasosUso\CU09ProgramarCita` corresponde al
archivo `app/paquetes/citas/casos_uso/CU09ProgramarCita.php`.

## Configuración

`config/config.php` tiene la conexión y los parámetros (minutos de sesión, intentos de acceso, días máximos de reserva…).
Para la contraseña real de PostgreSQL cree `config/config.local.php` (no se sube a GitHub):

```php
<?php return ['bd' => ['clave' => 'SU_CLAVE']];
```

Durante el desarrollo puede ver el detalle técnico de los errores con
`<?php return ['app' => ['depuracion' => true], 'bd' => ['clave' => 'SU_CLAVE']];` (desactívelo para la defensa).

### Correo (Brevo)

Los correos del sistema (CU15 recuperar contraseña y, más adelante, los recordatorios) se envían con la **API HTTP de
Brevo** (plan gratuito: 300 correos al día) usando la extensión `curl` de PHP, sin librerías externas.

1. Cree una cuenta en brevo.com y verifique el correo remitente en *Remitentes*.
2. En *SMTP y API → Claves API* cree una clave con el permiso **Emails transaccionales: Todos** (lo demás, *Ninguno*).
3. En *Configuración → Seguridad → IPs autorizadas* desactive el bloqueo de IPs desconocidas (en la nube la IP cambia).
4. Agregue la sección `correo` a `config/config.local.php`:

```php
<?php
return [
    'bd' => ['clave' => 'SU_CLAVE'],
    'correo' => [
        'api_key'   => 'xkeysib-...',                 // la clave de Brevo (nunca se sube a GitHub)
        'remitente' => 'correo.verificado@gmail.com', // el remitente verificado en Brevo
        'nombre'    => 'Clínica Vida Salud',
    ],
];
```

Sin `api_key` el sistema funciona igual, pero no envía: escribe cada correo en `storage/logs/correos.log`. A los
dominios reservados para pruebas (`example.com`, `.test`…) nunca se envía, aunque haya clave: los datos de prueba no
escriben a personas reales. Cada correo queda registrado en la tabla `notificacion` (enviado o con el error de Brevo).

Mientras desarrolla, agregue `'solo_a' => ['su.correo@gmail.com']` dentro de `correo`: así **solo** a esas direcciones se
envía de verdad y todo lo demás va al log, aunque los datos tengan correos reales.

Otros parámetros (en `config.php`, o por variables de entorno en la nube):

| Parámetro | Variable de entorno | Para qué |
|---|---|---|
| `app.url` | `APP_URL` | Dirección completa del sistema (con su carpeta) para los enlaces de los correos. Vacía = la de la petición |
| `app.secreto` | `APP_SECRETO` | Clave que firma los enlaces de confirmar o cancelar cita y de desactivar avisos. Vacía = se genera en `storage/secreto.key` (no se sube) |
| `notificaciones.*` | — | Límite diario de correos (280), atraso máximo de un recordatorio (6 h), reintentos (3) y correos por ejecución (50) |

### Tarea programada (recordatorios)

Los recordatorios (24 h y 1 h antes de la cita, y cada toma de un medicamento) los programa la base de datos con su
hora exacta en la tabla `notificacion`. Un sistema web no "despierta" solo: hace falta que algo revise cada 5 minutos
si hay correos cuya hora llegó y los envíe. Eso hace `tareas/enviar_notificaciones.php`:

```bat
C:\xampp\php\php.exe backend\tareas\enviar_notificaciones.php --url=http://localhost/clinica-vida-salud
```

- **En Windows:** doble clic en `tareas\programar_tarea_windows.bat` la registra en el Programador de tareas (cada 5
  minutos, solo con la PC encendida). Para quitarla: `schtasks /delete /tn "ClinicaVidaSalud_Notificaciones" /f`.
- **En la nube:** se programa el mismo comando como tarea cron cada 5 minutos.
- **A mano:** en *Notificaciones* (administrador), el botón «Enviar pendientes ahora» hace lo mismo.

## Pruebas

```bat
php backend\tests\pruebas_sistema.php http://localhost/clinica-vida-salud
```
Ejecuta las 10 pruebas mínimas del examen contra el sistema encendido y guarda el resultado en
`tests/resultados_pruebas.md` y `.json`. Las pruebas registran datos; para dejar la base limpia vuelva a instalarla.

```bat
php backend\tests\pruebas_recuperacion.php http://localhost/clinica-vida-salud
```
Ejecuta las 7 pruebas de CU15 (enlace de un solo uso, enlace inventado, contraseña débil, límite de 3 enlaces por hora…)
con una cuenta nueva de `example.com`: no envía correos reales, toma el enlace de `storage/logs/correos.log`. Debe
ejecutarse en la misma PC del servidor. Resultado en `tests/resultados_pruebas_recuperacion.md`.

```bat
php backend\tests\pruebas_atencion.php http://localhost/clinica-vida-salud
```
Ejecuta las 15 pruebas de CU16 a CU19: permisos de la atención, signos vitales fuera de rango, receta con recordatorios,
privacidad del diagnóstico, «Mis recetas», la tarea programada, confirmar, cancelar y desactivar avisos desde el
correo, enlace alterado, recordatorio atrasado y medicamento duplicado. Usa pacientes de `example.com` (no envía correos
reales) y lee la base para comprobar lo que no se ve en pantalla. Resultado en `tests/resultados_pruebas_atencion.md`.

## Problemas comunes

| Problema | Solución |
|---|---|
| Página en blanco o *500* al abrir el sistema | Active `'app' => ['depuracion' => true]` en `config/config.local.php` y recargue para ver el error; revise `storage/logs/errores.log` |
| *could not find driver* | Falta `extension=pdo_pgsql` en `C:\xampp\php\php.ini`; quite el `;` y reinicie Apache |
| *password authentication failed for user "postgres"* | La contraseña de PostgreSQL no es `postgres`: créela en `config/config.local.php` |
| *database "clinica_vida_salud" does not exist* | Instale la base con `basededato\sql\instalar_bd.bat` |
| *404 Not Found* de Apache en cualquier página que no sea el inicio | `mod_rewrite` desactivado o `AllowOverride None`: en `C:\xampp\apache\conf\httpd.conf` active `LoadModule rewrite_module` y ponga `AllowOverride All` en `<Directory "C:/xampp/htdocs">` |
| La página se ve sin estilos | Abra `http://localhost/clinica-vida-salud/assets/css/app.css`; si da 404 revise el punto anterior |
| `php` no se reconoce en la terminal | Use `C:\xampp\php\php.exe` o agregue `C:\xampp\php` al PATH |
| *Call to undefined function curl_init* al correr las pruebas | Quite el `;` de `extension=curl` en `php.ini` |
| No llega el correo de recuperación | Revise la tabla `notificacion` (columna `error`) o `storage/logs/correos.log`. *unrecognised IP address*: desactive el bloqueo de IPs en Brevo. *Sender not valid*: el `remitente` no es el verificado en Brevo. Si llegó, revise también la carpeta de spam |
