import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { money } from '../components.jsx';
import { Alert, Badge, Card, Empty, fmt, rel, when } from '../ui.jsx';
import { invoiceStatus } from './MyBilling.jsx';

const hello = () => { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'; };

export default function PatientDashboard() {
  const { user, lookups, name } = useAuth();
  const pid = lookups?.patientId;
  const [d, setD] = useState(null);
  const [error, setError] = useState([]);

  useEffect(() => {
    if (!pid) return;
    Promise.all([api(`/patients/${pid}/appointments`), api('/r/invoices'), api('/r/treatments'), api('/r/prescriptions'), api('/my/notifications')])
      .then(([ap, inv, tx, rx, nt]) => setD({ ap: ap.data || [], inv: inv.data || [], tx: tx.data || [], rx: rx.data || [], unread: nt.unread || 0 }))
      .catch((e) => setError(e.messages));
  }, [pid]);

  if (!pid) return <Alert>This account has no patient record.</Alert>;
  if (!d) return <Alert>{error.join(' ')}</Alert>;

  const now = new Date().toISOString().slice(0, 16);
  const start = (a) => String(a.attributes.startTime || '').replace(' ', 'T').slice(0, 16);
  const upcoming = d.ap.filter((a) => start(a) >= now && ['pending', 'confirmed'].includes(a.attributes.status)).sort((x, y) => start(x).localeCompare(start(y)));
  const next = upcoming[0];
  const recent = [...d.ap].sort((x, y) => start(y).localeCompare(start(x))).slice(0, 5);
  const owed = d.inv.reduce((s, i) => s + Number(i.balance || 0), 0);
  const done = d.ap.filter((a) => a.attributes.status === 'completed').length;

  return (
    <>
      <div className="page-top">
        <div>
          <h1 className="page-title">{hello()}, {user?.name}</h1>
          <p className="page-sub" style={{ margin: 0 }}>Here is your dental summary.</p>
        </div>
        <div className="top-actions"><button className="btn btn-primary" onClick={() => window.dispatchEvent(new CustomEvent('open-chat', { detail: { action: 'book', label: 'Book an appointment' } }))}>+ Book appointment</button></div>
      </div>
      <Alert>{error.join(' ')}</Alert>
      {owed > 0 && <div className="alert alert-warn">You have an unpaid balance of <strong>{money(owed)}</strong>. <Link to="/my-invoices">View invoices</Link></div>}
      <Card title="Next appointment">
        {!next ? <Empty>No upcoming appointment. Tap "+ Book appointment" to set one.</Empty> : (
          <div className="row"><span><strong>{name.service(rel(next, 'service'))}</strong><div className="muted small">{when(next.attributes.startTime)} · {name.dentist(rel(next, 'dentist'))} · {name.branch(rel(next, 'branch'))}</div></span><Badge status={next.attributes.status} /></div>
        )}
      </Card>
      <div className="stats">
        <div className="stat"><div className="stat-label">Appointments</div><div className="stat-value">{d.ap.length}</div><div className="stat-sub">{done} completed</div></div>
        <div className="stat"><div className="stat-label">Treatments</div><div className="stat-value">{d.tx.length}</div><div className="stat-sub">on file</div></div>
        <div className="stat"><div className="stat-label">Prescriptions</div><div className="stat-value">{d.rx.length}</div><div className="stat-sub">on file</div></div>
        <div className="stat"><div className="stat-label">Balance</div><div className="stat-value">{money(owed)}</div><div className="stat-sub warn">{d.inv.filter((i) => Number(i.balance) > 0).length} unpaid</div></div>
        <div className="stat"><div className="stat-label">Notifications</div><div className="stat-value">{d.unread}</div><div className="stat-sub"><Link to="/my-notifications">unread</Link></div></div>
      </div>
      <div className="split">
        <Card title="Recent appointments" right={<Link to="/my-appointments">See all</Link>}>
          {recent.length === 0 && <Empty>None yet.</Empty>}
          {recent.map((a) => <div className="row" key={a.id}><span>{name.service(rel(a, 'service'))}<div className="muted small">{when(a.attributes.startTime)}</div></span><Badge status={a.attributes.status} /></div>)}
        </Card>
        <Card title="Recent invoices" right={<Link to="/my-invoices">See all</Link>}>
          {d.inv.length === 0 && <Empty>None yet.</Empty>}
          {d.inv.slice(0, 5).map((i) => <div className="row" key={i.invoice_id}><span>#{i.invoice_id}<div className="muted small">{fmt(i.date_issued).slice(0, 10)} · {invoiceStatus(i)}</div></span><strong>{money(i.total_amount)}</strong></div>)}
        </Card>
      </div>
    </>
  );
}
