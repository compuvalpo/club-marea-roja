import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js';
import { h, badge, mount, spinner, table, tabs, toast, toastError } from '../ui/dom.js';
import { field } from '../ui/form.js';
import { confirmDialog, formModal } from '../ui/modal.js';
import { card, kpi, pageHeader } from '../ui/layout.js';
import { LABELS, currentPeriod, formatCLP, formatDate, formatPeriod, isoDate } from '../ui/format.js';
import { del, get, patch, post } from '../api/client.js';
import { toCsv, download } from '../ui/csv.js';
import { icon } from '../ui/icons.js';

Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend);

const TABS = [
  { id: 'summary', label: 'Resumen' },
  { id: 'charges', label: 'Mensualidades' },
  { id: 'delinquents', label: 'Morosos' },
  { id: 'transactions', label: 'Ingresos y egresos' },
  { id: 'fixed', label: 'Gastos fijos' },
];

/** Módulo de finanzas (Admin). */
export function financeView({ query }) {
  const panel = h('div.tab-panel', { role: 'tabpanel' });
  const show = async (id) => {
    mount(panel, spinner());
    try {
      const views = {
        summary: summaryTab,
        charges: chargesTab,
        delinquents: delinquentsTab,
        transactions: transactionsTab,
        fixed: fixedTab,
      };
      mount(panel, await views[id]());
    } catch (error) {
      toastError(error);
    }
  };
  const initial = TABS.some((t) => t.id === query.get('tab')) ? query.get('tab') : 'summary';

  return h(
    'div',
    null,
    pageHeader('Finanzas', 'Mensualidades, pagos, morosidad, ingresos, egresos y gastos fijos.'),
    tabs(TABS, show, initial),
    panel,
  );
}

const periodInput = (value, onChange) =>
  h('input.inline-select', { type: 'month', value, 'aria-label': 'Período', onChange: (e) => onChange(e.target.value) });

// ------------------------------------------------------------------ Resumen

async function summaryTab() {
  const year = new Date().getFullYear();
  let from = `${year}-01-01`;
  let to = isoDate();
  const body = h('div');

  const load = async () => {
    const s = await get('/finance/summary', { from, to });
    const canvas = h('canvas', { role: 'img', 'aria-label': 'Ingresos y egresos por mes' });
    mount(
      body,
      h(
        'div.kpis',
        { 'data-testid': 'finance-kpis' },
        kpi('Ingresos', formatCLP(s.income), 'success', undefined, 'up'),
        kpi('Egresos', formatCLP(s.expense), 'danger', undefined, 'down'),
        kpi('Balance', formatCLP(s.balance), s.balance >= 0 ? 'success' : 'danger', undefined, 'balance'),
      ),
      h(
        'div.grid-2',
        null,
        card('Evolución mensual', h('div.chart-box', null, canvas)),
        card(
          'Por categoría',
          table(
            [
              { label: 'Tipo', render: (c) => badge(LABELS.txType[c.type], c.type === 'income' ? 'success' : 'danger') },
              { label: 'Categoría', render: (c) => c.category },
              { label: 'Total', render: (c) => formatCLP(c.total), class: 'num' },
            ],
            s.by_category,
          ),
        ),
      ),
    );
    setTimeout(
      () =>
        new Chart(canvas, {
          type: 'bar',
          data: {
            labels: s.by_month.map((m) => formatPeriod(m.month)),
            datasets: [
              { label: 'Ingresos', data: s.by_month.map((m) => m.income), backgroundColor: '#16a34a' },
              { label: 'Egresos', data: s.by_month.map((m) => m.expense), backgroundColor: '#e11d48' },
            ],
          },
          options: { responsive: true, scales: { y: { ticks: { callback: (v) => formatCLP(v) } } } },
        }),
      0,
    );
  };
  await load();

  return h(
    'div',
    null,
    h(
      'div.toolbar',
      null,
      h('label', null, 'Desde ', h('input', { type: 'date', value: from, onChange: (e) => ((from = e.target.value), load()) })),
      h('label', null, 'Hasta ', h('input', { type: 'date', value: to, onChange: (e) => ((to = e.target.value), load()) })),
    ),
    body,
  );
}

// ----------------------------------------------------------- Mensualidades

