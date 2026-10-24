# Sistema Web de Gestión de Citas Médicas — Clínica "Vida Salud"

Sistema web para registrar pacientes, médicos, especialidades y horarios de atención; programar, reprogramar y cancelar
citas **sin choques de horario**; controlar la asistencia; consultar la agenda y el historial; y generar reportes.
Incluye el portal del paciente: registro en línea, perfil médico, reserva de citas y recordatorios.

Segundo Examen Parcial — Sistemas de Información I (SA) — UAGRM, FICCT · **Grupo #13**

## Carpetas

| Carpeta | Contenido | Guía |
|---|---|---|
| `basededato/sql/` | Base de datos PostgreSQL: 24 tablas (PERSONA como generalización de paciente y médico, bitácora con auditoría, atención médica y notificaciones), restricciones `CHECK`/`UNIQUE`/`EXCLUDE`, 15 funciones de trigger, 8 vistas, datos de prueba y 25 pruebas | [LEEME.md](basededato/sql/LEEME.md) |
| `backend/` | PHP 8 sin frameworks: **toda la lógica de negocio**, un archivo por caso de uso, acceso a datos con PDO, login y permisos | [README.md](backend/README.md) |
| `frontend/` | Interfaz: vistas HTML5 por paquete, CSS3 y JavaScript | [README.md](frontend/README.md) |
| `doc/` | Documento del proyecto (.docx y .pdf), diagramas StarUML, capturas, guía de defensa y prompt para Claude Code | [README.md](doc/README.md) |

## Arquitectura

```
Navegador ──petición HTTP──► Apache (XAMPP) ──► backend/public/index.php ──► Enrutador ──► caso de uso (CUxx)
   ▲                          .htaccess raíz                               sesión, permiso,     │ reglas de negocio
   │                          /assets → frontend/assets                     token CSRF           ▼
   └──── página HTML ◄──── frontend/vistas/<paquete>/*.php ◄──────────────────────────── modelos (PDO) ──SQL──► PostgreSQL
                                                                                                         restricciones + triggers
```

- **Frontend (`frontend/`)**: lo que ve el usuario. Plantillas PHP/HTML por paquete, estilos y scripts.
- **Backend (`backend/`)**: recibe cada petición, valida, aplica las reglas de negocio y es el único que habla con la base.
- **Base de datos (`basededato/sql/`)**: guarda los datos y vuelve a garantizar las reglas críticas (por ejemplo, que un
  médico nunca tenga dos citas a la misma hora) aunque alguien intente saltarse la aplicación.

## Versiones

| Herramienta | Versión |
|---|---|
| PHP (incluido en XAMPP) | 8.1 o superior, con `pdo_pgsql`, `pgsql`, `mbstring` y `curl` activos |
| Apache (incluido en XAMPP) | 2.4 con `mod_rewrite` |
| PostgreSQL | 14 o superior (probado en 16) |
| Navegador | Chrome, Edge o Firefox actuales |

No se usan Composer, npm ni librerías externas: basta con XAMPP y PostgreSQL.

## Paquetes y casos de uso (todos implementados)

El backend y el frontend se organizan igual: **un paquete por área del negocio**, y dentro de cada paquete un archivo
por caso de uso en `backend/app/paquetes/<paquete>/casos_uso/` y sus pantallas en `frontend/vistas/<paquete>/`.

| Paquete | Casos de uso |
|---|---|
| `seguridad` | CU01 Iniciar sesión · CU02 Gestionar usuarios y permisos (incluye la bitácora de acciones) |
| `pacientes` | CU03 Gestionar pacientes · CU04 Gestionar perfil del paciente · CU05 Consultar historial de citas |
| `medicos` | CU06 Gestionar especialidades · CU07 Gestionar médicos · CU08 Gestionar horarios de atención |
| `citas` | CU09 Programar cita · CU10 Reprogramar cita · CU11 Cancelar cita · CU12 Registrar asistencia · CU13 Consultar agenda médica (diaria y semanal) |
| `reportes` | CU14 Generar reportes |
| `inicio` | Panel principal según el rol |

