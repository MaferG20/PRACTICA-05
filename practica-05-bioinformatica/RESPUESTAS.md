# Respuestas (borrador para Classroom) · Práctica 05

> Están redactadas según lo que implementa este proyecto. Revísalas y ajústalas con tus palabras; la 11 y la 13 son de experiencia personal y conviene que las edites.

**1. Modelo botánico y relevancia.** Simula el ciclo anual de un geófito bulboso, el tulipán (*Tulipa*): dormancia con frío, emergencia, crecimiento, floración y senescencia, con la biomasa limitada por una capacidad de carga K y por estrés ambiental. Es relevante en bioinformática porque convierte datos biológicos (rangos de temperatura, luz, frío, nutrientes) en modelos computables, guardados y comparables, como hacen DSSAT o AquaCrop. Es la base para integrar datos de sensores o genómica.

**2. Esquema MySQL.** `especies` (catálogo con óptimos, r, K y requerimiento de frío), `simulaciones` (una corrida: parámetros usados + resultados agregados), `mediciones` (serie temporal cada 5 días), `decisiones` (recomendaciones emitidas) y `comparaciones_ab` (ganador y puntajes). Relaciones 1:N: una especie tiene muchas simulaciones; una simulación tiene muchas mediciones y decisiones (`ON DELETE CASCADE`); una comparación enlaza dos simulaciones.

**3. API REST con FlightPHP.** `GET /especies` entrega el catálogo; `POST /simulaciones` guarda una corrida con sus mediciones en una transacción; `GET /reportes` calcula top 5, eficiencia por especie y tiempo a madurez; también `GET /simulaciones/{id}`, `DELETE`, `/csv` y `/comparaciones`.

**4. Flujo.** El front llama `fetch()` (async/await) → `api/index.php` recibe la ruta con FlightPHP → valida el JSON → PDO ejecuta una sentencia preparada en MySQL → el resultado se serializa con `Flight::json()` → `js/api.js` lo normaliza y la UI lo pinta (por ejemplo, el selector de especies y los reportes). Si la API no responde, usa `data/especies.json`.

**5. Modelo de crecimiento.** Hay cuatro seleccionables (lineal, exponencial, logístico, Gompertz). Cada uno se escribe como derivada `dN/dt` y se integra por Euler cada día; así `r` y `K` pueden cambiar con el estrés. El logístico es `rN(1−N/K)`. A la escala visual: `g=(N−N₀)/(K_eff−N₀)` controla altura del tallo (`altura_max·g^0.8`), número de hojas (2–6), tamaño de hojas y apertura de la flor.

**6. Índices de estrés.** Para cada factor, `100·(|x−óptimo|/rango)^1.4` (0–100). El global = 65 % promedio ponderado + 35 % del peor factor. Afecta al crecimiento con `r_eff = r·(1−0.9·S)` y `K_eff = K·(1−0.45·S)`; además cambia el color de hojas y tépalos.

**7. Motor de decisiones.** Reglas con umbral evaluadas cada día y ordenadas por severidad. Ejemplo: *calor* — si la temperatura supera 21 °C en crecimiento → "Reducir temperatura" (pérdida de vigor floral). Otro: agua > 82 % → "Reducir riego / mejorar drenaje" (riesgo de pudrición del bulbo).

**8. Comparación A/B.** Dos escenarios corren en paralelo con el mismo motor (plantas A y B en la escena y curvas en la misma gráfica). Se proyecta cada uno a fin de ciclo y se calcula un puntaje: 50 % biomasa final, 30 % rapidez a floración, 20 % bajo estrés, con penalización por aborto floral. Gana el mayor puntaje (empate si difieren menos de 0.01).

**9. Gráficas.** Chart.js en vivo: biomasa A/B con su proyección y estrés global (dónde se frena el crecimiento y cuándo conviene intervenir). En Reportes: barras de eficiencia (g/día) por especie para elegir especie. Exportación: CSV (reporte y serie por simulación) y PDF (impresión del reporte).

**10. Seguridad.** Sentencias preparadas con PDO (`EMULATE_PREPARES=false`), validación de tipos y rangos en servidor, listas blancas (modelo, severidad, ganador), `LIMIT` forzado a entero, transacciones, errores genéricos al cliente (el detalle va al log), CORS con lista blanca y escape HTML (`esc()`) al pintar datos de la BD. Pendiente para producción: autenticación y límite de peticiones.

**11. Parte más difícil.** *(Redáctala con tu experiencia.)* Candidatos reales de este proyecto: que el ciclo del tulipán (frío → templado) funcionara con el modelo logístico sin perder el sentido biológico; y mantener el motor determinista para poder reproducir una simulación guardada.

**12. Decisiones que apoya.** Cuándo regar o drenar, cuándo subir la temperatura tras el frío, qué luz y fertilización conviene, qué especie rinde más por día y cuántos días faltan para cosechar la flor o el bulbo.

**13. Para un sistema real.** Sensores IoT de humedad, temperatura y luz que alimenten las mediciones; controlador PID de riego y de temperatura; calibrar r y K con datos experimentales (ajuste de curvas); secuenciación/expresión génica (p. ej. SOC1, TFL1) para parametrizar cultivares; machine learning para predecir floración; módulos de plagas y enfermedades.

**14. Escalar.** Índices compuestos (`simulacion_id, dia`) y particionar `mediciones`; caché (Redis) para `/especies` y `/reportes`; tablas de resumen precalculadas; guardado por lotes o cola de trabajos; PHP-FPM con balanceador; migraciones versionadas (Phinx/Flyway); réplicas de lectura; autenticación por usuario.
