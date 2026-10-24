# Frontend — HTML5, CSS3 y JavaScript

Todo lo que ve el usuario. No usa frameworks ni librerías externas: las pantallas son plantillas PHP que generan HTML5,
con una hoja de estilos propia y JavaScript puro. Las fuentes (Inter y Lora) están incluidas, así que el sistema se ve
igual sin internet.

## Estructura

```
frontend/
├── vistas/                 ← pantallas, una carpeta por paquete (igual que el backend)
│   ├── compartido/         layout_principal.php (menú lateral y barra superior), layout_simple.php (login y registro),
│   │                       menu.php (se arma según los permisos del rol), paginacion.php, error.php,
│   │                       portada_acceso.php (portada del login y de recuperar contraseña)
│   ├── seguridad/          login, usuarios, formulario de usuario, permisos, bitácora, cambiar contraseña,
│   │                       olvidé mi contraseña y crear nueva contraseña (CU15)
│   ├── correos/            correos del sistema en HTML y texto: base.php (diseño común), recuperar_clave.php
│   ├── pacientes/          lista, formulario, ficha, registro en línea, mi perfil, historial, mis citas
│   ├── medicos/            especialidades, médicos (lista, formulario, ficha), horarios
│   ├── citas/              programar, reprogramar, detalle, listado, agenda diaria y agenda semanal
│   ├── reportes/           reportes (pantalla, impresión/PDF y CSV)
│   └── inicio/             panel según el rol, citas de hoy
└── assets/                 ← se publican en /assets (ver el .htaccess de la raíz)
    ├── css/app.css         sistema de diseño: colores, tipografía, tarjetas, tablas, formularios, impresión y celular
    ├── js/app.js           validación de formularios, confirmaciones, ventanas modales, menú en celular, ver contraseña
    ├── js/citas.js         CU09/CU10: carga médicos, días y horarios disponibles con fetch, sin recargar la página
    ├── img/                logo, favicon e íconos (iconos.svg)
    └── fonts/              Inter y Lora
```

## Cómo se usa una vista

Desde un caso de uso del backend:

```php
$this->vista('medicos/especialidades', ['titulo' => 'Especialidades', 'especialidades' => $lista]);
//            frontend/vistas/medicos/especialidades.php   ← variables disponibles en la plantilla
```

La plantilla se inserta dentro de `vistas/compartido/layout_principal.php`. Reglas para escribir vistas:

- Imprimir datos **siempre** con `e($valor)` (evita XSS).
- Todo formulario `POST` lleva `<?= csrf_campo() ?>`.
- Enlaces con `url('/ruta')` y archivos con `recurso('css/app.css')`, así funciona en `http://localhost/clinica-vida-salud`
  y en cualquier otra carpeta.
- Los íconos se dibujan con `icono('calendario')` (sprite `img/iconos.svg`).
- Validación en el navegador con atributos HTML5 (`required`, `pattern`, `min`, `max`); el backend vuelve a validar todo.

## Colores y estilo

Los colores están como variables al inicio de `css/app.css` (`--pino`, `--verde`, `--menta`, …). Cambiando esas variables
cambia el aspecto de todo el sistema. Las pantallas se adaptan al celular y los reportes tienen estilo de impresión.
