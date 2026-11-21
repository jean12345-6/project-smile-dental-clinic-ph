import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Alert, Badge, Card, Empty, day, fmt, rel, when } from '../ui.jsx';
import { Modal } from '../components.jsx';

// What staff can do next, per status
const ACTIONS = {
  pending: [['confirmed', 'Confirm', 'primary'], ['cancelled', 'Cancel', 'danger']],
  confirmed: [['checked_in', 'Check in', 'primary'], ['no_show', 'No-show', 'ghost'], ['cancelled', 'Cancel', 'danger']],
  checked_in: [['completed', 'Complete', 'primary']],
};

export default function Appointments() {
  const { name } = useAuth();
  const [rows, setRows] = useState(null);
  const [page, setPage] = useState(1);
  const PER = 25;
  const [draft, setDraft] = useState('');
  const [query, setQuery] = useState('');
  const [sort, setSort] = useState('when_desc');
  const [status, setStatus] = useState('');
  const [date, setDate] = useState('');
  const [year, setYear] = useState('');
  const [error, setError] = useState([]);
  const [busyId, setBusyId] = useState(null);
  const [view, setView] = useState(null);
  const openView = async (a) => {
    setView({ a, detail: null, patient: null });
    let detail = null; let patient = null;
    try { detail = (await api(`/appointments/${a.id}`)).data || null; } catch (e) { detail = null; }
    try { patient = (await api(`/patients/${rel(a, 'patient')}`)).data || null; } catch (e) { patient = null; }
    setView({ a, detail, patient });
  };

  // loads every page (5 at a time), so the search and the A-Z order cover all appointments. The first page shows at once.
  const [loaded, setLoaded] = useState(null); // [done, total] while pages are still coming in
  const load = async () => {
    try {
      const first = await api('/appointments', { params: { page: 1 } });
      let all = first.data || [];
      const last = Math.min(first.meta?.last_page || 1, 60);
      setRows(all);
      for (let p = 2; p <= last; p += 5) {
        setLoaded([p - 1, last]);
        const batch = [];
        for (let q = p; q < p + 5 && q <= last; q += 1) batch.push(api('/appointments', { params: { page: q } }));
        const res = await Promise.all(batch);
        res.forEach((r) => { all = all.concat(r.data || []); });
        setRows(all);
      }
      setLoaded(null);
    } catch (e) { setError(e.messages); setLoaded(null); }
  };
  useEffect(() => { load(); }, []);

  const change = async (id, next) => {
    setBusyId(id);
    setError([]);
    try {
      await api(`/appointments/${id}`, { method: 'PATCH', body: { data: { attributes: { status: next } } } });
      load();
    } catch (e) {
      setError(e.messages);
    } finally {
      setBusyId(null);
    }
  };

  const text = (a) => [name.patient(rel(a, 'patient')), name.service(rel(a, 'service')), name.dentist(rel(a, 'dentist')), name.branch(rel(a, 'branch')), a.attributes.status, a.attributes.startTime].join(' ').toLowerCase();
  const key = {
    when: (a) => a.attributes.startTime || '',
    patient: (a) => name.patient(rel(a, 'patient')).toLowerCase(),
    service: (a) => name.service(rel(a, 'service')).toLowerCase(),
    dentist: (a) => name.dentist(rel(a, 'dentist')).toLowerCase(),
  };
  const [by, dir] = sort.split('_');
  const years = [...new Set((rows || []).map((a) => String(a.attributes.startTime || '').slice(0, 4)).filter(Boolean))].sort().reverse();
  const filtered = (rows || [])
    .filter((a) => (!status || a.attributes.status === status) && (!date || day(a.attributes.startTime) === date) && (!year || String(a.attributes.startTime || '').slice(0, 4) === year) && (!query || text(a).includes(query.toLowerCase())))
    .sort((x, y) => String(key[by](x)).localeCompare(String(key[by](y))) * (dir === 'desc' ? -1 : 1));
  const pages = Math.max(1, Math.ceil(filtered.length / PER));
  const shown = filtered.slice((page - 1) * PER, page * PER);
  const doSearch = () => { setQuery(draft.trim()); setPage(1); };

  return (
    <>
      <h1 className="page-title">Appointments</h1>
      <Card
        title="All appointments"
        right={
          <div className="filters">
            <input placeholder="Search patient, service, dentist…" value={draft} onChange={(e) => setDraft(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && doSearch()} />
            <button className="btn btn-ghost btn-sm" onClick={doSearch}>Search</button>
            {query && <button className="btn btn-ghost btn-sm" onClick={() => { setDraft(''); setQuery(''); setPage(1); }}>Clear</button>}
            <select value={sort} onChange={(e) => { setSort(e.target.value); setPage(1); }}>
              <option value="when_desc">Newest first</option>
              <option value="when_asc">Oldest first</option>
              <option value="patient_asc">Patient A–Z</option>
              <option value="patient_desc">Patient Z–A</option>
              <option value="service_asc">Service A–Z</option>
              <option value="dentist_asc">Dentist A–Z</option>
            </select>
            <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}>
              <option value="">All statuses</option>
              {['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'].map((s) => (
                <option key={s} value={s}>{s.replace('_', ' ')}</option>
              ))}
            </select>
            <select value={year} onChange={(e) => { setYear(e.target.value); setPage(1); }}><option value="">All years</option>{years.map((y) => <option key={y} value={y}>{y}</option>)}</select>
            <input type="date" value={date} onChange={(e) => { setDate(e.target.value); setPage(1); }} />
          </div>
        }
      >
        <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
        {rows === null && <p className="muted">Loading appointments…</p>}
        {loaded && <p className="muted small">Loading all appointments… {loaded[0]} of {loaded[1]} pages. Search and sort will cover everything once this finishes.</p>}
        {rows && shown.length === 0 && <Empty>No appointments match.</Empty>}
        {shown.length > 0 && (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>When</th>
                  <th>Patient</th>
                  <th>Service</th>
                  <th>Dentist</th>
                  <th>Branch</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {shown.map((a) => (
                  <tr key={a.id}>
                    <td>{when(a.attributes.startTime)}</td>
                    <td>{name.patient(rel(a, 'patient'))}</td>
                    <td>{name.service(rel(a, 'service'))}</td>
                    <td>{name.dentist(rel(a, 'dentist'))}</td>
                    <td>{name.branch(rel(a, 'branch'))}</td>
                    <td><Badge status={a.attributes.status} /></td>
                    <td className="actions">
                      <button className="btn btn-ghost btn-sm" onClick={() => openView(a)}>View</button>
                      {(ACTIONS[a.attributes.status] || []).map(([next, label, kind]) => (
                        <button
                          key={next}
                          className={`btn btn-${kind} btn-sm`}
                          disabled={busyId === a.id}
                          onClick={() => change(a.id, next)}
                        >
                          {label}
                        </button>
                      ))}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {pages > 1 && (
          <div className="pager">
            <button className="btn btn-ghost btn-sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>← Prev</button>
            <span>Page {page} of {pages} · {filtered.length} appointments</span>
            <button className="btn btn-ghost btn-sm" disabled={page >= pages} onClick={() => setPage(page + 1)}>Next →</button>
          </div>
        )}
      </Card>
      {/* MODAL-MOVED */}
      {view && (
          <Modal title={'Appointment - ' + name.patient(rel(view.a, 'patient'))} onClose={() => setView(null)}>
            {[
              ['Status', <Badge key="s" status={view.a.attributes.status} />],
              ['Starts', when(view.a.attributes.startTime)],
              ['Ends', view.a.attributes.endTime ? fmt(view.a.attributes.endTime) : '-'],
              ['Patient', name.patient(rel(view.a, 'patient'))],
              ['Contact', view.patient ? (view.patient.attributes.contactNo || '-') : '...'],
              ['Email', view.patient ? (view.patient.attributes.email || '-') : '...'],
              ['Birthdate', view.patient ? (String(view.patient.attributes.birthdate || '-').slice(0, 10)) : '...'],
              ['Service', name.service(rel(view.a, 'service'))],
              ['Dentist', name.dentist(rel(view.a, 'dentist'))],
              ['Branch', name.branch(rel(view.a, 'branch'))],
              ['Booked via', view.a.attributes.bookedVia || '-'],
              ['Risk flag', view.a.attributes.riskFlag || '-'],
              ['Checked in at', view.detail ? (view.detail.attributes.checkInTime ? fmt(view.detail.attributes.checkInTime) : '-') : '...'],
              ['Booked on', view.detail ? (view.detail.attributes.createdAt ? fmt(view.detail.attributes.createdAt) : '-') : '...'],
            ].map(([k, v]) => (
              <div key={k} style={{ display: 'flex', gap: 12, padding: '6px 0', borderBottom: '1px solid var(--line, #eee)' }}>
                <strong style={{ width: 120, flex: 'none' }}>{k}</strong><span>{v}</span>
              </div>
            ))}
          </Modal>
        )}
    </>
  );
}
