# Resultados de las pruebas de atención médica, recetas y notificaciones (CU16 a CU19)

Ejecutadas el 24/10/2026 13:39 sobre http://localhost:8000

| N.º | Prueba | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|
| 1 | La recepción intenta abrir el formulario de atención | Acceso denegado (no tiene atencion.registrar) | HTTP 403 | Aprobada |
| 2 | Otro médico intenta atender la cita | El sistema lo impide | Solo puede registrar la atención de sus propias citas. | Aprobada |
| 3 | Guardar signos vitales fuera de rango | El servidor rechaza los datos y no guarda nada | Revise los datos marcados en el formulario. / Alergias: Penicilina y polen.                        / Revise los datos marcados en el formulario. / La presión sistólica debe ser mayor que la diastólica. / Temperatura (°C) debe ser un número entre 30 y 45. | Aprobada |
| 4 | El médico registra la atención con signos vitales y receta de 2 medicamentos | Atención, signos (IMC calculado) y receta guardados; tomas programadas solo del medicamento con recordatorio; receta enviada | Atención registrada y cita marcada como atendida. La receta se envió al correo del paciente. · IMC 22.9 · tomas programadas: paracetamol 9, omeprazol (sin recordatorio) 0 | Aprobada |
| 5 | La recepción abre la cita atendida | Ve la cita pero no el diagnóstico (dato clínico) | Ve la cita sin la sección «Atención médica» | Aprobada |
| 6 | El paciente desactiva y vuelve a activar el recordatorio de un medicamento en «Mis recetas» | Las tomas pendientes se anulan y luego se vuelven a programar | Mis recetas muestra la receta · tomas pendientes: 9 → 0 → 9 | Aprobada |
| 7 | La tarea programada envía una toma vencida y el paciente desactiva ese medicamento desde el correo | Llega «Hora de su medicamento» con el enlace; tras confirmar, ya no quedan tomas pendientes | Tarea: [2026-10-24 13:39:23] Notificaciones: 1 enviada(s), 0 con error, 0 anulada(s) · enlace en el correo: sí · tomas pendientes después: 0 | Aprobada |
| 8 | La recepción programa una cita para dentro de unos días | Se envía «Cita registrada» y se programa el recordatorio de 24 h | Cita CM-1005 registrada. · cita_registrada: enviada · recordatorio 24 h: pendiente | Aprobada |
| 9 | El paciente cambia su preferencia a «24 horas y 1 hora antes» en Mi perfil | Se programan los dos recordatorios de la cita | Cambios guardados. · pendientes: recordatorio_cita, recordatorio_cita_1h | Aprobada |
| 10 | Llega el recordatorio de 24 h y el paciente confirma desde el correo | La cita queda confirmada (sin iniciar sesión) | Tarea: [2026-10-24 13:39:25] Notificaciones: 1 enviada(s), 0 con error, 0 anulada(s) · ¡Gracias! Su asistencia quedó confirmada. · estado de la cita: confirmada | Aprobada |
| 11 | Abrir el enlace de la cita con un carácter cambiado | El sistema rechaza el enlace alterado | Muestra «Enlace no válido» | Aprobada |
| 12 | El paciente cancela la cita desde el mismo correo | La cita se cancela, se avisa por correo y se anula el recordatorio de 1 h | Su cita fue cancelada y el horario quedó libre. · cita: cancelada · aviso de cancelación: enviada · recordatorio 1 h: anulada | Aprobada |
| 13 | Un recordatorio con más de 6 horas de atraso | La tarea lo anula en vez de enviarlo | Estado: anulada · No se envió a tiempo (más de 6 h de atraso). | Aprobada |
| 14 | El administrador consulta las notificaciones y envía las pendientes | Ve la lista y el envío manual responde | Envío terminado: 0 enviado(s), 0 con error, 0 anulado(s). | Aprobada |
| 15 | El administrador registra un medicamento y luego lo intenta repetir | Se registra una vez; el duplicado se rechaza | Medicamento «Medicamento prueba 425» registrado. · Ya existe ese medicamento con la misma presentación y concentración. | Aprobada |

**15 de 15 pruebas aprobadas.**
