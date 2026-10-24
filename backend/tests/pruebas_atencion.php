<?php
/**
 * =====================================================================
 *  PRUEBAS DE CU16 · CU17 · CU18 · CU19
 *  (atención médica, recetas, recordatorios por correo y medicamentos)
 *
 *  Actúan como los usuarios (médico, recepción, paciente, administrador)
 *  contra el sistema EN FUNCIONAMIENTO y verifican en la base lo que no se
 *  ve en pantalla (tomas programadas, estado de los correos). No envían
 *  correos reales: los pacientes de prueba usan example.com y los correos
 *  quedan en storage/logs/correos.log. Debe ejecutarse en la PC del servidor
 *  y deja datos de prueba (vuelva a instalar la base para limpiarla).
 *
 *  Uso:
 *     php backend/tests/pruebas_atencion.php http://localhost/clinica-vida-salud
 *     php backend/tests/pruebas_atencion.php http://localhost:8000
 *
 *  El resultado se guarda en tests/resultados_pruebas_atencion.md.
 * =====================================================================
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/nucleo/Aplicacion.php';

use App\Nucleo\Aplicacion;
use App\Nucleo\BaseDatos;

Aplicacion::iniciar();

$base = rtrim($argv[1] ?? 'http://localhost/clinica-vida-salud', '/');
$logCorreos = dirname(__DIR__) . '/storage/logs/correos.log';
$clave = 'Clinica2026*';

/* ---------------- Cliente HTTP mínimo con cookies ---------------- */
final class Navegador
{
    private string $cookies;

    public function __construct(private string $base)
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'cvs');
    }

    /** @return array{codigo:int,cuerpo:string} */
    public function pedir(string $metodo, string $ruta, array $datos = []): array
    {
        $ch = curl_init(str_starts_with($ruta, 'http') ? $ruta : $this->base . $ruta);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $this->cookies, CURLOPT_COOKIEFILE => $this->cookies]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
        }
        $r = ['cuerpo' => (string) curl_exec($ch), 'codigo' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE)];
        curl_close($ch);
        return $r;
    }

    public function token(string $ruta): string
    {
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->pedir('GET', $ruta)['cuerpo'], $m);
        return $m[1] ?? '';
    }

    public function enviar(string $formulario, string $accion, array $datos): array
    {
        return $this->pedir('POST', $accion, ['_csrf' => $this->token($formulario)] + $datos);
    }

    public static function ingresar(string $base, string $usuario, string $clave): self
    {
        $n = new self($base);
        $n->enviar('/login', '/login', ['usuario' => $usuario, 'clave' => $clave]);
        return $n;
    }
}

function mensajes(string $html): string
{
    preg_match_all('/<div class="alerta[^"]*"[^>]*>.*?<p>(.*?)<\/p>/s', $html, $m);
    preg_match_all('/<p class="campo-error"[^>]*>(.*?)<\/p>/s', $html, $c);
    return html_entity_decode(trim(strip_tags(implode(' | ', array_merge($m[1], $c[1])))), ENT_QUOTES, 'UTF-8');
}

/** Último enlace de un tipo (/correo/cita, /correo/baja) escrito en correos.log para un correo. */
function enlaceDelLog(string $log, string $correo, string $ruta): ?string
{
    $texto = is_file($log) ? (string) file_get_contents($log) : '';
    $bloques = preg_split('/^===== /m', $texto);
    foreach (array_reverse($bloques) as $b) {
        if (str_contains($b, 'Para: ' . $correo . ' ') && preg_match('#(https?://\S*' . preg_quote($ruta, '#') . '\?e=[A-Za-z0-9_.-]+)#', $b, $m)) {
            return $m[1];
        }
    }
    return null;
}

