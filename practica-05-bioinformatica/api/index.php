<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';   // composer install  (flightphp/core)

$config = require __DIR__ . '/config.php';

// ---------- CORS controlado (lista blanca) ----------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $config['cors_origins'], true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
header('X-Content-Type-Options: nosniff');

// ---------- Conexión PDO (sentencias preparadas reales, errores como excepciones) ----------
Flight::register('db', PDO::class, [
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['db']['host'], $config['db']['port'], $config['db']['name'], $config['db']['charset']),
    $config['db']['user'], $config['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],
]);

// ---------- Utilidades ----------
const MODELOS = ['lineal', 'exponencial', 'logistico', 'gompertz'];
const SEVERIDADES = ['critica', 'alta', 'media', 'info', 'ok'];

function fallo(int $code, string $msg): never { Flight::json(['ok' => false, 'error' => $msg], $code); exit; }
function cuerpo(): array {
    $d = json_decode(Flight::request()->getBody(), true);
    if (!is_array($d)) fallo(400, 'Cuerpo JSON inválido');
    return $d;
}
function num(array $a, string $k, float $min, float $max, ?float $def = null): float {
    $v = $a[$k] ?? $def;
    if ($v === null || !is_numeric($v)) fallo(422, "Campo '$k' numérico requerido");
    $v = (float)$v;
    if ($v < $min || $v > $max) fallo(422, "Campo '$k' fuera de rango ($min–$max)");
    return $v;
}
function texto(array $a, string $k, int $max, bool $req = true): ?string {
    $v = isset($a[$k]) ? trim((string)$a[$k]) : '';
    if ($v === '') { if ($req) fallo(422, "Campo '$k' requerido"); return null; }
    return mb_substr($v, 0, $max);
}
function entero(mixed $v, int $def, int $min, int $max): int { return max($min, min($max, is_numeric($v) ? (int)$v : $def)); }

// ---------- Manejo global de errores (sin filtrar detalles internos) ----------
Flight::map('error', function (Throwable $e) {
    error_log($e->getMessage());
    Flight::json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
});
Flight::map('notFound', fn() => Flight::json(['ok' => false, 'error' => 'Ruta no encontrada'], 404));

// ======================= RUTAS =======================
Flight::route('GET /health', function () {
    Flight::db()->query('SELECT 1');
    Flight::json(['ok' => true, 'db' => 'conectada', 'hora' => date('c')]);
});

// --- Catálogo de especies ---
Flight::route('GET /especies', function () {
    Flight::json(['ok' => true, 'data' => Flight::db()->query('SELECT * FROM especies ORDER BY id')->fetchAll()]);
});
Flight::route('GET /especies/@id:[0-9]+', function ($id) {
    $st = Flight::db()->prepare('SELECT * FROM especies WHERE id = ?'); $st->execute([(int)$id]);
    $r = $st->fetch(); if (!$r) fallo(404, 'Especie no encontrada');
    Flight::json(['ok' => true, 'data' => $r]);
});

