# Práctica 05 · Laboratorio Bioinformático de Cultivo (Tulipán 🌷)

Continuación de la **Práctica 04** (planta procedural 3D con jerarquías, viento GLSL y raycasting). Ahora la planta no crece por un contador fijo: su forma se dibuja a partir de un **motor matemático** alimentado por un **catálogo en MySQL**, y cada corrida se **guarda** para analizarla, compararla (A/B) y tomar decisiones.

La planta es el **tulipán (*Tulipa*, Liliaceae)** y la biología sale de la investigación del equipo (ciclo cálido → frío → templado, dormancia del bulbo, 4–9 °C de frío durante 8–15 semanas, vigor floral que se pierde sobre ~21 °C, luz abundante, drenaje y riesgo de pudrición del bulbo).

## Arquitectura (3 capas)

| Capa | Archivos | Qué hace |
|---|---|---|
| Presentación | `index.html`, `style.css`, `js/escena.js` | Three.js, paneles, línea de tiempo, Chart.js, modales |
| Lógica | `main.js`, `js/modelos.js`, `js/api.js` | Motor de simulación, estrés, decisiones, A/B, cliente `fetch` |
| Datos | `api/index.php`, `database/*.sql` | FlightPHP + PDO + MySQL |

```
practica-05-bioinformatica/
├── index.html · style.css · main.js
├── js/        modelos.js (motor puro) · escena.js (Three.js) · api.js (fetch)
├── shaders/   wind.vert · wind.frag          (de la Práctica 04)
├── data/      especies.json                  (respaldo si la API no responde)
├── api/       index.php · config.php · composer.json · .htaccess
└── database/  schema.sql · seed.sql · generar_seed.mjs
```

## Instalación (XAMPP / Laragon / Apache + MySQL 8)

1. Copia la carpeta a `htdocs` (o `www`) → `http://localhost/practica-05-bioinformatica/`.
2. Importa en MySQL Workbench / phpMyAdmin: `database/schema.sql` y luego `database/seed.sql` (crea `practica05_bioinformatica`, 6 especies, 15 simulaciones con mediciones, decisiones y 4 comparaciones A/B).
3. En `api/` ejecuta `composer install` (instala `flightphp/core`). Ajusta usuario/clave en `api/config.php` (o con variables `P05_DB_USER`, `P05_DB_PASS`).
4. Activa `mod_rewrite` y `AllowOverride All` para que `api/.htaccess` envíe todas las rutas a `index.php`.
5. Prueba la API antes del front: `curl http://localhost/practica-05-bioinformatica/api/health` y `.../api/especies`.
6. Abre la página. La insignia **MySQL conectado** confirma el enlace. Si dice *Modo local*, la app sigue funcionando pero no puede guardar.

Si sirves el front con otro puerto (Live Server), define `window.P05_API = 'http://localhost/practica-05-bioinformatica/api'` o usa `?api=…`, y agrega el origen en `cors_origins` de `config.php`.

## Rutas de la API

| Método y ruta | Propósito |
|---|---|
| `GET /api/health` | Comprueba la conexión a MySQL |
| `GET /api/especies` · `/especies/{id}` | Catálogo de especies |
| `GET /api/simulaciones` · `/simulaciones/{id}` | Historial · detalle con mediciones y decisiones |
| `POST /api/simulaciones` | Guarda corrida + mediciones + decisiones (transacción) |
| `DELETE /api/simulaciones/{id}` | Borra (cascada a mediciones y decisiones) |
| `GET /api/simulaciones/{id}/csv` | Exporta la serie temporal |
| `POST /api/comparaciones` · `GET /api/comparaciones` | Resultados A/B |
| `GET /api/reportes` | Top 5 por biomasa, eficiencia por especie, tiempo a madurez |

## Motor bioinformático (`js/modelos.js`)

- **Modelos** (integrados por Euler con 4 subpasos/día, para que el estrés cambie r y K en vivo): lineal `dN/dt=r·K/4`, exponencial `dN/dt=rN`, logístico `dN/dt=rN(1−N/K)`, Gompertz `dN/dt=rN·ln(K/N)`.
- **Fases:** Dormancia/Frío → Emergencia → Vegetativo → Elongación y botón → Floración → Senescencia. El frío se acumula solo entre `frio_tmin` y `frio_tmax` (barra ❄️); si el frío es insuficiente, el tallo sale corto y crece más lento (`frioEmer`).
- **Estrés (0–100)** hídrico, térmico, lumínico y nutricional por desviación del óptimo de la especie; el global combina promedio ponderado y el peor factor. Reduce `r` y `K`.
- **Salud del bulbo:** agua > 82 % la deteriora (riesgo de pudrición).
- **Predicción de cosecha:** se proyecta el ciclo con las condiciones actuales → *días a floración*.
- **Decisiones:** reglas con umbral (riego, calor, frío, luz, nutrientes, pudrición…).
- **A/B:** puntaje = 50 % biomasa final + 30 % rapidez a floración + 20 % bajo estrés (−0.3 si hay aborto floral).

## Uso

Elige especie y modelo, mueve los parámetros (el **termostato por fase** cambia la temperatura solo; desactívalo para probar escenarios sin frío), activa **A/B**, haz clic en hojas/tallo/flor/bulbo (activa *Corte de maceta*), revisa días en la **línea de tiempo** y guarda en MySQL. **📷 Captura PNG** descarga `captura.png`.

Para regenerar el seed con el mismo motor: `node database/generar_seed.mjs`.

> Los valores del catálogo (óptimos, r, K, días de frío) son parámetros **de simulación** calibrados con los rangos de la investigación; no son constantes biológicas universales (varían por especie y cultivar).
