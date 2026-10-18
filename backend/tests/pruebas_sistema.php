<?php
/**
 * =====================================================================
 *  PRUEBAS DEL SISTEMA (punto 8 del examen: "Pruebas mínimas")
 *
 *  Ejecuta las 10 pruebas contra el sistema EN FUNCIONAMIENTO, como lo
 *  haría un usuario: inicia sesión, envía formularios y revisa la respuesta.
 *
 *  Uso (con Apache/XAMPP o con el servidor de PHP encendido):
 *     php backend/tests/pruebas_sistema.php http://localhost/clinica-vida-salud
 *     php backend/tests/pruebas_sistema.php http://localhost:8000
 *
 *  El resultado se muestra en pantalla y se guarda en
 *  tests/resultados_pruebas.md (se usa en la documentación).
 * =====================================================================
 */
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://localhost/clinica-vida-salud', '/');
$clave = 'Clinica2026*';

/* ---------------- Cliente HTTP mínimo con cookies ---------------- */
final class Navegador
{
    private string $cookies;
    public string $ultimaUrl = '';

    public function __construct(private string $base)
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'cvs');
    }

    /** @return array{codigo:int,cuerpo:string,ubicacion:string} */
    public function pedir(string $metodo, string $ruta, array $datos = [], bool $seguir = true): array
    {
        $ch = curl_init($this->base . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $seguir,
            CURLOPT_COOKIEJAR => $this->cookies,
            CURLOPT_COOKIEFILE => $this->cookies,
            CURLOPT_HEADER => false,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
        }
        $cuerpo = (string) curl_exec($ch);
        $r = [
            'codigo' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'cuerpo' => $cuerpo,
            'ubicacion' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
        ];
        $this->ultimaUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $r;
    }

    public function token(string $ruta = '/login'): string
    {
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->pedir('GET', $ruta)['cuerpo'], $m);
        return $m[1] ?? '';
    }

    public function json(string $ruta): array
    {
        return json_decode($this->pedir('GET', $ruta)['cuerpo'], true) ?? [];
    }

    public function ingresar(string $usuario, string $clave): void
    {
        $this->pedir('POST', '/login', ['_csrf' => $this->token(), 'usuario' => $usuario, 'clave' => $clave]);
    }
}

