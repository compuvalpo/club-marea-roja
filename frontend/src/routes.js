import { dashboardView } from './views/dashboard.js';
import { calendarView } from './views/calendar.js';
import { practiceView } from './views/practice.js';
import { athletesView } from './views/athletes.js';
import { athleteStatsView } from './views/athlete-stats.js';
import { groupsView } from './views/groups.js';
import { venuesView } from './views/venues.js';
import { eventsView } from './views/events.js';
import { financeView } from './views/finance.js';
import { myPaymentsView } from './views/my-payments.js';
import { usersView } from './views/users.js';

/**
 * Cada ruta exige un permiso de la matriz RBAC del servidor (App\\Auth\\Permissions).
 * Ocultar un menú NO es la seguridad: la API vuelve a validar el permiso en cada petición.
 * @type {import('./router.js').Route[]}
 */
export const routes = [
  { path: '/', title: 'Inicio', view: dashboardView, permission: 'dashboard.view', nav: { icon: 'home' } },
  { path: '/calendar', title: 'Calendario', view: calendarView, permission: 'practices.view', nav: { icon: 'calendar' } },
  { path: '/practices/:id', title: 'Práctica', view: practiceView, permission: 'practices.view', icon: 'timer' },
  { path: '/events', title: 'Eventos', view: eventsView, permission: 'events.view', nav: { icon: 'trophy' } },
  { path: '/athletes', title: 'Alumnos', view: athletesView, permission: 'athletes.list', nav: { icon: 'swim' } },
  { path: '/athletes/:id/stats', title: 'Estadísticas', view: athleteStatsView, permission: 'athletes.view', icon: 'chart' },
  { path: '/my-stats', title: 'Mis estadísticas', view: athleteStatsView, permission: 'stats.own', nav: { icon: 'chart' } },
  { path: '/my-payments', title: 'Mis pagos', view: myPaymentsView, permission: 'finance.own', nav: { icon: 'card' } },
  { path: '/finance', title: 'Finanzas', view: financeView, permission: 'finance.manage', nav: { icon: 'wallet' } },
  { path: '/groups', title: 'Grupos y horarios', view: groupsView, permission: 'schedules.manage', nav: { icon: 'calendar-plus' } },
  { path: '/venues', title: 'Recintos', view: venuesView, permission: 'catalog.manage', nav: { icon: 'pin' } },
  { path: '/users', title: 'Usuarios', view: usersView, permission: 'users.manage', nav: { icon: 'users' } },
];
