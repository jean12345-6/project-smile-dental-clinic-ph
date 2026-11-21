import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { money } from '../components.jsx';
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
    if (user?.role !== 'superadmin') return;
    api('/r/clinics').then((r) => {
      // Lowest clinic_id first, so the home clinic leads the list.
      const list = [...(r.data || [])].sort((a, b) => Number(a.clinic_id) - Number(b.clinic_id));
      setClinics(list);
      // With nothing stored the box displayed the first clinic while the API
      // fell back to another one, so the label and the clinic that records
      // actually went to could disagree. Settle it on first load.
      if (list.length && !localStorage.getItem('smilesync-clinic')) {
        setWc(String(list[0].clinic_id));
        try { localStorage.setItem('smilesync-clinic', String(list[0].clinic_id)); } catch (e) { /* ignore */ }
      }
    }).catch(() => {});
  }, [user?.role]);
  // Changing the clinic now changes the figures as well as where new records go, so the
  // dashboard has to be reloaded. Before this, the numbers never moved.
  const pickClinic = (v) => {
    setWc(v);
    try { localStorage.setItem('smilesync-clinic', v); } catch (e) { /* ignore */ }
    setS(null);
    api('/stats', { params: v && v !== 'all' ? { clinicId: v } : { clinicId: 'all' } }).then(setS).catch((e) => setError(e.messages));
  };

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
            Showing, and adding records to
            <select value={wc || clinics[0].clinic_id} onChange={(e) => pickClinic(e.target.value)}>
              {clinics.map((c) => <option key={c.clinic_id} value={c.clinic_id}>{c.clinic_name}</option>)}
              <option value="all">All clinics (new records go to {clinics[0].clinic_name})</option>
            </select>
          </label>
        )}
        {admin && <button className="btn btn-ghost" onClick={runReminders}>Run reminders</button>}
        {front && <Link className="btn btn-primary" to="/book">New appointment</Link>}
      </div>
    </div>
  );
  if (!s) return <>{head}<Alert>{error.join(' ')}</Alert></>;

  const by = s.appointments.byStatus || {};
  const max = Math.max(1, ...ORDER.map((k) => by[k] || 0));
  const todays = (s.today || []).filter((a) => filter === 'all' || (filter === 'waiting' ? ['pending', 'confirmed', 'checked_in'].includes(a.status) : a.status === 'completed'));
  // This counted every unfinished appointment of the day, so at 9 PM it still reported
  // people as waiting whose time had long passed. Only what is still ahead counts.
  const nowMs = Date.now();
  const stillWaiting = (s.today || []).filter((a) => ['pending', 'confirmed', 'checked_in'].includes(a.status) && new Date(String(a.start_time).replace(' ', 'T')).getTime() >= nowMs).length;

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

      {user?.role === 'superadmin' && !s.clinicScope && (
        <div className="alert alert-warn">
          These figures cover <strong>every clinic added together</strong>. Pick one clinic above to see its own numbers.
        </div>
      )}

      <div className="stats">
        <div className="stat"><div className="stat-label">Appointments today</div><div className="stat-value">{s.appointments.today}</div><div className="stat-sub">{stillWaiting} still waiting</div></div>
        <div className="stat"><div className="stat-label">Patients</div><div className="stat-value">{s.patients}</div><div className="stat-sub">{s.appointments.total} appointments on file</div></div>
        {admin && <div className="stat"><div className="stat-label">Collected</div><div className="stat-value">{money(s.revenue)}</div><div className="stat-sub warn">{money(s.unpaid)} unpaid</div></div>}
        <div className="stat"><div className="stat-label">Waiting list</div><div className="stat-value">{s.waitlist?.waiting || 0}</div><div className="stat-sub">{s.waitlist?.offered || 0} slots offered</div></div>
      </div>

      {s.lowStock?.length > 0 && (
        <div className="alert alert-warn">
          {/* The same item was listed once per stock row, so one item held at two branches read
              as two separate warnings. Group by name and say how many places are low. */}
          <strong>Low stock:</strong> {[...s.lowStock.reduce((m, i) => {
            const k = i.item_name;
            const p = m.get(k) || { name: k, qty: 0, places: [] };
            m.set(k, { ...p, qty: p.qty + Number(i.quantity || 0), places: [...p.places, i.branch_id].filter((x) => x != null) });
            return m;
          }, new Map()).values()].map((i) => {
            const where = [...new Set(i.places)].map((b) => name.branch(b)).filter(Boolean);
            return `${i.name} (${i.qty} left${where.length > 1 ? ` across ${where.join(' and ')}` : where.length === 1 ? ` at ${where[0]}` : ''})`;
          }).join(', ')} · <Link to="/inventory">Open inventory</Link>
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
