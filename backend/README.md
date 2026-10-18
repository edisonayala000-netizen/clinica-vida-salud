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
| Panel principal | `inicio/casos_uso/PanelPrincipal.php` | CitaModelo | `inicio/panel.php`, `inicio/citas_hoy.php` |

**Estado:** los 14 casos de uso y el panel principal están implementados y probados.

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

## Pruebas

```bat
php backend\tests\pruebas_sistema.php http://localhost/clinica-vida-salud
```
Ejecuta las 10 pruebas mínimas del examen contra el sistema encendido y guarda el resultado en
`tests/resultados_pruebas.md` y `.json`. Las pruebas registran datos; para dejar la base limpia vuelva a instalarla.

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
