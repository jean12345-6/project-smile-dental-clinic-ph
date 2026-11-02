import React, { useState } from 'react';
import { api } from '../api.js';
import { Crud, Field, Modal } from '../components.jsx';
import { Alert } from '../ui.jsx';

const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1';

export const clinicColumns = (extra) =>
  Object.assign([
    { key: 'logo_path', label: 'Logo', render: (r) => (r.logo_path ? <img className="logo-sm" src={API.replace(/\/api\/v1\/?$/, '') + '/storage/' + r.logo_path} alt="" /> : '—') },
    { key: 'clinic_name', label: 'Clinic' }, { key: 'contact_no', label: 'Contact' }, { key: 'email', label: 'Email' }, { key: 'status', label: 'Status' },
    ...(extra || []),
  ], { pk: 'clinic_id' });

export const clinicFields = [
  { key: 'clinic_name', label: 'Clinic name', required: true }, { key: 'contact_no', label: 'Contact no.' },
  { key: 'email', label: 'Email', type: 'email' }, { key: 'status', label: 'Status', type: 'select', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] },
];

export async function uploadLogo(clinic, file) {
  const fd = new FormData();
  fd.append('image', file);
  fd.append('clinicId', clinic.clinic_id);
  const res = await fetch(API + '/clinic/logo', { method: 'POST', headers: { Authorization: 'Bearer ' + localStorage.getItem('token'), Accept: 'application/json' }, body: fd });
  const j = await res.json();
  if (!res.ok) throw new Error(j.errors?.[0]?.message || j.message || 'Upload failed.');
}

export function LogoButton({ row, reload, setError }) {
  return (
    <label className="btn btn-ghost btn-sm">
      Logo
      <input type="file" accept="image/*" hidden onChange={async (e) => { try { await uploadLogo(row, e.target.files[0]); reload(); } catch (er) { setError([er.message]); } }} />
    </label>
  );
}

/** SuperAdmin only: register clinics and create each clinic's administrator (FR-06). */
export default function Clinics() {
  const [admin, setAdmin] = useState(null);
  const [f, setF] = useState({});
  const [error, setError] = useState([]);
  const [ok, setOk] = useState('');

  const create = async (e) => {
    e.preventDefault();
    try {
      await api(`/clinics/${admin.clinic_id}/admin`, { method: 'POST', body: f });
      setOk(`Clinic administrator ${f.email} created for ${admin.clinic_name}.`); setAdmin(null); setF({});
    } catch (er) { setError(er.messages); setAdmin(null); }
  };
  return (
    <>
      <h1 className="page-title">Clinics</h1>
      <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
      <Alert kind="success">{ok}</Alert>
      <Crud res="clinics" title="Registered clinics" hint="Register a clinic, then create its Clinic Administrator account." columns={clinicColumns()} fields={clinicFields} canDelete={false}
        rowActions={(r, reload) => (
          <>
            <LogoButton row={r} reload={reload} setError={setError} />
            <button className="btn btn-primary btn-sm" onClick={() => { setAdmin(r); setF({}); }}>Create admin</button>
          </>
        )} />
      {admin && (
        <Modal title={`Administrator for ${admin.clinic_name}`} onClose={() => setAdmin(null)}>
          <form onSubmit={create}>
            <Field f={{ label: 'Full name', required: true }} value={f.name} options={{}} onChange={(v) => setF({ ...f, name: v })} />
            <Field f={{ label: 'Email', type: 'email', required: true }} value={f.email} options={{}} onChange={(v) => setF({ ...f, email: v })} />
            <Field f={{ label: 'Password (8+ characters)', type: 'password', required: true }} value={f.password} options={{}} onChange={(v) => setF({ ...f, password: v })} />
            <div className="modal-foot"><button type="button" className="btn btn-ghost" onClick={() => setAdmin(null)}>Cancel</button><button className="btn btn-primary">Create account</button></div>
          </form>
        </Modal>
      )}
    </>
  );
}
