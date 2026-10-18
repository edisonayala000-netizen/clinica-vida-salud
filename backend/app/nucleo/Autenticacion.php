<?php
namespace App\Nucleo;

/**
 * Control de acceso (RF01, RN12).
 *
 * En la sesión solo se guarda el id del usuario. En cada petición se
 * vuelve a leer de la base su estado y sus permisos: si el administrador
 * lo desactiva o le quita un permiso, el cambio se aplica de inmediato.
 */
final class Autenticacion
{
    /** @var array<string,mixed>|null|false  false = aún no se consultó */
    private static array|null|false $usuario = false;

    /** Registra al usuario en la sesión tras validar sus credenciales. */
    public static function iniciar(int $idUsuario): void
    {
        Sesion::regenerar();
        Sesion::guardar('id_usuario', $idUsuario);
        self::$usuario = false;
    }

    public static function cerrar(): void
    {
        Sesion::destruir();
        self::$usuario = null;
    }

    public static function id(): ?int
    {
        $u = self::usuario();
        return $u ? (int) $u['id_usuario'] : null;
    }

    public static function hayUsuario(): bool
    {
        return self::usuario() !== null;
    }

    /** @return array<string,mixed>|null datos del usuario con sesión válida */
    public static function usuario(): ?array
    {
        if (self::$usuario !== false) {
            return self::$usuario;
        }
        self::$usuario = null;
        $id = Sesion::obtener('id_usuario');
        if (!$id) {
            return null;
        }
        $fila = BaseDatos::uno(
            "SELECT u.id_usuario, u.nombre_usuario, u.nombre_completo, u.correo, u.id_medico,
                    u.id_paciente, r.nombre AS rol
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = :id AND u.estado = 'activo'",
            ['id' => (int) $id]
        );
        if ($fila === null) {          // usuario desactivado o eliminado
            Sesion::quitar('id_usuario');
            return null;
        }
        $fila['permisos'] = array_column(BaseDatos::todos(
            "SELECT p.codigo
               FROM rol_permiso rp
               JOIN permiso p ON p.id_permiso = rp.id_permiso
               JOIN usuario u ON u.id_rol = rp.id_rol
              WHERE u.id_usuario = :id",
            ['id' => (int) $id]
        ), 'codigo');
        return self::$usuario = $fila;
    }

    public static function rol(): ?string
    {
        return self::usuario()['rol'] ?? null;
    }

    /** ¿Tiene el permiso (o al menos uno de la lista)? */
    public static function puede(string|array $permisos): bool
    {
        $u = self::usuario();
        if ($u === null) {
            return false;
        }
        foreach ((array) $permisos as $p) {
            if (in_array($p, $u['permisos'], true)) {
                return true;
            }
        }
        return false;
    }

    /** Prueba 9: sin sesión válida no se entra a páginas internas. */
    public static function exigirSesion(): void
    {
        if (self::hayUsuario()) {
            return;
        }
        if (Peticion::esAjax()) {
            Respuesta::json(['ok' => false, 'mensaje' => 'Su sesión no es válida. Inicie sesión.'], 401);
        }
        Sesion::flash('aviso', 'Acceso denegado: inicie sesión para continuar.');
        Sesion::guardar('volver_a', Peticion::ruta());
        Respuesta::redirigir('/login');
    }

    /** RN12: solo los usuarios autorizados realizan cada operación. */
    public static function exigirPermiso(string|array $permisos): void
    {
        if (self::puede($permisos)) {
            return;
        }
        http_response_code(403);
        if (Peticion::esAjax()) {
            Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permiso para esta operación.'], 403);
        }
        Vista::mostrar('compartido/error', [
            'titulo'  => 'Acceso denegado',
            'codigo'  => 403,
            'mensaje' => 'Su rol no tiene permiso para usar esta opción. Si lo necesita, pídalo al administrador.',
        ]);
        exit;
    }
}
