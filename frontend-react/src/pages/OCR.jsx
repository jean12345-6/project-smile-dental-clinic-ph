import React, { useEffect, useState } from 'react';
import { createWorker } from 'tesseract.js';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Field, Modal, useOptions } from '../components.jsx';
import { Alert, Badge, Card, Empty } from '../ui.jsx';

const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1';
const FILES = API.replace(/\/api\/v1\/?$/, '') + '/storage/';

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

  const load = () => api('/r/ocr').then((r) => setRows(r.data)).catch((e) => setError(e.messages));
  useEffect(() => { load(); }, []);

  // Reads the scan with Tesseract inside the browser. Words it is not sure about are marked [?] (FR-32).
  const readScan = async (img) => {
    setFile(img);
    if (!img) return;
    setReading(0);
    try {
      const worker = await createWorker('eng', 1, { logger: (m) => m.status === 'recognizing text' && setReading(Math.round(m.progress * 100)) });
      const { data } = await worker.recognize(img);
      await worker.terminate();
      const out = (data.lines || []).map((l) => (l.words || []).map((w) => (w.confidence < 70 ? `${w.text}[?]` : w.text)).join(' ')).join('\n');
      setF((prev) => ({ ...prev, text: out }));
    } catch {
      setError(['Could not read the image automatically. You can type the text yourself.']);
    }
    setReading(null);
  };

  const upload = async (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('image', file); fd.append('patientId', f.patientId); fd.append('text', f.text || '');
    try {
      const res = await fetch(API + '/ocr/upload', { method: 'POST', headers: { Authorization: 'Bearer ' + localStorage.getItem('token'), Accept: 'application/json' }, body: fd });
      const j = await res.json();
      if (!res.ok) throw new Error(j.errors?.[0]?.message || j.message || 'Upload failed.');
      setOk('Uploaded. Open it to check the text.'); setFile(null); setF({}); load();
    } catch (err) { setError([err.message]); }
  };
  const approve = async () => {
    try { await api(`/r/ocr/${view.batch_id}`, { method: 'PATCH', body: { extracted_text: text, verify_status: 'verified' } }); setView(null); setOk('Approved and saved to the record.'); load(); } catch (e) { setError(e.messages); }
  };
  const unclear = text.split(/\s+/).filter((w) => /\[\?\]|\?\?/.test(w)).length;

  return (
    <>
      <h1 className="page-title">Scanned forms (OCR)</h1>
      <Alert>{error.join(' ')}</Alert>
      <Alert kind="success">{ok}</Alert>
      <Card title="Upload a scanned paper form">
        <form onSubmit={upload}>
          <div className="grid">
            <Field f={{ label: 'Patient', type: 'select', optionsFrom: 'patient', required: true }} value={f.patientId} options={options} onChange={(v) => setF({ ...f, patientId: v })} />
            <div><label>Scanned image *</label><input type="file" accept="image/*" onChange={(e) => readScan(e.target.files[0])} />{reading !== null && <p className="muted small">Reading the scan… {reading}%</p>}</div>
          </div>
          <Field f={{ label: 'Extracted text (read automatically, fix it here. Unclear words end with [?])', type: 'textarea' }} value={f.text} options={{}} onChange={(v) => setF({ ...f, text: v })} />
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
                <td className="actions"><button className="btn btn-ghost btn-sm" onClick={() => { setView(r); setText(r.extracted_text || ''); }}>{r.verify_status === 'verified' ? 'View' : 'Check and approve'}</button></td></tr>
            ))}</tbody>
          </table></div>
        )}
      </Card>
      {view && (
        <Modal title={`Check form #${view.batch_id}`} onClose={() => setView(null)} wide>
          <div className="side">
            <div><label>Scanned image</label><img className="scan" src={FILES + view.image_path} alt="scan" /></div>
            <div><label>Extracted text {unclear > 0 && <span className="tag-mini risk">{unclear} unclear</span>}</label>
              <textarea rows={14} value={text} onChange={(e) => setText(e.target.value)} disabled={view.verify_status === 'verified'} />
              <p className="muted small">Fix the text, then approve. Nothing is saved to the record until you approve.</p></div>
          </div>
          {view.verify_status !== 'verified' && <div className="modal-foot"><button className="btn btn-primary" onClick={approve}>Approve and save</button></div>}
        </Modal>
      )}
    </>
  );
}
