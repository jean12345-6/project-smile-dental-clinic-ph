import React from 'react';
import { Crud, money } from '../components.jsx';
import { useAuth } from '../auth.jsx';
import { fmt } from '../ui.jsx';

// All treatment records. New treatments are recorded from the Daily queue; here staff can search, sort and fix them.
export default function Treatments() {
  const { user, name } = useAuth();
  const canFix = ['superadmin', 'clinic_admin', 'dentist'].includes(user?.role);
  const cols = Object.assign([
    { key: 'patient_id', label: 'Patient', render: (r) => name.patient(r.patient_id) },
    { key: 'procedure_name', label: 'Treatment' },
    { key: 'tooth_no', label: 'Tooth #' },
    { key: 'treatment_date', label: 'Date', render: (r) => fmt(r.treatment_date || r.created_at).slice(0, 10), sortable: true },
    { key: 'dentist_id', label: 'Dentist', render: (r) => (r.dentist_id ? name.dentist(r.dentist_id) : '-') },
    { key: 'cost', label: 'Cost', render: (r) => money(r.cost), sortable: true },
  ], { pk: 'treatment_id' });
  return (
    <div>
      <h1 className="page-title">Treatments</h1>
      <Crud
        res="treatments"
        title="Treatment records"
        hint="New treatments are recorded from the Daily queue. Here you can search, sort and correct them."
        canAdd={false}
        canEdit={canFix}
        canDelete={canFix}
        columns={cols}
        fields={[
          { key: 'procedure_name', label: 'Treatment', required: true },
          { key: 'tooth_no', label: 'Tooth no.' },
          { key: 'cost', label: 'Cost (PHP)', type: 'number', step: '0.01' },
          { key: 'notes', label: 'Notes', type: 'textarea' },
        ]}
      />
    </div>
  );
}
