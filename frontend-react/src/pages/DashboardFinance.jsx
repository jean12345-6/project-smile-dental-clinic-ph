import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { money } from '../components.jsx';
import { Alert, Card, Empty } from '../ui.jsx';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const mlabel = (m) => `${MONTHS[Number(String(m).slice(5, 7)) - 1] || ''} ${String(m).slice(2, 4)}`;

// Horizontal bars. rows: [{label, value, value2?}], colour for the main bar.
function Bars({ rows, color = '#2563eb', color2 = '#16a34a', fmt = (v) => v }) {
  const max = Math.max(1, ...rows.map((r) => Math.max(r.value, r.value2 || 0)));
  if (!rows.length) return <Empty>No data yet.</Empty>;
  return rows.map((r) => (
    <div className="bar-row" key={r.label}>
      <span className="bar-label" style={{ minWidth: 110 }}>{r.label}</span>
      <div className="bar" style={{ display: 'flex', flexDirection: 'column', gap: 2, background: 'transparent' }}>
        <div style={{ height: 9, borderRadius: 4, background: color, width: `${(r.value / max) * 100}%` }} />
        {r.value2 !== undefined && <div style={{ height: 9, borderRadius: 4, background: color2, width: `${(r.value2 / max) * 100}%` }} />}
      </div>
      <span className="bar-n" style={{ minWidth: 90, textAlign: 'right' }}>{fmt(r.value)}</span>
    </div>
  ));
}

/** Money cards and charts for admins. Superadmin can look at one branch or all. */
export default function DashboardFinance() {
  const { name, lookups, user } = useAuth();
  const [branch, setBranch] = useState('');
  const [d, setD] = useState(null);
  const [error, setError] = useState([]);

  useEffect(() => {
    setD(null);
    api('/stats/finance', { params: branch ? { branch_id: branch } : {} }).then(setD).catch((e) => setError(e.messages));
  }, [branch]);

  const t = d?.totals;
  return (
    <>
      <div className="page-top" style={{ marginTop: 8 }}>
        <h2 className="page-title" style={{ fontSize: 20 }}>Sales and performance</h2>
        <div className="filters">
          <label className="muted small">Branch</label>
          <select value={branch} onChange={(e) => setBranch(e.target.value)}>
            <option value="">All branches</option>
            {(lookups?.branches || []).map((b) => <option key={b.branch_id} value={b.branch_id}>{name.branch(b.branch_id)}</option>)}
          </select>
        </div>
      </div>
      <Alert>{error}</Alert>
      {t && (
        <div className="stats">
          <div className="stat"><div className="stat-label">Total sales</div><div className="stat-value">{money(t.sales)}</div><div className="stat-sub">{t.invoices} invoices</div></div>
          <div className="stat"><div className="stat-label">Paid</div><div className="stat-value">{money(t.paid)}</div><div className="stat-sub">Collection {t.pct}%</div></div>
          <div className="stat"><div className="stat-label">Pending</div><div className="stat-value">{money(t.pending)}</div><div className="stat-sub warn">Outstanding balances</div></div>
        </div>
      )}
      {d && (
        <>
          <div className="split">
            <Card title="Revenue by month (last 12)" right={<span className="muted small">blue = sales, green = paid</span>}>
              <Bars rows={d.byMonth.map((m) => ({ label: mlabel(m.month), value: Number(m.sales), value2: Number(m.paid) }))} fmt={money} />
            </Card>
            <Card title="Most common procedures">
              <Bars color="#7c3aed" rows={d.procedures.map((p) => ({ label: p.name, value: Number(p.n) }))} />
            </Card>
          </div>
          <div className="split">
            <Card title="Dentist performance (sales)">
              <Bars color="#d97706" rows={d.byDentist.map((x) => ({ label: x.name, value: Number(x.sales) }))} fmt={money} />
            </Card>
            <Card title="Appointment trend (last 12)" right={<span className="muted small">blue = all, green = completed</span>}>
              <Bars rows={d.trend.map((m) => ({ label: mlabel(m.month), value: Number(m.total), value2: Number(m.completed) }))} />
            </Card>
          </div>
          <Card title="Sales by branch">
            {d.byBranch.length === 0 ? <Empty>No invoices yet.</Empty> : (
              <div className="table-wrap"><table>
                <thead><tr><th>Branch</th><th>Invoices</th><th>Total sales</th><th>Paid</th><th>Pending</th><th>Collection</th></tr></thead>
                <tbody>{d.byBranch.map((b) => <tr key={b.name}><td>{b.name}</td><td>{b.invoices}</td><td>{money(b.sales)}</td><td>{money(b.paid)}</td><td>{money(b.pending)}</td><td>{b.pct}%</td></tr>)}</tbody>
              </table></div>
            )}
          </Card>
        </>
      )}
    </>
  );
}
