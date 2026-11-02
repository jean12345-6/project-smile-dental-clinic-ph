import React, { useEffect, useRef, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';

export default function Chat() {
  const { user } = useAuth();
  const [msgs, setMsgs] = useState([]);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
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

  useEffect(() => { send({ action: 'menu' }); }, []); // eslint-disable-line
  useEffect(() => { end.current?.scrollIntoView({ behavior: 'smooth' }); }, [msgs, busy]);

  const submit = (e) => {
    e.preventDefault();
    const t = text.trim();
    if (!t || busy) return;
    setText('');
    send({ message: t }, t);
  };

  return (
    <div className="chat">
      <div className="chat-head">
        <div className="avatar"><img src="/logo.png" alt="" /></div>
        <div>
          <strong>SmileSync Assistant</strong>
          <div className="muted small">Ask anything or tap a button · English or Filipino</div>
        </div>
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
    </div>
  );
}
