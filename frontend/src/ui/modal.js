import { h, toastError } from './dom.js';
import { icon } from './icons.js';
import { readForm, showErrors } from './form.js';
import { ApiError } from '../api/client.js';

/**
 * Diálogo modal nativo (<dialog>) accesible.
 * @returns {{close: () => void, el: HTMLDialogElement}}
 */
export function openModal(title, content, { wide = false } = {}) {
  const dialog = h(
    'dialog.modal',
    { class: wide ? 'wide' : '', 'aria-label': title },
    h(
      'header',
      null,
      h('h2', null, title),
      h('button.icon-btn', { type: 'button', 'aria-label': 'Cerrar', onClick: () => close() }, icon('close', { size: 20 })),
    ),
    h('div.modal-body', null, content),
  );
  const close = () => {
    dialog.close();
    dialog.remove();
  };
  dialog.addEventListener('cancel', close);
  document.body.append(dialog);
  dialog.showModal();
  return { close, el: dialog };
}

/**
 * Modal con formulario: al enviar llama onSubmit(datos); si la API responde 422, muestra errores.
 * @param {string} title
 * @param {Node[]} fields
 * @param {(data: Record<string, any>) => Promise<void>} onSubmit
 */
export function formModal(title, fields, onSubmit, { submitLabel = 'Guardar', wide = false } = {}) {
  const submit = h('button.btn.primary', { type: 'submit' }, submitLabel);
  const form = h('form.form-grid', { novalidate: true }, fields, h('div.actions', null, submit));
  const modal = openModal(title, form, { wide });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    submit.disabled = true;
    try {
      await onSubmit(readForm(form));
      modal.close();
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) showErrors(form, error.errors);
      else toastError(error);
    } finally {
      submit.disabled = false;
    }
  });
  form.querySelector('input,select,textarea')?.focus();
  return modal;
}

/** Confirmación simple. */
export function confirmDialog(message, { confirmLabel = 'Confirmar', danger = false } = {}) {
  return new Promise((resolve) => {
    const modal = openModal(
      'Confirmar',
      h(
        'div',
        null,
        h('p', null, message),
        h(
          'div.actions',
          null,
          h('button.btn', { type: 'button', onClick: () => (modal.close(), resolve(false)) }, 'Cancelar'),
          h(`button.btn.${danger ? 'danger' : 'primary'}`, { type: 'button', onClick: () => (modal.close(), resolve(true)) }, confirmLabel),
        ),
      ),
    );
  });
}
