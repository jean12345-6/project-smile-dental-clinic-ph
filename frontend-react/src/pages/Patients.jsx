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
  const show = async (p) => {
    try { const r = await api(`/patient-summary/${p.patient_id}`); setSum({ p, ...r }); } catch (e) { setError(e.messages); }
  };
  return (
    <>
      <h1 className="page-title">Patient records</h1>
      <Alert>{error.join(' ')}</Alert>
      <Crud
        res="patient-records"
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
        rowActions={(r) => <button className="btn btn-ghost btn-sm" onClick={() => show(r)}>Summary</button>}
        canAdd={edit} canEdit={edit} canDelete={edit}
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
