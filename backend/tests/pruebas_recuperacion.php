<?php
/**
 * =====================================================================
 *  PRUEBAS DE CU15 · RECUPERAR CONTRASEÑA POR CORREO
 *
 *  Se ejecutan contra el sistema EN FUNCIONAMIENTO, como un usuario.
 *  No envían correos reales: la cuenta de prueba usa el dominio reservado
 *  example.com, así el correo solo se escribe en storage/logs/correos.log
 *  y de ahí se toma el enlace. Cada ejecución crea su propia cuenta.
 *
 *  Uso (con Apache/XAMPP o con el servidor de PHP encendido, en esta PC):
 *     php backend/tests/pruebas_recuperacion.php http://localhost/clinica-vida-salud
 *     php backend/tests/pruebas_recuperacion.php http://localhost:8000
 *
 *  El resultado se guarda en tests/resultados_pruebas_recuperacion.md.
 * =====================================================================
 */
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://localhost/clinica-vida-salud', '/');
$logCorreos = dirname(__DIR__) . '/storage/logs/correos.log';

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
        $ch = curl_init($this->base . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $this->cookies,
            CURLOPT_COOKIEFILE => $this->cookies,
        ]);
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

    public function ingresar(string $usuario, string $clave): array
    {
        return $this->pedir('POST', '/login', ['_csrf' => $this->token('/login'), 'usuario' => $usuario, 'clave' => $clave]);
    }

    public function solicitarEnlace(string $usuario): array
    {
        return $this->pedir('POST', '/recuperar', ['_csrf' => $this->token('/recuperar'), 'usuario' => $usuario]);
    }
}

/** Texto de los mensajes (alertas y errores de campos) que mostró la página. */
function mensajes(string $html): string
{
    preg_match_all('/<div class="alerta[^"]*"[^>]*>.*?<p>(.*?)<\/p>/s', $html, $m);
    preg_match_all('/<p class="campo-error"[^>]*>(.*?)<\/p>/s', $html, $c);
    return html_entity_decode(trim(implode(' | ', array_merge($m[1], $c[1]))), ENT_QUOTES, 'UTF-8');
}