// --- Simulaciones ---
Flight::route('GET /simulaciones', function () {
    $q = Flight::request()->query;
    $limite = entero($q['limite'] ?? 50, 50, 1, 200);
    $sql = 'SELECT s.*, e.nombre_cientifico, e.nombre_comun FROM simulaciones s JOIN especies e ON e.id = s.especie_id';
    $args = [];
    if (!empty($q['especie_id']) && is_numeric($q['especie_id'])) { $sql .= ' WHERE s.especie_id = ?'; $args[] = (int)$q['especie_id']; }
    $sql .= ' ORDER BY s.id DESC LIMIT ' . $limite;   // $limite ya es entero acotado
    $st = Flight::db()->prepare($sql); $st->execute($args);
    Flight::json(['ok' => true, 'data' => $st->fetchAll()]);
});
Flight::route('GET /simulaciones/@id:[0-9]+', function ($id) {
    $db = Flight::db();
    $st = $db->prepare('SELECT s.*, e.nombre_cientifico FROM simulaciones s JOIN especies e ON e.id = s.especie_id WHERE s.id = ?');
    $st->execute([(int)$id]); $sim = $st->fetch(); if (!$sim) fallo(404, 'Simulación no encontrada');
    $m = $db->prepare('SELECT * FROM mediciones WHERE simulacion_id = ? ORDER BY dia'); $m->execute([(int)$id]);
    $d = $db->prepare('SELECT * FROM decisiones WHERE simulacion_id = ? ORDER BY dia'); $d->execute([(int)$id]);
    Flight::json(['ok' => true, 'data' => $sim + ['mediciones' => $m->fetchAll(), 'decisiones' => $d->fetchAll()]]);
});
Flight::route('POST /simulaciones', function () {
    $b = cuerpo(); $db = Flight::db();
    $modelo = (string)($b['modelo'] ?? '');
    if (!in_array($modelo, MODELOS, true)) fallo(422, 'Modelo no válido');
    $esp = $db->prepare('SELECT id FROM especies WHERE id = ?'); $esp->execute([(int)($b['especie_id'] ?? 0)]);
    if (!$esp->fetch()) fallo(422, 'Especie inexistente');
    $meds = is_array($b['mediciones'] ?? null) ? array_slice($b['mediciones'], 0, 1500) : [];
    $decs = is_array($b['decisiones'] ?? null) ? array_slice($b['decisiones'], 0, 200) : [];

    $db->beginTransaction();
    try {
        $ins = $db->prepare('INSERT INTO simulaciones (especie_id,nombre,notas,modelo,luz,agua,temp,nutr,co2,r,k,termostato,escenario,biomasa_final,altura_final,dias_madurez,dias_totales,estres_medio,aborto)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([
            (int)$b['especie_id'], texto($b, 'nombre', 100), texto($b, 'notas', 1000, false), $modelo,
            num($b, 'luz', 0, 100), num($b, 'agua', 0, 100), num($b, 'temp', -10, 45), num($b, 'nutr', 0, 100), (int)num($b, 'co2', 200, 2000, 420),
            num($b, 'r', 0.001, 1), num($b, 'k', 0.1, 1000), empty($b['termostato']) ? 0 : 1, in_array($b['escenario'] ?? 'A', ['A', 'B'], true) ? $b['escenario'] : 'A',
            num($b, 'biomasa_final', 0, 100000, 0), num($b, 'altura_final', 0, 1000, 0),
            isset($b['dias_madurez']) && is_numeric($b['dias_madurez']) ? (int)$b['dias_madurez'] : null,
            (int)num($b, 'dias_totales', 0, 5000, 0), num($b, 'estres_medio', 0, 100, 0), empty($b['aborto']) ? 0 : 1,
        ]);
        $sid = (int)$db->lastInsertId();

        $im = $db->prepare('INSERT INTO mediciones (simulacion_id,dia,fase,biomasa,altura,hojas,bulbillos,icr,estres_hidrico,estres_termico,estres_luminico,estres_nutricional,estres_global,frio_acum,salud_bulbo,temp,agua,luz,nutr)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE biomasa = VALUES(biomasa)');
        foreach ($meds as $m) {
            if (!is_array($m)) continue;
            $im->execute([$sid, (int)num($m, 'dia', 0, 5000), (int)num($m, 'fase', 0, 6), num($m, 'biomasa', 0, 100000), num($m, 'altura', 0, 1000),
                (int)num($m, 'hojas', 0, 255, 0), (int)num($m, 'bulbillos', 0, 255, 0), num($m, 'icr', -10, 10, 0),
                num($m, 'estres_hidrico', 0, 100, 0), num($m, 'estres_termico', 0, 100, 0), num($m, 'estres_luminico', 0, 100, 0), num($m, 'estres_nutricional', 0, 100, 0), num($m, 'estres_global', 0, 100, 0),
                num($m, 'frio_acum', 0, 100, 0), num($m, 'salud_bulbo', 0, 100, 100), num($m, 'temp', -10, 45, 0), num($m, 'agua', 0, 100, 0), num($m, 'luz', 0, 100, 0), num($m, 'nutr', 0, 100, 0)]);
        }
        $id_ = $db->prepare('INSERT INTO decisiones (simulacion_id,dia,codigo,severidad,titulo,detalle,umbral,valor) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($decs as $d) {
            if (!is_array($d) || !in_array($d['severidad'] ?? '', SEVERIDADES, true)) continue;
            $id_->execute([$sid, (int)num($d, 'dia', 0, 5000, 0), texto($d, 'codigo', 30), $d['severidad'], texto($d, 'titulo', 120), texto($d, 'detalle', 255, false), texto($d, 'umbral', 60, false), isset($d['valor']) && is_numeric($d['valor']) ? (float)$d['valor'] : null]);
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
    Flight::json(['ok' => true, 'id' => $sid], 201);
});
Flight::route('DELETE /simulaciones/@id:[0-9]+', function ($id) {
    $st = Flight::db()->prepare('DELETE FROM simulaciones WHERE id = ?'); $st->execute([(int)$id]);
    if (!$st->rowCount()) fallo(404, 'Simulación no encontrada');
    Flight::json(['ok' => true]);
});
Flight::route('GET /simulaciones/@id:[0-9]+/csv', function ($id) {
    $st = Flight::db()->prepare('SELECT dia,fase,biomasa,altura,hojas,bulbillos,icr,estres_hidrico,estres_termico,estres_luminico,estres_nutricional,estres_global,frio_acum,salud_bulbo,temp,agua,luz,nutr FROM mediciones WHERE simulacion_id = ? ORDER BY dia');
    $st->execute([(int)$id]); $filas = $st->fetchAll();
    header('Content-Type: text/csv; charset=utf-8'); header("Content-Disposition: attachment; filename=simulacion_$id.csv");
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    if ($filas) { fputcsv($out, array_keys($filas[0])); foreach ($filas as $f) fputcsv($out, $f); }
    fclose($out);
});

// --- Comparaciones A/B ---
Flight::route('POST /comparaciones', function () {
    $b = cuerpo();
    if (!in_array($b['ganador'] ?? '', ['A', 'B', 'empate'], true)) fallo(422, 'Ganador no válido');
    $st = Flight::db()->prepare('INSERT INTO comparaciones_ab (sim_a_id,sim_b_id,ganador,puntaje_a,puntaje_b) VALUES (?,?,?,?,?)');
    try { $st->execute([(int)($b['sim_a_id'] ?? 0), (int)($b['sim_b_id'] ?? 0), $b['ganador'], num($b, 'puntaje_a', -10, 10), num($b, 'puntaje_b', -10, 10)]); }
    catch (PDOException $e) { fallo(422, 'Simulaciones A/B inexistentes'); }
    Flight::json(['ok' => true, 'id' => (int)Flight::db()->lastInsertId()], 201);
});
Flight::route('GET /comparaciones', function () {
    Flight::json(['ok' => true, 'data' => Flight::db()->query('SELECT c.*, a.nombre AS nombre_a, b.nombre AS nombre_b FROM comparaciones_ab c JOIN simulaciones a ON a.id=c.sim_a_id JOIN simulaciones b ON b.id=c.sim_b_id ORDER BY c.id DESC LIMIT 50')->fetchAll()]);
});

// --- Reportes ---
Flight::route('GET /reportes', function () {
    $db = Flight::db();
    $top = $db->query('SELECT s.id, s.nombre, e.nombre_cientifico, s.modelo, s.biomasa_final, s.dias_madurez, s.estres_medio FROM simulaciones s JOIN especies e ON e.id = s.especie_id ORDER BY s.biomasa_final DESC LIMIT 5')->fetchAll();
    // Eficiencia = biomasa final por día hasta la floración (g/día); solo corridas que llegaron a florecer
    $efi = $db->query('SELECT e.nombre_cientifico, COUNT(*) AS corridas, ROUND(AVG(s.biomasa_final),2) AS biomasa_prom, ROUND(AVG(s.dias_madurez),1) AS dias_madurez_prom,
                              ROUND(AVG(s.biomasa_final / s.dias_madurez),4) AS eficiencia_g_dia
                       FROM simulaciones s JOIN especies e ON e.id = s.especie_id WHERE s.dias_madurez IS NOT NULL AND s.dias_madurez > 0
                       GROUP BY e.id, e.nombre_cientifico ORDER BY eficiencia_g_dia DESC')->fetchAll();
    $madurez = $db->query('SELECT ROUND(AVG(dias_madurez),1) AS prom, MIN(dias_madurez) AS minimo, MAX(dias_madurez) AS maximo, COUNT(dias_madurez) AS n FROM simulaciones WHERE dias_madurez IS NOT NULL')->fetch();
    Flight::json(['ok' => true, 'top_biomasa' => $top, 'eficiencia_especies' => $efi, 'madurez' => $madurez]);
});

Flight::start();
