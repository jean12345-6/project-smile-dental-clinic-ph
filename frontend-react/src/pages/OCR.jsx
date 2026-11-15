import React, { useEffect, useState } from 'react';
import { createWorker } from 'tesseract.js';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Field, Modal, useOptions } from '../components.jsx';
import { Alert, Badge, Card, Empty } from '../ui.jsx';
import { cleanText, matchPatient, parseForm, prepareImage, shrinkImage } from '../ocrParse.js';

const FIELDS = [
  { key: 'full_name', label: 'Full name' }, { key: 'birthdate', label: 'Birthdate', type: 'date' },
  { key: 'contact_no', label: 'Contact no.' }, { key: 'email', label: 'Email', type: 'email' },
  { key: 'address', label: 'Address' }, { key: 'medical_history', label: 'Medical history / allergies', type: 'textarea' },
];

const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1';
const FILES = API.replace(/\/api\/v1\/?$/, '') + '/storage/';

/** Type a name, phone or email to find the patient. */
function PatientPicker({ id, name, onPick }) {
  const [q, setQ] = useState('');
  const [res, setRes] = useState([]);
  const [open, setOpen] = useState(false);
  useEffect(() => {
    if (!open) return undefined;
    const t = setTimeout(() => api('/r/patient-records', { params: { q, perPage: 8 } }).then((r) => setRes(r.data || [])).catch(() => setRes([])), 250);
    return () => clearTimeout(t);
  }, [q, open]);
  if (id) {
    return (
      <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
        <span className="tag-mini" style={{ margin: 0, padding: '8px 14px', fontSize: 14 }}>{name || `Patient #${id}`}</span>
        <button type="button" className="btn btn-ghost btn-sm" onClick={() => onPick('', '')}>Change</button>
      </div>
    );
  }
  return (
    <div style={{ position: 'relative' }}>
      <input placeholder="Type a name, phone or email…" value={q} onChange={(e) => { setQ(e.target.value); setOpen(true); }} onFocus={() => setOpen(true)} />
      {open && (
        <div className="card" style={{ position: 'absolute', zIndex: 20, left: 0, right: 0, top: '100%', marginTop: 4, padding: 6, maxHeight: 260, overflowY: 'auto' }}>
          {res.length === 0 && <div className="muted small" style={{ padding: 8 }}>No patient found. Use "Create new patient" below.</div>}
          {res.map((p) => (
            <button type="button" key={p.patient_id} className="btn btn-ghost" style={{ display: 'block', width: '100%', textAlign: 'left', marginBottom: 4 }}
              onClick={() => { onPick(p.patient_id, p.full_name); setOpen(false); setQ(''); }}>
              {p.full_name} <span className="muted small">{p.contact_no || ''} {p.email || ''}</span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

const fieldsToText = (s) => (s ? FIELDS.filter((x) => s[x.key]).map((x) => `${x.label}: ${s[x.key]}`).join('\n') : '');

export default function OCR() {
  const { name } = useAuth();
  const options = useOptions();
  const [rows, setRows] = useState(null);
  const [error, setError] = useState([]);
  const [ok, setOk] = useState('');
  const [f, setF] = useState({});
  const [file, setFile] = useState(null);
  const [view, setView] = useState(null);
  const [text, setText] = useState('');
  const [reading, setReading] = useState(null); // OCR progress 0-100
  const [vf, setVf] = useState({});
  const [updatePatient, setUpdatePatient] = useState(true);

  const load = () => api('/r/ocr').then((r) => setRows(r.data)).catch((e) => setError(e.messages));
  useEffect(() => { load(); }, []);

  // looks the scanned name up in the patient list (server search) and returns the best match
  const findPatient = async (fullName) => {
    const word = (fullName || '').split(/\s+/).sort((a, b) => b.length - a.length)[0];
    if (!word) return null;
    try {
      const r = await api('/r/patient-records', { params: { q: word, perPage: 30 } });
      return matchPatient(fullName, (r.data || []).map((p) => ({ value: p.patient_id, label: p.full_name })));
    } catch { return null; }
  };

  // makes a new patient record from the scanned details
  const createPatient = async () => {
    const s = f.scan || {};
    if (!s.full_name) { setError(['Type the full name first.']); return; }
    const body = {};
    FIELDS.forEach((x) => { if (s[x.key]) body[x.key] = s[x.key]; });
    try {
      const r = await api('/r/patient-records', { method: 'POST', body });
      const row = r.data || r;
      setF((p) => ({ ...p, patientId: row.patient_id, patientName: s.full_name }));
      setOk(`New patient created: ${s.full_name}.`); setError([]);
    } catch (e) { setError(e.messages); }
  };

  // Reads the scan with Tesseract inside the browser, cleans the text and fills the patient form (FR-32).
  const readScan = async (img) => {
    setFile(img);
    if (!img) return;
    setReading(0); setError([]);
    // 1) handwriting: AI reader on the server. 2) if it is not set up, the basic reader in the browser.
    try {
      const fd = new FormData();
      fd.append('image', await shrinkImage(img), 'form.jpg');
      const res = await fetch(API + '/ocr/read', { method: 'POST', headers: { Authorization: 'Bearer ' + localStorage.getItem('token'), Accept: 'application/json' }, body: fd });
      if (res.ok) {
        const { fields } = await res.json();
        const who = await findPatient(fields.full_name);
        setF((prev) => ({ ...prev, text: fieldsToText(fields), scan: fields, patientId: prev.patientId || (who ? who.value : ''), patientName: prev.patientId ? prev.patientName : (who ? who.label : '') }));
        setOk(who ? `Handwriting read. Patient matched: ${who.label}. Please check every detail below.` : 'Handwriting read. Please choose the patient and check every detail below.');
        setReading(null);
        return;
      }
      if (res.status !== 503) { const j = await res.json().catch(() => ({})); setError([j.errors?.[0]?.message || 'The AI reader failed. Using the basic reader instead.']); }
    } catch { /* fall back to the basic reader */ }
    try {
      const worker = await createWorker('eng', 1, { logger: (m) => m.status === 'recognizing text' && setReading(Math.round(m.progress * 100)) });
      const { data } = await worker.recognize(await prepareImage(img));
      await worker.terminate();
      const text = cleanText((data.lines || []).map((l) => (l.words || []).filter((w) => w.confidence >= 40).map((w) => w.text).join(' ')).join('\n'));
      const found = parseForm(text);
      const who = await findPatient(found.full_name);
      setF((prev) => ({ ...prev, text, scan: found, patientId: prev.patientId || (who ? who.value : ''), patientName: prev.patientId ? prev.patientName : (who ? who.label : '') }));
      setOk(who ? `Form read. Patient matched: ${who.label}. Please check the details below.` : 'Form read. Please choose the patient and check the details below.');
    } catch {
      setError(['Could not read the image automatically. You can type the details yourself.']);
    }
    setReading(null);
  };

  const upload = async (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('image', file); fd.append('patientId', f.patientId); fd.append('text', fieldsToText(f.scan) || f.text || '');
    try {
      const res = await fetch(API + '/ocr/upload', { method: 'POST', headers: { Authorization: 'Bearer ' + localStorage.getItem('token'), Accept: 'application/json' }, body: fd });
      const j = await res.json();
      if (!res.ok) throw new Error(j.errors?.[0]?.message || j.message || 'Upload failed.');
      setOk('Uploaded. Open it to check the text.'); setFile(null); setF({}); load();
    } catch (err) { setError([err.message]); }
  };
  const approve = async () => {
    try {
      await api(`/r/ocr/${view.batch_id}`, { method: 'PATCH', body: { extracted_text: fieldsToText(vf) || text, verify_status: 'verified' } });
      if (updatePatient) {
        const body = {};
        FIELDS.forEach((x) => { if (vf[x.key]) body[x.key] = vf[x.key]; });
        if (Object.keys(body).length) await api(`/r/patient-records/${view.patient_id}`, { method: 'PATCH', body });
      }
      setView(null); setOk(updatePatient ? 'Approved. The patient record was updated with these details.' : 'Approved and saved.'); load();
    } catch (e) { setError(e.messages); }
  };
  

  return (
    <>
      <h1 className="page-title">Scanned forms (OCR)</h1>
      <Alert>{error.join(' ')}</Alert>
      <Alert kind="success">{ok}</Alert>
      <Card title="Upload a scanned paper form">
        <form onSubmit={upload}>
          <div className="grid">
            <div><label>Patient *</label><PatientPicker id={f.patientId} name={f.patientName} onPick={(i, n) => setF((p) => ({ ...p, patientId: i, patientName: n }))} /></div>
            <div><label>Scanned image *</label><input type="file" accept="image/*" onChange={(e) => readScan(e.target.files[0])} />{reading !== null && <p className="muted small">Reading the scan… {reading}%</p>}</div>
          </div>
          {f.scan && (
            <>
              <h3 style={{ margin: '16px 0 8px' }}>Details found in the scan (check and fix)</h3>
              <div className="grid">
                {FIELDS.map((x) => <Field key={x.key} f={x} value={f.scan[x.key]} options={{}} onChange={(v) => setF({ ...f, scan: { ...f.scan, [x.key]: v } })} />)}
              </div>
            </>
          )}
          {f.scan && !f.patientId && f.scan.full_name && <button type="button" className="btn btn-ghost" style={{ marginTop: 12 }} onClick={createPatient}>+ Create new patient from this form</button>}
          <details style={{ marginTop: 10 }}><summary className="muted small">Show the cleaned text</summary><textarea rows={6} style={{ width: '100%' }} value={f.text || ''} onChange={(v) => setF({ ...f, text: v.target.value, scan: parseForm(v.target.value) })} /></details>
          <button className="btn btn-primary" style={{ marginTop: 12 }} disabled={!file || !f.patientId || reading !== null}>Upload and send for approval</button>
        </form>
      </Card>
      <Card title="Forms waiting for approval">
        {rows && rows.length === 0 && <Empty>No scanned forms yet.</Empty>}
        {rows && rows.length > 0 && (
          <div className="table-wrap"><table>
            <thead><tr><th>#</th><th>Patient</th><th>Status</th><th /></tr></thead>
            <tbody>{rows.map((r) => (
              <tr key={r.batch_id}><td>{r.batch_id}</td><td>{name.patient(r.patient_id)}</td><td><Badge status={r.verify_status === 'verified' ? 'completed' : 'pending'} /></td>
                <td className="actions"><button className="btn btn-ghost btn-sm" onClick={() => { setView(r); setText(r.extracted_text || ''); setVf(parseForm(r.extracted_text || '')); setUpdatePatient(true); }}>{r.verify_status === 'verified' ? 'View' : 'Check and approve'}</button></td></tr>
            ))}</tbody>
          </table></div>
        )}
      </Card>
      {view && (
        <Modal title={`Check form #${view.batch_id}`} onClose={() => setView(null)} wide>
          <div className="side">
            <div><label>Scanned image</label><img className="scan" src={FILES + view.image_path} alt="scan" /></div>
            <div>
              <label>Details from the form</label>
              <div style={{ display: 'grid', gap: 10 }}>
                {FIELDS.map((x) => <Field key={x.key} f={x} value={vf[x.key]} options={{}} onChange={(v) => setVf({ ...vf, [x.key]: v })} />)}
              </div>
              {view.verify_status !== 'verified' && (
                <label style={{ display: 'flex', gap: 8, alignItems: 'center', marginTop: 12 }}>
                  <input type="checkbox" style={{ width: 'auto' }} checked={updatePatient} onChange={(e) => setUpdatePatient(e.target.checked)} />
                  Also update {name.patient(view.patient_id)}'s patient record with these details
                </label>
              )}
              <p className="muted small">Fix anything that is wrong, then approve. Nothing is saved to the record until you approve.</p>
            </div>
          </div>
          {view.verify_status !== 'verified' && <div className="modal-foot"><button className="btn btn-primary" onClick={approve}>Approve and save</button></div>}
        </Modal>
      )}
    </>
  );
}
