
import React, { useState } from 'react';
import { Crud } from '../components.jsx';

export default function Reminders() {
  const [status, setStatus] = useState('');

  const columns = Object.assign(
    [
      {
        key: 'reminder_id',
        label: 'Reminder ID'
      },
      {
        key: 'appointment_id',
        label: 'Appointment',
        render: (r) => `#${r.appointment_id}`
      },
      {
        key: 'patient',
        label: 'Patient',
        render: (r) =>
          r.patient?.name ||
          r.patient_name ||
          '—'
      },
      {
        key: 'type',
        label: 'Type'
      },
      {
        key: 'channel',
        label: 'Channel',
        render: (r) => (
          <span className="badge">
            {String(r.channel || '-').toUpperCase()}
          </span>
        )
      },
      {
        key: 'send_time',
        label: 'Send Time',
        render: (r) => r.send_time || '—'
      },
      {
        key: 'send_status',
        label: 'Status',
        render: (r) => (
          <span className="badge">
            {String(r.send_status || 'pending').toUpperCase()}
          </span>
        )
      }
    ],
    { pk: 'reminder_id' }
  );

  return (
    <>
      <h1 className="page-title">
        Reminders Management
      </h1>

      <p className="muted">
        View and monitor patient appointment reminders
        sent through SMS and email.
      </p>

      <div className="filters" style={{ marginBottom: 16 }}>
        <label htmlFor="reminder-status">
          Filter by status
        </label>

        <select
          id="reminder-status"
          value={status}
          onChange={(e) => setStatus(e.target.value)}
        >
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="sent">Sent</option>
          <option value="failed">Failed</option>
        </select>
      </div>

      <Crud
        key={status}
        res="reminders"
        title="Appointment Reminders"
        hint="Track scheduled SMS and email reminders."
        columns={columns}
        fields={[]}
        fixed={status ? { send_status: status } : {}}
        canAdd={false}
        canEdit={false}
        canDelete={false}
        defaultSort={{ key: 'send_time', dir: 'desc' }}
      />
    </>
  );
}
