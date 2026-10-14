/**
 * Router por hash (#/ruta) con parámetros y control de acceso por rol.
 */

/**
 * @typedef {{path: string, view: (ctx: {params: Record<string,string>, query: URLSearchParams}) => Promise<Node>|Node,
 *            permission?: string, title: string, nav?: {icon: string, label?: string}}} Route
 */

/**
 * Convierte "/practices/:id" en regex y extrae parámetros.
 * @returns {Record<string,string>|null}
 */
export function matchPath(pattern, path) {
  const keys = [];
  const regex = new RegExp(
    '^' +
      pattern.replace(/\//g, '\\/').replace(/:(\w+)/g, (_, key) => {
        keys.push(key);
        return '([^/]+)';
      }) +
      '\\/?$',
  );
  const match = path.match(regex);
  if (!match) return null;
  return Object.fromEntries(keys.map((key, i) => [key, decodeURIComponent(match[i + 1])]));
}

/**
 * ¿El usuario puede entrar a la ruta? Se usa para la guarda del router y para filtrar el menú.
 * @param {Route} route
 * @param {{permissions?: string[]}|null} user
 */
export function canAccess(route, user) {
  if (!route.permission) return true;
  return Boolean(user?.permissions?.includes(route.permission));
}

/**
 * Resuelve la ruta para un hash y el usuario actual (según sus permisos).
 * @param {Route[]} routes
 * @returns {{route: Route|null, params: Record<string,string>, query: URLSearchParams, forbidden: boolean}}
 */
export function resolve(routes, hash, user) {
  const [rawPath, rawQuery = ''] = (hash.replace(/^#/, '') || '/').split('?');
  for (const route of routes) {
    const params = matchPath(route.path, rawPath);
    if (params) {
      const forbidden = !canAccess(route, user);
      return { route, params, query: new URLSearchParams(rawQuery), forbidden };
    }
  }
  return { route: null, params: {}, query: new URLSearchParams(rawQuery), forbidden: false };
}

export const navigate = (path) => {
  window.location.hash = path;
};
