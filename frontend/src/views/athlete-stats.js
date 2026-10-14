import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js';
import { h, badge, emptyState, table } from '../ui/dom.js';
import { card, kpi, pageHeader } from '../ui/layout.js';
import { LABELS, formatDate } from '../ui/format.js';
import { get } from '../api/client.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend);

/**
 * Estadísticas de un alumno: asistencia, evolución de marcas y lesiones/incidencias.
 * En /my-stats (rol alumno) se usa la ficha propia.
 */
export async function athleteStatsView({ params }) {
  let id = params.id;
  if (!id) {
    const mine = await get('/athletes');
    if (!mine.length) return card('Mis estadísticas', emptyState('Su usuario no está vinculado a una ficha de alumno.'));
    id = mine[0].id;
  }
  const stats = await get(`/athletes/${id}/stats`);
  const { athlete, attendance, metrics, incidents } = stats;

  const charts = metrics.map((m) => {
    const canvas = h('canvas', { role: 'img', 'aria-label': `Evolución ${m.metric}` });
    setTimeout(() => {
      new Chart(canvas, {
        type: 'line',
        data: {
          labels: m.series.map((s) => formatDate(s.date, { day: '2-digit', month: 'short' })),
          datasets: [
            {
              label: `${m.metric} (${m.unit})`,
              data: m.series.map((s) => s.value),
              borderColor: '#be123c',
              backgroundColor: '#be123c26',
              tension: 0.3,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { reverse: false, title: { display: true, text: m.unit } } },
        },
      });
    }, 0);
    return card(
      m.metric,
      h(
        'div.kpis.small',
        null,
        kpi('Mejor marca', `${m.best} ${m.unit}`, 'success', undefined, 'trophy'),
        kpi('Última', `${m.last} ${m.unit}`, '', undefined, 'timer'),
        kpi('Registros', m.count, '', undefined, 'chart'),
      ),
      h('div.chart-box', null, canvas),
    );
  });

  return h(
    'div',
    null,
    pageHeader(`Estadísticas de ${athlete.full_name}`, `${athlete.group_name ?? 'Sin grupo'}`),
    h(
      'div.kpis',
      { 'data-testid': 'stats-kpis' },
      kpi(
        'Asistencia',
        attendance.rate === null ? '—' : `${attendance.rate}%`,
        attendance.rate >= 75 ? 'success' : 'warning',
        undefined,
        'target',
      ),
      kpi('Prácticas asistidas', `${attendance.attended} / ${attendance.total}`, '', undefined, 'check'),
      kpi('Confirmaciones', attendance.confirmed, '', undefined, 'calendar'),
      kpi('Cancelaciones', attendance.cancelled, '', undefined, 'close'),
    ),
    metrics.length ? h('div.grid-2', null, charts) : card('Marcas', emptyState('Aún no hay marcas registradas.')),
    card(
      'Lesiones e incidencias',
      table(
        [
          { label: 'Fecha', render: (i) => formatDate(i.created_at) },
          { label: 'Tipo', render: (i) => LABELS.incidentType[i.type] },
          { label: 'Gravedad', render: (i) => badge(LABELS.severity[i.severity], i.severity === 'high' ? 'danger' : 'neutral') },
          { label: 'Descripción', render: (i) => i.description },
        ],
        incidents,
        { empty: 'Sin incidencias registradas.' },
      ),
    ),
  );
}
