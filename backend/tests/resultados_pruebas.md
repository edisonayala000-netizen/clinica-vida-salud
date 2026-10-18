# Resultados de las pruebas del sistema

Ejecutadas el 18/10/2026 18:53 sobre http://localhost/clinica-vida-salud

| N.º | Prueba | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|
| 1 | Registrar un paciente nuevo | El paciente queda guardado | Paciente PAC-0024 · Paciente De Prueba registrado. (CI 55989716) | Aprobada |
| 2 | Registrar un paciente con CI duplicado | El sistema rechaza el duplicado | Ya existe un paciente registrado con ese CI. / Este CI ya pertenece a otro paciente. | Aprobada |
| 3 | Programar una cita en horario disponible | La cita queda registrada | Cita CM-1002 registrada. (2026-10-19 08:00, Dr. Juan Pérez) | Aprobada |
| 4 | Programar otra cita superpuesta para el mismo médico | El sistema impide el conflicto | RN07: el médico ya tiene una cita de 08:00 a 08:30. Elija otro horario. | Aprobada |
| 5 | Intentar reservar fuera del horario del médico | El sistema rechaza la cita | RN05: la hora elegida está fuera del horario de atención del médico o no respeta los intervalos configurados. (lunes 2026-10-19 a las 20:00; atiende de 08:00 a 12:00) | Aprobada |
| 6 | Cancelar una cita | Cambia a estado cancelado y conserva el historial | Cita cancelada. El horario quedó libre y la cita se conserva en el historial. Historial: «Cita registrada» y «Cambio de estado de programada a cancelada». | Aprobada |
| 7 | Consultar el historial de un paciente | Aparecen sus citas registradas | El historial muestra la cita CM-1002 con estado Cancelada | Aprobada |
| 8 | Buscar las citas de un médico por fecha | Se muestran los resultados correctos | 3 cita(s) del 2026-10-19, todas del Dr. Juan Pérez Rojas | Aprobada |
| 9 | Ingresar a una página interna sin iniciar sesión | El acceso es denegado | HTTP 302 → redirige a /login: «Acceso denegado: inicie sesión para continuar.» | Aprobada |
| 10 | Ejecutar una operación con datos inválidos | El servidor valida y rechaza la operación | Paciente: Revise los datos marcados en el formulario. / CI debe tener de 5 a 12 dígitos (puede llevar complemento, ej. 1234567-1B). / Nombres solo admite letras y espacio… · Cita: Revise los datos marcados en el formulario. / Fecha no puede ser una fecha pasada. / Hora no es una hora válida (HH:MM).… | Aprobada |

**10 de 10 pruebas aprobadas.**
