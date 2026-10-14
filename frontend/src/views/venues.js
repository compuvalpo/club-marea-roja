import { h, badge, mount, table, toast, toastError } from '../ui/dom.js';
import { field } from '../ui/form.js';
import { confirmDialog, formModal } from '../ui/modal.js';
import { card, pageHeader } from '../ui/layout.js';
import { del, get, patch, post } from '../api/client.js';
import { icon } from '../ui/icons.js';

/** Recintos: lugares de entrenamiento y sedes de eventos externos (Admin). */
export async function venuesView() {
  let venues = await get('/venues');
  const box = h('div');

  const fields = (v = {}) => [
    field({ label: 'Nombre', name: 'name', required: true, value: v.name }),
    field({ label: 'Dirección', name: 'address', value: v.address }),
    field({ label: 'Notas', name: 'notes', type: 'textarea', value: v.notes }),
    field({ label: 'Es lugar de entrenamiento habitual', name: 'is_training_venue', type: 'checkbox', value: v.is_training_venue }),
  ];

  const reload = async () => {
    venues = await get('/venues');
    render();
  };

  function render() {
    mount(
      box,
      table(
        [
          { label: 'Nombre', render: (v) => v.name },
          { label: 'Dirección', render: (v) => v.address ?? '—' },
          {
            label: 'Tipo',
            render: (v) => (v.is_training_venue ? badge('Entrenamiento', 'success') : badge('Eventos / externo', 'neutral')),
          },
          {
            label: '',
            class: 'actions-cell',
            render: (v) =>
              h(
                'div.row-actions',
                null,
                h(
                  'button.btn.sm.ghost',
                  {
                    type: 'button',
                    onClick: () =>
                      formModal(
                        'Editar recinto',
                        fields(v),
                        async (d) => (await patch(`/venues/${v.id}`, d), toast('Recinto actualizado.'), reload()),
                      ),
                  },
                  'Editar',
                ),
                h(
                  'button.btn.sm.danger-outline',
                  {
                    type: 'button',
                    onClick: async () => {
                      if (!(await confirmDialog(`¿Eliminar ${v.name}?`, { danger: true, confirmLabel: 'Eliminar' }))) return;
                      await del(`/venues/${v.id}`).catch(toastError);
                      await reload();
                    },
                  },
                  'Eliminar',
                ),
              ),
          },
        ],
        venues,
      ),
    );
  }

  render();

  return h(
    'div',
    null,
    pageHeader(
      'Recintos',
      'Piscinas, gimnasios y sedes donde se realizan prácticas y eventos.',
      h(
        'button.btn.primary',
        {
          type: 'button',
          onClick: () => formModal('Nuevo recinto', fields(), async (d) => (await post('/venues', d), toast('Recinto creado.'), reload())),
        },
        icon('plus', { size: 16 }),
        'Nuevo recinto',
      ),
    ),
    card(null, box),
  );
}
