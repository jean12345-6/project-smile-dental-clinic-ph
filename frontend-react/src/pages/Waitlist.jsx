import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Alert, Badge, Card, Empty, fmt } from '../ui.jsx';

export default function Waitlist() {
  const { isPatient, name } = useAuth();
  const [rows, setRows] = useState(null);
  const [status, setStatus] = useState('');
  const [error, setError] = useState([]);

  useEffect(() => {
    setRows(null);
    api('/waitlist', { params: { status } })
      .then((res) => setRows(res.data || []))
      .catch((e) => setError(e.messages));
  }, [status]);

  return (
    <>
      <h1 className="page-title">{isPatient ? 'My waitlist' : 'Waitlist'}</h1>
      <Card
        title="People waiting for a slot"
        right={
          <select value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All</option>
            <option value="waiting">Waiting</option>
            <option value="offered">Offered</option>
          </select>
        }
      >
        <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
        {isPatient && (
          <p className="muted">
            When someone cancels, the freed time is offered to the first person waiting. Your status changes to
            “offered” and the clinic will contact you.
          </p>
        )}
        {rows && rows.length === 0 && <Empty>The waitlist is empty.</Empty>}
        {rows && rows.length > 0 && (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  {!isPatient && <th>Patient</th>}
                  <th>Service</th>
                  <th>Dentist</th>
                  <th>Branch</th>
                  <th>Preferred date</th>
                  <th>Status</th>
                  <th>Offered at</th>
                  {isPatient && <th />}
                </tr>
              </thead>
              <tbody>
                {rows.map((w) => (
                  <tr key={w.id}>
                    {!isPatient && <td>{name.patient(w.patientId)}</td>}
                    <td>{name.service(w.serviceId)}</td>
                    <td>{w.dentistId ? name.dentist(w.dentistId) : 'Any'}</td>
                    <td>{name.branch(w.branchId)}</td>
                    <td>{w.preferredDate || 'Any date'}</td>
                    <td><Badge status={w.status} /></td>
                    <td>{fmt(w.offeredAt)}</td>
                    {isPatient && (
                      <td>
                        {w.status === 'offered' && (
                          <button className="btn btn-primary btn-sm" onClick={async () => {
                            try { const r = await api(`/waitlist/${w.id}/confirm`, { method: 'POST' }); alert(r.message); window.location.reload(); } catch (e) { setError(e.messages); }
                          }}>Confirm offer</button>
                        )}
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </>
  );
}