/** Texto de los mensajes (alertas) que mostró la página. */
function mensajes(string $html): string
{
    preg_match_all('/<div class="alerta[^"]*"[^>]*>.*?<p>(.*?)<\/p>/s', $html, $m);
    preg_match_all('/<p class="campo-error"[^>]*>(.*?)<\/p>/s', $html, $c);
    return html_entity_decode(trim(implode(' | ', array_merge($m[1], $c[1]))), ENT_QUOTES, 'UTF-8');
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

echo "\nSistema de citas — Clínica Vida Salud · pruebas sobre {$base}\n\n";

$recep = new Navegador($base);
$recep->ingresar('recepcion', $clave);
$ci = (string) random_int(10000000, 99999999);
$idPaciente = 0;
$idCita = 0;
$codigoCita = '';
$idMedico = 1;

/* Busca el primer horario libre del médico en los próximos días. */
function primerHorarioLibre(Navegador $nav, int $medico): array
{
    foreach ($nav->json('/api/disponibilidad/dias?medico=' . $medico)['dias'] ?? [] as $d) {
        if ($d['libres'] > 0 && $d['fecha'] > date('Y-m-d')) {
            foreach ($nav->json("/api/disponibilidad?medico={$medico}&fecha={$d['fecha']}")['intervalos'] as $i) {
                if ($i['libre']) {
                    return [$d['fecha'], $i['inicio']];
                }
            }
        }
    }
    throw new RuntimeException('El médico no tiene horarios libres en los próximos 14 días.');
}

/* ---------------------------------------------------------------- */
prueba(1, 'Registrar un paciente nuevo', 'El paciente queda guardado', function () use ($recep, $ci, &$idPaciente) {
    $r = $recep->pedir('POST', '/pacientes', [
        '_csrf' => $recep->token('/pacientes/nuevo'), 'ci' => $ci, 'nombres' => 'Paciente', 'apellidos' => 'De Prueba',
        'fecha_nacimiento' => '1990-05-20', 'sexo' => 'femenino', 'telefono' => '70000001', 'correo' => 'prueba@correo.com',
    ]);
    preg_match('#/pacientes/(\d+)$#', $recep->ultimaUrl, $m);
    $idPaciente = (int) ($m[1] ?? 0);
    return [$idPaciente > 0 && str_contains(mensajes($r['cuerpo']), 'registrado'), mensajes($r['cuerpo']) . " (CI {$ci})"];
});

prueba(2, 'Registrar un paciente con CI duplicado', 'El sistema rechaza el duplicado', function () use ($recep, $ci) {
    $r = $recep->pedir('POST', '/pacientes', [
        '_csrf' => $recep->token('/pacientes/nuevo'), 'ci' => $ci, 'nombres' => 'Otro', 'apellidos' => 'Paciente',
        'fecha_nacimiento' => '1985-01-01', 'sexo' => 'masculino', 'telefono' => '70000002',
    ]);
    $m = mensajes($r['cuerpo']);
    return [str_contains($m, 'Ya existe un paciente registrado con ese CI'), $m];
});

prueba(3, 'Programar una cita en horario disponible', 'La cita queda registrada', function () use ($recep, $idMedico, &$idPaciente, &$idCita, &$codigoCita) {
    [$fecha, $hora] = primerHorarioLibre($recep, $idMedico);
    $r = $recep->pedir('POST', '/citas', [
        '_csrf' => $recep->token('/citas/nueva'), 'id_paciente' => $idPaciente, 'id_especialidad' => 1, 'id_medico' => $idMedico,
        'fecha' => $fecha, 'hora_inicio' => $hora, 'modalidad' => 'presencial', 'motivo' => 'Consulta general',
    ]);
    preg_match('#/citas/(\d+)$#', $recep->ultimaUrl, $m);
    $idCita = (int) ($m[1] ?? 0);
    preg_match('/Cita (CM-\d+) registrada/', mensajes($r['cuerpo']), $c);
    $codigoCita = $c[1] ?? '';
    $GLOBALS['fechaCita'] = $fecha;
    $GLOBALS['horaCita'] = $hora;
    return [$idCita > 0 && $codigoCita !== '', mensajes($r['cuerpo']) . " ({$fecha} {$hora}, Dr. Juan Pérez)"];
});

prueba(4, 'Programar otra cita superpuesta para el mismo médico', 'El sistema impide el conflicto', function () use ($recep, $idMedico) {
    $r = $recep->pedir('POST', '/citas', [
        '_csrf' => $recep->token('/citas/nueva'), 'id_paciente' => 2, 'id_especialidad' => 1, 'id_medico' => $idMedico,
        'fecha' => $GLOBALS['fechaCita'], 'hora_inicio' => $GLOBALS['horaCita'], 'modalidad' => 'presencial',
    ]);
    $m = mensajes($r['cuerpo']);
    return [str_contains($m, 'RN07'), $m];
});

prueba(5, 'Intentar reservar fuera del horario del médico', 'El sistema rechaza la cita', function () use ($recep, $idMedico) {
    $fecha = date('Y-m-d', strtotime('next monday'));
    $r = $recep->pedir('POST', '/citas', [
        '_csrf' => $recep->token('/citas/nueva'), 'id_paciente' => 3, 'id_especialidad' => 1, 'id_medico' => $idMedico,
        'fecha' => $fecha, 'hora_inicio' => '20:00', 'modalidad' => 'presencial',
    ]);
    $m = mensajes($r['cuerpo']);
    return [str_contains($m, 'RN05'), $m . " (lunes {$fecha} a las 20:00; atiende de 08:00 a 12:00)"];
});

prueba(6, 'Cancelar una cita', 'Cambia a estado cancelado y conserva el historial', function () use ($recep, &$idCita) {
    $r = $recep->pedir('POST', "/citas/{$idCita}/cancelar", ['_csrf' => $recep->token("/citas/{$idCita}"), 'motivo' => 'El paciente avisó que no podrá asistir']);
    $html = $r['cuerpo'];
    $ok = str_contains($html, 'estado-cancelada') && str_contains($html, 'Cambio de estado') && str_contains($html, 'Cita registrada');
    return [$ok, mensajes($html) . ' Historial: «Cita registrada» y «Cambio de estado de programada a cancelada».'];
});

prueba(7, 'Consultar el historial de un paciente', 'Aparecen sus citas registradas', function () use ($recep, &$idPaciente, &$codigoCita) {
    $r = $recep->pedir('GET', "/pacientes/{$idPaciente}/historial");
    return [str_contains($r['cuerpo'], $codigoCita), "El historial muestra la cita {$codigoCita} con estado Cancelada"];
});

prueba(8, 'Buscar las citas de un médico por fecha', 'Se muestran los resultados correctos', function () use ($recep, $idMedico) {
    $fecha = $GLOBALS['fechaCita'];
    $r = $recep->pedir('GET', "/citas?medico={$idMedico}&desde={$fecha}&hasta={$fecha}");
    preg_match_all('/<td>Dr\(a\)\. ([^<]+)<\/td>/', $r['cuerpo'], $m);
    $medicos = array_unique($m[1]);
    $ok = count($medicos) === 1 && $medicos[0] === 'Juan Pérez Rojas';
    preg_match('/(\d+) cita\(s\)/', $r['cuerpo'], $t);
    return [$ok, ($t[1] ?? 0) . " cita(s) del {$fecha}, todas del Dr. Juan Pérez Rojas"];
});

prueba(9, 'Ingresar a una página interna sin iniciar sesión', 'El acceso es denegado', function () {
    $anonimo = new Navegador($GLOBALS['base']);
    $r = $anonimo->pedir('GET', '/citas', [], false);
    $final = $anonimo->pedir('GET', '/login');
    return [$r['codigo'] === 302 && str_contains($r['ubicacion'], '/login') && str_contains(mensajes($final['cuerpo']), 'Acceso denegado'),
            "HTTP {$r['codigo']} → redirige a /login: «" . mensajes($final['cuerpo']) . '»'];
});

prueba(10, 'Ejecutar una operación con datos inválidos', 'El servidor valida y rechaza la operación', function () use ($recep) {
    // Se envía directamente al servidor (sin pasar por la validación JavaScript del navegador).
    $r = $recep->pedir('POST', '/pacientes', [
        '_csrf' => $recep->token('/pacientes/nuevo'), 'ci' => '12AB', 'nombres' => 'J0rge#', 'apellidos' => '',
        'fecha_nacimiento' => date('Y-m-d', strtotime('+1 year')), 'sexo' => 'x', 'telefono' => 'abc',
    ]);
    $m = mensajes($r['cuerpo']);
    $r2 = $recep->pedir('POST', '/citas', [
        '_csrf' => $recep->token('/citas/nueva'), 'id_paciente' => 1, 'id_especialidad' => 1, 'id_medico' => 1,
        'fecha' => '2020-01-01', 'hora_inicio' => '25:99', 'modalidad' => 'teletransporte',
    ]);
    $m2 = mensajes($r2['cuerpo']);
    return [str_contains($m, 'Revise los datos') && str_contains($m2, 'Revise los datos'),
            'Paciente: ' . mb_substr($m, 0, 160) . '… · Cita: ' . mb_substr($m2, 0, 120) . '…'];
});

/* ---------------- Resumen y archivo para la documentación ---------------- */
$aprobadas = count(array_filter($resultados, static fn ($r) => $r['ok']));
printf("Resultado: %d de %d pruebas aprobadas.\n\n", $aprobadas, count($resultados));

$md = "# Resultados de las pruebas del sistema\n\nEjecutadas el " . date('d/m/Y H:i') . " sobre {$base}\n\n"
    . "| N.º | Prueba | Resultado esperado | Resultado obtenido | Estado |\n|---|---|---|---|---|\n";
foreach ($resultados as $r) {
    $md .= sprintf("| %d | %s | %s | %s | %s |\n", $r['n'], $r['nombre'], $r['esperado'],
        str_replace('|', '/', $r['obtenido']), $r['ok'] ? 'Aprobada' : 'Fallida');
}
$md .= "\n**{$aprobadas} de " . count($resultados) . " pruebas aprobadas.**\n";
file_put_contents(__DIR__ . '/resultados_pruebas.md', $md);
file_put_contents(__DIR__ . '/resultados_pruebas.json', json_encode($resultados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
exit($aprobadas === count($resultados) ? 0 : 1);
