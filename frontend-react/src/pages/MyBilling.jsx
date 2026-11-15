import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Modal, money } from '../components.jsx';
import { Alert, Card, Empty, fmt } from '../ui.jsx';

// Same rule staff see on the Billing page.
export const invoiceStatus = (r) => {
  const total = Number(r.total_amount || 0);
  const bal = Number(r.balance || 0);
  if (bal <= 0.001) return 'paid';
  const age = (Date.now() - new Date(String(r.date_issued).replace(' ', 'T')).getTime()) / 86400000;
  if (age > 30) return 'overdue';
  return bal < total - 0.001 ? 'partial' : 'unpaid';
};
const BADGE = { paid: 'badge-completed', partial: 'badge-confirmed', unpaid: 'badge-pending', overdue: 'badge-cancelled' };

/** Patient portal: view="invoices" or view="transactions". */
export default function MyBilling({ view = 'invoices' }) {
  const { user } = useAuth();
  const [inv, setInv] = useState(null);
  const [tx, setTx] = useState(null);
  const [error, setError] = useState([]);
  const [pay, setPay] = useState(null);
  const [detail, setDetail] = useState(null);

  useEffect(() => {
    setError([]);
    if (view === 'invoices') api('/r/invoices').then((r) => setInv(r.data)).catch((e) => setError(e.messages));
    else api('/my/transactions').then((r) => setTx(r.data)).catch((e) => setError(e.messages));
  }, [view]);

  if (view === 'transactions') {
    const total = (tx || []).reduce((s, t) => s + Number(t.amount || 0), 0);
    return (
      <>
        <h1 className="page-title">My transactions</h1>
        <Alert>{error.join(' ')}</Alert>
        <div className="stats"><div className="stat"><div className="stat-label">Total paid</div><div className="stat-value">{money(total)}</div><div className="stat-sub">{(tx || []).length} payments</div></div></div>
        <Card title="Payment history">
          {tx && tx.length === 0 && <Empty>No payments yet.</Empty>}
          {tx && tx.length > 0 && (
            <div className="table-wrap"><table>
              <thead><tr><th>Date</th><th>Invoice</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
              <tbody>{tx.map((t) => (
                <tr key={t.payment_id}><td>{fmt(t.paid_at).slice(0, 16)}</td><td>#{t.invoice_id}</td><td>{t.method}</td><td>{money(t.amount)}</td><td><span className="badge badge-completed">paid</span></td></tr>
              ))}</tbody>
            </table></div>
          )}
        </Card>
      </>
    );
  }

  const owed = (inv || []).reduce((s, r) => s + Number(r.balance || 0), 0);
  return (
    <>
      <h1 className="page-title">My invoices</h1>
      <Alert>{error.join(' ')}</Alert>
      <div className="stats"><div className="stat"><div className="stat-label">Balance to pay</div><div className="stat-value">{money(owed)}</div><div className="stat-sub">{(inv || []).filter((r) => Number(r.balance) > 0).length} unpaid invoices</div></div></div>
      {owed > 0 && <div className="alert alert-warn">You have a balance of <strong>{money(owed)}</strong>. Please settle it at your next visit or use Pay Now.</div>}
      <Card title="Invoices">
        {inv && inv.length === 0 && <Empty>No invoices yet.</Empty>}
        {inv && inv.length > 0 && (
          <div className="table-wrap"><table>
            <thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Balance</th><th>Status</th><th /></tr></thead>
            <tbody>{inv.map((r) => {
              const st = invoiceStatus(r);
              return (
                <tr key={r.invoice_id}>
                  <td>#{r.invoice_id}</td><td>{fmt(r.date_issued).slice(0, 10)}</td><td>{money(r.total_amount)}</td><td><strong>{money(r.balance)}</strong></td>
                  <td><span className={`badge ${BADGE[st]}`}>{st}</span></td>
                  <td className="actions">
                    <button className="btn btn-ghost btn-sm" onClick={() => setDetail(r)}>View</button>
                    {Number(r.balance) > 0 && <button className="btn btn-primary btn-sm" onClick={() => setPay(r)}>Pay Now</button>}
                  </td>
                </tr>
              );
            })}</tbody>
          </table></div>
        )}
      </Card>
      {detail && (
        <Modal title={`Invoice #${detail.invoice_id}`} onClose={() => setDetail(null)}>
          <div className="row"><span>Patient</span><strong>{user?.name}</strong></div>
          <div className="row"><span>Date issued</span><strong>{fmt(detail.date_issued).slice(0, 10)}</strong></div>
          <div className="row"><span>Total</span><strong>{money(detail.total_amount)}</strong></div>
          <div className="row"><span>Paid</span><strong>{money(Number(detail.total_amount) - Number(detail.balance))}</strong></div>
          <div className="row"><span>Balance</span><strong>{money(detail.balance)}</strong></div>
          <div className="row"><span>Status</span><strong>{invoiceStatus(detail)}</strong></div>
          <div className="modal-foot"><button className="btn btn-ghost" onClick={() => window.print()}>Print</button><button className="btn btn-primary" onClick={() => setDetail(null)}>Close</button></div>
        </Modal>
      )}
      {pay && (
        <Modal title={`Pay invoice #${pay.invoice_id}`} onClose={() => setPay(null)}>
          <p>Amount due: <strong>{money(pay.balance)}</strong></p>
          <p className="muted">Online payment is not connected yet. You can pay at the clinic front desk by cash, GCash, card or bank transfer. The staff will record your payment, and it will then show under My transactions.</p>
          <p className="muted small">Please mention invoice number #{pay.invoice_id}.</p>
          <div className="modal-foot"><button className="btn btn-primary" onClick={() => setPay(null)}>OK</button></div>
        </Modal>
      )}
    </>
  );
}