/** Enlaces de recuperación escritos en correos.log para un correo, del más antiguo al más reciente. */
function enlaces(string $log, string $correo): array
{
    $texto = is_file($log) ? (string) file_get_contents($log) : '';
    preg_match_all('/Para: ' . preg_quote($correo, '/') . ' · .*?\n.*?(\/restablecer\?token=[a-f0-9]{64})/s', $texto, $m);
    return $m[1];
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

echo "\nCU15 · Recuperar contraseña — pruebas sobre {$base}\n\n";

// Cuenta de prueba nueva (recepcionista) con un correo del dominio reservado example.com.
$sufijo = (string) random_int(100000, 999999);
$usuario = "prueba.clave{$sufijo}";
$correo = "prueba.clave{$sufijo}@example.com";
$claveInicial = 'Inicial2026';
$claveNueva = 'Nueva2026x';
$admin = new Navegador($base);
$admin->ingresar('admin', 'Clinica2026*');
$admin->pedir('POST', '/usuarios', [
    '_csrf' => $admin->token('/usuarios/nuevo'), 'nombres' => 'Prueba', 'apellidos' => 'Recuperación',
    'nombre_usuario' => $usuario, 'correo' => $correo, 'id_rol' => 2,
    'clave' => $claveInicial, 'clave_confirmacion' => $claveInicial,
]);

$anonimo = new Navegador($base);
$enlace = '';

prueba(1, 'Solicitar el enlace con el nombre de usuario', 'Mensaje genérico y correo con un enlace de un solo uso',
    function () use ($anonimo, $usuario, $correo, $logCorreos, &$enlace) {
        $m = mensajes($anonimo->solicitarEnlace($usuario)['cuerpo']);
        $lista = enlaces($logCorreos, $correo);
        $enlace = (string) end($lista);
        return [str_contains($m, 'Si los datos corresponden a una cuenta activa') && $enlace !== '',
                $m . ' · Enlace en correos.log: ' . ($enlace !== '' ? substr($enlace, 0, 32) . '…' : 'ninguno')];
    });

prueba(2, 'Solicitar el enlace para una cuenta que no existe', 'El mismo mensaje, sin enviar ningún correo',
    function () use ($anonimo, $logCorreos) {
        $antes = is_file($logCorreos) ? filesize($logCorreos) : 0;
        $m = mensajes($anonimo->solicitarEnlace('no.existe' . random_int(1000, 9999) . '@example.com')['cuerpo']);
        clearstatcache();
        $despues = is_file($logCorreos) ? filesize($logCorreos) : 0;
        return [str_contains($m, 'Si los datos corresponden a una cuenta activa') && $antes === $despues,
                $m . ' · Correos nuevos: ' . ($despues === $antes ? 'ninguno' : 'sí')];
    });

prueba(3, 'Abrir un enlace inventado', 'El sistema rechaza el enlace', function () use ($anonimo) {
    $html = $anonimo->pedir('GET', '/restablecer?token=' . str_repeat('ab', 32))['cuerpo'];
    return [str_contains($html, 'Enlace no válido'), str_contains($html, 'Enlace no válido')
        ? 'Muestra «Enlace no válido» y ofrece pedir uno nuevo' : 'No rechazó el enlace'];
});

prueba(4, 'Guardar una contraseña débil con el enlace', 'El servidor valida y la rechaza', function () use ($anonimo, &$enlace) {
    $anonimo->pedir('GET', $enlace);
    $r = $anonimo->pedir('POST', '/restablecer', [
        '_csrf' => $anonimo->token($enlace), 'token' => substr($enlace, strlen('/restablecer?token=')),
        'clave' => 'corta', 'clave_confirmacion' => 'corta',
    ]);
    $m = mensajes($r['cuerpo']);
    return [str_contains($m, 'de 8 a 64 caracteres'), $m];
});

prueba(5, 'Crear la nueva contraseña con el enlace', 'Cambia la contraseña: entra con la nueva y no con la anterior',
    function () use ($base, $anonimo, $usuario, $claveInicial, $claveNueva, &$enlace) {
        $r = $anonimo->pedir('POST', '/restablecer', [
            '_csrf' => $anonimo->token($enlace), 'token' => substr($enlace, strlen('/restablecer?token=')),
            'clave' => $claveNueva, 'clave_confirmacion' => $claveNueva,
        ]);
        $m = mensajes($r['cuerpo']);
        $conNueva = (new Navegador($base))->ingresar($usuario, $claveNueva)['cuerpo'];
        $conAnterior = (new Navegador($base))->ingresar($usuario, $claveInicial)['cuerpo'];
        $ok = str_contains($m, 'Su contraseña fue cambiada') && str_contains($conNueva, 'Panel principal')
            && str_contains(mensajes($conAnterior), 'Usuario o contraseña incorrectos');
        return [$ok, $m . ' · Ingreso con la nueva: ' . (str_contains($conNueva, 'Panel principal') ? 'sí' : 'no')
            . ' · con la anterior: ' . (str_contains($conAnterior, 'Panel principal') ? 'sí' : 'no')];
    });

prueba(6, 'Usar otra vez el mismo enlace', 'El enlace ya no sirve (un solo uso)', function () use ($anonimo, &$enlace) {
    $html = $anonimo->pedir('GET', $enlace)['cuerpo'];
    return [str_contains($html, 'Enlace no válido'), str_contains($html, 'Enlace no válido')
        ? 'Muestra «Enlace no válido»' : 'El enlace todavía funciona'];
});

prueba(7, 'Pedir 4 enlaces en una hora para la misma cuenta', 'Solo se envían 3; el cuarto se omite con el mismo mensaje',
    function () use ($anonimo, $usuario, $correo, $logCorreos) {
        for ($i = 0; $i < 3; $i++) {
            $m = mensajes($anonimo->solicitarEnlace($usuario)['cuerpo']);
        }
        $total = count(enlaces($logCorreos, $correo));
        return [$total === 3 && str_contains($m, 'Si los datos corresponden a una cuenta activa'),
                "4 solicitudes en la hora → {$total} correos enviados · Mensaje: {$m}"];
    });

/* ---------------- Resumen y archivo para la documentación ---------------- */
$aprobadas = count(array_filter($resultados, static fn ($r) => $r['ok']));
printf("Resultado: %d de %d pruebas aprobadas.\n\n", $aprobadas, count($resultados));

$md = "# Resultados de las pruebas de CU15 · Recuperar contraseña\n\nEjecutadas el " . date('d/m/Y H:i') . " sobre {$base}\n\n"
    . "| N.º | Prueba | Resultado esperado | Resultado obtenido | Estado |\n|---|---|---|---|---|\n";
foreach ($resultados as $r) {
    $md .= sprintf("| %d | %s | %s | %s | %s |\n", $r['n'], $r['nombre'], $r['esperado'],
        str_replace('|', '/', $r['obtenido']), $r['ok'] ? 'Aprobada' : 'Fallida');
}
$md .= "\n**{$aprobadas} de " . count($resultados) . " pruebas aprobadas.**\n";
file_put_contents(__DIR__ . '/resultados_pruebas_recuperacion.md', $md);
exit($aprobadas === count($resultados) ? 0 : 1);
