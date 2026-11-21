import React, { useEffect, useMemo, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Alert, Card, day, rel } from '../ui.jsx';

const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
const startOfWeek = (d) => addDays(d, -d.getDay());
const DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const COLOR = { pending: '#d97706', confirmed: '#2563eb', checked_in: '#7c3aed', completed: '#16a34a', cancelled: '#9ca3af', no_show: '#dc2626' };
const hhmm = (s) => (s ? String(s).replace('T', ' ').slice(11, 16) : '');

/** Doctor Schedule: every appointment on a calendar. Monthly, weekly or daily, for all dentists or one. */
export default function Schedule() {
  const { name, lookups } = useAuth();
  const [rows, setRows] = useState([]);
  const [error, setError] = useState([]);
  const [view, setView] = useState('month');
  const [cursor, setCursor] = useState(new Date());
  const [dentist, setDentist] = useState('');
  const [status, setStatus] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const first = await api('/appointments', { params: { page: 1 } });
        let all = first.data || [];
        setRows(all);
        const last = Math.min(first.meta?.last_page || 1, 60);
        for (let p = 2; p <= last; p += 5) {
          const batch = [];
          for (let q = p; q < p + 5 && q <= last; q += 1) batch.push(api('/appointments', { params: { page: q } }));
          (await Promise.all(batch)).forEach((r) => { all = all.concat(r.data || []); });
          setRows(all);
        }
      } catch (e) { setError(e.messages); }
    })();
  }, []);

  const byDay = useMemo(() => {
    const m = {};
    rows.forEach((a) => {
      if (dentist && String(rel(a, 'dentist')) !== String(dentist)) return;
      if (status && a.attributes.status !== status) return;
      const k = day(a.attributes.startTime);
      (m[k] = m[k] || []).push(a);
    });
    Object.values(m).forEach((l) => l.sort((x, y) => String(x.attributes.startTime).localeCompare(String(y.attributes.startTime))));
    return m;
  }, [rows, dentist, status]);

  const move = (n) => {
    const d = new Date(cursor);
    if (view === 'month') d.setMonth(d.getMonth() + n); else d.setDate(d.getDate() + n * (view === 'week' ? 7 : 1));
    setCursor(d);
  };

  const chip = (a) => (
    <div key={a.id} title={`${name.patient(rel(a, 'patient'))} - ${name.service(rel(a, 'service'))} - ${a.attributes.status}`}
      className="cal-chip"
      style={{ background: COLOR[a.attributes.status] || '#2563eb' }}>
      {hhmm(a.attributes.startTime)} {name.patient(rel(a, 'patient'))}
    </div>
  );

  const cell = (d, full) => {
    const list = byDay[iso(d)] || [];
    const today = iso(d) === iso(new Date());
    return (
      <div key={iso(d)} className="cal-cell" style={{ minHeight: full ? 120 : 84, background: today ? 'rgba(37,99,235,.07)' : undefined, opacity: view === 'month' && d.getMonth() !== cursor.getMonth() ? 0.45 : 1 }}>
        <div style={{ fontSize: 12, fontWeight: 600, cursor: 'pointer' }} onClick={() => { setCursor(d); setView('day'); }}>{d.getDate()}{list.length ? ` · ${list.length}` : ''}</div>
        {(full ? list : list.slice(0, 3)).map(chip)}
        {!full && list.length > 3 && <div style={{ fontSize: 11 }}>+{list.length - 3} more</div>}
      </div>
    );
  };

  let body;
  let label;
  if (view === 'month') {
    const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const start = startOfWeek(first);
    label = cursor.toLocaleString('en', { month: 'long', year: 'numeric' });
    body = (
      <div className="cal-grid">
        {DOW.map((d) => <div key={d} className="cal-head">{d}</div>)}
        {Array.from({ length: 42 }, (_, i) => cell(addDays(start, i), false))}
      </div>
    );
  } else if (view === 'week') {
    const start = startOfWeek(cursor);
    label = `${iso(start)} to ${iso(addDays(start, 6))}`;
    body = (
      <div className="cal-grid">
        {DOW.map((d, i) => <div key={d} className="cal-head">{d} {addDays(start, i).getDate()}</div>)}
        {Array.from({ length: 7 }, (_, i) => cell(addDays(start, i), true))}
      </div>
    );
  } else {
    const list = byDay[iso(cursor)] || [];
    label = cursor.toLocaleDateString('en', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    body = list.length ? (
      <table><thead><tr><th>Time</th><th>Patient</th><th>Service</th><th>Dentist</th><th>Branch</th><th>Status</th></tr></thead>
        <tbody>{list.map((a) => (
          <tr key={a.id}><td>{hhmm(a.attributes.startTime)} - {hhmm(a.attributes.endTime)}</td><td>{name.patient(rel(a, 'patient'))}</td><td>{name.service(rel(a, 'service'))}</td><td>{name.dentist(rel(a, 'dentist'))}</td><td>{name.branch(rel(a, 'branch'))}</td><td>{String(a.attributes.status).replace('_', ' ')}</td></tr>
        ))}</tbody></table>
    ) : <p className="empty">No appointments this day.</p>;
  }

  return (
    <div>
      <h1 className="page-title">Doctor schedule</h1>
      <Alert>{error}</Alert>
      <Card title={label} right={
        <div className="filters">
          {['month', 'week', 'day'].map((v) => <button key={v} className={`btn btn-sm ${view === v ? 'btn-primary' : 'btn-ghost'}`} onClick={() => setView(v)}>{v[0].toUpperCase() + v.slice(1)}</button>)}
          <button className="btn btn-ghost btn-sm" onClick={() => move(-1)}>‹ Prev</button>
          <button className="btn btn-ghost btn-sm" onClick={() => setCursor(new Date())}>Today</button>
          <button className="btn btn-ghost btn-sm" onClick={() => move(1)}>Next ›</button>
          <select value={dentist} onChange={(e) => setDentist(e.target.value)}>
            <option value="">All dentists</option>
            {(lookups?.dentists || []).map((d) => <option key={d.dentist_id} value={d.dentist_id}>{name.dentist(d.dentist_id)}</option>)}
          </select>
          <select value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All statuses</option>
            {Object.keys(COLOR).map((s) => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
          </select>
        </div>
      }>
        {body}
        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 10, fontSize: 12 }}>
          {Object.entries(COLOR).map(([s, c]) => <span key={s}><span style={{ display: 'inline-block', width: 10, height: 10, background: c, borderRadius: 2, marginRight: 4 }} />{s.replace('_', ' ')}</span>)}
        </div>
      </Card>
    </div>
  );
}