/** Ejecuta la tarea programada (como lo hace el Programador de tareas o el cron). */
function correrTarea(string $base): string
{
    $php = PHP_BINARY;
    $script = dirname(__DIR__) . '/tareas/enviar_notificaciones.php';
    return trim((string) shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --url=' . escapeshellarg($base) . ' 2>&1'));
}

$resultados = [];
function prueba(int $n, string $nombre, string $esperado, callable $fn): void
{
    global $resultados;
    try {
        [$ok, $obtenido] = $fn();
    } catch (Throwable $e) {
        [$ok, $obtenido] = [false, 'Excepción: ' . $e->getMessage()];
    }
    $resultados[] = compact('n', 'nombre', 'esperado', 'obtenido', 'ok');
    printf("%s Prueba %2d · %s\n      Esperado: %s\n      Obtenido: %s\n\n", $ok ? '[OK]  ' : '[FALLA]', $n, $nombre, $esperado, $obtenido);
}

echo "\nCU16 · CU17 · CU18 · CU19 — pruebas sobre {$base}\n\n";

/* ---------- Datos de apoyo: una cita atendida, sin atención, de un médico y un paciente con cuenta ---------- */
$caso = BaseDatos::uno(
    "SELECT c.id_cita, c.id_paciente, c.id_medico, um.nombre_usuario AS usuario_medico, up.nombre_usuario AS usuario_paciente,
            pe.correo
       FROM cita c
       JOIN usuario um ON um.id_persona = c.id_medico
       JOIN usuario up ON up.id_persona = c.id_paciente
       JOIN persona pe ON pe.id_persona = c.id_paciente
      WHERE c.estado = 'atendida' AND pe.correo LIKE '%@example.com'
        AND NOT EXISTS (SELECT 1 FROM atencion a WHERE a.id_cita = c.id_cita)
      ORDER BY c.fecha DESC LIMIT 1"
) ?? throw new RuntimeException('No hay una cita atendida sin atención de un paciente con cuenta: reinstale la base.');
$idCita = (int) $caso['id_cita'];
$otroMedico = (string) BaseDatos::valor(
    "SELECT u.nombre_usuario FROM usuario u JOIN medico m ON m.id_medico = u.id_persona WHERE m.id_medico <> :m LIMIT 1",
    ['m' => $caso['id_medico']]
);
$paracetamol = (int) BaseDatos::valor("SELECT id_medicamento FROM medicamento WHERE nombre = 'Paracetamol' AND presentacion = 'tableta'");
$omeprazol = (int) BaseDatos::valor("SELECT id_medicamento FROM medicamento WHERE nombre = 'Omeprazol'");
$horaToma = date('H:00', strtotime('+1 hour'));
$diagnostico = 'Prueba automática ' . random_int(1000, 9999) . ': infección respiratoria alta';

$medico = Navegador::ingresar($base, $caso['usuario_medico'], $clave);
$recep = Navegador::ingresar($base, 'recepcion', $clave);
$paciente = Navegador::ingresar($base, $caso['usuario_paciente'], $clave);
$admin = Navegador::ingresar($base, 'admin', $clave);
$formAtencion = "/citas/{$idCita}/atencion";
$idReceta = 0;
$idDetalleParacetamol = 0;

prueba(1, 'La recepción intenta abrir el formulario de atención', 'Acceso denegado (no tiene atencion.registrar)',
    function () use ($recep, $formAtencion) {
        $r = $recep->pedir('GET', $formAtencion);
        return [$r['codigo'] === 403, "HTTP {$r['codigo']}"];
    });

prueba(2, 'Otro médico intenta atender la cita', 'El sistema lo impide', function () use ($base, $otroMedico, $clave, $formAtencion) {
    $otro = Navegador::ingresar($base, $otroMedico, $clave);
    $m = mensajes($otro->pedir('GET', $formAtencion)['cuerpo']);
    return [str_contains($m, 'Solo puede registrar la atención de sus propias citas'), $m];
});

prueba(3, 'Guardar signos vitales fuera de rango', 'El servidor rechaza los datos y no guarda nada',
    function () use ($medico, $formAtencion, $idCita) {
        $m = mensajes($medico->enviar($formAtencion, $formAtencion, ['diagnostico' => 'Fiebre', 'temperatura_c' => '50',
            'presion_sistolica' => '80', 'presion_diastolica' => '90'])['cuerpo']);
        $guardada = (bool) BaseDatos::valor("SELECT EXISTS (SELECT 1 FROM atencion WHERE id_cita = :c)", ['c' => $idCita]);
        return [str_contains($m, 'Temperatura') && str_contains($m, 'sistólica debe ser mayor') && !$guardada, $m];
    });

prueba(4, 'El médico registra la atención con signos vitales y receta de 2 medicamentos',
    'Atención, signos (IMC calculado) y receta guardados; tomas programadas solo del medicamento con recordatorio; receta enviada',
    function () use ($medico, $formAtencion, $idCita, $diagnostico, $paracetamol, $omeprazol, $horaToma, &$idReceta, &$idDetalleParacetamol) {
        $hoy = date('Y-m-d');
        $m = mensajes($medico->enviar($formAtencion, $formAtencion, [
            'diagnostico' => $diagnostico, 'tratamiento' => 'Reposo, líquidos y analgésico.',
            'peso_kg' => '70', 'talla_cm' => '175', 'presion_sistolica' => '120', 'presion_diastolica' => '80', 'temperatura_c' => '37.8',
            'receta' => [
                ['id_medicamento' => $paracetamol, 'dosis' => '1 tableta', 'via' => 'oral', 'frecuencia_horas' => '8',
                 'duracion_dias' => '3', 'fecha_inicio' => $hoy, 'hora_primera_toma' => $horaToma, 'recordar' => '1'],
                ['id_medicamento' => $omeprazol, 'dosis' => '1 cápsula', 'via' => 'oral', 'frecuencia_horas' => '24',
                 'duracion_dias' => '7', 'fecha_inicio' => $hoy, 'hora_primera_toma' => '07:00'],
            ],
            'indicaciones' => 'Volver si la fiebre dura más de 3 días.',
        ])['cuerpo']);
        $a = BaseDatos::uno("SELECT a.id_atencion, c.estado, s.imc, r.id_receta, r.enviada_en
                               FROM atencion a JOIN cita c ON c.id_cita = a.id_cita
                               LEFT JOIN v_signo_vital s ON s.id_atencion = a.id_atencion
                               LEFT JOIN receta r ON r.id_atencion = a.id_atencion WHERE a.id_cita = :c", ['c' => $idCita]);
        $idReceta = (int) ($a['id_receta'] ?? 0);
        $idDetalleParacetamol = (int) BaseDatos::valor("SELECT id_detalle FROM receta_detalle WHERE id_receta = :r AND id_medicamento = :m",
            ['r' => $idReceta, 'm' => $paracetamol]);
        $tomas = BaseDatos::todos("SELECT d.id_medicamento, count(n.*) AS n FROM receta_detalle d
                                     LEFT JOIN notificacion n ON n.id_detalle = d.id_detalle AND n.estado = 'pendiente'
                                    WHERE d.id_receta = :r GROUP BY d.id_medicamento", ['r' => $idReceta]);
        $porMedicamento = array_column($tomas, 'n', 'id_medicamento');
        $ok = str_contains($m, 'Atención registrada') && $a && $a['estado'] === 'atendida' && (float) $a['imc'] === 22.9
            && $a['enviada_en'] !== null && (int) ($porMedicamento[$paracetamol] ?? 0) > 0 && (int) ($porMedicamento[$omeprazol] ?? -1) === 0;
        return [$ok, "{$m} · IMC {$a['imc']} · tomas programadas: paracetamol " . ($porMedicamento[$paracetamol] ?? 0)
            . ', omeprazol (sin recordatorio) ' . ($porMedicamento[$omeprazol] ?? 0)];
    });

prueba(5, 'La recepción abre la cita atendida', 'Ve la cita pero no el diagnóstico (dato clínico)',
    function () use ($recep, $idCita, $diagnostico) {
        $html = $recep->pedir('GET', "/citas/{$idCita}")['cuerpo'];
        return [str_contains($html, 'Datos de la cita') && !str_contains($html, $diagnostico),
            str_contains($html, $diagnostico) ? 'El diagnóstico aparece' : 'Ve la cita sin la sección «Atención médica»'];
    });

prueba(6, 'El paciente desactiva y vuelve a activar el recordatorio de un medicamento en «Mis recetas»',
    'Las tomas pendientes se anulan y luego se vuelven a programar', function () use ($paciente, &$idDetalleParacetamol) {
        $pendientes = static fn () => (int) BaseDatos::valor(
            "SELECT count(*) FROM notificacion WHERE id_detalle = :d AND estado = 'pendiente'", ['d' => $idDetalleParacetamol]);
        $antes = $pendientes();
        $lista = $paciente->pedir('GET', '/mis-recetas')['cuerpo'];
        $paciente->enviar('/mis-recetas', "/mis-recetas/{$idDetalleParacetamol}/recordar", ['recordar' => '0']);
        $apagado = $pendientes();
        $paciente->enviar('/mis-recetas', "/mis-recetas/{$idDetalleParacetamol}/recordar", ['recordar' => '1']);
        $encendido = $pendientes();
        return [str_contains($lista, 'Paracetamol') && $antes > 0 && $apagado === 0 && $encendido === $antes,
            "Mis recetas muestra la receta · tomas pendientes: {$antes} → {$apagado} → {$encendido}"];
    });

prueba(7, 'La tarea programada envía una toma vencida y el paciente desactiva ese medicamento desde el correo',
    'Llega «Hora de su medicamento» con el enlace; tras confirmar, ya no quedan tomas pendientes',
    function () use ($base, $paciente, $caso, $logCorreos, &$idDetalleParacetamol) {
        BaseDatos::ejecutar("UPDATE notificacion SET programada_para = now() - INTERVAL '1 minute'
                              WHERE id_notificacion = (SELECT min(id_notificacion) FROM notificacion
                                                        WHERE id_detalle = :d AND estado = 'pendiente')", ['d' => $idDetalleParacetamol]);
        $salida = correrTarea($base);
        $enlace = enlaceDelLog($logCorreos, $caso['correo'], '/correo/baja');
        $anonimo = new Navegador($base);
        $pagina = $anonimo->pedir('GET', $enlace ?? '/correo/baja?e=x')['cuerpo'];
        preg_match('/name="e" value="([^"]+)"/', $pagina, $t);
        $hecho = $anonimo->enviar($enlace ?? '/correo/baja', '/correo/baja', ['e' => html_entity_decode($t[1] ?? '')])['cuerpo'];
        $pendientes = (int) BaseDatos::valor("SELECT count(*) FROM notificacion WHERE id_detalle = :d AND estado = 'pendiente'",
            ['d' => $idDetalleParacetamol]);
        $ok = str_contains($salida, '1 enviada') && $enlace && str_contains($pagina, 'Dejar de recibir recordatorios')
            && str_contains($hecho, 'Recordatorios desactivados') && $pendientes === 0;
        return [$ok, "Tarea: {$salida} · enlace en el correo: " . ($enlace ? 'sí' : 'no') . " · tomas pendientes después: {$pendientes}"];
    });

/* ---------- Recordatorio de una cita nueva: confirmar y cancelar desde el correo ---------- */
$idCitaNueva = 0;
prueba(8, 'La recepción programa una cita para dentro de unos días', 'Se envía «Cita registrada» y se programa el recordatorio de 24 h',
    function () use ($recep, $caso, &$idCitaNueva) {
        $esp = (int) BaseDatos::valor("SELECT id_especialidad FROM medico_especialidad WHERE id_medico = :m LIMIT 1", ['m' => $caso['id_medico']]);
        $libre = null;
        foreach (json_decode($recep->pedir('GET', "/api/disponibilidad/dias?medico={$caso['id_medico']}&desde=" . date('Y-m-d', strtotime('+2 days')))['cuerpo'], true)['dias'] ?? [] as $d) {
            if (($d['libres'] ?? 0) > 0) {
                $int = json_decode($recep->pedir('GET', "/api/disponibilidad?medico={$caso['id_medico']}&fecha={$d['fecha']}")['cuerpo'], true)['intervalos'] ?? [];
                foreach ($int as $i) {
                    if (!empty($i['libre'])) { $libre = [$d['fecha'], $i['inicio']]; break 2; }
                }
            }
        }
        if (!$libre) {
            return [false, 'El médico no tiene horarios libres'];
        }
        $m = mensajes($recep->enviar('/citas/nueva', '/citas', ['id_paciente' => $caso['id_paciente'], 'id_especialidad' => $esp,
            'id_medico' => $caso['id_medico'], 'fecha' => $libre[0], 'hora_inicio' => $libre[1], 'modalidad' => 'presencial',
            'motivo' => 'Control (prueba automática)'])['cuerpo']);
        $idCitaNueva = (int) BaseDatos::valor("SELECT max(id_cita) FROM cita WHERE id_paciente = :p", ['p' => $caso['id_paciente']]);
        $n = array_column(BaseDatos::todos("SELECT tipo, estado FROM notificacion WHERE id_cita = :c", ['c' => $idCitaNueva]), 'estado', 'tipo');
        return [str_contains($m, 'registrada') && ($n['cita_registrada'] ?? '') === 'enviada' && ($n['recordatorio_cita'] ?? '') === 'pendiente',
            "{$m} · cita_registrada: " . ($n['cita_registrada'] ?? '—') . ' · recordatorio 24 h: ' . ($n['recordatorio_cita'] ?? '—')];
    });

prueba(9, 'El paciente cambia su preferencia a «24 horas y 1 hora antes» en Mi perfil', 'Se programan los dos recordatorios de la cita',
    function () use ($paciente, &$idCitaNueva) {
        $m = mensajes($paciente->enviar('/mi-perfil?seccion=cuenta', '/mi-perfil/cuenta',
            ['recordatorio_cita' => '24h_1h', 'recordar_medicamentos' => '1', 'compartir_info_medica' => '1'])['cuerpo']);
        $tipos = array_column(BaseDatos::todos("SELECT tipo FROM notificacion WHERE id_cita = :c AND estado = 'pendiente' ORDER BY tipo",
            ['c' => $idCitaNueva]), 'tipo');
        return [str_contains($m, 'Cambios guardados') && $tipos === ['recordatorio_cita', 'recordatorio_cita_1h'],
            "{$m} · pendientes: " . implode(', ', $tipos)];
    });

$enlaceCita = null;
prueba(10, 'Llega el recordatorio de 24 h y el paciente confirma desde el correo', 'La cita queda confirmada (sin iniciar sesión)',
    function () use ($base, $caso, $logCorreos, &$idCitaNueva, &$enlaceCita) {
        BaseDatos::ejecutar("UPDATE notificacion SET programada_para = now() - INTERVAL '1 minute'
                              WHERE id_cita = :c AND tipo = 'recordatorio_cita' AND estado = 'pendiente'", ['c' => $idCitaNueva]);
        $salida = correrTarea($base);
        $enlaceCita = enlaceDelLog($logCorreos, $caso['correo'], '/correo/cita');
        $anonimo = new Navegador($base);
        $pagina = $anonimo->pedir('GET', (string) $enlaceCita)['cuerpo'];
        preg_match('/name="e" value="([^"]+)"/', $pagina, $t);
        $m = mensajes($anonimo->enviar((string) $enlaceCita, '/correo/cita', ['e' => html_entity_decode($t[1] ?? ''), 'accion' => 'confirmar'])['cuerpo']);
        $estado = BaseDatos::valor("SELECT estado FROM cita WHERE id_cita = :c", ['c' => $idCitaNueva]);
        return [str_contains($pagina, 'Confirmar mi asistencia') && str_contains($m, 'confirmada') && $estado === 'confirmada',
            "Tarea: {$salida} · {$m} · estado de la cita: {$estado}"];
    });

prueba(11, 'Abrir el enlace de la cita con un carácter cambiado', 'El sistema rechaza el enlace alterado', function () use ($base, &$enlaceCita) {
    $alterado = substr((string) $enlaceCita, 0, -3) . (substr((string) $enlaceCita, -3, 1) === 'A' ? 'B' : 'A') . substr((string) $enlaceCita, -2);
    $html = (new Navegador($base))->pedir('GET', $alterado)['cuerpo'];
    return [str_contains($html, 'Enlace no válido'), str_contains($html, 'Enlace no válido') ? 'Muestra «Enlace no válido»' : 'Aceptó el enlace'];
});

prueba(12, 'El paciente cancela la cita desde el mismo correo', 'La cita se cancela, se avisa por correo y se anula el recordatorio de 1 h',
    function () use ($base, &$idCitaNueva, &$enlaceCita) {
        $anonimo = new Navegador($base);
        $pagina = $anonimo->pedir('GET', (string) $enlaceCita)['cuerpo'];
        preg_match('/name="e" value="([^"]+)"/', $pagina, $t);
        $m = mensajes($anonimo->enviar((string) $enlaceCita, '/correo/cita', ['e' => html_entity_decode($t[1] ?? ''), 'accion' => 'cancelar',
            'motivo' => 'Prueba: cancelada desde el correo'])['cuerpo']);
        $n = array_column(BaseDatos::todos("SELECT tipo, estado FROM notificacion WHERE id_cita = :c", ['c' => $idCitaNueva]), 'estado', 'tipo');
        $estado = BaseDatos::valor("SELECT estado FROM cita WHERE id_cita = :c", ['c' => $idCitaNueva]);
        return [$estado === 'cancelada' && ($n['cita_cancelada'] ?? '') === 'enviada' && ($n['recordatorio_cita_1h'] ?? '') === 'anulada',
            "{$m} · cita: {$estado} · aviso de cancelación: " . ($n['cita_cancelada'] ?? '—') . ' · recordatorio 1 h: ' . ($n['recordatorio_cita_1h'] ?? '—')];
    });

prueba(13, 'Un recordatorio con más de 6 horas de atraso', 'La tarea lo anula en vez de enviarlo', function () use ($base, $caso) {
    $id = (int) BaseDatos::valor("INSERT INTO notificacion (tipo, id_persona, correo_destino, asunto, id_cita, programada_para)
                                  VALUES ('recordatorio_cita_1h', :p, :c, 'Prueba de atraso', (SELECT max(id_cita) FROM cita WHERE id_paciente = :p),
                                          now() - INTERVAL '7 hours') RETURNING id_notificacion",
        ['p' => $caso['id_paciente'], 'c' => $caso['correo']]);
    correrTarea($base);
    $n = BaseDatos::uno("SELECT estado, error FROM notificacion WHERE id_notificacion = :id", ['id' => $id]);
    return [$n['estado'] === 'anulada', "Estado: {$n['estado']} · {$n['error']}"];
});

prueba(14, 'El administrador consulta las notificaciones y envía las pendientes', 'Ve la lista y el envío manual responde',
    function () use ($admin) {
        $lista = $admin->pedir('GET', '/notificaciones')['cuerpo'];
        $m = mensajes($admin->enviar('/notificaciones', '/notificaciones/enviar', [])['cuerpo']);
        return [str_contains($lista, 'Enviar pendientes ahora') && str_contains($m, 'Envío terminado'), $m];
    });

prueba(15, 'El administrador registra un medicamento y luego lo intenta repetir', 'Se registra una vez; el duplicado se rechaza',
    function () use ($admin) {
        $nombre = 'Medicamento prueba ' . random_int(100, 999);
        $datos = ['nombre' => $nombre, 'presentacion' => 'jarabe', 'concentracion' => '100 mg/5 ml'];
        $m1 = mensajes($admin->enviar('/medicamentos', '/medicamentos', $datos)['cuerpo']);
        $m2 = mensajes($admin->enviar('/medicamentos', '/medicamentos', ['nombre' => strtoupper($nombre)] + $datos)['cuerpo']);
        return [str_contains($m1, 'registrado') && str_contains($m2, 'Ya existe'), "{$m1} · {$m2}"];
    });

/* ---------------- Resumen y archivo para la documentación ---------------- */
$aprobadas = count(array_filter($resultados, static fn ($r) => $r['ok']));
printf("Resultado: %d de %d pruebas aprobadas.\n\n", $aprobadas, count($resultados));

$md = "# Resultados de las pruebas de atención médica, recetas y notificaciones (CU16 a CU19)\n\nEjecutadas el "
    . date('d/m/Y H:i') . " sobre {$base}\n\n| N.º | Prueba | Resultado esperado | Resultado obtenido | Estado |\n|---|---|---|---|---|\n";
foreach ($resultados as $r) {
    $md .= sprintf("| %d | %s | %s | %s | %s |\n", $r['n'], $r['nombre'], $r['esperado'],
        str_replace('|', '/', $r['obtenido']), $r['ok'] ? 'Aprobada' : 'Fallida');
}
$md .= "\n**{$aprobadas} de " . count($resultados) . " pruebas aprobadas.**\n";
file_put_contents(__DIR__ . '/resultados_pruebas_atencion.md', $md);
exit($aprobadas === count($resultados) ? 0 : 1);
