import React from 'react';
import { Crud } from '../components.jsx';

export default function Dentists() {
  return (
    <>
      <h1 className="page-title">Dentists</h1>
      <Crud
        res="dentists"
        title="Dentists"
        hint="Create the dentist's login first under Users, then add their professional profile here using that user's ID."
        columns={Object.assign([
          { key: 'full_name', label: 'Name' },
          { key: 'specialization', label: 'Specialization' },
          { key: 'license_no', label: 'License No.' },
        ], { pk: 'dentist_id' })}
        fields={[
          { key: 'full_name', label: 'Full name', required: true },
          { key: 'specialization', label: 'Specialization' },
          { key: 'license_no', label: 'License number' },
          { key: 'user_id', label: 'Linked user ID', type: 'number', required: true },
        ]}
      />
    </>
  );
}
