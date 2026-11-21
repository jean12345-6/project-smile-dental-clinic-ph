import React, { useEffect, useMemo, useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Alert, Card, Empty } from '../ui.jsx';

// FDI tooth numbers, as seen from the front: upper right to upper left, lower right to lower left.
const UPPER = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
const LOWER = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
const PRESETS = ['Healthy', 'Cavity', 'Filled', 'Crown', 'Root canal', 'Implant', 'Missing', 'For extraction'];

// Pick a colour from the words in the condition (old records use free text such as "Small cavity" or "Caries").
function tone(cond = '') {
  const c = cond.toLowerCase();
  if (!c) return { bg: 'transparent', fg: 'inherit', label: 'No record' };
  if (/extract|bunot/.test(c)) return { bg: '#dc2626', fg: '#fff', label: 'For extraction' };
  if (/missing|wala|absent/.test(c)) return { bg: '#6b7280', fg: '#fff', label: 'Missing' };
  if (/cavit|carie|decay|butas|sira/.test(c)) return { bg: '#f59e0b', fg: '#1f1300', label: 'Cavity' };
  if (/root|rct/.test(c)) return { bg: '#7c3aed', fg: '#fff', label: 'Root canal' };
  if (/crown|cap|bridge|veneer/.test(c)) return { bg: '#2563eb', fg: '#fff', label: 'Crown' };
  if (/implant/.test(c)) return { bg: '#0891b2', fg: '#fff', label: 'Implant' };
  if (/fill|pasta/.test(c)) return { bg: '#16a34a', fg: '#fff', label: 'Filled' };
  if (/health|normal|good|ok/.test(c)) return { bg: '#bbf7d0', fg: '#14532d', label: 'Healthy' };
  return { bg: '#e5e7eb', fg: '#111827', label: 'Other' };
}
const LEGEND = ['Cavity', 'Filled', 'Crown', 'Root canal', 'Implant', 'Missing', 'For extraction', 'Healthy'];

