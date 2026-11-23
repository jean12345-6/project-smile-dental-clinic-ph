import React, { useState } from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Crud, Modal } from '../components.jsx';
import { Alert } from '../ui.jsx';

export default function Patients() {
  const { user } = useAuth();
  const edit = user.role !== 'dentist';
  const [sum, setSum] = useState(null);
  const [error, setError] = useState([]);
  const [done, setDone] = useState('');
  // Archived patients are hidden from the normal list; this switch shows them so they can be restored
  const [archived, setArchived] = useState(false);
  const [reload, setReload] = useState(0);
  const restore = async (p) => {
    if (!window.confirm(`Restore ${p.full_name}? The patient will appear in the list again.`)) return;
    setError([]); setDone('');
    try {
      const r = await api(`/r/patient-records/${p.patient_id}/restore`, { method: 'POST' });
      setDone(r?.message || 'Restored.');
      setReload((n) => n + 1);
    } catch (e) { setError(e.messages); }
  };
  const show = async (p) => {
    try { const r = await api(`/patient-summary/${p.patient_id}`); setSum({ p, ...r }); } catch (e) { setError(e.messages); }
  };
  return (
    <>
      <div className="page-top">
        <h1 className="page-title">{archived ? 'Archived patients' : 'Patient records'}</h1>
        {edit && (
          <button className="btn btn-ghost" onClick={() => { setArchived((a) => !a); setDone(''); setError([]); }}>
            {archived ? 'Back to active patients' : 'Show archived'}
          </button>
        )}
      </div>
      <Alert>{error.join(' ')}</Alert>
      <Alert kind="success">{done}</Alert>
      {archived && <p className="muted">These records are hidden from lists but never erased. Restore one to bring it back with all its history.</p>}
      <Crud
        key={archived ? `archived-${reload}` : `active-${reload}`}
        res="patient-records"
        fixed={archived ? { status: 'archived' } : {}}
        defaultSort={{ key: 'full_name', dir: 'asc' }}
        title="Patients"
        columns={Object.assign([
          { key: 'full_name', label: 'Name' }, { key: 'contact_no', label: 'Contact' }, { key: 'email', label: 'Email' },
          { key: 'gender', label: 'Gender' }, { key: 'created_at', label: 'Registered', render: (r) => String(r.created_at || '').slice(0, 10) }, { key: 'no_show_count', label: 'No-shows' },
        ], { pk: 'patient_id' })}
        fields={[
          { key: 'full_name', label: 'Full name', required: true }, { key: 'birthdate', label: 'Birthdate', type: 'date' }, { key: 'gender', label: 'Gender', type: 'select', options: [{ value: 'Female', label: 'Female' }, { value: 'Male', label: 'Male' }, { value: 'Other', label: 'Other' }] },
          { key: 'contact_no', label: 'Contact no.' }, { key: 'email', label: 'Email', type: 'email' },
          { key: 'address', label: 'Address' }, { key: 'medical_history', label: 'Medical history', type: 'textarea' },
        ]}
        rowActions={(r) => (archived
          ? <button className="btn btn-primary btn-sm" onClick={() => restore(r)}>Restore</button>
          : <button className="btn btn-ghost btn-sm" onClick={() => show(r)}>Summary</button>)}
        canAdd={edit && !archived} canEdit={edit && !archived} canDelete={edit && !archived}
        // The record is archived, never erased, so the button should not say Delete.
        deleteLabel="Archive"
        deleteConfirm="Archive this patient? The record is kept and can be restored by an administrator."
      />
      {sum && (
        <Modal title={`Summary · ${sum.p.full_name}`} onClose={() => setSum(null)}>
          <div className="alert alert-warn">{sum.label}</div>
          <p>{sum.summary}</p>
        </Modal>
      )}
    </>
  );
}
