import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Field, Modal, money, useOptions } from '../components.jsx';
import { Alert, Badge, Card, Empty, fmt } from '../ui.jsx';

const today = () => new Date().toISOString().slice(0, 10);

export default function Queue() {
  const { user, name } = useAuth();
  const options = useOptions();
  const [date, setDate] = useState(today());
  const [dentist, setDentist] = useState('');
  const [rows, setRows] = useState(null);
  const [error, setError] = useState([]);
  const [ok, setOk] = useState('');
  const [walk, setWalk] = useState(null);
  const [treat, setTreat] = useState(null);
  const role = user.role;
  const front = ['receptionist', 'clinic_admin', 'superadmin'].includes(role);
  const doc = ['dentist', 'clinic_admin', 'superadmin'].includes(role);

  const load = () => api('/queue', { params: { date, dentistId: dentist } }).then((r) => setRows(r.data)).catch((e) => setError(e.messages));
  useEffect(() => { load(); }, [date, dentist]); // eslint-disable-line

  const move = async (a, status) => {
    setError([]); setOk('');
    try { await api(`/queue/${a.appointment_id}/status`, { method: 'POST', body: { status } }); load(); } catch (e) { setError(e.messages); }
  };
  const invoice = async (a) => {
    setError([]);
    try { const r = await api('/invoices/generate', { method: 'POST', body: { appointmentId: a.appointment_id } }); setOk(`Invoice #${r.data.invoice_id} created for ${money(r.data.total_amount)}. Open Billing to record payment.`); } catch (e) { setError(e.messages); }
  };

  const actions = (a) => {
    const b = [];
    if (['pending', 'confirmed'].includes(a.status)) {
      if (front) b.push(<button key="in" className="btn btn-primary btn-sm" onClick={() => move(a, 'checked_in')}>Check in</button>);
      if (front) b.push(<button key="ns" className="btn btn-danger btn-sm" onClick={() => move(a, 'no_show')}>No-show</button>);
    }
    if (a.status === 'pending' && front) b.push(<button key="cf" className="btn btn-ghost btn-sm" onClick={() => move(a, 'confirmed')}>Confirm</button>);
    if (a.status === 'checked_in' && doc) b.push(<button key="st" className="btn btn-primary btn-sm" onClick={() => move(a, 'in_treatment')}>Start treatment</button>);
    if (a.status === 'in_treatment' && doc) {
      b.push(<button key="tr" className="btn btn-primary btn-sm" onClick={() => setTreat(a)}>Record treatment</button>);
      b.push(<button key="cm" className="btn btn-ghost btn-sm" onClick={() => move(a, 'completed')}>Complete</button>);
    }
    if (a.status === 'completed' && front) b.push(<button key="iv" className="btn btn-ghost btn-sm" onClick={() => invoice(a)}>Create invoice</button>);
    return b;
  };

  return (
    <>
      <div className="page-top">
        <h1 className="page-title">Daily queue</h1>
        <div className="filters">
          <input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
          {role !== 'dentist' && (
            <select value={dentist} onChange={(e) => setDentist(e.target.value)}>
              <option value="">All dentists</option>
              {options.dentist.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          )}
          {front && <button className="btn btn-primary" onClick={() => setWalk({})}>+ Walk-in</button>}
        </div>
      </div>
      <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
      <Alert kind="success">{ok}</Alert>
      <Card>
        {rows && rows.length === 0 && <Empty>No patients in the queue for this day.</Empty>}
        {rows && rows.length > 0 && (
          <div className="table-wrap queue-wrap">
            <table className="queue-table">
              <thead><tr><th className="col-no">#</th><th>Time</th><th>Patient</th><th className="col-service">Service</th><th className="col-dentist">Dentist</th><th>Status</th><th /></tr></thead>
              <tbody>
                {rows.map((a, i) => (
                  <tr key={a.appointment_id}>
                    <td className="col-no">{i + 1}</td>
                    <td>{fmt(a.start_time).slice(11)}{a.booked_via === 'walk-in' && <span className="tag-mini">walk-in</span>}</td>
                    <td>
                      <strong>{a.patient}</strong>{a.risk_flag ? <span className="tag-mini risk">high risk</span> : null}
                      {/* On a phone the service and dentist columns are hidden so that the
                          action buttons fit on screen, so repeat them here. */}
                      <div className="only-narrow muted small">{a.service} · {name.dentist(a.dentist_id)}</div>
                    </td>
                    <td className="col-service">{a.service}</td>
                    <td className="col-dentist">{name.dentist(a.dentist_id)}</td>
                    <td><Badge status={a.status} /></td>
                    <td className="actions">{actions(a)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      {walk && <WalkIn options={options} onClose={() => setWalk(null)} onDone={() => { setWalk(null); load(); }} setError={setError} />}
      {treat && <Treat a={treat} onClose={() => setTreat(null)} onDone={(m) => { setTreat(null); setOk(m); load(); }} setError={setError} />}
    </>
  );
}

function WalkIn({ options, onClose, onDone, setError }) {
  const [f, setF] = useState({});
  const go = async (e) => {
    e.preventDefault();
    try { await api('/queue/walkin', { method: 'POST', body: { patientId: +f.patientId, dentistId: +f.dentistId, serviceId: +f.serviceId } }); onDone(); } catch (err) { setError(err.messages); onClose(); }
  };
  return (
    <Modal title="Add walk-in patient" onClose={onClose}>
      <form onSubmit={go}>
        <Field f={{ label: 'Patient', type: 'select', optionsFrom: 'patient', required: true }} value={f.patientId} options={options} onChange={(v) => setF({ ...f, patientId: v })} />
        <Field f={{ label: 'Dentist', type: 'select', optionsFrom: 'dentist', required: true }} value={f.dentistId} options={options} onChange={(v) => setF({ ...f, dentistId: v })} />
        <Field f={{ label: 'Service', type: 'select', optionsFrom: 'service', required: true }} value={f.serviceId} options={options} onChange={(v) => setF({ ...f, serviceId: v })} />
        <div className="modal-foot"><button type="button" className="btn btn-ghost" onClick={onClose}>Cancel</button><button className="btn btn-primary" disabled={!f.patientId || !f.dentistId || !f.serviceId}>Add to queue</button></div>
      </form>
    </Modal>
  );
}

function Treat({ a, onClose, onDone, setError }) {
  const [f, setF] = useState({ cost: a.price || 0, qty: 1 });
  const [err, setErr] = useState([]);
  const [stock, setStock] = useState([]);
  useEffect(() => { api('/r/inventory', { params: { perPage: 100 } }).then((r) => setStock(r.data || [])).catch(() => {}); }, []);
  const set = (k) => (v) => setF({ ...f, [k]: v });
  const go = async (e) => {
    e.preventDefault();
    setErr([]);
    setError([]);
    if (f.cost === '' || f.cost === undefined || f.cost === null) { setErr(['Please enter the cost of the treatment.']); return; }
    try {
      const t = await api('/r/treatments', { method: 'POST', body: { appointment_id: a.appointment_id, patient_id: a.patient_id, dentist_id: a.dentist_id, tooth_no: f.tooth_no, procedure_name: f.procedure_name || a.service, notes: f.notes, cost: f.cost } });
      if (f.medicine) await api('/r/prescriptions', { method: 'POST', body: { treatment_id: t.data.treatment_id, patient_id: a.patient_id, dentist_id: a.dentist_id, medicine: f.medicine, dosage: f.dosage, frequency: f.frequency, duration: f.duration } });
      if (f.tooth_no && f.condition) await api('/r/dental-chart', { method: 'POST', body: { patient_id: a.patient_id, tooth_no: f.tooth_no, condition: f.condition } });
      let msg = 'Treatment saved.';
      if (f.supply) {
        const item = stock.find((i) => String(i.item_id) === String(f.supply));
        if (item) {
          await api(`/r/inventory/${item.item_id}`, { method: 'PATCH', body: { quantity: Math.max(0, Number(item.quantity) - Number(f.qty || 1)) } });
          msg += ` ${f.qty || 1} ${item.item_name} deducted from stock.`;
        }
      }
      if (f.followup) {
        try { await api(`/queue/${a.appointment_id}/followup`, { method: 'POST', body: { date: f.followup } }); msg += ' Follow-up booked.'; } catch (er) { msg += ' ' + er.messages.join(' '); }
      }
      onDone(msg);
    } catch (x) {
      // Kept open with the message inside, so what the dentist typed is not lost.
      setErr(x.messages || ['Could not save the treatment.']);
    }
  };
  const T = (k, label, type = 'text', required = false) => <Field f={{ label, type, required }} value={f[k]} options={{}} onChange={set(k)} />;
  return (
    <Modal title={`Treatment · ${a.patient}`} onClose={onClose} wide>
      <form onSubmit={go} noValidate>
        {err.length > 0 && <div className="alert alert-error">{err.map((m, i) => <div key={i}>{m}</div>)}</div>}
        <h3 className="sub">Treatment</h3>
        <div className="grid">{T('procedure_name', `Procedure (default: ${a.service})`)}{T('tooth_no', 'Tooth no.')}{T('cost', 'Cost (₱) *', 'number', true)}</div>
        {T('notes', 'Notes', 'textarea')}
        <h3 className="sub">Prescription (optional)</h3>
        <div className="grid">{T('medicine', 'Medicine')}{T('dosage', 'Dosage')}{T('frequency', 'Frequency')}{T('duration', 'Duration')}</div>
        <h3 className="sub">Dental chart (needs a tooth no.)</h3>
        {T('condition', 'Condition of the tooth, e.g. filled, cavity, extracted')}
        <h3 className="sub">Supply used (optional)</h3>
        <div className="grid">
          <Field f={{ label: 'Item', type: 'select', options: stock.map((i) => ({ value: i.item_id, label: `${i.item_name} (${i.quantity} left)` })) }} value={f.supply} options={{}} onChange={set('supply')} />
          {T('qty', 'Quantity used', 'number')}
        </div>
        <h3 className="sub">Follow-up (optional)</h3>
        {T('followup', 'Follow-up date', 'date')}
        <div className="modal-foot"><button type="button" className="btn btn-ghost" onClick={onClose}>Cancel</button><button className="btn btn-primary">Save treatment</button></div>
      </form>
    </Modal>
  );
}
