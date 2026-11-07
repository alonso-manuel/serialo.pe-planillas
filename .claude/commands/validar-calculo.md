---
description: Verifica el motor de cálculo contra las reglas y los casos validados
argument-hint: [regla o caso nuevo opcional]
---

1. Ejecuta `php artisan test tests/Unit/Planilla tests/Unit/Sunat` y reporta resultados.
2. Compara `app/Domain/Planilla/CalculadoraPlanilla.php` con `docs/reglas-calculo.md`: señala cualquier diferencia entre fórmula documentada e implementada.
3. Busca números legales escritos en el código (`1130`, `0.09`, `0.13`, `15`, etc.) fuera de seeders y tests: deben venir de `parametros`.
4. Si te paso un caso nuevo ($ARGUMENTS) con entradas y resultados esperados de una planilla real (sin datos personales), agrégalo como test con comentario de origen. Si falla, explica qué regla difiere antes de tocar el motor; **nunca ajustes el esperado para que pase**.
5. Actualiza la sección "Verificaciones pendientes" de `docs/reglas-calculo.md` si algo quedó confirmado.
