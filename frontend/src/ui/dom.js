/**
 * Helpers de DOM seguros: el contenido dinámico se inserta SIEMPRE como texto (textContent),
 * nunca con innerHTML, para prevenir XSS.
 */

/**
 * Crea un elemento.
 * @param {string} tag  Etiqueta con clases opcionales: "button.btn.primary"
 * @param {Record<string, any>|null} [attrs]
 * @param {...(Node|string|number|null|false|undefined|Array)} children
 * @returns {HTMLElement}
 */
export function h(tag, attrs = null, ...children) {
  const [name, ...classes] = tag.split('.');
  const el = document.createElement(name || 'div');
  if (classes.length) el.classList.add(...classes);

  Object.entries(attrs ?? {}).forEach(([key, value]) => {
    if (value === null || value === undefined || value === false) return;
    if (key.startsWith('on') && typeof value === 'function') {
      el.addEventListener(key.slice(2).toLowerCase(), value);
    } else if (key === 'class') {
      el.classList.add(...String(value).split(' ').filter(Boolean));
    } else if (key === 'dataset') {
      Object.assign(el.dataset, value);
    } else if (key === 'style' && typeof value === 'object') {
      Object.assign(el.style, value);
    } else if (value === true) {
      el.setAttribute(key, '');
    } else if (key in el && key !== 'list' && key !== 'form') {
      el[key] = value;
    } else {
      el.setAttribute(key, String(value));
    }
  });

  append(el, children);
  return el;
}

function append(parent, children) {
  children.flat(Infinity).forEach((child) => {
    if (child === null || child === undefined || child === false) return;
    parent.append(child instanceof Node ? child : document.createTextNode(String(child)));
  });
}

export function mount(target, ...children) {
  target.replaceChildren();
  append(target, children);
  return target;
}

export function toast(message, type = 'success') {
  const container = document.getElementById('toasts');
  if (!container) return;
  const item = h(`div.toast.${type}`, { role: type === 'error' ? 'alert' : 'status' }, message);
  container.append(item);
  setTimeout(() => item.remove(), 4000);
}

/** Muestra un error de API de forma amigable. */
export function toastError(error) {
  toast(error?.message ?? 'Ocurrió un error inesperado.', 'error');
}

export function badge(text, variant = 'neutral') {
  return h(`span.badge.${variant}`, null, text);
}

export function emptyState(message) {
  return h('p.empty', null, message);
}

export function spinner() {
  return h('div.spinner', { role: 'progressbar', 'aria-label': 'Cargando' });
}

/**
 * Tabla accesible a partir de columnas declarativas.
 * @param {{label: string, render: (row: any) => any, class?: string}[]} columns
 * @param {any[]} rows
 */
export function table(columns, rows, { empty = 'Sin registros.', testId } = {}) {
  if (!rows.length) return emptyState(empty);
  return h(
    'div.table-wrap',
    null,
    h(
      'table',
      { 'data-testid': testId },
      h(
        'thead',
        null,
        h(
          'tr',
          null,
          columns.map((c) => h('th', { scope: 'col', class: c.class }, c.label)),
        ),
      ),
      h(
        'tbody',
        null,
        rows.map((row) =>
          h(
            'tr',
            null,
            columns.map((c) => h('td', { class: c.class, 'data-label': c.label }, c.render(row))),
          ),
        ),
      ),
    ),
  );
}

/** Pestañas simples. */
export function tabs(items, onChange, active = items[0].id) {
  const bar = h('div.tabs', { role: 'tablist' });
  const select = (id) => {
    bar.querySelectorAll('button').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.id === id)));
    onChange(id);
  };
  items.forEach((item) =>
    bar.append(
      h(
        'button.tab',
        {
          type: 'button',
          role: 'tab',
          dataset: { id: item.id },
          'aria-selected': String(item.id === active),
          onClick: () => select(item.id),
        },
        item.label,
      ),
    ),
  );
  queueMicrotask(() => select(active));
  return bar;
}
