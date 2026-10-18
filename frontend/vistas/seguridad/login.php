<?php /** IU Inicio de sesión (CU01) */ ?>
<div class="acceso">
    <section class="acceso-portada" aria-hidden="true">
        <div class="acceso-portada-marca">
            <img src="<?= e(recurso('img/logo-claro.svg')) ?>" alt="" width="48" height="48">
            <span>Clínica Vida Salud</span>
        </div>
        <div class="acceso-portada-texto">
            <h2>Cada cita en su lugar,<br>cada paciente a tiempo.</h2>
            <p>Agenda de medicina general, pediatría, ginecología, odontología y cardiología en un solo sistema.</p>
        </div>
        <svg class="acceso-pulso" viewBox="0 0 600 120" preserveAspectRatio="none">
            <path d="M0 60h170l18-34 24 76 22-58 14 16h80l16-40 20 70 18-30h218"/>
        </svg>
        <ul class="acceso-especialidades">
            <li>Medicina general</li><li>Pediatría</li><li>Ginecología</li><li>Odontología</li><li>Cardiología</li>
        </ul>
    </section>

    <section class="acceso-formulario">
        <form class="tarjeta-acceso" method="post" action="<?= e(url('/login')) ?>" data-validar novalidate>
            <?= csrf_campo() ?>
            <img class="acceso-logo-movil" src="<?= e(recurso('img/logo.svg')) ?>" alt="" width="44" height="44">
            <h1>Iniciar sesión</h1>
            <p class="texto-suave">Ingrese con su usuario o correo electrónico.</p>

            <div class="campo<?= clase_error($errores, 'usuario') ?>">
                <label for="usuario">Usuario o correo</label>
                <div class="campo-con-icono">
                    <?= icono('usuario') ?>
                    <input id="usuario" name="usuario" type="text" autocomplete="username" required maxlength="120"
                           value="<?= viejo($anterior, 'usuario') ?>" autofocus>
                </div>
                <?= error_campo($errores, 'usuario') ?>
            </div>

            <div class="campo<?= clase_error($errores, 'clave') ?>">
                <label for="clave">Contraseña</label>
                <div class="campo-con-icono">
                    <?= icono('llave') ?>
                    <input id="clave" name="clave" type="password" autocomplete="current-password" required maxlength="64">
                    <button type="button" class="ver-clave" data-ver-clave="clave" aria-label="Mostrar contraseña"><?= icono('ojo') ?></button>
                </div>
                <?= error_campo($errores, 'clave') ?>
            </div>

            <button class="boton boton-primario boton-bloque" type="submit">Ingresar</button>

            <p class="acceso-registro">¿Es paciente y aún no tiene cuenta?
                <a href="<?= e(url('/registro')) ?>">Crear mi cuenta</a></p>
        </form>
        <p class="acceso-pie">Su sesión se cierra sola después de 30 minutos sin uso.</p>
    </section>
</div>