## Puesta en marcha en Windows (XAMPP)

1. **PHP:** en `C:\xampp\php\php.ini` quitar el `;` de `extension=pdo_pgsql` y `extension=pgsql` y reiniciar Apache.
2. **Proyecto:** copiar o clonar esta carpeta en `C:\xampp\htdocs\clinica-vida-salud`.
3. **Base de datos:** doble clic en `basededato\sql\instalar_bd.bat` (pide la contraseña de `postgres`). Detalle en
   [basededato/sql/LEEME.md](basededato/sql/LEEME.md).
4. **Contraseña:** si la de `postgres` no es `postgres`, crear `backend\config\config.local.php`:
   ```php
   <?php return ['bd' => ['clave' => 'SU_CLAVE']];
   ```
5. **Abrir:** <http://localhost/clinica-vida-salud> — usuario `admin`, contraseña `Clinica2026*`.

Sin Apache (desde la carpeta raíz): `php -S localhost:8000 backend/public/router.php` → <http://localhost:8000>

### Usuarios de demostración (contraseña de todos: `Clinica2026*`)

| Usuario | Rol | Qué puede hacer |
|---|---|---|
| `admin` | Administrador | Usuarios, permisos, bitácora, especialidades, médicos, horarios y reportes |
| `recepcion`, `recepcion2` | Recepcionista | Pacientes, programar, reprogramar y cancelar citas, registrar asistencia |
| `dr.perez`, `dra.gutierrez`, `dra.suarez`, `dr.mendoza` | Médico | Su agenda y marcar citas como atendidas |
| `maria.lopez`, `carlos.vargas` | Paciente | Su perfil, reservar citas, sus citas y su historial |

## Pruebas

```bat
:: 10 pruebas del examen (con el sistema encendido)
php backend\tests\pruebas_sistema.php http://localhost/clinica-vida-salud

:: 25 pruebas de restricciones y triggers (no dejan cambios en la base)
psql -U postgres -d clinica_vida_salud -f basededato\sql\pruebas\pruebas_bd.sql
```

## Cómo colaborar y modificar el proyecto

**Cualquier integrante (o cualquier persona con acceso al repositorio) puede modificar todo el proyecto**: código,
base de datos, diseño y documentación. Para que los cambios no se pisen:

1. Haga `git pull` antes de empezar.
2. Para un cambio grande, cree una rama: `git checkout -b mejora-reportes`.
3. Siga la misma organización: un caso de uso nuevo lleva su ruta en `backend/app/paquetes/<paquete>/rutas.php`, su
   archivo en `casos_uso/`, sus consultas en `modelos/` y su pantalla en `frontend/vistas/<paquete>/`
   (paso a paso en [backend/README.md](backend/README.md)).
4. Si cambia la base de datos, actualice el script en `basededato/sql/` y vuelva a instalarla.
5. Ejecute las pruebas antes de subir: `php backend\tests\pruebas_sistema.php http://localhost/clinica-vida-salud`.
6. `git add .` → `git commit -m "descripción del cambio"` → `git push` (o abra un *pull request* si trabajó en una rama).

Para seguir trabajando con Claude Code desde la terminal de VS Code, use el prompt de
[doc/PROMPT_CLAUDE_CODE.md](doc/PROMPT_CLAUDE_CODE.md).

## Integrantes — Grupo #13

**Docente:** MSc. Ing. Angélica Garzón Cuéllar · **Materia:** Sistemas de Información I - SA

| N.º | Apellidos y nombres | Registro |
|---|---|---|
| 1 | Ayala Ayala Edison | 222153512 |
| 2 | Saldaña Zurita Pablo Andres | 223044253 |

## Licencia

Código abierto bajo la [licencia MIT](LICENSE): se puede usar, copiar y modificar libremente citando a los autores.
