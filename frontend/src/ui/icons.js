/**
 * Iconos SVG (Lucide, licencia ISC). Sólo se importan los usados para mantener el bundle pequeño.
 * Uso: icon('calendar') → <svg> decorativo; icon('trash', { label: 'Eliminar' }) → con texto accesible.
 */
import {
  createElement,
  Banknote,
  CalendarDays,
  CalendarPlus,
  ChartLine,
  CircleAlert,
  ClipboardCheck,
  CreditCard,
  Download,
  House,
  LogOut,
  MapPin,
  Menu,
  PiggyBank,
  Plus,
  Receipt,
  Scale,
  Target,
  Timer,
  TrendingDown,
  TrendingUp,
  Trophy,
  UserRound,
  Users,
  UsersRound,
  Wallet,
  Waves,
  X,
} from 'lucide';

const ICONS = {
  home: House,
  calendar: CalendarDays,
  'calendar-plus': CalendarPlus,
  chart: ChartLine,
  alert: CircleAlert,
  check: ClipboardCheck,
  card: CreditCard,
  download: Download,
  logout: LogOut,
  pin: MapPin,
  menu: Menu,
  piggy: PiggyBank,
  plus: Plus,
  receipt: Receipt,
  balance: Scale,
  target: Target,
  timer: Timer,
  down: TrendingDown,
  up: TrendingUp,
  trophy: Trophy,
  user: UserRound,
  users: Users,
  team: UsersRound,
  wallet: Wallet,
  money: Banknote,
  swim: Waves,
  close: X,
};

/**
 * @param {keyof typeof ICONS} name
 * @param {{size?: number, label?: string, class?: string}} [options]
 * @returns {SVGElement}
 */
export function icon(name, { size = 18, label, class: className = '' } = {}) {
  const node = ICONS[name] ?? CircleAlert;
  const svg = createElement(node, {
    width: size,
    height: size,
    class: `icon ${className}`.trim(),
    'aria-hidden': label ? 'false' : 'true',
    focusable: 'false',
    ...(label ? { role: 'img', 'aria-label': label } : {}),
  });
  return svg;
}

export const ICON_NAMES = Object.keys(ICONS);
