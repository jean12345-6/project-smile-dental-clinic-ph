import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { money } from '../components.jsx';
import { Alert, Card, Empty } from '../ui.jsx';
import { ColumnChart, Donut, LineChart } from '../charts.jsx';

const SALES = '#2563eb';
const PAID = '#16a34a';
const PENDING = '#d97706';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const mlabel = (m) => `${MONTHS[Number(String(m).slice(5, 7)) - 1] || ''} ${String(m).slice(2, 4)}`;

// Horizontal bars. rows: [{label, value, value2?}], colour for the main bar.
function Bars({ rows, color = '#2563eb', color2 = '#16a34a', fmt = (v) => v, labelW = 110, valueW = 150 }) {
  const max = Math.max(1, ...rows.map((r) => Math.max(r.value, r.value2 || 0)));
  if (!rows.length) return <Empty>No data yet.</Empty>;
  return rows.map((r) => (
    // Set the two fixed columns as variables rather than a grid, so a phone can shrink
    // them. As a hard-coded grid they came to 300px of the 390px screen and the amount
    // was pushed against the edge of the card.
    <div className="bar-row" key={r.label} style={{ '--label-w': `${labelW}px`, '--value-w': `${valueW}px` }}>
      <span className="bar-label">{r.label}</span>
      <div className="bar" style={{ height: 'auto', overflow: 'visible', display: 'flex', flexDirection: 'column', gap: 3, background: 'transparent', minWidth: 0 }}>
        <div style={{ height: 9, borderRadius: 4, background: color, width: `${Math.max(1, (r.value / max) * 100)}%` }} />
        {r.value2 !== undefined && <div style={{ height: 9, borderRadius: 4, background: color2, width: `${Math.max(1, (r.value2 / max) * 100)}%` }} />}
      </div>
      <span className="bar-n" style={{ whiteSpace: 'nowrap', textAlign: 'right' }}>{fmt(r.value)}</span>
    </div>
  ));
}

/**
 * Money cards and charts for admins. Superadmin can look at one branch or all.
 * Two columns, so there are no empty gaps. The dashboard passes extra cards to fill each column:
 * leftBottom (under the sales cards), rightTop (above the charts), rightBottom (under the charts).
 */
export default function DashboardFinance({ leftBottom = null, rightTop = null, rightBottom = null }) {
  const { name, lookups } = useAuth();
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
        <h2 className="page-title" style={{ fontSize: 22 }}>Sales and performance</h2>
        <div className="filters">
          <label className="muted small" style={{ margin: 0 }}>Branch</label>
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
      <div className="split">
        <div style={{ minWidth: 0 }}>
          {d && (
            <>
              <Card title="Revenue by month (last 12)">
                {d.byMonth.length === 0 ? <Empty>No invoices yet.</Empty> : (
                  <ColumnChart fmt={money}
                    series={[{ key: 'sales', label: 'Sales', color: SALES }, { key: 'paid', label: 'Paid', color: PAID }]}
                    rows={d.byMonth.map((m) => ({ label: mlabel(m.month), sales: Number(m.sales), paid: Number(m.paid) }))} />
                )}
              </Card>
              {t && Number(t.sales) > 0 && (
                <Card title="Collection">
                  <Donut center={`${t.pct}%`} sub="collected"
                    parts={[
                      { label: 'Paid', value: Number(t.paid), display: money(t.paid), short: `${Math.round((Number(t.paid) / Number(t.sales)) * 100)}%`, color: PAID },
                      { label: 'Pending', value: Number(t.pending), display: money(t.pending), short: `${Math.round((Number(t.pending) / Number(t.sales)) * 100)}%`, color: PENDING },
                    ]} />
                </Card>
              )}
              <Card title="Dentist performance (sales)">
                <Bars color="#d97706" labelW={150} rows={d.byDentist.map((x) => ({ label: x.name, value: Number(x.sales) }))} fmt={money} />
              </Card>
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
          {leftBottom}
        </div>
        <div style={{ minWidth: 0 }}>
          {rightTop}
          {d && (
            <>
              <Card title="Most common procedures">
                <Bars color="#7c3aed" labelW={170} valueW={50} rows={d.procedures.map((p) => ({ label: p.name, value: Number(p.n) }))} />
              </Card>
              <Card title="Appointment trend (last 12)">
                {d.trend.length === 0 ? <Empty>No appointments yet.</Empty> : (
                  <LineChart
                    series={[{ key: 'total', label: 'All appointments', color: SALES }, { key: 'completed', label: 'Completed', color: PAID }]}
                    rows={d.trend.map((m) => ({ label: mlabel(m.month), total: Number(m.total), completed: Number(m.completed) }))} />
                )}
              </Card>
            </>
          )}
          {rightBottom}
        </div>
      </div>
    </>
  );
}
