import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { csv } from '../components.jsx';
import { Alert, Card, Empty, fmt } from '../ui.jsx';
import StaffInbox from './StaffInbox.jsx';

// A log nobody can read is not a log. These two turn the stored row into something a
// person can check: the user's name instead of their id, and the change as words instead
// of the JSON the column actually holds.
const LABELS = {
  patient_id: 'patient', dentist_id: 'dentist', tooth_no: 'tooth', service_id: 'service',
  branch_id: 'branch', appointment_id: 'appointment', condition: 'condition', status: 'status',
  procedure_name: 'procedure', full_name: 'name', quantity: 'quantity', item_name: 'item',
  medicine: 'medicine', total_amount: 'total', balance: 'balance', cost: 'cost',
};

function readable(v, resolve) {
  if (v === null || v === undefined || v === '') return '-';
  let o = v;
  if (typeof o === 'string') {
    const t = o.trim();
    if (!t.startsWith('{') && !t.startsWith('[')) return o;
    try { o = JSON.parse(t); } catch { return o; }
  }
  if (Array.isArray(o)) return o.map((x) => readable(x, resolve)).join('; ');
  if (typeof o !== 'object') return String(o);
  return Object.entries(o)
    .filter(([k]) => !['created_at', 'updated_at', 'id'].includes(k))
    .map(([k, val]) => `${LABELS[k] || k.replace(/_/g, ' ')}: ${val === null ? 'none' : (resolve ? resolve(k, val) : val)}`)
    .join(' · ') || '-';
}

const TABS = {
  inbox: { label: 'Staff inbox' },
  'audit-logs': { label: 'Audit log', cols: [['timestamp', 'When'], ['user_id', 'User'], ['action', 'Action'], ['table_name', 'Table'], ['record_id', 'Record'], ['new_value', 'New value']] },
  'session-logs': { label: 'Session log', cols: [['login_time', 'Login'], ['logout_time', 'Logout'], ['user_id', 'User'], ['ip_address', 'IP'], ['device', 'Device'], ['end_reason', 'Ended by']] },
  'chat-logs': { label: 'All chat logs', cols: [['timestamp', 'When'], ['patient_id', 'Patient'], ['message_in', 'Patient asked'], ['message_out', 'Assistant replied']] },
};

export default function Logs() {
  const { name } = useAuth();
  const [tab, setTab] = useState('inbox');
  const [q, setQ] = useState('');
  const [rows, setRows] = useState(null);
  const [error, setError] = useState([]);
  const [users, setUsers] = useState({});

  // The audit and session logs store a user id; only this page needs the names.
  useEffect(() => {
    if (tab !== 'audit-logs' && tab !== 'session-logs') return;
    // /r/users does not exist: the generic resource list has no users entry, so this
    // 404'd silently and the column fell back to "User 5". The users endpoint is the
    // JSON:API one, where the name is under attributes.
    api('/users', { params: { perPage: 200 } })
      .then((r) => setUsers(Object.fromEntries((r.data || []).map((u) => [String(u.id), u.attributes?.name || u.name]))))
      .catch(() => {});
  }, [tab]);

  const idName = (key, val) => {
    const by = { patient_id: name.patient, dentist_id: name.dentist, service_id: name.service, branch_id: name.branch };
    return (by[key] && by[key](val)) || val;
  };

  const cellOf = (k, r) => {
    if (/time|timestamp/.test(k)) return fmt(r[k]);
    if (k === 'user_id') return users[String(r[k])] || (r[k] ? `User ${r[k]}` : '-');
    if (k === 'patient_id') return name.patient(r[k]) || '-';
    // An id in the detail is as unreadable as an id in the User column was.
    if (k === 'new_value' || k === 'old_value') return readable(r[k], idName);
    return String(r[k] ?? '-');
  };
  const load = () => { if (tab === 'inbox') return; setRows(null); api('/r/' + tab, { params: { q, perPage: 100, ...TABS[tab].fixed } }).then((r) => setRows(r.data)).catch((e) => setError(e.messages)); };
  useEffect(() => { load(); }, [tab]); // eslint-disable-line
  const T = TABS[tab];
  const isInbox = tab === 'inbox';
  return (
    <>
      <h1 className="page-title">Logs</h1>
      <div className="tabs">{Object.entries(TABS).map(([k, v]) => <button key={k} className={tab === k ? 'on' : ''} onClick={() => setTab(k)}>{v.label}</button>)}</div>
      {isInbox && <StaffInbox />}
      {!isInbox && <Alert>{error.join(' ')}</Alert>}
      {!isInbox && <Card title={T.label} right={<div className="filters"><input placeholder="Filter…" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()} /><button className="btn btn-ghost btn-sm" onClick={() => csv(tab + '.csv', rows)}>Export CSV</button></div>}>
        <p className="muted small">Log entries are read-only. Nobody can edit or delete them.</p>
        {rows && rows.length === 0 && <Empty>No entries.</Empty>}
        {rows && rows.length > 0 && (
          <div className="table-wrap"><table>
            <thead><tr>{T.cols.map(([, l]) => <th key={l}>{l}</th>)}</tr></thead>
            <tbody>{rows.map((r, i) => <tr key={i}>{T.cols.map(([k]) => (
              <td key={k} className={k === 'new_value' || k === 'old_value' ? 'wrap-cell' : 'clip'} title={String(r[k] ?? '')}>{cellOf(k, r)}</td>
            ))}</tr>)}</tbody>
          </table></div>
        )}
      </Card>}
    </>
  );
}
