import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Alert, Badge, Card, Empty, day, fmt, rel } from '../ui.jsx';

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
  const [meta, setMeta] = useState(null);
  const [status, setStatus] = useState('');
  const [date, setDate] = useState('');
  const [error, setError] = useState([]);
  const [busyId, setBusyId] = useState(null);

  const load = () => {
    api('/appointments', { params: { page } })
      .then((res) => {
        setRows(res.data || []);
        setMeta(res.meta || null);
      })
      .catch((e) => setError(e.messages));
  };
  useEffect(load, [page]);

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

  const shown = (rows || []).filter(
    (a) => (!status || a.attributes.status === status) && (!date || day(a.attributes.startTime) === date)
  );

  return (
    <>
      <h1 className="page-title">Appointments</h1>
      <Card
        title="All appointments"
        right={
          <div className="filters">
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">All statuses</option>
              {['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'].map((s) => (
                <option key={s} value={s}>{s.replace('_', ' ')}</option>
              ))}
            </select>
            <input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
          </div>
        }
      >
        <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
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
                    <td>{fmt(a.attributes.startTime)}</td>
                    <td>{name.patient(rel(a, 'patient'))}</td>
                    <td>{name.service(rel(a, 'service'))}</td>
                    <td>{name.dentist(rel(a, 'dentist'))}</td>
                    <td>{name.branch(rel(a, 'branch'))}</td>
                    <td><Badge status={a.attributes.status} /></td>
                    <td className="actions">
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
        {meta?.last_page > 1 && (
          <div className="pager">
            <button className="btn btn-ghost btn-sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>← Prev</button>
            <span>Page {meta.current_page} of {meta.last_page}</span>
            <button className="btn btn-ghost btn-sm" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Next →</button>
          </div>
        )}
      </Card>
    </>
  );
}
