import React, { createContext, useContext, useEffect, useState } from 'react';
import { api, findKey, findObj, getToken } from './api.js';

const Ctx = createContext(null);
export const useAuth = () => useContext(Ctx);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [lookups, setLookups] = useState(null);
  const [loading, setLoading] = useState(!!getToken());

  const load = async () => {
    const me = await api('/auth/me');
    const u = findObj(me, (o) => 'role' in o && typeof o.role === 'string');
    if (!u) throw new Error('Could not read your account.');
    const lk = await api('/lookups');
    setUser(u);
    setLookups(lk);
  };

  useEffect(() => {
    if (!getToken()) return;
    load()
      .catch(() => localStorage.removeItem('token'))
      .finally(() => setLoading(false));
  }, []);

  const login = async (email, password) => {
    const res = await api('/auth/login', { method: 'POST', body: { email, password } });
    const token = findKey(res, 'access_token') || findKey(res, 'token');
    if (!token) throw new Error('Login worked but no token was returned.');
    localStorage.setItem('token', token);
    await load();
  };

  const logout = async (reason = 'logout') => {
    try {
      await api('/auth/logout', { method: 'POST', body: { reason } });
    } catch {
      /* ignore */
    }
    localStorage.removeItem('token');
    setUser(null);
    setLookups(null);
  };

  // NFR-11: end the session after 30 minutes of no activity; keep it alive while the person is working
  const logoutRef = React.useRef(logout);
  logoutRef.current = logout;
  useEffect(() => {
    if (!user) return undefined;
    let last = Date.now();
    let lastRefresh = Date.now();
    const bump = () => { last = Date.now(); };
    const events = ['mousemove', 'keydown', 'click', 'touchstart', 'scroll'];
    events.forEach((e) => window.addEventListener(e, bump, { passive: true }));
    const timer = setInterval(async () => {
      const idle = Date.now() - last;
      if (idle > 30 * 60 * 1000) {
        await logoutRef.current('timeout');
        window.location.href = '/login?timeout=1';
        return;
      }
      if (Date.now() - lastRefresh > 10 * 60 * 1000 && idle < 10 * 60 * 1000) {
        try {
          const r = await api('/auth/refresh', { method: 'POST' });
          const t = findKey(r, 'access_token') || findKey(r, 'token');
          if (t) localStorage.setItem('token', t);
          lastRefresh = Date.now();
        } catch { /* the next request will send the person to the login page */ }
      }
    }, 30000);
    return () => { events.forEach((e) => window.removeEventListener(e, bump)); clearInterval(timer); };
  }, [user]);

  const isPatient = user?.role === 'patient';

  // Name helpers built from the /lookups data
  const name = {
    service: (id) => lookups?.services?.find((s) => String(s.service_id) === String(id))?.service_name || `Service #${id}`,
    dentist: (id) => {
      const d = lookups?.dentists?.find((x) => String(x.dentist_id) === String(id));
      return d?.display_name || d?.dentist_name || d?.name || d?.full_name || `Dentist #${id}`;
    },
    branch: (id) => {
      const b = lookups?.branches?.find((x) => String(x.branch_id) === String(id));
      return b?.branch_name || b?.name || `Branch #${id}`;
    },
    patient: (id) => lookups?.patients?.find((p) => String(p.patient_id) === String(id))?.name || `Patient #${id}`,
  };

  return (
    <Ctx.Provider value={{ user, lookups, loading, login, logout, isPatient, name }}>{children}</Ctx.Provider>
  );
}
