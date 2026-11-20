import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Field, Modal, money } from '../components.jsx';
import { Alert, Card, Empty, fmt } from '../ui.jsx';

export default function MyRecords() {
  const { lookups } = useAuth();
  const [d, setD] = useState(null);
  const [error, setError] = useState([]);
  const [ok, setOk] = useState('');
  const [rate, setRate] = useState(null);
  const [f, setF] = useState({ rating: 5 });
  const pid = lookups?.patientId;

  const load = () =>
    Promise.all([api('/r/invoices'), api('/r/treatments'), api('/r/prescriptions'), api('/r/dental-chart'), api('/patients/' + pid + '/appointments', { params: { 'filter[status]': 'completed' } }), api('/r/feedback'), pid ? api('/patient-summary/' + pid) : null])
      .then(([inv, tx, rx, ch, ap, fb, sm]) => setD({ inv: inv.data, tx: tx.data, rx: rx.data, ch: ch.data, ap: ap.data || [], fb: fb.data, sm }))
      .catch((e) => setError(e.messages));
  useEffect(() => { if (pid) load(); }, [pid]); // eslint-disable-line

  const submit = async (e) => {
    e.preventDefault();
    try { await api('/r/feedback', { method: 'POST', body: { appointment_id: rate, rating: +f.rating, comment: f.comment } }); setRate(null); setOk('Thank you for your feedback!'); load(); } catch (er) { setError(er.messages); setRate(null); }
  };
  if (!pid) return <Alert>This account has no patient record.</Alert>;
  if (!d) return <Alert>{error.join(' ')}</Alert>;
  const owed = d.inv.reduce((s, i) => s + Number(i.balance), 0);
  const rated = new Set(d.fb.map((x) => String(x.appointment_id)));
  const toRate = d.ap.filter((a) => String(a.attributes?.status).toLowerCase() === 'completed' && !rated.has(String(a.id)));

  return (
    <>
      <h1 className="page-title">My records</h1>
      <Alert>{error.join(' ')}</Alert>
      <Alert kind="success">{ok}</Alert>
      <div className="stats"><div className="stat"><div className="stat-value">{money(owed)}</div><div className="stat-label">Unpaid balance</div></div><div className="stat"><div className="stat-value">{d.tx.length}</div><div className="stat-label">Treatments on file</div></div></div>
      {d.sm && <Card title="Summary of my visits"><div className="alert alert-warn">{d.sm.label}</div><p>{d.sm.summary}</p></Card>}
      {toRate.length > 0 && (
        <Card title="How was your visit?">
          {toRate.map((a) => <div className="row" key={a.id}><span>Visit on {fmt(a.attributes.startTime).slice(0, 10)}</span><button className="btn btn-primary btn-sm" onClick={() => { setRate(a.id); setF({ rating: 5 }); }}>Rate</button></div>)}
        </Card>
      )}
      <div className="two">
        <Card title="Treatments">{d.tx.length === 0 ? <Empty>None yet.</Empty> : d.tx.map((t) => <div className="row" key={t.treatment_id}><span>{t.procedure_name}{t.tooth_no ? ` · tooth ${t.tooth_no}` : ''}<div className="muted small">{fmt(t.treatment_date).slice(0, 10)}</div></span><strong>{money(t.cost)}</strong></div>)}</Card>
        <Card title="Prescriptions">{d.rx.length === 0 ? <Empty>None yet.</Empty> : d.rx.map((p) => <div className="row" key={p.prescription_id}><span>{p.medicine}<div className="muted small">{[p.dosage, p.frequency, p.duration].filter(Boolean).join(' · ')}</div></span></div>)}</Card>
      </div>
      <div className="two">
        <Card title="Dental chart">{d.ch.length === 0 ? <Empty>No chart entries yet.</Empty> : d.ch.map((c) => <div className="row" key={c.chart_id}><span>Tooth {c.tooth_no}</span><span>{c.condition}</span></div>)}</Card>
        <Card title="Invoices">{d.inv.length === 0 ? <Empty>No invoices.</Empty> : d.inv.map((i) => <div className="row" key={i.invoice_id}><span>#{i.invoice_id} · {money(i.total_amount)}</span><strong>{Number(i.balance) > 0 ? `Owe ${money(i.balance)}` : 'Paid'}</strong></div>)}</Card>
      </div>
      {rate && (
        <Modal title="Rate your visit" onClose={() => setRate(null)}>
          <form onSubmit={submit}>
            <Field f={{ label: 'Rating', type: 'select', options: [5, 4, 3, 2, 1].map((n) => ({ value: n, label: `${n} out of 5` })) }} value={f.rating} options={{}} onChange={(v) => setF({ ...f, rating: v })} />
            <Field f={{ label: 'Comment', type: 'textarea' }} value={f.comment} options={{}} onChange={(v) => setF({ ...f, comment: v })} />
            <div className="modal-foot"><button type="button" className="btn btn-ghost" onClick={() => setRate(null)}>Cancel</button><button className="btn btn-primary">Send</button></div>
          </form>
        </Modal>
      )}
    </>
  );
}