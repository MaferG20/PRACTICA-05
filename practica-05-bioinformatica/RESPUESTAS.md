
# Respuestas – Práctica 05

### 1. ¿Qué modelo ecológico o botánico representa tu simulador y por qué es relevante en bioinformática?

Mi simulador representa el ciclo de crecimiento de un tulipán. Toma en cuenta etapas como el periodo de frío, cuando la planta está en reposo, después su crecimiento, la floración y finalmente cuando la planta empieza a perder actividad. También toma en cuenta factores como la temperatura, la luz y el agua. Es importante para la bioinformática porque permite usar datos de una planta y convertirlos en información que una computadora puede analizar y comparar.

### 2. ¿Cómo diseñaste el esquema de la base de datos MySQL? Explica la función de cada tabla y las relaciones.

La base de datos está dividida en varias tablas para tener la información organizada. La tabla `especies` guarda los datos de las especies y sus características. `simulaciones` guarda cada simulación que se realiza. `mediciones` almacena los datos que se van obteniendo durante la simulación y `decisiones` guarda las recomendaciones que genera el sistema. También está `comparaciones_ab`, que sirve para guardar los resultados cuando se comparan dos simulaciones. Las tablas están relacionadas entre sí; por ejemplo, una especie puede tener varias simulaciones y una simulación puede tener muchas mediciones.

### 3. ¿Cómo implementaste la API REST con FlightPHP? Menciona al menos 3 rutas y su propósito.

Usé FlightPHP para crear la API y poder conectar el frontend con la base de datos. Algunas de las rutas que utilicé son `GET /especies`, que sirve para obtener las especies disponibles; `POST /simulaciones`, que guarda una nueva simulación; y `GET /reportes`, que obtiene los resultados y estadísticas de las simulaciones. También agregué rutas para consultar y eliminar simulaciones, hacer comparaciones y exportar información.

### 4. ¿Cómo conectaste Three.js con MySQL a través de PHP? Describe el flujo frontend → fetch → FlightPHP → PDO → MySQL → JSON → frontend.

La conexión funciona de la siguiente manera: primero, el frontend hace una petición usando `fetch()`. Esa petición llega a PHP, donde FlightPHP identifica qué información se está solicitando. Después, PDO se encarga de hacer la consulta a MySQL. Cuando MySQL devuelve los datos, PHP los convierte a JSON y los manda de regreso al frontend. Finalmente, el frontend recibe esos datos y los utiliza para mostrar la información y actualizar la simulación de Three.js. También dejé un archivo JSON como respaldo por si la API no está disponible.

### 5. ¿Qué modelo matemático de crecimiento usaste y cómo lo tradujiste a código y a la escala visual de la planta?

El simulador tiene cuatro modelos de crecimiento: lineal, exponencial, logístico y Gompertz. Cada uno calcula cómo va cambiando la biomasa de la planta con el paso de los días. El cálculo se hace día por día y después ese resultado se usa para cambiar visualmente la planta. Por ejemplo, conforme aumenta el crecimiento, también aumenta la altura del tallo, el tamaño de las hojas y la apertura de la flor.

### 6. ¿Cómo calculaste los índices de estrés y cómo afectan al crecimiento simulado?

El estrés se calcula comparando las condiciones actuales con las condiciones ideales para la planta. Se revisan factores como la temperatura, la luz, el agua y otros valores. Después se obtiene un porcentaje de estrés general. Mientras más alto sea este porcentaje, peor son las condiciones para la planta. Esto afecta directamente su crecimiento, haciendo que crezca más lento y que pueda alcanzar una biomasa menor.

### 7. ¿En qué consiste tu motor de decisiones? Da un ejemplo de regla, su umbral y la recomendación que emite.

El motor de decisiones revisa las condiciones de la planta todos los días y, dependiendo de ciertos límites, genera recomendaciones.

