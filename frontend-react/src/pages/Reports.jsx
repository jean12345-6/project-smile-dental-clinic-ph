import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { csv, money } from '../components.jsx';
import { Alert, Card, Empty } from '../ui.jsx';

const first = () => new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
const last = () => new Date().toISOString().slice(0, 10);

function Table({ rows, cols }) {
  if (!rows?.length) return <Empty>No data for this range.</Empty>;
  return (
    <div className="table-wrap"><table>
      <thead><tr>{cols.map(([, l]) => <th key={l}>{l}</th>)}</tr></thead>
      <tbody>{rows.map((r, i) => <tr key={i}>{cols.map(([k]) => <td key={k}>{r[k] ?? 0}</td>)}</tr>)}</tbody>
    </table></div>
  );
}

export default function Reports() {
  const [from, setFrom] = useState(first());
  const [to, setTo] = useState(last());
  const [d, setD] = useState(null);
  const [error, setError] = useState([]);
  const load = () => api('/reports', { params: { from, to } }).then(setD).catch((e) => setError(e.messages));
  useEffect(() => { load(); }, []); // eslint-disable-line

  const totals = d ? d.byDay.reduce((a, r) => ({ total: a.total + +r.total, done: a.done + +r.completed, canc: a.canc + +r.cancelled, ns: a.ns + +r.no_show }), { total: 0, done: 0, canc: 0, ns: 0 }) : null;
  return (
    <>
      <div className="page-top no-print">
        <h1 className="page-title">Reports</h1>
        <div className="filters">
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
          <button className="btn btn-primary" onClick={load}>Show</button>
          <button className="btn btn-ghost" onClick={() => window.print()}>Print</button>
        </div>
      </div>
      <Alert>{error.join(' ')}</Alert>
      {d && (
        <>
          <p className="print-only">SmileSync report · {d.from} to {d.to}</p>
          <div className="stats">
            <div className="stat"><div className="stat-value">{totals.total}</div><div className="stat-label">Appointments</div></div>
            <div className="stat"><div className="stat-value">{totals.done}</div><div className="stat-label">Completed</div></div>
            <div className="stat"><div className="stat-value">{totals.canc}</div><div className="stat-label">Cancelled</div></div>
            <div className="stat"><div className="stat-value">{totals.ns}</div><div className="stat-label">No-shows</div></div>
            {/* The server sends no revenue to a receptionist or a dentist, so do not show a tile. */}
            {d.revenue !== null && d.revenue !== undefined && (
              <div className="stat"><div className="stat-value">{money(d.revenue)}</div><div className="stat-label">Revenue</div></div>
            )}
          </div>
          <Card title="By day" right={<button className="btn btn-ghost btn-sm no-print" onClick={() => csv('report-by-day.csv', d.byDay)}>Export CSV</button>}>
            <Table rows={d.byDay} cols={[['date', 'Date'], ['total', 'Total'], ['completed', 'Completed'], ['cancelled', 'Cancelled'], ['no_show', 'No-show']]} />
          </Card>
          <div className="two">
            <Card title="By dentist" right={<button className="btn btn-ghost btn-sm no-print" onClick={() => csv('report-by-dentist.csv', d.byDentist)}>Export CSV</button>}>
              <Table rows={d.byDentist} cols={[['name', 'Dentist'], ['total', 'Total'], ['completed', 'Completed']]} />
            </Card>
            <Card title="By branch" right={<button className="btn btn-ghost btn-sm no-print" onClick={() => csv('report-by-branch.csv', d.byBranch)}>Export CSV</button>}>
              <Table rows={d.byBranch} cols={[['name', 'Branch'], ['total', 'Total'], ['completed', 'Completed']]} />
            </Card>
          </div>
          <Card title="By month (last 12)">
            <Table rows={d.byMonth} cols={[['month', 'Month'], ['total', 'Total'], ['completed', 'Completed']]} />
          </Card>
        </>
      )}
    </>
  );
}
