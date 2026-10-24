# Resultados de las pruebas de CU15 · Recuperar contraseña

Ejecutadas el 24/10/2026 19:36 sobre http://localhost:8000

| N.º | Prueba | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|
| 1 | Solicitar el enlace con el nombre de usuario | Mensaje genérico y correo con un enlace de un solo uso | Si los datos corresponden a una cuenta activa, le enviamos un enlace a su correo. Revise también la carpeta de spam. El enlace vence en 30 minutos. · Enlace en correos.log: /restablecer?token=16df8ec0479c1… | Aprobada |
| 2 | Solicitar el enlace para una cuenta que no existe | El mismo mensaje, sin enviar ningún correo | Si los datos corresponden a una cuenta activa, le enviamos un enlace a su correo. Revise también la carpeta de spam. El enlace vence en 30 minutos. · Correos nuevos: ninguno | Aprobada |
| 3 | Abrir un enlace inventado | El sistema rechaza el enlace | Muestra «Enlace no válido» y ofrece pedir uno nuevo | Aprobada |
| 4 | Guardar una contraseña débil con el enlace | El servidor valida y la rechaza | Revise los datos marcados en el formulario. / Nueva contraseña debe tener de 8 a 64 caracteres, con letras y números. | Aprobada |
| 5 | Crear la nueva contraseña con el enlace | Cambia la contraseña: entra con la nueva y no con la anterior | Su contraseña fue cambiada. Ya puede iniciar sesión con la nueva contraseña. · Ingreso con la nueva: sí · con la anterior: no | Aprobada |
| 6 | Usar otra vez el mismo enlace | El enlace ya no sirve (un solo uso) | Muestra «Enlace no válido» | Aprobada |
| 7 | Pedir 4 enlaces en una hora para la misma cuenta | Solo se envían 3; el cuarto se omite con el mismo mensaje | 4 solicitudes en la hora → 3 correos enviados · Mensaje: Si los datos corresponden a una cuenta activa, le enviamos un enlace a su correo. Revise también la carpeta de spam. El enlace vence en 30 minutos. | Aprobada |

**7 de 7 pruebas aprobadas.**
