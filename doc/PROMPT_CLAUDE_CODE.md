# Prompts para Claude Code (terminal de VS Code)

Este archivo tiene **dos prompts**:

| Prompt | Cuándo usarlo |
|---|---|
| **1. Instalar, probar y publicar** | La primera vez: deja el sistema funcionando en XAMPP, corre las pruebas y lo sube a GitHub |
| **2. Seguir modificando** | Cualquier día y cualquier integrante: da a Claude Code el contexto del proyecto antes de pedirle un cambio |

## Antes de empezar

1. Copia la carpeta del proyecto en `C:\xampp\htdocs\` para que quede `C:\xampp\htdocs\clinica-vida-salud\`
   (descomprimiendo el zip o con `git clone https://github.com/edisonayala000-netizen/clinica-vida-salud.git`).
2. En VS Code: *Archivo → Abrir carpeta* → `C:\xampp\htdocs\clinica-vida-salud`.
3. Enciende Apache en el panel de XAMPP y verifica que PostgreSQL esté encendido. Ten a mano la contraseña de `postgres`.
4. Abre la terminal (Ctrl + ñ), escribe `claude` y pega el prompt que corresponda.

Estructura del proyecto (igual que en el README):

```
clinica-vida-salud/
├── .htaccess          /assets → frontend/assets · todo lo demás → backend/public/index.php
├── README.md  LICENSE
├── backend/           PHP: public/index.php, app/nucleo, app/paquetes/<paquete>/{rutas.php, casos_uso, modelos, servicios},
│                      config/config.php, storage/logs, tests/pruebas_sistema.php
├── frontend/          vistas/<paquete>/*.php, vistas/compartido, assets/{css, js, img, fonts}
├── basededato/sql/    01..05 *.sql, instalar.sql, instalar_bd.bat, 99_reiniciar.sql, pruebas/pruebas_bd.sql
└── doc/               documento .docx/.pdf, staruml/, capturas/, GUIA_DEFENSA.md, este archivo
```

---

## Prompt 1 — Instalar, probar y publicar en GitHub

```text
Trabajas en el proyecto del Segundo Parcial de Sistemas de Información I (UAGRM), Grupo #13:
Ayala Ayala Edison (222153512) y Saldaña Zurita Pablo Andres (223044253). Docente: MSc. Ing. Angélica Garzón Cuéllar.
PROYECTO: "Sistema web para la gestión de citas médicas de la Clínica Vida Salud".
TECNOLOGÍAS OBLIGATORIAS (no cambiar): PHP 8 sin framework con PDO pdo_pgsql, PostgreSQL, HTML5, CSS3,
JavaScript sin librerías, Apache de XAMPP en Windows. Sin Composer ni npm.

La carpeta actual (C:\xampp\htdocs\clinica-vida-salud) YA contiene el sistema completo y probado, que coincide con
la documentación (doc/Grupo13_SI1_Clinica_Vida_Salud.docx). En esta tarea NO lo reescribas: solo instálalo,
pruébalo y publícalo. Lee primero README.md, backend/README.md, frontend/README.md y basededato/sql/LEEME.md.

Hazlo paso a paso, mostrándome qué haces. Pídeme datos solo cuando los necesites (contraseña, correo de git).

PASO 1 · ENTORNO
- `php -v` (8.1 o superior). Si `php` no está en el PATH usa C:\xampp\php\php.exe.
- `php -m` debe listar pdo_pgsql, pgsql, mbstring y curl. Si falta alguna, quita el ";" de su línea
  `extension=...` en C:\xampp\php\php.ini y pídeme reiniciar Apache desde el panel de XAMPP.
- Localiza psql.exe (PATH o "C:\Program Files\PostgreSQL\<versión>\bin\psql.exe"), versión 14 o superior.
- En C:\xampp\apache\conf\httpd.conf comprueba que mod_rewrite esté activo y que <Directory "C:/xampp/htdocs">
  tenga AllowOverride All (el .htaccess de la raíz lo necesita). Si cambias algo, pídeme reiniciar Apache.

PASO 2 · BASE DE DATOS (desde basededato/sql)
- Pídeme la contraseña de postgres y úsala en la variable de entorno PGPASSWORD (nunca en archivos del repositorio).
- Borra la base si existe y créala con:
    CREATE DATABASE clinica_vida_salud ENCODING 'UTF8' TEMPLATE template0
  luego ejecuta instalar.sql con psql -h localhost (equivale a instalar_bd.bat).
- Comprueba las tildes: SELECT nombre FROM especialidad; debe mostrar "Pediatría" y "Cardiología".
- Si la contraseña de postgres NO es "postgres", crea backend/config/config.local.php con:
    <?php return ['bd' => ['clave' => 'LA_CONTRASEÑA']];
  (está en .gitignore, no se sube).

PASO 3 · PRUEBAS
- psql -U postgres -h localhost -d clinica_vida_salud -f basededato/sql/pruebas/pruebas_bd.sql  → 16 de 16 APROBADA.
- Abre http://localhost/clinica-vida-salud/login (curl o Invoke-WebRequest) → debe responder 200, y
  http://localhost/clinica-vida-salud/assets/css/app.css también 200.
- php backend/tests/pruebas_sistema.php http://localhost/clinica-vida-salud  → 10 de 10 pruebas aprobadas.
- Si algo falla, busca la causa (php.ini, mod_rewrite, AllowOverride, contraseña) y corrige la configuración.
  Si encuentras un error real en el código, corrígelo de forma mínima, explícame qué y por qué, y repite las pruebas.
- Al final reinstala la base (paso 2) para dejarla limpia: las pruebas registran datos.

PASO 4 · RECORRIDO GUIADO
Explícame caso de uso por caso de uso (CU01 a CU14): archivo y método en backend/app/paquetes/, ruta, vista en
frontend/vistas/, tablas y regla de negocio, usando doc/GUIA_DEFENSA.md. Dime qué usuario de prueba usar
(contraseña de todos Clinica2026*: admin, recepcion, recepcion2, dr.perez, maria.lopez).

PASO 5 · GITHUB (usuario edisonayala000-netizen, repositorio clinica-vida-salud, público)
- Configura git si hace falta: git config --global user.name "Edison Ayala" y el correo que yo te indique.
- git init -b main (si no existe), git add ., commit "Sistema web de gestión de citas médicas - Clínica Vida Salud".
  Revisa con git status que NO se suban backend/config/config.local.php ni backend/storage/logs/*.log.
- Si tengo GitHub CLI con sesión iniciada (gh auth status):
    gh repo create edisonayala000-netizen/clinica-vida-salud --public --source . --push
  Si no, pídeme crear el repositorio vacío en https://github.com/new con ese nombre y luego:
    git remote add origin https://github.com/edisonayala000-netizen/clinica-vida-salud.git
    git push -u origin main
- Confirma que https://github.com/edisonayala000-netizen/clinica-vida-salud muestra el README
  (el código QR del documento apunta a esa dirección).
- Si te doy el usuario de GitHub de mi compañero, agrégalo como colaborador con permiso de escritura:
    gh api -X PUT repos/edisonayala000-netizen/clinica-vida-salud/collaborators/USUARIO -f permission=push
  (o indícame cómo hacerlo en Settings → Collaborators → Add people).

Reglas: responde en español, no agregues frameworks ni dependencias, no borres datos sin avisarme.
```

