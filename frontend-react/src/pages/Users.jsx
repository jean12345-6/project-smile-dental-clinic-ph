import React, { useEffect, useMemo, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Alert, Card, Empty } from '../ui.jsx';

const ROLES = ['superadmin', 'clinic_admin', 'receptionist', 'dentist', 'patient'];
const label = (r) => String(r || '').replace('_', ' ');

export default function Users() {
  const { user } = useAuth();
  const isSuper = user?.role === 'superadmin';
  const [rows, setRows] = useState(null);
  const [clinics, setClinics] = useState([]);
  const [q, setQ] = useState('');
  const [role, setRole] = useState('');
  const [error, setError] = useState([]);
  const [note, setNote] = useState('');
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const blank = { name: '', email: '', password: '', role: 'receptionist', clinicId: '' };
  const [f, setF] = useState(blank);

  const load = async () => {
    try {
      let all = [];
      for (let page = 1; page <= 20; page++) {
        const r = await api('/users', { params: { perPage: 100, page } });
        all = all.concat(r.data || []);
        if (!r.links?.next) break;
      }
      setRows(all);
    } catch (e) { setError(e.messages || ['Could not load users.']); setRows([]); }
  };
  useEffect(() => {
    load();
    api('/r/clinics').then((r) => setClinics(r.data || [])).catch(() => {});
  }, []);

  const clinicName = (id) => clinics.find((c) => String(c.clinic_id) === String(id))?.clinic_name || (id ? `Clinic ${id}` : 'All clinics');
  const shown = useMemo(() => (rows || []).filter((u) => {
    const a = u.attributes;
    if (role && a.role !== role) return false;
    const t = q.trim().toLowerCase();
    return !t || a.name?.toLowerCase().includes(t) || a.email?.toLowerCase().includes(t);
  }), [rows, q, role]);

  const submit = async (e) => {
    e.preventDefault();
    setBusy(true); setError([]); setNote('');
    try {
      const attributes = { name: f.name.trim(), email: f.email.trim(), password: f.password, role: f.role };
      if (isSuper && f.role !== 'superadmin') attributes.clinicId = Number(f.clinicId || clinics[0]?.clinic_id);
      await api('/users', { method: 'POST', body: { data: { attributes } } });
      setNote(`${f.name} was added as ${label(f.role)}.`);
      setF(blank); setOpen(false); load();
    } catch (er) { setError(er.messages || ['Could not add the user.']); }
    setBusy(false);
  };

  const rolesAllowed = isSuper ? ROLES.filter((r) => r !== 'patient') : ['clinic_admin', 'receptionist', 'dentist'];

  return (
    <>
      <div className="page-top">
        <div>
          <h1 className="page-title">Users</h1>
          <p className="page-sub" style={{ margin: 0 }}>Staff and patient login accounts.</p>
        </div>
        <div className="top-actions">
          <button className="btn btn-primary" onClick={() => setOpen(!open)}>{open ? 'Close' : 'Add staff'}</button>
        </div>
      </div>
      <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
      <Alert kind="success">{note}</Alert>

      {open && (
        <Card title="Add a staff account">
          <form onSubmit={submit} style={{ display: 'grid', gap: 12, gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))' }}>
            <label>Full name<input required value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} /></label>
            <label>Email<input required type="email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} /></label>
            <label>Password<input required type="password" minLength={8} value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} /></label>
            <label>Role
              <select value={f.role} onChange={(e) => setF({ ...f, role: e.target.value })}>
                {rolesAllowed.map((r) => <option key={r} value={r}>{label(r)}</option>)}
              </select>
            </label>
            {isSuper && f.role !== 'superadmin' && (
              <label>Clinic
                <select value={f.clinicId || clinics[0]?.clinic_id || ''} onChange={(e) => setF({ ...f, clinicId: e.target.value })}>
                  {clinics.map((c) => <option key={c.clinic_id} value={c.clinic_id}>{c.clinic_name}</option>)}
                </select>
              </label>
            )}
            <div style={{ display: 'flex', alignItems: 'end' }}><button className="btn btn-primary" disabled={busy}>{busy ? 'Saving...' : 'Add account'}</button></div>
          </form>
          {f.role === 'dentist' && <p className="muted small" style={{ marginBottom: 0 }}>Also add this dentist in Clinic settings, Dentists, so patients can book them.</p>}
        </Card>
      )}

      <Card title={`Accounts${rows ? ` (${shown.length})` : ''}`} right={
        <div className="filters">
          <input placeholder="Search name or email" value={q} onChange={(e) => setQ(e.target.value)} />
          <select value={role} onChange={(e) => setRole(e.target.value)}>
            <option value="">All roles</option>
            {ROLES.map((r) => <option key={r} value={r}>{label(r)}</option>)}
          </select>
        </div>}>
        {rows === null && <Empty>Loading...</Empty>}
        {rows && shown.length === 0 && <Empty>No users match.</Empty>}
        {rows && shown.length > 0 && (
          <div className="table-wrap"><table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Clinic</th><th>Status</th></tr></thead>
            <tbody>{shown.map((u) => { const a = u.attributes; return (
              <tr key={u.id}><td>{a.name}</td><td>{a.email}</td><td style={{ textTransform: 'capitalize' }}>{label(a.role)}</td><td>{a.role === 'superadmin' ? 'All clinics' : clinicName(a.clinicId)}</td><td>{a.status || 'active'}</td></tr>); })}</tbody>
          </table></div>
        )}
      </Card>
    </>
  );
}