Por ejemplo, si la temperatura durante el crecimiento supera los 21 °C, el sistema recomienda **“Reducir temperatura”**, porque demasiado calor puede afectar el crecimiento y la floración. Otro ejemplo es cuando el agua supera el 82 %, donde recomienda **“Reducir riego / mejorar drenaje”** para evitar problemas por exceso de agua.

### 8. ¿Cómo implementaste la comparación A/B y qué métricas usaste para declarar un "ganador"?

La comparación A/B sirve para comparar dos escenarios diferentes y ver cuál funciona mejor. Los dos escenarios utilizan el mismo modelo para que la comparación sea justa. Para decidir cuál gana, se toma en cuenta principalmente la biomasa final, qué tan rápido llega a la floración y el nivel de estrés que tuvo la planta. La biomasa representa el 50 %, la rapidez de floración el 30 % y el estrés el 20 %. También se toma en cuenta si hubo aborto floral. El escenario con el mayor puntaje es el ganador.

### 9. ¿Qué gráficas generaste y para qué sirven en el análisis? (Chart.js, CSV, PDF).

Generé gráficas con Chart.js para ver cómo cambia la biomasa de las plantas A y B y cómo cambia el nivel de estrés durante la simulación. Estas gráficas ayudan a entender mejor el crecimiento y saber en qué momentos las condiciones afectan a la planta. También hice gráficas para comparar la eficiencia de las especies. Además, el sistema permite guardar los resultados en CSV y generar un PDF con el reporte.

### 10. ¿Cómo evitaste SQL Injection y qué otras medidas de seguridad aplicaste en la API?

Para evitar SQL Injection utilicé consultas preparadas con PDO, para que los datos que recibe el sistema no puedan modificar directamente las consultas SQL. También validé los datos que recibe el servidor y limité algunos valores para que solamente se puedan usar opciones permitidas. Además, utilicé transacciones para evitar problemas al guardar información, configuré CORS y también protegí los datos que se muestran en la página para evitar que se pueda insertar código HTML. Para una versión real todavía agregaría inicio de sesión y límites de peticiones.

### 11. ¿Qué parte del proyecto fue la más difícil y cómo la resolviste?

La parte que más se me complicó fue lograr que el crecimiento del tulipán se viera de una manera lógica. No quería que solamente aumentara de tamaño, sino que también pasara por diferentes etapas, como el frío, el crecimiento y la floración. Para resolverlo tuve que ajustar varias veces los valores del modelo y revisar los resultados de la simulación hasta que el comportamiento tuviera más sentido.

### 12. ¿Qué decisiones agronómicas o biológicas concretas puede apoyar tu simulador?

El simulador puede ayudar a saber cuándo conviene regar o reducir el riego, cuándo mejorar el drenaje y cuándo cambiar la temperatura. También permite comparar diferentes especies y ver cuál tiene mejores resultados. Además, puede dar una idea de cuánto tiempo falta para que la planta llegue a una etapa como la floración o la madurez.

### 13. Si tuvieras que llevar este proyecto a un sistema real de investigación, ¿qué agregarías?

Si el proyecto se llevara a un sistema real, agregaría sensores que midan automáticamente la temperatura, humedad y luz de las plantas. También agregaría un sistema que pudiera controlar automáticamente el riego y la temperatura. Otra mejora sería usar datos reales de experimentos para hacer que los cálculos del simulador fueran más exactos. También sería interesante agregar información genética de diferentes tipos de tulipanes y utilizar machine learning para intentar predecir mejor cuándo va a florecer la planta.

### 14. ¿Cómo escalarías la aplicación para manejar cientos de simulaciones y usuarios concurrentes?

Para manejar muchas simulaciones y usuarios al mismo tiempo, primero mejoraría la base de datos agregando índices para que las búsquedas fueran más rápidas. También usaría una memoria caché para no tener que consultar la base de datos cada vez que se solicita la misma información. Las simulaciones que tarden más podrían ponerse en una cola para que se procesen poco a poco. También se podrían usar varios servidores para repartir el trabajo y réplicas de la base de datos para manejar más consultas. Finalmente, agregaría un sistema de usuarios para controlar quién puede acceder a cada simulación.