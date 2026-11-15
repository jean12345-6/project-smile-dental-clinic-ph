import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Alert, Card } from '../ui.jsx';
import { DEFAULTS } from '../landingDefaults.js';

const range = (n) => Array.from({ length: n }, (_, i) => i + 1);
// [section title, [[key, label, long?], ...]]
const SECTIONS = [
  ['Hero (top of the page)', [['hero.title', 'Headline'], ['hero.tagline', 'Tagline'], ['hero.desc', 'Description', true]]],
  ['Services (6 cards)', range(6).flatMap((i) => [[`svc.${i}.name`, `Service ${i} title`], [`svc.${i}.desc`, `Service ${i} description`, true]])],
  ['Why choose us (4 cards)', range(4).flatMap((i) => [[`why.${i}.title`, `Reason ${i} title`], [`why.${i}.desc`, `Reason ${i} description`, true]])],
  ['Our team (3 cards)', range(3).flatMap((i) => [[`team.${i}.name`, `Person ${i} name`], [`team.${i}.role`, `Person ${i} role`], [`team.${i}.years`, `Person ${i} note`]])],
  ['Contact and hours', [['contact.address', 'Address'], ['contact.phone1', 'Phone 1'], ['contact.phone2', 'Phone 2'], ['contact.emergency', 'Emergency line'], ['contact.email', 'Email'], ['hours.weekday', 'Weekday hours'], ['hours.sat', 'Saturday hours'], ['hours.sun', 'Sunday hours']]],
  ['Footer', [['footer.text', 'Footer description', true]]],
];

export default function LandingEditor() {
  const [v, setV] = useState(null);
  const [error, setError] = useState([]);
  const [ok, setOk] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => { api('/public/landing').then((j) => setV({ ...DEFAULTS, ...(j.content || {}) })).catch((e) => setError(e.messages)); }, []);

  const save = async () => {
    setBusy(true); setError([]); setOk('');
    try { const r = await api('/landing', { method: 'PUT', body: { content: v } }); setOk(r.message || 'Saved.'); } catch (e) { setError(e.messages); }
    setBusy(false);
  };
  const reset = () => { if (window.confirm('Put every text back to the original wording? (Save to keep it.)')) setV({ ...DEFAULTS }); };

  if (!v) return <Alert>{error.join(' ')}</Alert>;
  return (
    <>
      <div className="page-top">
        <div>
          <h1 className="page-title">Edit landing page</h1>
          <p className="page-sub" style={{ margin: 0 }}>This is the public home page visitors see. Testimonials come from the feedback you publish on the Feedback page.</p>
        </div>
        <div className="top-actions">
          <a className="btn btn-ghost" href="/landing" target="_blank" rel="noreferrer">Preview landing page</a>
          <button className="btn btn-ghost" onClick={reset}>Reset to original</button>
          <button className="btn btn-primary" disabled={busy} onClick={save}>{busy ? 'Saving…' : 'Save landing page'}</button>
        </div>
      </div>
      <Alert>{error.join(' ')}</Alert>
      <Alert kind="success">{ok}</Alert>
      {SECTIONS.map(([title, fields]) => (
        <Card key={title} title={title}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 12 }}>
            {fields.map(([k, label, long]) => (
              <div key={k}>
                <label>{label}</label>
                {long
                  ? <textarea rows={3} maxLength={250} value={v[k] ?? ''} onChange={(e) => setV({ ...v, [k]: e.target.value })} />
                  : <input maxLength={250} value={v[k] ?? ''} onChange={(e) => setV({ ...v, [k]: e.target.value })} />}
              </div>
            ))}
          </div>
        </Card>
      ))}
    </>
  );
}