function paymentModal(charge, onDone) {
  formModal(
    `Registrar pago · ${charge.athlete_name} (${formatPeriod(charge.period)})`,
    [
      h(
        'p.muted',
        null,
        `Monto: ${formatCLP(charge.amount)} · Pagado: ${formatCLP(charge.paid_amount)} · Saldo: ${formatCLP(charge.balance)}`,
      ),
      field({
        label: 'Monto a pagar (CLP)',
        name: 'amount',
        type: 'number',
        min: 1,
        max: charge.balance,
        value: charge.balance,
        required: true,
      }),
      field({ label: 'Fecha de pago', name: 'paid_at', type: 'date', value: isoDate(), required: true }),
      field({
        label: 'Medio de pago',
        name: 'method',
        type: 'select',
        required: true,
        value: 'transfer',
        options: Object.entries(LABELS.method).map(([value, label]) => ({ value, label })),
      }),
      field({ label: 'N° comprobante / referencia', name: 'reference' }),
    ],
    async (data) => {
      await post('/finance/payments', { ...data, charge_id: charge.id });
      toast('Pago registrado.');
      await onDone();
    },
    { submitLabel: 'Registrar pago' },
  );
}

const chargeStatus = (c) =>
  c.status === 'paid'
    ? badge('Pagado', 'success')
    : c.is_overdue
      ? badge(`Atrasado ${c.days_late} d`, 'danger')
      : badge('Pendiente', 'warning');

async function chargesTab() {
  let period = currentPeriod();
  let status = '';
  const box = h('div');

  const load = async () => {
    const charges = await get('/finance/charges', { period, status });
    const total = charges.reduce((s, c) => s + c.amount, 0);
    const paid = charges.reduce((s, c) => s + c.paid_amount, 0);
    mount(
      box,
      h(
        'div.kpis.small',
        null,
        kpi('Emitido', formatCLP(total), '', undefined, 'receipt'),
        kpi('Recaudado', formatCLP(paid), 'success', undefined, 'money'),
        kpi('Por cobrar', formatCLP(total - paid), 'warning', undefined, 'timer'),
      ),
      table(
        [
          { label: 'Alumno', render: (c) => c.athlete_name },
          { label: 'Apoderado', render: (c) => c.guardian_name ?? '—' },
          { label: 'Vence', render: (c) => formatDate(c.due_date) },
          { label: 'Monto', render: (c) => formatCLP(c.amount), class: 'num' },
          { label: 'Saldo', render: (c) => formatCLP(c.balance), class: 'num' },
          { label: 'Estado', render: chargeStatus },
          {
            label: '',
            class: 'actions-cell',
            render: (c) =>
              c.status === 'paid'
                ? null
                : h(
                    'button.btn.sm.primary',
                    { type: 'button', 'data-testid': `pay-${c.id}`, onClick: () => paymentModal(c, load) },
                    'Registrar pago',
                  ),
          },
        ],
        charges,
        { empty: 'No hay mensualidades emitidas para este período. Use «Emitir mensualidades».', testId: 'charges-table' },
      ),
    );
  };

  const emit = async () => {
    try {
      const r = await post('/finance/charges/generate', { period });
      toast(`Mensualidades emitidas: ${r.created} nuevas, ${r.skipped} ya existían.`);
      await load();
    } catch (error) {
      toastError(error);
    }
  };

  await load();

  return h(
    'div',
    null,
    h(
      'div.toolbar',
      null,
      periodInput(period, (v) => ((period = v), load())),
      h(
        'select.inline-select',
        { 'aria-label': 'Estado', onChange: (e) => ((status = e.target.value), load()) },
        h('option', { value: '' }, 'Todos'),
        h('option', { value: 'pending' }, 'Pendientes'),
        h('option', { value: 'paid' }, 'Pagados'),
      ),
      h('button.btn.primary', { type: 'button', 'data-testid': 'emit-charges', onClick: emit }, 'Emitir mensualidades del período'),
    ),
    box,
  );
}

// ------------------------------------------------------------------ Morosos

