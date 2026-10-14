import { h } from './dom.js';

/**
 * Campo de formulario con etiqueta y contenedor de error.
 * @param {{label: string, name: string, type?: string, value?: any, required?: boolean,
 *          options?: {value: any, label: string}[], placeholder?: string, step?: string,
 *          min?: any, max?: any, hint?: string, rows?: number}} cfg
 */
export function field(cfg) {
  const id = `f-${cfg.name}-${Math.random().toString(36).slice(2, 7)}`;
  const common = { id, name: cfg.name, required: cfg.required ?? false, disabled: cfg.disabled ?? false, 'aria-describedby': `${id}-err` };
  let control;

  if (cfg.type === 'select') {
    control = h(
      'select',
      common,
      cfg.required ? null : h('option', { value: '' }, cfg.placeholder ?? '— Ninguno —'),
      (cfg.options ?? []).map((o) =>
        h('option', { value: String(o.value), selected: String(o.value) === String(cfg.value ?? '') }, o.label),
      ),
    );
  } else if (cfg.type === 'textarea') {
    control = h('textarea', { ...common, rows: cfg.rows ?? 3, placeholder: cfg.placeholder ?? '' });
    control.value = cfg.value ?? '';
  } else if (cfg.type === 'checkbox') {
    control = h('input', { ...common, type: 'checkbox', checked: Boolean(cfg.value) });
    return h('label.check', { for: id }, control, cfg.label);
  } else {
    control = h('input', {
      ...common,
      type: cfg.type ?? 'text',
      value: cfg.value ?? '',
      placeholder: cfg.placeholder ?? '',
      step: cfg.step,
      min: cfg.min,
      max: cfg.max,
      autocomplete: cfg.autocomplete ?? 'off',
    });
  }

  return h(
    'div.field',
    null,
    h('label', { for: id }, cfg.label, cfg.required ? h('span.req', { 'aria-hidden': 'true' }, ' *') : null),
    control,
    cfg.hint ? h('small.hint', null, cfg.hint) : null,
    h('small.error', { id: `${id}-err` }),
  );
}

/**
 * Lee un formulario a objeto. Convierte números (type=number), checkboxes y vacíos a null.
 * @param {HTMLFormElement} form
 */
export function readForm(form) {
  const data = {};
  Array.from(form.elements).forEach((el) => {
    if (!el.name || el.disabled) return; // los campos deshabilitados no se envían
    if (el.type === 'checkbox') data[el.name] = el.checked;
    else if (el.value === '') data[el.name] = null;
    else if (el.type === 'number') data[el.name] = Number(el.value);
    else if (el.tagName === 'SELECT' && /^\d+$/.test(el.value) && el.dataset.keep !== 'string') data[el.name] = Number(el.value);
    else data[el.name] = el.value;
  });
  return data;
}

/** Muestra errores 422 de la API junto a cada campo. */
export function showErrors(form, errors = {}) {
  form.querySelectorAll('.field').forEach((f) => f.classList.remove('invalid'));
  form.querySelectorAll('small.error').forEach((s) => (s.textContent = ''));
  Object.entries(errors).forEach(([name, messages]) => {
    const control = form.elements.namedItem(name);
    const wrapper = control?.closest?.('.field');
    if (wrapper) {
      wrapper.classList.add('invalid');
      wrapper.querySelector('small.error').textContent = messages.join(' ');
    }
  });
}

/** Opciones para <select> desde una lista de entidades. */
export const options = (items, label = (i) => i.name, value = (i) => i.id) => items.map((i) => ({ value: value(i), label: label(i) }));
