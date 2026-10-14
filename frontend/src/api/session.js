import { api, getAccessToken, lastApiCall, onExpired, refreshSession, setAccessToken, setSessionGuard } from './client.js';

/**
 * Estado de sesión observable: usuario autenticado, su rol y sus permisos.
 *
 * Reglas de sesión en el navegador:
 * - Los permisos vienen del servidor (misma matriz que aplica la API); la interfaz sólo los refleja.
 * - Una sola sesión por usuario: si otra pestaña inicia sesión, esta pestaña se cierra (BroadcastChannel)
 *   y el servidor ya revocó su token.
 * - Las pestañas de la misma sesión comparten actividad; tras el tiempo de inactividad se cierra la sesión.
 */
const state = { user: null, sessionId: null, idleTimeout: 1800, reason: '' };
const listeners = new Set();

const CHANNEL = 'marea-roja-auth';
const HEARTBEAT_MS = 5 * 60_000;
const channel = typeof BroadcastChannel === 'function' ? new BroadcastChannel(CHANNEL) : null;

let lastActivity = Date.now();
let lastShared = 0;
let idleTimer = null;

export const ROLE_LABELS = {
  admin: 'Administrador',
  coach: 'Entrenador',
  guardian: 'Apoderado',
  athlete: 'Alumno',
};

export const REASONS = {
  otherTab: 'Se inició sesión en otra pestaña. Esta sesión fue cerrada.',
  loggedOutElsewhere: 'La sesión se cerró en otra pestaña.',
  idle: 'La sesión se cerró por inactividad.',
};

export function currentUser() {
  return state.user;
}

export function currentSessionId() {
  return state.sessionId;
}

export function hasRole(...roles) {
  return state.user !== null && roles.includes(state.user.role);
}

/** ¿El usuario actual tiene el permiso? (matriz RBAC entregada por el servidor). */
export function can(permission, user = state.user) {
  return Boolean(user?.active !== false && user?.permissions?.includes(permission));
}

/** Devuelve (y borra) el motivo del último cierre de sesión, para mostrarlo en la pantalla de ingreso. */
export function consumeLogoutReason() {
  const reason = state.reason;
  state.reason = '';
  return reason;
}

export function subscribe(listener) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

function notify() {
  listeners.forEach((listener) => listener(state.user));
}

function applySession(data) {
  state.user = data.user;
  state.sessionId = data.session_id;
  state.idleTimeout = data.idle_timeout ?? state.idleTimeout;
  state.reason = '';
  lastActivity = Date.now();
  startIdleWatch();
  notify();
}

/** Limpia la sesión local (sin llamar al servidor) y guarda el motivo para la pantalla de ingreso. */
function resetLocal(reason = '') {
  const hadUser = state.user !== null;
  setAccessToken(null);
  state.user = null;
  state.sessionId = null;
  state.reason = reason;
  stopIdleWatch();
  if (hadUser || reason) notify();
}

function broadcast(message) {
  try {
    channel?.postMessage(message);
  } catch {
    /* canal no disponible: no es crítico */
  }
}

export async function login(email, password) {
  const data = await api('/auth/login', { method: 'POST', body: { email, password } });
  setAccessToken(data.access_token);
  applySession(data);
  broadcast({ type: 'login', sessionId: data.session_id, userId: data.user.id });
  return data.user;
}

export async function logout(reason = '') {
  const sessionId = state.sessionId;
  try {
    await api('/auth/logout', { method: 'POST', body: {} });
  } catch {
    /* aunque falle la red, la sesión local se cierra */
  } finally {
    resetLocal(reason);
    broadcast({ type: 'logout', sessionId });
  }
}

/** Restaura la sesión al recargar o abrir otra pestaña usando la cookie de refresh. */
export async function restore() {
  const result = await refreshSession();
  if (result.ok) applySession(result.data);
  else resetLocal('');
  return state.user;
}

// Una renovación que trae otra sesión (otra pestaña inició sesión con otro usuario) no se adopta.
setSessionGuard((data) => state.sessionId === null || data.session_id === state.sessionId);

// 401 definitivo (token revocado, sesión expirada, permisos cambiados...): volver al ingreso con el motivo.
onExpired((message) => resetLocal(message || 'Su sesión expiró. Inicie sesión nuevamente.'));

/* ---------- Sincronización entre pestañas ---------- */

channel?.addEventListener('message', async ({ data: msg }) => {
  if (!msg || typeof msg !== 'object') return;

  if (msg.type === 'login' && state.user && msg.sessionId !== state.sessionId) {
    // Otro usuario en el mismo navegador: cerrar en servidor sólo la sesión de ESTA pestaña (sin tocar la
    // cookie, que ahora pertenece a la sesión nueva). Si es el mismo usuario, el servidor ya la revocó.
    if (msg.userId !== state.user.id && getAccessToken()) {
      await api('/auth/logout', { method: 'POST', body: { scope: 'token' } }).catch(() => {});
    }
    resetLocal(REASONS.otherTab);
  } else if (msg.type === 'logout' && state.user && msg.sessionId === state.sessionId) {
    resetLocal(REASONS.loggedOutElsewhere);
  } else if (msg.type === 'activity' && msg.sessionId === state.sessionId) {
    lastActivity = Math.max(lastActivity, msg.at);
  }
});

/* ---------- Inactividad ---------- */

function markActivity() {
  lastActivity = Date.now();
  if (state.sessionId && lastActivity - lastShared > 30_000) {
    lastShared = lastActivity;
    broadcast({ type: 'activity', sessionId: state.sessionId, at: lastActivity });
  }
}

async function checkIdle() {
  if (!state.user) return;
  const now = Date.now();
  if (now - lastActivity > state.idleTimeout * 1000) {
    await logout(REASONS.idle);
    return;
  }
  // Usuario activo sin llamadas a la API (p. ej. completando un formulario largo): se avisa al servidor.
  if (now - lastActivity < 60_000 && now - lastApiCall() > HEARTBEAT_MS) {
    await api('/auth/me').catch(() => {});
  }
}

function startIdleWatch() {
  stopIdleWatch();
  idleTimer = setInterval(checkIdle, 30_000);
}

function stopIdleWatch() {
  if (idleTimer) clearInterval(idleTimer);
  idleTimer = null;
}

if (typeof window !== 'undefined') {
  ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach((type) => window.addEventListener(type, markActivity, { passive: true }));
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') checkIdle();
  });
}
