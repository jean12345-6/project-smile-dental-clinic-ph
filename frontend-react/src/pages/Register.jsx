import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import { Alert } from '../ui.jsx';

export default function Register() {
  const navigate = useNavigate();
  const [f, setF] = useState({});
  const [clinics, setClinics] = useState([]);
  const [error, setError] = useState([]);
  const [busy, setBusy] = useState(false);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  useEffect(() => { api('/auth/clinics').then((r) => setClinics(r.data || [])).catch(() => {}); }, []);

  const submit = async (e) => {
    e.preventDefault();
    setBusy(true); setError([]);
    try {
      await api('/auth/register-patient', { method: 'POST', body: f });
      navigate('/login?registered=1');
    } catch (err) { setError(err.messages); }
    setBusy(false);
  };

  return (
    <div className="login-wrap">
      <form className="login-card wide" onSubmit={submit}>
        <img className="login-img" src="/logo.png" alt="Project Smile Dental Clinic" />
        <h1>Create your patient account</h1>
        <p className="muted">Then use the assistant to book and manage your visits.</p>
        <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
        <div className="grid">
          <div><label>Full name *</label><input value={f.name || ''} onChange={set('name')} required /></div>
          <div><label>Email *</label><input type="email" value={f.email || ''} onChange={set('email')} required /></div>
          <div><label>Mobile number</label><input value={f.contact_no || ''} onChange={set('contact_no')} /></div>
          <div><label>Birthdate</label><input type="date" value={f.birthdate || ''} onChange={set('birthdate')} /></div>
          <div><label>Password * (8+ characters)</label><input type="password" value={f.password || ''} onChange={set('password')} required minLength={8} /></div>
          <div><label>Confirm password *</label><input type="password" value={f.password_confirmation || ''} onChange={set('password_confirmation')} required /></div>
        </div>
        <label>Address</label>
        <input value={f.address || ''} onChange={set('address')} />
        {clinics.length > 1 && (
          <>
            <label>Clinic</label>
            <select value={f.clinic_id || ''} onChange={set('clinic_id')}>
              <option value="">Choose a clinic</option>
              {clinics.map((c) => <option key={c.clinic_id} value={c.clinic_id}>{c.clinic_name}</option>)}
            </select>
          </>
        )}
        <p className="muted small">By registering you agree that the clinic keeps your records under the Data Privacy Act of 2012 (RA 10173).</p>
        <button className="btn btn-primary" disabled={busy}>{busy ? 'Creating…' : 'Create account'}</button>
        <p className="muted center-text"><Link to="/login">Back to sign in</Link></p>
      </form>
    </div>
  );
}
