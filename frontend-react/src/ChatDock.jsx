import React, { useEffect, useRef, useState } from 'react';
import { api } from './api.js';
import { useAuth } from './auth.jsx';

/** The assistant lives in a panel on the right side of every patient page (FR-10 to FR-16). It keeps the conversation while the patient moves between pages. */
export default function ChatDock() {
  const { user, isPatient } = useAuth();
  const wide = typeof window !== 'undefined' && window.innerWidth >= 1100;
  const [open, setOpen] = useState(wide);
  const [msgs, setMsgs] = useState([]);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const started = useRef(false);
  const end = useRef(null);
  const openRef = useRef(open);
  const [unread, setUnread] = useState(0);
  const seenKey = `staff-seen-${user?.id ?? 'x'}`;
  const lastId = useRef(0);

  // replies from the clinic staff arrive here (checked every few seconds)
  const poll = async () => {
    try {
      const r = await api('/chat/replies', { params: { after: lastId.current } });
      const rows = r.data || [];
      if (!rows.length) return;
      lastId.current = Math.max(...rows.map((x) => x.id));
      try { localStorage.setItem(seenKey, String(lastId.current)); } catch { /* ignore */ }
      setMsgs((m) => [...m.map((x) => ({ ...x, options: [] })), ...rows.map((x) => ({ from: 'staff', text: x.text, options: [{ label: 'Main menu', action: 'menu', data: {} }] }))]);
      if (!openRef.current) setUnread((n) => n + rows.length);
    } catch { /* the next check will try again */ }
  };

  const send = async (body, shown) => {
    if (shown) setMsgs((m) => [...m.map((x) => ({ ...x, options: [] })), { from: 'me', text: shown }]);
    setBusy(true);
    try {
      const r = await api('/chat', { method: 'POST', body });
      setMsgs((m) => [...m.map((x) => ({ ...x, options: [] })), { from: 'bot', text: r.reply, options: r.options, escalated: r.escalated }]);
    } catch (e) {
      setMsgs((m) => [...m, { from: 'bot', text: e.messages?.join(' ') || 'Something went wrong. Please try again.', options: [] }]);
    }
    setBusy(false);
  };

  // a different person logged in: forget the old conversation and start fresh
  useEffect(() => {
    started.current = false;
    lastId.current = 0;
    setMsgs([]); setText(''); setUnread(0); setBusy(false);
  }, [user?.id]);

  useEffect(() => {
    if (!isPatient) return undefined;
    try { lastId.current = Number(localStorage.getItem(seenKey) || 0); } catch { lastId.current = 0; }
    poll();
    const t = setInterval(poll, 8000);
    return () => clearInterval(t);
  }, [isPatient, user?.id]); // eslint-disable-line

  useEffect(() => { openRef.current = open; if (open) setUnread(0); }, [open]);

  useEffect(() => {
    if (isPatient && !started.current) { started.current = true; send({ action: 'menu' }); }
    // other pages can open the panel and start an action, e.g. the "+ Book" button
    const openIt = (e) => { setOpen(true); const d = e?.detail; if (d?.action) send({ action: d.action, data: d.data }, d.label); };
    window.addEventListener('open-chat', openIt);
    return () => window.removeEventListener('open-chat', openIt);
  }, [isPatient, user?.id]); // eslint-disable-line

  // make room for the panel on wide screens
  useEffect(() => {
    document.body.classList.toggle('dock-open', open && isPatient);
    return () => document.body.classList.remove('dock-open');
  }, [open, isPatient]);
  useEffect(() => { end.current?.scrollIntoView({ behavior: 'smooth' }); }, [msgs, busy, open]);

  if (!isPatient) return null;

  const submit = (e) => {
    e.preventDefault();
    const t = text.trim();
    if (!t || busy) return;
    setText('');
    send({ message: t }, t);
  };

  if (!open) {
    return (
      <button className="dock-launch" onClick={() => setOpen(true)} aria-label="Open the assistant">
        <img src="/logo.png" alt="" />
        <span>Ask the assistant</span>
        {unread > 0 && <b style={{ background: '#d64545', color: '#fff', borderRadius: 999, padding: '1px 8px', fontSize: 12, marginLeft: 8 }}>{unread} new</b>}
      </button>
    );
  }

  return (
    <aside className="dock">
      <div className="chat-head">
        <div className="avatar"><img src="/logo.png" alt="" /></div>
        <div style={{ flex: 1 }}>
          <strong>SmileSync Assistant</strong>
          <div className="muted small">English or Filipino · tap a button</div>
        </div>
        <button className="x" onClick={() => setOpen(false)} aria-label="Close the assistant">×</button>
      </div>
      <div className="chat-body">
        {msgs.map((m, i) => (
          <div key={i} className={`bubble-row ${m.from}`}>
            <div className={`bubble ${m.from === 'staff' ? 'bot' : m.from} ${m.escalated ? 'escalated' : ''}`} style={m.from === 'staff' ? { background: 'var(--gold-soft)', border: '1px solid var(--gold)' } : undefined}>
              {m.from === 'staff' && <div className="tag" style={{ marginBottom: 6 }}>Reply from clinic staff</div>}
              {m.text}
              {m.escalated && <div className="tag">Sent to clinic staff</div>}
            </div>
            {m.options?.length > 0 && (
              <div className="chips">
                {m.options.map((o, j) => (
                  <button key={j} className="chip" disabled={busy} onClick={() => send({ action: o.action, data: o.data }, o.label)}>{o.label}</button>
                ))}
              </div>
            )}
          </div>
        ))}
        {busy && <div className="bubble-row bot"><div className="bubble bot typing"><span /><span /><span /></div></div>}
        <div ref={end} />
      </div>
      <form className="chat-input" onSubmit={submit}>
        <input value={text} onChange={(e) => setText(e.target.value)} placeholder={`Message the assistant, ${user?.name?.split(' ')[0] || ''}…`} />
        <button className="btn btn-primary" disabled={busy || !text.trim()}>Send</button>
      </form>
    </aside>
  );
}
