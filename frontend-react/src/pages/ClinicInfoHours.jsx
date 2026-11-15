import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Alert, Card } from '../ui.jsx';

const DAYS = [['mon', 'Monday'], ['tue', 'Tuesday'], ['wed', 'Wednesday'], ['thu', 'Thursday'], ['fri', 'Friday'], ['sat', 'Saturday'], ['sun', 'Sunday']];

export default function ClinicInfoHours() {
  const [info, setInfo] = useState(null);
  const [hours, setHours] = useState(null);
  const [sync, setSync] = useState(true);
  const [error, setError] = useState([]);
  const [ok, setOk] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api('/clinic-info').then((j) => { setInfo(j.info); setHours(j.hours); }).catch((e) => setError(e.messages));
  }, []);

  const setDay = (d, patch) => setHours({ ...hours, [d]: { ...hours[d], ...patch } });

  const save = async () => {
    setBusy(true); setError([]); setOk('');
    try {
      const r = await api('/clinic-info', { method: 'PUT', body: { info, hours, sync_landing: sync } });
      setOk(r.message || 'Saved.');
    } catch (e) { setError(e.messages); }
    setBusy(false);
  };

  if (!info || !hours) return <Alert>{error.join(' ') || 'Loading...'}</Alert>;
  return (
    <>
      <Alert>{error.join(' ')}</Alert>
      <Alert kind="success">{ok}</Alert>
      <Card title="Clinic info">
        <p className="page-sub">The clinic name, main phone and email are in the Clinic profile tab. These are the extra details.</p>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 12 }}>
          <div><label>Director / dentist in charge</label><input maxLength={250} value={info.director} onChange={(e) => setInfo({ ...info, director: e.target.value })} /></div>
          <div><label>Second phone number</label><input maxLength={250} value={info.phone2} onChange={(e) => setInfo({ ...info, phone2: e.target.value })} /></div>
          <div style={{ gridColumn: '1 / -1' }}><label>Address</label><input maxLength={250} value={info.address} onChange={(e) => setInfo({ ...info, address: e.target.value })} /></div>
        </div>
      </Card>
      <Card title="Operating hours">
        <table className="table">
          <thead><tr><th>Day</th><th>Closed</th><th>Opens</th><th>Closes</th></tr></thead>
          <tbody>
            {DAYS.map(([d, name]) => (
              <tr key={d}>
                <td>{name}</td>
                <td><input type="checkbox" checked={hours[d].closed} onChange={(e) => setDay(d, { closed: e.target.checked })} /></td>
                <td><input type="time" disabled={hours[d].closed} value={hours[d].open} onChange={(e) => setDay(d, { open: e.target.value })} /></td>
                <td><input type="time" disabled={hours[d].closed} value={hours[d].close} onChange={(e) => setDay(d, { close: e.target.value })} /></td>
              </tr>
            ))}
          </tbody>
        </table>
        <label style={{ display: 'flex', gap: 8, alignItems: 'center', marginTop: 12 }}>
          <input type="checkbox" checked={sync} onChange={(e) => setSync(e.target.checked)} style={{ width: 'auto' }} />
          Also update the hours, address and second phone on the public landing page
        </label>
        <div style={{ marginTop: 12 }}>
          <button className="btn btn-primary" disabled={busy} onClick={save}>{busy ? 'Saving…' : 'Save clinic info and hours'}</button>
        </div>
      </Card>
    </>
  );
}
