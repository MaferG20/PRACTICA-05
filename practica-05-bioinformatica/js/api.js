// CLIENTE DE LA API · frontend → fetch → FlightPHP → PDO → MySQL → JSON → frontend
// Si la API no responde, la app sigue funcionando en "modo local" con data/especies.json.
const BASE = (window.P05_API || new URLSearchParams(location.search).get('api') || './api').replace(/\/$/, '');
export const estado = { conectado: false };

async function llamar(ruta, opciones = {}) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), 6000);
  try {
    const res = await fetch(BASE + ruta, { ...opciones, signal: ctrl.signal, headers: { 'Content-Type': 'application/json', ...(opciones.headers || {}) } });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.error || `HTTP ${res.status}`);
    return json;
  } finally { clearTimeout(t); }
}

export async function cargarEspecies() {
  try {
    const r = await llamar('/especies');
    estado.conectado = true;
    return r.data.map(normalizar);
  } catch (err) {
    console.warn('API no disponible, usando modo local:', err.message);
    estado.conectado = false;
    const local = await fetch('./data/especies.json').then(r => r.json());
    return local.map(normalizar);
  }
}
// MySQL devuelve DECIMAL como texto: convertir a número
function normalizar(e) {
  const o = { ...e };
  for (const k of ['luz_opt','agua_opt','temp_opt','nutr_opt','r_defecto','k_defecto','n0','altura_max','frio_req_dias','frio_tmin','frio_tmax','temp_umbral_calor']) o[k] = Number(o[k]);
  o.id = Number(o.id);
  return o;
}
export const guardarSimulacion = body => llamar('/simulaciones', { method: 'POST', body: JSON.stringify(body) });
export const guardarComparacion = body => llamar('/comparaciones', { method: 'POST', body: JSON.stringify(body) });
export const listarSimulaciones = () => llamar('/simulaciones?limite=100').then(r => r.data);
export const obtenerSimulacion = id => llamar('/simulaciones/' + id).then(r => r.data);
export const borrarSimulacion = id => llamar('/simulaciones/' + id, { method: 'DELETE' });
export const obtenerReportes = () => llamar('/reportes');
export const urlCsv = id => `${BASE}/simulaciones/${id}/csv`;