async function delinquentsTab() {
  let period = '';
  const box = h('div');
  let rows = [];

  const load = async () => {
    rows = await get('/finance/delinquents', { period });
    const total = rows.reduce((s, c) => s + c.balance, 0);
    mount(
      box,
      h(
        'div.kpis.small',
        null,
        kpi(period ? 'Impagos del mes' : 'Cargos vencidos', rows.length, rows.length ? 'danger' : 'success', 'delinquents-count', 'alert'),
        kpi('Deuda total', formatCLP(total), 'warning', undefined, 'wallet'),
      ),
      table(
        [
          { label: 'Alumno', render: (c) => c.athlete_name },
          { label: 'Mes', render: (c) => formatPeriod(c.period) },
          { label: 'Apoderado', render: (c) => c.guardian_name ?? '—' },
          {
            label: 'Teléfono',
            render: (c) => (c.guardian_phone ? h('a', { href: `tel:${c.guardian_phone.replace(/\s/g, '')}` }, c.guardian_phone) : '—'),
          },
          { label: 'Saldo', render: (c) => formatCLP(c.balance), class: 'num' },
          { label: 'Atraso', render: (c) => (c.days_late ? badge(`${c.days_late} días`, 'danger') : badge('Por vencer', 'warning')) },
          {
            label: '',
            class: 'actions-cell',
            render: (c) => h('button.btn.sm.primary', { type: 'button', onClick: () => paymentModal(c, load) }, 'Registrar pago'),
          },
        ],
        rows,
        { empty: '¡Sin morosos! Todos los pagos están al día.', testId: 'delinquents-table' },
      ),
    );
  };
  await load();

  const exportCsv = () =>
    download(
      `morosos-${period || 'vencidos'}.csv`,
      toCsv(rows, [
        ['Alumno', (c) => c.athlete_name],
        ['Mes', (c) => c.period],
        ['Apoderado', (c) => c.guardian_name ?? ''],
        ['Teléfono', (c) => c.guardian_phone ?? ''],
        ['Saldo', (c) => c.balance],
        ['Días de atraso', (c) => c.days_late],
      ]),
    );

  return h(
    'div',
    null,
    h(
      'div.toolbar',
      null,
      h(
        'select.inline-select',
        { 'aria-label': 'Vista', onChange: (e) => ((period = e.target.value === 'month' ? currentPeriod() : ''), load()) },
        h('option', { value: '' }, 'Todos los cargos vencidos'),
        h('option', { value: 'month' }, 'Quiénes no han pagado este mes'),
      ),
      h('button.btn.ghost', { type: 'button', onClick: exportCsv }, icon('download', { size: 16 }), 'Exportar CSV'),
    ),
    box,
  );
}

// --------------------------------------------------------- Movimientos

async function transactionsTab() {
  const now = new Date();
  let from = isoDate(new Date(now.getFullYear(), now.getMonth(), 1));
  let to = isoDate(new Date(now.getFullYear(), now.getMonth() + 1, 0));
  let type = '';
  const box = h('div');

  const load = async () => {
    const items = await get('/finance/transactions', { from, to, type });
    mount(
      box,
      table(
        [
          { label: 'Fecha', render: (t) => formatDate(t.occurred_on) },
          { label: 'Tipo', render: (t) => badge(LABELS.txType[t.type], t.type === 'income' ? 'success' : 'danger') },
          { label: 'Categoría', render: (t) => t.category },
          { label: 'Descripción', render: (t) => t.description ?? '' },
          { label: 'Monto', render: (t) => (t.type === 'expense' ? `−${formatCLP(t.amount)}` : formatCLP(t.amount)), class: 'num' },
          {
            label: '',
            class: 'actions-cell',
            render: (t) =>
              t.payment_id
                ? h('small.muted', null, 'Mensualidad')
                : h(
                    'button.btn.sm.danger-outline',
                    {
                      type: 'button',
                      onClick: async () => {
                        if (!(await confirmDialog('¿Eliminar este movimiento?', { danger: true, confirmLabel: 'Eliminar' }))) return;
                        await del(`/finance/transactions/${t.id}`).catch(toastError);
                        await load();
                      },
                    },
                    'Eliminar',
                  ),
          },
        ],
        items,
        { empty: 'Sin movimientos en el rango.', testId: 'transactions-table' },
      ),
    );
  };

  const add = (txType) =>
    formModal(
      txType === 'income' ? 'Registrar ingreso' : 'Registrar egreso',
      [
        field({
          label: 'Categoría',
          name: 'category',
          required: true,
          placeholder: txType === 'income' ? 'Auspicios, Rifas…' : 'Equipamiento, Transporte…',
        }),
        field({ label: 'Monto (CLP)', name: 'amount', type: 'number', min: 1, required: true }),
        field({ label: 'Fecha', name: 'occurred_on', type: 'date', value: isoDate(), required: true }),
        field({ label: 'Descripción', name: 'description' }),
      ],
      async (data) => {
        await post('/finance/transactions', { ...data, type: txType });
        toast('Movimiento registrado.');
        await load();
      },
    );

  await load();

  return h(
    'div',
    null,
    h(
      'div.toolbar',
      null,
      h('label', null, 'Desde ', h('input', { type: 'date', value: from, onChange: (e) => ((from = e.target.value), load()) })),
      h('label', null, 'Hasta ', h('input', { type: 'date', value: to, onChange: (e) => ((to = e.target.value), load()) })),
      h(
        'select.inline-select',
        { 'aria-label': 'Tipo', onChange: (e) => ((type = e.target.value), load()) },
        h('option', { value: '' }, 'Ingresos y egresos'),
        h('option', { value: 'income' }, 'Sólo ingresos'),
        h('option', { value: 'expense' }, 'Sólo egresos'),
      ),
      h('button.btn.success', { type: 'button', onClick: () => add('income') }, icon('plus', { size: 16 }), 'Ingreso'),
      h(
        'button.btn.danger',
        { type: 'button', 'data-testid': 'add-expense', onClick: () => add('expense') },
        icon('plus', { size: 16 }),
        'Egreso',
      ),
    ),
    box,
  );
}

