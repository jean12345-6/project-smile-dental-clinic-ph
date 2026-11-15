import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Alert, Card, Empty, fmt } from '../ui.jsx';

const ICON = { appointment: '\u{1F4C5}', reminder: '\u{23F0}', message: '\u{1F4AC}', invoice: '\u{1F9FE}', payment: '\u{1F4B3}' };

/** Small red unread count for the sidebar. */
export function NotifBadge() {
  const [n, setN] = useState(0);
  useEffect(() => {
    let alive = true;
    const load = () => api('/my/notifications').then((r) => alive && setN(r.unread || 0)).catch(() => {});
    load();
    const t = setInterval(load, 60000);
    window.addEventListener('notif-changed', load);
    return () => { alive = false; clearInterval(t); window.removeEventListener('notif-changed', load); };
  }, []);
  if (!n) return null;
  return <span style={{ marginLeft: 8, background: '#dc2626', color: '#fff', borderRadius: 99, fontSize: 11, padding: '1px 7px', fontWeight: 700 }}>{n}</span>;
}

export default function MyNotifications() {
  const [rows, setRows] = useState(null);
  const [unread, setUnread] = useState(0);
  const [error, setError] = useState([]);

  const load = () => api('/my/notifications').then((r) => { setRows(r.data); setUnread(r.unread); window.dispatchEvent(new Event('notif-changed')); }).catch((e) => setError(e.messages));
  useEffect(() => { load(); }, []);

  const mark = async (body) => {
    try { await api('/my/notifications/read', { method: 'POST', body }); load(); } catch (e) { setError(e.messages); }
  };

  return (
    <>
      <h1 className="page-title">Notifications</h1>
      <Alert>{error.join(' ')}</Alert>
      <Card title={`${unread} unread`} right={unread > 0 && <button className="btn btn-ghost btn-sm" onClick={() => mark({ all: true })}>Mark all as read</button>}>
        {rows && rows.length === 0 && <Empty>No notifications yet.</Empty>}
        {(rows || []).map((n) => (
          <div className="row" key={n.key} style={{ opacity: n.read ? 0.6 : 1, alignItems: 'flex-start' }}>
            <span>
              <strong>{ICON[n.type] || ''} {n.title}</strong>{!n.read && <span style={{ marginLeft: 8, background: '#dc2626', color: '#fff', borderRadius: 99, fontSize: 10, padding: '1px 6px' }}>new</span>}
              <div className="muted small">{n.body}</div>
              <div className="muted small">{fmt(n.at)}</div>
            </span>
            {!n.read && <button className="btn btn-ghost btn-sm" onClick={() => mark({ keys: [n.key] })}>Mark read</button>}
          </div>
        ))}
      </Card>
    </>
  );
}
