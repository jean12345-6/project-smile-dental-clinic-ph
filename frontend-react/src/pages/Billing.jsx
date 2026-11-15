import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Field, Modal, money } from '../components.jsx';
import { Alert, Card, Empty, fmt } from '../ui.jsx';
import { invoiceStatus } from './MyBilling.jsx';

const BADGE = { paid: 'badge-completed', partial: 'badge-confirmed', unpaid: 'badge-pending', overdue: 'badge-cancelled' };

export default function Billing() {
  const { name } = useAuth();
  const [rows, setRows] = useState(null);
  const [error, setError] = useState([]);
  const [ok, setOk] = useState('');
  const [pay, setPay] = useState(null);
  const [hist, setHist] = useState(null);
  const [f, setF] = useState({ method: 'cash' });
  const [st, setSt] = useState('');
  const [mon, setMon] = useState('');
  const [qry, setQry] = useState('');

  const load = async () => {
    try {
      const first = await api('/r/invoices', { params: { page: 1 } });
      let all = first.data || [];
      const pages = Math.min(Math.ceil((first.meta?.total || 0) / (first.meta?.perPage || 50)), 40);
      for (let p = 2; p <= pages; p += 1) all = all.concat((await api('/r/invoices', { params: { page: p } })).data || []);
      setRows(all);
    } catch (e) { setError(e.messages); }
  };
  useEffect(() => { load(); }, []);

  const doPay = async (e) => {
    e.preventDefault();
    try {
      await api('/r/payments', { method: 'POST', body: { invoice_id: pay.invoice_id, amount: +f.amount, method: f.method } });
      setOk('Payment recorded.'); setPay(null); load();
    } catch (err) { setError(err.messages); setPay(null); }
  };
  const showHist = async (inv) => {
    const r = await api('/r/payments', { params: { invoice_id: inv.invoice_id } });
    setHist({ inv, rows: r.data });
  };
  const shown = (rows || []).filter((r) => (!st || invoiceStatus(r) === st) && (!mon || String(r.date_issued).slice(0, 7) === mon) && (!qry || name.patient(r.patient_id).toLowerCase().includes(qry.toLowerCase())));
  const totalBal = shown.reduce((s, r) => s + Number(r.balance || 0), 0);

  return (
    <>
      <h1 className="page-title">Billing and payments</h1>
      <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
      <Alert kind="success">{ok}</Alert>
      <div className="stats"><div className="stat"><div className="stat-value">{money(totalBal)}</div><div className="stat-label">Unpaid in this list</div></div></div>
      <Card title="Invoices" right={
        <div className="filters">
          <input placeholder="Patient name…" value={qry} onChange={(e) => setQry(e.target.value)} />
          <select value={st} onChange={(e) => setSt(e.target.value)}>
            <option value="">All statuses</option>
            {['unpaid', 'partial', 'paid', 'overdue'].map((x) => <option key={x} value={x}>{x}</option>)}
          </select>
          <input type="month" value={mon} onChange={(e) => setMon(e.target.value)} title="Month" />
          {(st || mon || qry) && <button className="btn btn-ghost btn-sm" onClick={() => { setSt(''); setMon(''); setQry(''); }}>Reset</button>}
        </div>}>
        <p className="muted small">Create an invoice from the Daily queue after a visit is completed. Overdue means unpaid for more than 30 days.</p>
        {rows && shown.length === 0 && <Empty>No invoices match.</Empty>}
        {rows && shown.length > 0 && (
          <div className="table-wrap">
            <table>
              <thead><tr><th>Invoice</th><th>Patient</th><th>Issued</th><th>Total</th><th>Balance</th><th>Status</th><th /></tr></thead>
              <tbody>
                {shown.map((r) => (
                  <tr key={r.invoice_id}>
                    <td>#{r.invoice_id}</td>
                    <td>{name.patient(r.patient_id)}</td>
                    <td>{fmt(r.date_issued).slice(0, 10)}</td>
                    <td>{money(r.total_amount)}</td>
                    <td><strong>{money(r.balance)}</strong></td>
                    <td><span className={`badge ${BADGE[invoiceStatus(r)]}`}>{invoiceStatus(r)}</span></td>
                    <td className="actions">
                      {Number(r.balance) > 0 && <button className="btn btn-primary btn-sm" onClick={() => { setPay(r); setF({ method: 'cash', amount: r.balance }); }}>Record payment</button>}
                      <button className="btn btn-ghost btn-sm" onClick={() => showHist(r)}>Payments</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      {pay && (
        <Modal title={`Pay invoice #${pay.invoice_id}`} onClose={() => setPay(null)}>
          <form onSubmit={doPay}>
            <p className="muted">Remaining balance: <strong>{money(pay.balance)}</strong></p>
            <Field f={{ label: 'Amount (₱)', type: 'number', step: '0.01' }} value={f.amount} options={{}} onChange={(v) => setF({ ...f, amount: v })} />
            <Field f={{ label: 'Method', type: 'select', options: ['cash', 'gcash', 'card', 'bank transfer'].map((m) => ({ value: m, label: m })) }} value={f.method} options={{}} onChange={(v) => setF({ ...f, method: v })} />
            <div className="modal-foot"><button type="button" className="btn btn-ghost" onClick={() => setPay(null)}>Cancel</button><button className="btn btn-primary">Save payment</button></div>
          </form>
        </Modal>
      )}
      {hist && (
        <Modal title={`Payments for invoice #${hist.inv.invoice_id}`} onClose={() => setHist(null)}>
          {hist.rows.length === 0 ? <Empty>No payments yet.</Empty> : hist.rows.map((p) => <div className="row" key={p.payment_id}><span>{fmt(p.paid_at)} · {p.method}</span><strong>{money(p.amount)}</strong></div>)}
        </Modal>
      )}
    </>
  );
}