// ------------------------------------------------------------ Gastos fijos

async function fixedTab() {
  const box = h('div');
  const fields = (f = {}) => [
    field({ label: 'Nombre', name: 'name', required: true, value: f.name }),
    field({ label: 'Categoría', name: 'category', required: true, value: f.category }),
    field({ label: 'Monto mensual (CLP)', name: 'amount', type: 'number', min: 1, required: true, value: f.amount }),
    field({ label: 'Día de pago', name: 'day_of_month', type: 'number', min: 1, max: 31, required: true, value: f.day_of_month ?? 1 }),
    field({ label: 'Activo', name: 'active', type: 'checkbox', value: f.active ?? true }),
  ];

  const load = async () => {
    const items = await get('/finance/fixed-expenses');
    const monthly = items.filter((f) => f.active).reduce((s, f) => s + f.amount, 0);
    mount(
      box,
      h('div.kpis.small', null, kpi('Total mensual comprometido', formatCLP(monthly), 'danger', undefined, 'piggy')),
      table(
        [
          { label: 'Nombre', render: (f) => f.name },
          { label: 'Categoría', render: (f) => f.category },
          { label: 'Día', render: (f) => f.day_of_month, class: 'num' },
          { label: 'Monto', render: (f) => formatCLP(f.amount), class: 'num' },
          { label: 'Estado', render: (f) => (f.active ? badge('Activo', 'success') : badge('Inactivo', 'neutral')) },
          {
            label: '',
            class: 'actions-cell',
            render: (f) =>
              h(
                'div.row-actions',
                null,
                h(
                  'button.btn.sm.ghost',
                  {
                    type: 'button',
                    onClick: () =>
                      formModal(
                        'Editar gasto fijo',
                        fields(f),
                        async (d) => (await patch(`/finance/fixed-expenses/${f.id}`, d), toast('Actualizado.'), load()),
                      ),
                  },
                  'Editar',
                ),
                h(
                  'button.btn.sm.danger-outline',
                  {
                    type: 'button',
                    onClick: async () => {
                      if (!(await confirmDialog(`¿Eliminar ${f.name}?`, { danger: true, confirmLabel: 'Eliminar' }))) return;
                      await del(`/finance/fixed-expenses/${f.id}`).catch(toastError);
                      await load();
                    },
                  },
                  'Eliminar',
                ),
              ),
          },
        ],
        items,
      ),
    );
  };

  const apply = () =>
    formModal(
      'Contabilizar gastos fijos del mes',
      [
        field({
          label: 'Período',
          name: 'period',
          type: 'month',
          value: currentPeriod(),
          required: true,
          hint: 'Los gastos ya contabilizados en el período se omiten.',
        }),
      ],
      async (data) => {
        const r = await post('/finance/fixed-expenses/apply', data);
        toast(`Gastos registrados: ${r.created} (${r.skipped} ya existían).`);
      },
      { submitLabel: 'Contabilizar' },
    );

  await load();

  return h(
    'div',
    null,
    h(
      'div.toolbar',
      null,
      h(
        'button.btn.primary',
        {
          type: 'button',
          onClick: () =>
            formModal(
              'Nuevo gasto fijo',
              fields(),
              async (d) => (await post('/finance/fixed-expenses', d), toast('Gasto fijo creado.'), load()),
            ),
        },
        icon('plus', { size: 16 }),
        'Nuevo gasto fijo',
      ),
      h('button.btn.ghost', { type: 'button', onClick: apply }, 'Contabilizar período'),
    ),
    box,
  );
}
