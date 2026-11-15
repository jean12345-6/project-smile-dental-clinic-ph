import React from 'react';
import { Crud } from '../components.jsx';
import { useAuth } from '../auth.jsx';
import { fmt } from '../ui.jsx';

// All prescriptions. New ones are written in the Daily queue when a treatment is saved.
export default function Prescriptions() {
  const { user, name } = useAuth();
  const canFix = ['superadmin', 'clinic_admin', 'dentist'].includes(user?.role);
  const cols = Object.assign([
    { key: 'patient_id', label: 'Patient', render: (r) => name.patient(r.patient_id) },
    { key: 'medicine', label: 'Medicine' },
    { key: 'dosage', label: 'Dosage' },
    { key: 'frequency', label: 'Frequency' },
    { key: 'duration', label: 'Duration' },
    { key: 'dentist_id', label: 'Dentist', render: (r) => (r.dentist_id ? name.dentist(r.dentist_id) : '-') },
    { key: 'created_at', label: 'Date', render: (r) => fmt(r.prescription_date || r.created_at).slice(0, 10), sortable: true },
  ], { pk: 'prescription_id' });
  return (
    <div>
      <h1 className="page-title">Prescriptions</h1>
      <Crud
        res="prescriptions"
        title="Prescription records"
        hint="New prescriptions are written in the Daily queue when a treatment is saved."
        canAdd={false}
        canEdit={canFix}
        canDelete={canFix}
        columns={cols}
        fields={[
          { key: 'medicine', label: 'Medicine', required: true },
          { key: 'dosage', label: 'Dosage' },
          { key: 'frequency', label: 'Frequency' },
          { key: 'duration', label: 'Duration' },
        ]}
      />
    </div>
  );
}
