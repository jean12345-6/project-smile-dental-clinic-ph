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

  useEffect(() => {
    if (isPatient && !started.current) { started.current = true; send({ action: 'menu' }); }
    const openIt = () => setOpen(true);
    window.addEventListener('open-chat', openIt);
    return () => window.removeEventListener('open-chat', openIt);
  }, [isPatient]); // eslint-disable-line

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
            <div className={`bubble ${m.from} ${m.escalated ? 'escalated' : ''}`}>
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
