import React, { useEffect, useRef, useState } from 'react';
import { api } from '../api.js';
import { Alert, Card, Empty, fmt } from '../ui.jsx';

/** Staff read the chats that the assistant passed on, and reply. The reply appears in the patient's assistant panel. */
export default function StaffInbox() {
  const [list, setList] = useState(null);
  const [filter, setFilter] = useState('open');
  const [sel, setSel] = useState(null);
  const [thread, setThread] = useState(null);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState([]);
  const end = useRef(null);

  const loadList = () => api('/chat/inbox').then((r) => setList(r.data)).catch((e) => setError(e.messages));
  const loadThread = (id) => api(`/chat/inbox/${id}`).then(setThread).catch((e) => setError(e.messages));

  useEffect(() => { loadList(); const t = setInterval(loadList, 15000); return () => clearInterval(t); }, []);
  useEffect(() => { if (sel) { setThread(null); loadThread(sel); } }, [sel]);
  useEffect(() => { end.current?.scrollIntoView({ behavior: 'smooth' }); }, [thread]);

  const send = async (e) => {
    e.preventDefault();
    const t = text.trim();
    if (!t || !sel) return;
    setBusy(true); setError([]);
    try {
      await api(`/chat/inbox/${sel}/reply`, { method: 'POST', body: { message: t } });
      setText('');
      await Promise.all([loadThread(sel), loadList()]);
    } catch (err) { setError(err.messages || ['Could not send the reply.']); }
    setBusy(false);
  };

  const shown = (list || []).filter((t) => filter === 'all' || (filter === 'open' ? !t.answered : t.answered));
  const openCount = (list || []).filter((t) => !t.answered).length;

  return (
    <>
      <Alert>{error.join(' ')}</Alert>
      <div className="tabs" style={{ marginBottom: 12 }}>
        {[['open', `Needs a reply (${openCount})`], ['answered', 'Answered'], ['all', 'All']].map(([k, l]) => <button key={k} className={filter === k ? 'on' : ''} onClick={() => setFilter(k)}>{l}</button>)}
      </div>
      {/* Two fixed columns do not fit a phone; on a narrow screen they stack (see CSS). */}
      <div className="inbox-split">
        <Card title="Conversations">
          {list === null && <p className="muted">Loading…</p>}
          {list && shown.length === 0 && <Empty>{filter === 'open' ? 'Nothing is waiting for a reply.' : 'No conversations.'}</Empty>}
          {shown.map((t) => (
            <button key={t.patientId} onClick={() => setSel(t.patientId)}
              style={{ display: 'block', width: '100%', textAlign: 'left', padding: '12px 14px', marginBottom: 8, borderRadius: 12, cursor: 'pointer', font: 'inherit', color: 'var(--ink)', background: sel === t.patientId ? 'var(--gold-soft)' : 'var(--card2)', border: `1px solid ${sel === t.patientId ? 'var(--gold)' : 'var(--line)'}` }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                <strong>{t.name}</strong>
                <span className={`badge ${t.answered ? 'badge-completed' : 'badge-pending'}`}>{t.answered ? 'Answered' : 'Needs reply'}</span>
              </div>
              <div className="muted small clip" style={{ marginTop: 4 }}>{t.lastMessage}</div>
              <div className="muted small">{fmt(t.lastAt)}</div>
            </button>
          ))}
        </Card>
        <Card title={thread?.patient?.name ? `Chat with ${thread.patient.name}` : 'Conversation'}>
          {!sel && <Empty>Pick a conversation on the left.</Empty>}
          {sel && !thread && <p className="muted">Loading…</p>}
          {thread && (
            <>
              <div style={{ maxHeight: 420, overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 10, padding: '4px 2px 10px' }}>
                {thread.messages.map((m, i) => (
                  <div key={i} className={`bubble-row ${m.from === 'staff' ? 'me' : 'bot'}`}>
                    <div className={`bubble ${m.from === 'staff' ? 'me' : 'bot'}`} style={m.from === 'assistant' ? { opacity: 0.7, fontSize: 13 } : undefined}>
                      <div className="muted small" style={{ marginBottom: 2, color: 'inherit', opacity: 0.8 }}>{m.from === 'staff' ? 'You (staff)' : m.from === 'patient' ? 'Patient' : 'Assistant'} · {fmt(m.at)}</div>
                      {m.text}
                      {m.escalated && <div className="tag">Passed to staff</div>}
                    </div>
                  </div>
                ))}
                <div ref={end} />
              </div>
              <form onSubmit={send} style={{ display: 'flex', gap: 10, marginTop: 8 }}>
                <textarea rows={2} style={{ flex: 1 }} maxLength={1000} placeholder="Type your reply to the patient…" value={text} onChange={(e) => setText(e.target.value)} />
                <button className="btn btn-primary" disabled={busy || !text.trim()}>{busy ? 'Sending…' : 'Send reply'}</button>
              </form>
              <p className="muted small">The patient sees this in their assistant panel.</p>
            </>
          )}
        </Card>
      </div>
    </>
  );
}
