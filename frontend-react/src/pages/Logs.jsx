import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { csv } from '../components.jsx';
import { Alert, Card, Empty, fmt } from '../ui.jsx';

const TABS = {
  'audit-logs': { label: 'Audit log', cols: [['timestamp', 'When'], ['user_id', 'User'], ['action', 'Action'], ['table_name', 'Table'], ['record_id', 'Record'], ['new_value', 'New value']] },
  'session-logs': { label: 'Session log', cols: [['login_time', 'Login'], ['logout_time', 'Logout'], ['user_id', 'User'], ['ip_address', 'IP'], ['device', 'Device'], ['end_reason', 'Ended by']] },
  'chat-logs': { label: 'Chats sent to staff', cols: [['timestamp', 'When'], ['patient_id', 'Patient'], ['message_in', 'Patient asked'], ['message_out', 'Assistant replied']], fixed: { escalated: 1 } },
};

export default function Logs() {
  const [tab, setTab] = useState('audit-logs');
  const [q, setQ] = useState('');
  const [rows, setRows] = useState(null);
  const [error, setError] = useState([]);
  const load = () => { setRows(null); api('/r/' + tab, { params: { q, perPage: 100, ...TABS[tab].fixed } }).then((r) => setRows(r.data)).catch((e) => setError(e.messages)); };
  useEffect(() => { load(); }, [tab]); // eslint-disable-line
  const T = TABS[tab];
  return (
    <>
      <h1 className="page-title">Logs</h1>
      <div className="tabs">{Object.entries(TABS).map(([k, v]) => <button key={k} className={tab === k ? 'on' : ''} onClick={() => setTab(k)}>{v.label}</button>)}</div>
      <Alert>{error.join(' ')}</Alert>
      <Card title={T.label} right={<div className="filters"><input placeholder="Filter…" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()} /><button className="btn btn-ghost btn-sm" onClick={() => csv(tab + '.csv', rows)}>Export CSV</button></div>}>
        <p className="muted small">Log entries are read-only. Nobody can edit or delete them.</p>
        {rows && rows.length === 0 && <Empty>No entries.</Empty>}
        {rows && rows.length > 0 && (
          <div className="table-wrap"><table>
            <thead><tr>{T.cols.map(([, l]) => <th key={l}>{l}</th>)}</tr></thead>
            <tbody>{rows.map((r, i) => <tr key={i}>{T.cols.map(([k]) => <td key={k} className="clip">{/time|timestamp/.test(k) ? fmt(r[k]) : String(r[k] ?? '-')}</td>)}</tr>)}</tbody>
          </table></div>
        )}
      </Card>
    </>
  );
}
