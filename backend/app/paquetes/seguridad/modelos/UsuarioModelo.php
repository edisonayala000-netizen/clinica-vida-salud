<?php
namespace App\Paquetes\Seguridad\Modelos;

use App\Nucleo\Aplicacion;
use App\Nucleo\BaseDatos;

/**
 * Clase ENTIDAD/acceso a datos de la tabla USUARIO.
 * Solo contiene consultas SQL parametrizadas; las reglas de negocio
 * están en los casos de uso (CU01, CU02).
 */
final class UsuarioModelo
{
    /** Busca por nombre de usuario O correo (RF01: "usuario o correo"). */
    public function buscarParaLogin(string $usuarioOCorreo): ?array
    {
        return BaseDatos::uno(
            "SELECT u.id_usuario, u.nombre_usuario, u.password_hash, u.estado, u.intentos_fallidos,
                    u.bloqueado_hasta, u.bloqueado_hasta > now() AS esta_bloqueado,
                    CEIL(EXTRACT(EPOCH FROM (u.bloqueado_hasta - now())) / 60) AS minutos_restantes,
                    r.nombre AS rol, m.estado AS estado_medico, p.estado AS estado_paciente
               FROM usuario u
               JOIN rol r            ON r.id_rol = u.id_rol
               LEFT JOIN medico m    ON m.id_medico = u.id_medico
               LEFT JOIN paciente p  ON p.id_paciente = u.id_paciente
              WHERE u.nombre_usuario = :v OR u.correo = :v",
            ['v' => mb_strtolower($usuarioOCorreo)]
        );
    }

    /** Suma un intento fallido; al llegar al máximo bloquea la cuenta unos minutos. */
    /** @return bool true si con este intento la cuenta quedó bloqueada */
    public function registrarIntentoFallido(int $id): bool
    {
        return (bool) BaseDatos::valor(
            "UPDATE usuario
                SET intentos_fallidos = CASE WHEN intentos_fallidos + 1 >= :max THEN 0 ELSE intentos_fallidos + 1 END,
                    bloqueado_hasta   = CASE WHEN intentos_fallidos + 1 >= :max
                                             THEN now() + make_interval(mins => :min) ELSE bloqueado_hasta END
              WHERE id_usuario = :id
          RETURNING bloqueado_hasta IS NOT NULL AND bloqueado_hasta > now()",
            [
                'id'  => $id,
                'max' => (int) Aplicacion::config('seguridad.max_intentos', 5),
                'min' => (int) Aplicacion::config('seguridad.minutos_bloqueo', 15),
            ]
        );
    }

    public function registrarAccesoExitoso(int $id): void
    {
        BaseDatos::ejecutar(
            "UPDATE usuario SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = now()
              WHERE id_usuario = :id",
            ['id' => $id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listar(?string $buscar, ?string $rol, ?string $estado): array
    {
        $sql = "SELECT u.id_usuario, u.nombre_usuario, u.nombre_completo, u.correo, u.estado,
                       u.ultimo_acceso, u.bloqueado_hasta > now() AS esta_bloqueado,
                       r.nombre AS rol, m.codigo AS codigo_medico, p.codigo AS codigo_paciente
                  FROM usuario u
                  JOIN rol r           ON r.id_rol = u.id_rol
                  LEFT JOIN medico m   ON m.id_medico = u.id_medico
                  LEFT JOIN paciente p ON p.id_paciente = u.id_paciente
                 WHERE 1 = 1";
        $p = [];
        if ($buscar) {
            $sql .= " AND (u.nombre_usuario ILIKE :q OR u.nombre_completo ILIKE :q OR u.correo ILIKE :q)";
            $p['q'] = '%' . $buscar . '%';
        }
        if ($rol) {
            $sql .= " AND r.nombre = :rol";
            $p['rol'] = $rol;
        }
        if ($estado) {
            $sql .= " AND u.estado = :estado";
            $p['estado'] = $estado;
        }
        return BaseDatos::todos($sql . " ORDER BY r.id_rol, u.nombre_completo", $p);
    }

    public function obtener(int $id): ?array
    {
        return BaseDatos::uno(
            "SELECT u.*, r.nombre AS rol FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = :id",
            ['id' => $id]
        );
    }

    public function crear(array $d): int
    {
        return (int) BaseDatos::valor(
            "INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol, id_medico, id_paciente)
             VALUES (:nombre_usuario, :correo, :hash, :nombre_completo, :id_rol, :id_medico, :id_paciente)
             RETURNING id_usuario",
            [
                'nombre_usuario'  => $d['nombre_usuario'],
                'correo'          => $d['correo'],
                'hash'            => $d['password_hash'],
                'nombre_completo' => $d['nombre_completo'],
                'id_rol'          => $d['id_rol'],
                'id_medico'       => $d['id_medico'] ?? null,
                'id_paciente'     => $d['id_paciente'] ?? null,
            ]
        );
    }

    public function actualizar(int $id, array $d): void
    {
        BaseDatos::ejecutar(
            "UPDATE usuario
                SET nombre_usuario = :nombre_usuario, correo = :correo, nombre_completo = :nombre_completo,
                    id_rol = :id_rol, id_medico = :id_medico, id_paciente = :id_paciente
              WHERE id_usuario = :id",
            [
                'id'              => $id,
                'nombre_usuario'  => $d['nombre_usuario'],
                'correo'          => $d['correo'],
                'nombre_completo' => $d['nombre_completo'],
                'id_rol'          => $d['id_rol'],
                'id_medico'       => $d['id_medico'] ?? null,
                'id_paciente'     => $d['id_paciente'] ?? null,
            ]
        );
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        BaseDatos::ejecutar(
            "UPDATE usuario SET estado = :e, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = :id",
            ['id' => $id, 'e' => $estado]
        );
    }

    public function cambiarClave(int $id, string $hash): void
    {
        BaseDatos::ejecutar(
            "UPDATE usuario SET password_hash = :h, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = :id",
            ['id' => $id, 'h' => $hash]
        );
    }

    public function hashActual(int $id): ?string
    {
        return BaseDatos::valor("SELECT password_hash FROM usuario WHERE id_usuario = :id", ['id' => $id]);
    }

    /** Médicos que todavía no tienen cuenta (para vincular). */
    public function medicosSinCuenta(?int $incluirId = null): array
    {
        return BaseDatos::todos(
            "SELECT m.id_medico, m.codigo, m.nombres || ' ' || m.apellidos AS nombre
               FROM medico m
              WHERE m.estado = 'activo'
                AND (NOT EXISTS (SELECT 1 FROM usuario u WHERE u.id_medico = m.id_medico) OR m.id_medico = :inc)
              ORDER BY m.apellidos",
            ['inc' => $incluirId ?? 0]
        );
    }

    /** Pacientes activos sin cuenta (para vincular). */
    public function pacientesSinCuenta(?int $incluirId = null): array
    {
        return BaseDatos::todos(
            "SELECT p.id_paciente, p.codigo, p.ci, p.nombres || ' ' || p.apellidos AS nombre
               FROM paciente p
              WHERE p.estado = 'activo'
                AND (NOT EXISTS (SELECT 1 FROM usuario u WHERE u.id_paciente = p.id_paciente) OR p.id_paciente = :inc)
              ORDER BY p.apellidos",
            ['inc' => $incluirId ?? 0]
        );
    }

    /** Cuántos administradores activos quedan (no se puede dejar al sistema sin ninguno). */
    public function administradoresActivos(): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
              WHERE r.nombre = 'administrador' AND u.estado = 'activo'"
        );
    }
}
