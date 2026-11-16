import React, { useState } from 'react';
import Treatments from './Treatments.jsx';
import Prescriptions from './Prescriptions.jsx';

export default function TreatmentHistory() {
  const [activeTab, setActiveTab] = useState('treatments');

  return (
    <>
      <nav aria-label="Treatment history">
        <button
          type="button"
          className="btn btn-ghost btn-sm"
          aria-pressed={activeTab === 'treatments'}
          onClick={() => setActiveTab('treatments')}
        >
          Treatments
        </button>
        <button
          type="button"
          className="btn btn-ghost btn-sm"
          aria-pressed={activeTab === 'prescriptions'}
          onClick={() => setActiveTab('prescriptions')}
        >
          Prescriptions
        </button>
      </nav>

      {activeTab === 'treatments' ? <Treatments /> : <Prescriptions />}
    </>
  );
}