---

## Prompt 2 — Seguir modificando el sistema (para cualquier integrante)

Pega esto al iniciar `claude` y **después** escribe lo que quieres cambiar (por ejemplo: «agrega un reporte de citas
por mes», «cambia el color principal a azul», «agrega el campo seguro médico al paciente»).

```text
Trabajas en el sistema web de citas médicas de la Clínica Vida Salud (Grupo #13, Sistemas de Información I, UAGRM).
Cualquier parte del proyecto se puede modificar. Antes de cambiar nada, lee README.md, backend/README.md,
frontend/README.md y basededato/sql/LEEME.md, y respeta esta organización:

- Tecnologías fijas: PHP 8 sin framework con PDO pdo_pgsql, PostgreSQL, HTML5, CSS3, JavaScript sin librerías,
  Apache de XAMPP. Sin Composer ni npm.
- backend/app/paquetes/<paquete>/: rutas.php (URL + permiso), casos_uso/ (un archivo por caso de uso, clase que
  extiende Controlador), modelos/ (solo SQL parametrizado con BaseDatos), servicios/ (reglas reutilizables).
- frontend/vistas/<paquete>/: pantallas; imprimir con e(), formularios POST con csrf_campo(), enlaces con url(),
  archivos con recurso(). Estilos en frontend/assets/css/app.css (variables de color al inicio).
- basededato/sql/: si cambias tablas, modifica el script que corresponda y reinstala con instalar_bd.bat.
  Las reglas críticas también deben quedar en la base (CHECK, UNIQUE, EXCLUDE, triggers).
- Validar siempre en el servidor aunque el navegador ya valide; mensajes de error claros en español.

Cómo trabajar:
1. Explícame primero qué archivos vas a tocar y por qué; luego hazlo.
2. Al terminar ejecuta las pruebas:
     php backend/tests/pruebas_sistema.php http://localhost/clinica-vida-salud
     psql -U postgres -h localhost -d clinica_vida_salud -f basededato/sql/pruebas/pruebas_bd.sql
   Si cambiaste la base, reinstálala antes (basededato/sql/instalar_bd.bat).
3. Si el cambio afecta lo documentado (nuevo caso de uso, tabla, columna, ruta o pantalla), actualiza
   doc/GUIA_DEFENSA.md y los README, y avísame qué sección del documento Word (doc/) y qué diagramas de
   doc/staruml/ hay que actualizar.
4. Haz commit con un mensaje claro en español y, si te lo pido, git push.
Responde siempre en español.
```

---

## Permitir que otros modifiquen el repositorio

- **Tu compañero (u otra persona) con permiso para subir cambios:** en GitHub abre el repositorio →
  *Settings* → *Collaborators* → *Add people* → escribe su usuario. Cuando acepte la invitación podrá hacer
  `git push` igual que tú.
- **Cualquier otra persona:** al ser un repositorio público con licencia MIT, puede hacer *Fork*, modificarlo y
  proponerte cambios con un *pull request*.
- El README ya indica que el proyecto se puede modificar libremente y explica cómo hacerlo sin romper la organización.

## Si la ingeniera pide verlo funcionando

- Login: <http://localhost/clinica-vida-salud> → `admin` / `Clinica2026*`.
- Prueba 4 en vivo (dos recepcionistas a la vez): ventana normal con `recepcion` y ventana de incógnito con `recepcion2`;
  elegir el mismo médico, día y hora en ambas; confirmar primero en una y luego en la otra: la segunda muestra «RN07».
- Pruebas automáticas: `php backend\tests\pruebas_sistema.php http://localhost/clinica-vida-salud`.
