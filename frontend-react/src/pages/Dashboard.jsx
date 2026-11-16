import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { money } from '../components.jsx';
import { toggleTheme } from '../theme.js';
import { Alert, Badge, Card, Empty, fmt } from '../ui.jsx';
import DashboardFinance from './DashboardFinance.jsx';

const ORDER = ['pending', 'confirmed', 'checked_in', 'in_treatment', 'completed', 'cancelled', 'no_show'];
const initials = (n = '') => n.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const hello = () => { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'; };

export default function Dashboard() {
  const { user, name } = useAuth();
  const [s, setS] = useState(null);
  const [error, setError] = useState([]);
  const [note, setNote] = useState('');
  const [filter, setFilter] = useState('all');
  const [clinics, setClinics] = useState([]);
  const [wc, setWc] = useState(() => localStorage.getItem('smilesync-clinic') || '');
  const admin = ['superadmin', 'clinic_admin'].includes(user?.role);
  const front = admin || user?.role === 'receptionist';

  const load = () => api('/stats').then(setS).catch((e) => setError(e.messages));
  useEffect(() => { load(); }, []);
  useEffect(() => {
    if (user?.role === 'superadmin') api('/r/clinics').then((r) => setClinics(r.data || [])).catch(() => {});
  }, [user?.role]);
  const pickClinic = (v) => { setWc(v); try { localStorage.setItem('smilesync-clinic', v); } catch (e) { /* ignore */ } };

  const runReminders = async () => {
    try {
      const r = await api('/reminders/run', { method: 'POST', body: {} });
      setNote(`Reminders sent: ${r.remindersSent} · failed: ${r.remindersFailed} · high-risk flagged: ${r.highRiskFlagged} · offers expired: ${r.offersExpired}`);
      load();
    } catch (e) { setError(e.messages); }
  };

  const head = (
    <div className="page-top">
      <div>
        <h1 className="page-title">{hello()}, {user?.name}</h1>
        <p className="page-sub" style={{ margin: 0 }}>Here is the clinic today.</p>
      </div>
      <div className="top-actions">
        {user?.role === 'superadmin' && clinics.length > 0 && (
          <label className="muted small" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
            Adding records to
            <select value={wc || clinics[0].clinic_id} onChange={(e) => pickClinic(e.target.value)}>
              {clinics.map((c) => <option key={c.clinic_id} value={c.clinic_id}>{c.clinic_name}</option>)}
            </select>
          </label>
        )}
        <button className="btn btn-ghost" onClick={toggleTheme}>Toggle theme</button>
        {admin && <button className="btn btn-ghost" onClick={runReminders}>Run reminders</button>}
        {front && <Link className="btn btn-primary" to="/book">New appointment</Link>}
      </div>
    </div>
  );
  if (!s) return <>{head}<Alert>{error.join(' ')}</Alert></>;

  const by = s.appointments.byStatus || {};
  const max = Math.max(1, ...ORDER.map((k) => by[k] || 0));
  const todays = (s.today || []).filter((a) => filter === 'all' || (filter === 'waiting' ? ['pending', 'confirmed', 'checked_in'].includes(a.status) : a.status === 'completed'));
  const stillWaiting = (s.today || []).filter((a) => ['pending', 'confirmed', 'checked_in'].includes(a.status)).length;

  const todayCard = (
    <Card title="Today’s appointments" right={
      <div className="chipset">{[['all', 'All'], ['waiting', 'Waiting'], ['done', 'Done']].map(([k, l]) => <button key={k} className={filter === k ? 'on' : ''} onClick={() => setFilter(k)}>{l}</button>)}</div>}>
      {todays.length === 0 && <Empty>No appointments to show.</Empty>}
      {todays.length > 0 && (
        <div className="table-wrap">
          <table>
            <thead><tr><th>Time</th><th>Patient</th><th>Treatment</th><th>Status</th></tr></thead>
            <tbody>
              {todays.map((a) => (
                <tr key={a.appointment_id}>
                  <td>{fmt(a.start_time).slice(11)}</td>
                  <td><div className="who"><span className="initials">{initials(a.patient)}</span>{a.patient}</div><div className="muted small" style={{ marginLeft: 40 }}>{name.dentist(a.dentist_id)}</div></td>
                  <td>{a.service}</td>
                  <td><Badge status={a.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );

  const quickCard = (
    <Card title="Quick actions">
      <div className="quick">
        <Link className="btn btn-primary" to="/queue">Open the daily queue</Link>
        {front && <Link className="btn btn-ghost" to="/book">Book for a patient</Link>}
        <Link className="btn btn-ghost" to="/reports">View reports</Link>
        {admin && <Link className="btn btn-ghost" to="/chat-logs">{s.escalated > 0 ? `${s.escalated} chats sent to staff` : 'Chat logs'}</Link>}
      </div>
    </Card>
  );

  const recentCard = (
    <Card title="Recent treatments">
      {(s.recentTreatments || []).length === 0 && <Empty>No treatments yet.</Empty>}
      {(s.recentTreatments || []).map((t, i) => (
        <div className="row" key={i}><div>{t.procedure_name}<div className="muted small">{t.patient}</div></div><strong>{money(t.cost)}</strong></div>
      ))}
    </Card>
  );

  const statusCard = (
    <Card title="Appointments by status">
      {ORDER.map((k) => (
        <div className="bar-row" key={k}>
          <span className="bar-label"><Badge status={k} /></span>
          <div className="bar"><div className={`bar-fill fill-${k}`} style={{ width: `${((by[k] || 0) / max) * 100}%` }} /></div>
          <span className="bar-n">{by[k] || 0}</span>
        </div>
      ))}
    </Card>
  );

  return (
    <>
      {head}
      <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
      <Alert kind="success">{note}</Alert>

      <div className="stats">
        <div className="stat"><div className="stat-label">Appointments today</div><div className="stat-value">{s.appointments.today}</div><div className="stat-sub">{stillWaiting} still waiting</div></div>
        <div className="stat"><div className="stat-label">Patients</div><div className="stat-value">{s.patients}</div><div className="stat-sub">{s.appointments.total} appointments on file</div></div>
        <div className="stat"><div className="stat-label">Collected</div><div className="stat-value">{money(s.revenue)}</div><div className="stat-sub warn">{money(s.unpaid)} unpaid</div></div>
        <div className="stat"><div className="stat-label">Waiting list</div><div className="stat-value">{s.waitlist?.waiting || 0}</div><div className="stat-sub">{s.waitlist?.offered || 0} slots offered</div></div>
      </div>

      {s.lowStock?.length > 0 && (
        <div className="alert alert-warn">
          <strong>Low stock:</strong> {s.lowStock.map((i) => `${i.item_name} (${i.quantity} left)`).join(', ')} · <Link to="/inventory">Open inventory</Link>
        </div>
      )}

      {admin ? (
        // Admins: sales and charts first, with the other cards placed in the two columns so nothing is left empty.
        <DashboardFinance
          leftBottom={<>{statusCard}{todayCard}</>}
          rightTop={quickCard}
          rightBottom={recentCard}
        />
      ) : (
        <>
          <div className="split">
            {todayCard}
            <div>
              {quickCard}
              {recentCard}
            </div>
          </div>
          {statusCard}
        </>
      )}
    </>
  );
}
