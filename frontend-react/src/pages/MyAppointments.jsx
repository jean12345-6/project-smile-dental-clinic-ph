import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Alert, Badge, Card, Empty, fmt, rel } from '../ui.jsx';

export default function MyAppointments() {
  const { lookups, name } = useAuth();
  const pid = lookups?.patientId;
  const [rows, setRows] = useState(null);
  const [error, setError] = useState([]);
  const [busyId, setBusyId] = useState(null);

  const load = () => {
    if (!pid) {
      setRows([]);
      return;
    }
    api(`/patients/${pid}/appointments`)
      .then((res) => setRows(res.data || []))
      .catch((e) => setError(e.messages));
  };
  useEffect(load, [pid]);

  const cancel = async (id) => {
    if (!window.confirm('Cancel this appointment?')) return;
    setBusyId(id);
    setError([]);
    try {
      await api(`/appointments/${id}`, { method: 'PATCH', body: { data: { attributes: { status: 'cancelled' } } } });
      load();
    } catch (e) {
      setError(e.messages);
    } finally {
      setBusyId(null);
    }
  };

  return (
    <>
      <h1 className="page-title">My appointments</h1>
      <Card title="Upcoming and past" right={<button className="btn btn-primary" onClick={() => window.dispatchEvent(new CustomEvent('open-chat', { detail: { action: 'book', label: 'Book an appointment' } }))}>+ Book</button>}>
        <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
        {!pid && <Empty>Your account is not linked to a patient record yet.</Empty>}
        {rows && rows.length === 0 && pid && <Empty>No appointments yet.</Empty>}
        {rows && rows.length > 0 && (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>When</th>
                  <th>Service</th>
                  <th>Dentist</th>
                  <th>Branch</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {rows.map((a) => (
                  <tr key={a.id}>
                    <td>{fmt(a.attributes.startTime)}</td>
                    <td>{name.service(rel(a, 'service'))}</td>
                    <td>{name.dentist(rel(a, 'dentist'))}</td>
                    <td>{name.branch(rel(a, 'branch'))}</td>
                    <td><Badge status={a.attributes.status} /></td>
                    <td>
                      {['pending', 'confirmed'].includes(a.attributes.status) && (
                        <button className="btn btn-danger btn-sm" disabled={busyId === a.id} onClick={() => cancel(a.id)}>
                          Cancel
                        </button>
                      )}
                    </td>
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
