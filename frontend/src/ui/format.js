/** Formateadores y etiquetas en español (Chile). */

const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });

export const formatCLP = (amount) => clp.format(Number(amount ?? 0));

export function formatDate(value, options = { day: '2-digit', month: 'short', year: 'numeric' }) {
  if (!value) return '—';
  const date = new Date(String(value).length === 10 ? `${value}T12:00:00` : String(value).replace(' ', 'T'));
  return new Intl.DateTimeFormat('es-CL', options).format(date);
}

export const formatWeekday = (value) => formatDate(value, { weekday: 'long', day: 'numeric', month: 'long' });

export const formatDateTime = (value) =>
  formatDate(value, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

export function formatPeriod(period) {
  if (!period) return '—';
  return formatDate(`${period}-01`, { month: 'long', year: 'numeric' });
}

/** Fecha local en formato ISO (AAAA-MM-DD) sin desfases de zona horaria. */
export function isoDate(date = new Date()) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export const currentPeriod = (date = new Date()) => isoDate(date).slice(0, 7);

export const WEEKDAYS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

export const LABELS = {
  rsvp: { pending: 'Sin confirmar', confirmed: 'Confirmado', cancelled: 'Cancelado' },
  practiceStatus: { scheduled: 'Programada', done: 'Realizada', cancelled: 'Suspendida' },
  chargeStatus: { pending: 'Pendiente', paid: 'Pagado' },
  method: { cash: 'Efectivo', transfer: 'Transferencia', card: 'Tarjeta', other: 'Otro' },
  incidentType: { injury: 'Lesión', behavior: 'Conducta', equipment: 'Equipamiento', other: 'Otra' },
  severity: { low: 'Leve', medium: 'Moderada', high: 'Grave' },
  eventType: { competition: 'Competencia', meeting: 'Reunión', social: 'Actividad social', other: 'Otro' },
  txType: { income: 'Ingreso', expense: 'Egreso' },
};

export const RSVP_VARIANT = { pending: 'neutral', confirmed: 'success', cancelled: 'danger' };