export default function DentalChart() {
  const { user, lookups } = useAuth();
  const canEdit = ['superadmin', 'clinic_admin', 'dentist'].includes(user?.role);
  const patients = lookups?.patients || [];
  const [pid, setPid] = useState('');
  const [rows, setRows] = useState([]);
  const [sel, setSel] = useState(null);
  const [cond, setCond] = useState('');
  const [error, setError] = useState([]);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => { if (!pid && patients.length) setPid(String(patients[0].patient_id)); }, [patients, pid]);

  const load = () => {
    if (!pid) return;
    api('/r/dental-chart', { params: { patient_id: pid, perPage: 100 } })
      .then((r) => setRows(r.data || []))
      .catch((e) => setError(e.messages || ['Could not load the chart.']));
  };
  useEffect(() => { setSel(null); setNote(''); load(); /* eslint-disable-next-line */ }, [pid]);

  // newest record per tooth
  const latest = useMemo(() => {
    const m = {};
    rows.forEach((r) => { const k = String(r.tooth_no); if (!m[k] || r.chart_id > m[k].chart_id) m[k] = r; });
    return m;
  }, [rows]);

  const pick = (n) => { setSel(n); setCond(latest[String(n)]?.condition || ''); setNote(''); setError([]); };

  const save = async () => {
    if (!sel || !cond.trim()) { setError(['Choose or type the condition first.']); return; }
    setBusy(true); setError([]);
    try {
      const cur = latest[String(sel)];
      if (cur) await api(`/r/dental-chart/${cur.chart_id}`, { method: 'PUT', body: { condition: cond.trim() } });
      else await api('/r/dental-chart', { method: 'POST', body: { patient_id: Number(pid), tooth_no: String(sel), condition: cond.trim() } });
      setNote(`Tooth ${sel} saved.`);
      load();
    } catch (e) { setError(e.messages || ['Could not save.']); }
    setBusy(false);
  };

  const clear = async () => {
    const cur = latest[String(sel)];
    if (!cur) return;
    setBusy(true); setError([]);
    try { await api(`/r/dental-chart/${cur.chart_id}`, { method: 'DELETE' }); setNote(`Tooth ${sel} cleared.`); setCond(''); load(); }
    catch (e) { setError(e.messages || ['Could not clear.']); }
    setBusy(false);
  };

  const tooth = (n) => {
    const t = tone(latest[String(n)]?.condition);
    const on = sel === n;
    return (
      <button key={n} type="button" className={`tooth${on ? ' tooth-on' : ''}`} title={latest[String(n)]?.condition || 'No record'} onClick={() => pick(n)}
        style={{ background: t.bg, color: t.fg }}>
        {n}
      </button>
    );
  };
  const row = (list) => (
    <div className="tooth-row">
      <div className="tooth-half">{list.slice(0, 8).map(tooth)}</div>
      <div className="tooth-mid" />
      <div className="tooth-half">{list.slice(8).map(tooth)}</div>
    </div>
  );

  const history = [...rows].sort((a, b) => b.chart_id - a.chart_id);
  const who = patients.find((p) => String(p.patient_id) === String(pid))?.name || '';

  return (
    <>
      <div className="page-top">
        <div>
          <h1 className="page-title">Dental chart</h1>
          <p className="page-sub" style={{ margin: 0 }}>Tap a tooth to see or change its condition. Numbers follow the FDI system.</p>
        </div>
        <div className="top-actions">
          <select value={pid} onChange={(e) => setPid(e.target.value)} style={{ minWidth: 220 }}>
            {patients.map((p) => <option key={p.patient_id} value={p.patient_id}>{p.name}</option>)}
          </select>
          <button className="btn btn-ghost" onClick={() => window.print()}>Print</button>
        </div>
      </div>
      <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
      <Alert kind="success">{note}</Alert>

      <Card title={who ? `Chart of ${who}` : 'Chart'}>
        <div className="muted small" style={{ textAlign: 'center' }}>Upper jaw (right side of the patient on the left)</div>
        {row(UPPER)}
        {row(LOWER)}
        <div className="muted small" style={{ textAlign: 'center' }}>Lower jaw</div>
        <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap', justifyContent: 'center', marginTop: 14 }}>
          {LEGEND.map((l) => { const t = tone(l); return (
            <span key={l} className="small" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
              <span style={{ width: 14, height: 14, borderRadius: 4, background: t.bg, border: '1px solid rgba(128,128,128,.45)' }} />{l}
            </span>); })}
        </div>
      </Card>

      {sel && (
        <Card title={`Tooth ${sel}`}>
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
            {/* This was free text, so "healthy" and "Healthy" were stored as different
                conditions and neither matched the legend. The legend is now the vocabulary.
                A condition recorded before this change is kept and shown as it was. */}
            <select value={cond} onChange={(e) => setCond(e.target.value)} style={{ minWidth: 260 }} disabled={!canEdit}>
              <option value="">Choose the condition…</option>
              {PRESETS.map((p) => <option key={p} value={p}>{p}</option>)}
              {cond && !PRESETS.includes(cond) && <option value={cond}>{cond} (recorded earlier)</option>}
            </select>
            {canEdit && <button className="btn btn-primary" onClick={save} disabled={busy}>Save</button>}
            {canEdit && latest[String(sel)] && <button className="btn btn-ghost" onClick={clear} disabled={busy}>Clear tooth</button>}
          </div>
          {latest[String(sel)] && <p className="muted small" style={{ marginBottom: 0 }}>Last updated {String(latest[String(sel)].date_updated).slice(0, 10)}</p>}
          {!canEdit && <p className="muted small" style={{ marginBottom: 0 }}>Only the dentist or the admin can change the chart.</p>}
        </Card>
      )}

      <Card title="All records">
        {history.length === 0 ? <Empty>No tooth records for this patient yet.</Empty> : (
          <div className="table-wrap"><table>
            <thead><tr><th>Tooth</th><th>Condition</th><th>Updated</th></tr></thead>
            <tbody>{history.map((r) => (
              <tr key={r.chart_id} onClick={() => pick(Number(r.tooth_no))} style={{ cursor: 'pointer' }}>
                <td>{r.tooth_no}</td><td>{r.condition}</td><td>{String(r.date_updated).slice(0, 10)}</td>
              </tr>))}</tbody>
          </table></div>
        )}
      </Card>
    </>
  );
}
