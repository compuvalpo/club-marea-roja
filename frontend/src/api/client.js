/**
 * Cliente HTTP de la API.
 *
 * - El access token (JWT, 15 min) vive sólo en memoria: nunca en localStorage/sessionStorage.
 * - El refresh token viaja en una cookie HttpOnly gestionada por el navegador (JS no puede leerla).
 * - Si el token está por expirar se renueva ANTES de la petición; ante un 401 se renueva una vez y se reintenta.
 * - La renovación es única por pestaña ("single-flight") y se serializa entre pestañas con la Web Locks API,
 *   porque todas comparten la misma cookie y el servidor rota el refresh token en cada uso.
 */

const BASE = '/api/v1';
const LOCK_NAME = 'marea-roja-auth-refresh';
/** Margen (ms) para renovar antes de que el token expire. */
const EXPIRY_MARGIN_MS = 20_000;
const RACE_MESSAGE = 'La sesión se renovó en otra pestaña. Reintente.';

let accessToken = null;
let accessExp = 0;
let refreshPromise = null;
let lastApiCallAt = 0;
let onSessionExpired = () => {};
/** Permite a session.js rechazar una renovación que pertenece a otra sesión (p. ej. otro usuario en otra pestaña). */
let sessionGuard = () => true;

export class ApiError extends Error {
  /**
   * @param {number} status
   * @param {string} message
   * @param {Record<string, string[]>} [errors]
   */
  constructor(status, message, errors = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

/** Lee el claim "exp" del JWT (sólo para saber cuándo renovar; la validación real la hace el servidor). */
export function tokenExpiry(token) {
  try {
    const payload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
    const { exp } = JSON.parse(atob(payload.padEnd(payload.length + ((4 - (payload.length % 4)) % 4), '=')));
    return typeof exp === 'number' ? exp * 1000 : 0;
  } catch {
    return 0;
  }
}

export function setAccessToken(token) {
  accessToken = token;
  accessExp = token ? tokenExpiry(token) : 0;
}

export function getAccessToken() {
  return accessToken;
}

export function hasAccessToken() {
  return accessToken !== null;
}

export function lastApiCall() {
  return lastApiCallAt;
}

export function onExpired(callback) {
  onSessionExpired = callback;
}

export function setSessionGuard(guard) {
  sessionGuard = guard;
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function callRefresh() {
  const res = await fetch(`${BASE}/auth/refresh`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' } });
  const payload = await res.json().catch(() => ({}));
  return { ok: res.ok, data: payload?.data, message: payload?.error?.message };
}

async function doRefresh() {
  let result = await callRefresh();
  // Otra pestaña acaba de rotar la cookie: el navegador ya tiene la nueva, se reintenta una vez.
  if (!result.ok && result.message === RACE_MESSAGE) {
    await sleep(300);
    result = await callRefresh();
  }
  if (!result.ok) {
    setAccessToken(null);
    return { ok: false, message: result.message ?? 'Su sesión expiró. Inicie sesión nuevamente.' };
  }
  if (!sessionGuard(result.data)) {
    setAccessToken(null);
    return { ok: false, message: 'Se inició otra sesión en este navegador. Esta sesión fue cerrada.' };
  }
  setAccessToken(result.data.access_token);
  return { ok: true, data: result.data };
}

/**
 * Renueva el access token usando la cookie de refresh.
 * @returns {Promise<{ok: true, data: any} | {ok: false, message: string}>}
 */
export function refreshSession() {
  refreshPromise ??= (navigator.locks?.request ? navigator.locks.request(LOCK_NAME, doRefresh) : doRefresh())
    .catch(() => ({ ok: false, message: 'No se pudo contactar al servidor.' }))
    .finally(() => {
      refreshPromise = null;
    });

  return refreshPromise;
}

function buildUrl(path, query) {
  const url = new URL(BASE + path, window.location.origin);
  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });
  return url.pathname + url.search;
}

function expireSession(message) {
  setAccessToken(null);
  onSessionExpired(message);
}

/**
 * @param {string} path  Ruta relativa a /api/v1 (p. ej. "/practices")
 * @param {{method?: string, body?: unknown, query?: Record<string, unknown>, retry?: boolean}} [options]
 * @returns {Promise<any>} el campo "data" de la respuesta
 */
export async function api(path, { method = 'GET', body, query, retry = true } = {}) {
  const isAuthCall = path.startsWith('/auth/') && path !== '/auth/me';

  // Token vencido o por vencer: se renueva antes de enviar la petición.
  if (!isAuthCall && accessToken && accessExp - Date.now() < EXPIRY_MARGIN_MS) {
    const renewed = await refreshSession();
    if (!renewed.ok) {
      expireSession(renewed.message);
      throw new ApiError(401, renewed.message);
    }
  }

  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (accessToken) headers.Authorization = `Bearer ${accessToken}`;

  lastApiCallAt = Date.now();
  const res = await fetch(buildUrl(path, query), {
    method,
    headers,
    credentials: 'same-origin',
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  if (res.status === 204) return null;
  const payload = await res.json().catch(() => ({}));
  const message = payload?.error?.message ?? (res.ok ? '' : 'Error inesperado.');

  if (res.status === 401 && !isAuthCall) {
    if (retry) {
      const renewed = await refreshSession();
      if (renewed.ok) return api(path, { method, body, query, retry: false });
      expireSession(renewed.message);
      throw new ApiError(401, renewed.message);
    }
    expireSession(message);
  }

  if (!res.ok) {
    throw new ApiError(res.status, message, payload?.error?.errors);
  }
  return payload.data;
}

export const get = (path, query) => api(path, { query });
export const post = (path, body) => api(path, { method: 'POST', body: body ?? {} });
export const put = (path, body) => api(path, { method: 'PUT', body });
export const patch = (path, body) => api(path, { method: 'PATCH', body });
export const del = (path) => api(path, { method: 'DELETE' });
