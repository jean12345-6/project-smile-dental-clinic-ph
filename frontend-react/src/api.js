const BASE = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1';

export const getToken = () => localStorage.getItem('token');

export async function api(path, { method = 'GET', body, params } = {}) {
  const url = new URL(BASE + path, window.location.origin);
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
    });
  }

  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
  if (getToken()) headers.Authorization = `Bearer ${getToken()}`;
  try { const wc = localStorage.getItem('smilesync-clinic'); if (wc) headers['X-Clinic-Id'] = wc; } catch (e) { /* ignore */ }

  let res;
  try {
    res = await fetch(url, { method, headers, body: body ? JSON.stringify(body) : undefined });
  } catch {
    const e = new Error('Cannot reach the server. Is `php artisan serve` running?');
    e.messages = [e.message];
    throw e;
  }

  let json = null;
  try {
    json = await res.json();
  } catch {
    json = null;
  }

  if (!res.ok) {
    const messages = (json?.errors || []).map((e) => e.message).filter(Boolean);
    if (!messages.length && json?.message) messages.push(json.message);
    if (!messages.length) messages.push(`Request failed (${res.status})`);
    const err = new Error(messages.join(' '));
    err.status = res.status;
    err.messages = messages;
    if (res.status === 401 && getToken()) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    throw err;
  }
  return json;
}

// Find the first primitive value stored under `key`, anywhere in the object.
export function findKey(obj, key) {
  if (!obj || typeof obj !== 'object') return undefined;
  if (key in obj && typeof obj[key] !== 'object') return obj[key];
  for (const v of Object.values(obj)) {
    const r = findKey(v, key);
    if (r !== undefined) return r;
  }
  return undefined;
}

// Find the first object that matches the test, anywhere in the object.
export function findObj(obj, test) {
  if (!obj || typeof obj !== 'object') return undefined;
  if (test(obj)) return obj;
  for (const v of Object.values(obj)) {
    const r = findObj(v, test);
    if (r !== undefined) return r;
  }
  return undefined;
}
