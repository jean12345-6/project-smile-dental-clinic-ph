import ClinicInfoHours from './ClinicInfoHours.jsx';
import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Crud, Field, money } from '../components.jsx';
import { Alert, Card } from '../ui.jsx';
import { LogoButton, clinicColumns, clinicFields } from './Clinics.jsx';

function Rules() {
  const [v, setV] = useState(null);
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState([]);
  useEffect(() => { api('/app-settings').then(setV).catch((e) => setErr(e.messages)); }, []);
  const save = async (e) => {
    e.preventDefault();
    try { setV(await api('/app-settings', { method: 'PUT', body: v })); setMsg('Saved. The assistant and the reminder job use these values now.'); setErr([]); } catch (er) { setErr(er.messages); }
  };
  if (!v) return <Alert>{err.join(' ')}</Alert>;
  const F = (k, label) => <Field f={{ label, type: 'number' }} value={v[k]} options={{}} onChange={(x) => setV({ ...v, [k]: x })} />;
  return (
    <Card title="Reminder and risk rules">
      <Alert>{err.map((m, i) => <div key={i}>{m}</div>)}</Alert>
      <Alert kind="success">{msg}</Alert>
      <form onSubmit={save}>
        <div className="grid">
          {F('reminder_hours', 'Send a reminder this many hours before the visit')}
          {F('no_show_threshold', 'Flag a booking as high risk after this many no-shows')}
          {F('offer_expiry_hours', 'A waitlist offer expires after this many hours')}
        </div>
        <button className="btn btn-primary" style={{ marginTop: 14 }}>Save rules</button>
      </form>
    </Card>
  );
}

const days = [[1, 'Monday'], [2, 'Tuesday'], [3, 'Wednesday'], [4, 'Thursday'], [5, 'Friday'], [6, 'Saturday'], [7, 'Sunday']].map(([value, label]) => ({ value, label }));
const P = (cols, pk) => Object.assign(cols, { pk });

export default function Settings() {
  const [tab, setTab] = useState('clinic');
  const tabs = {
    clinic: ['Clinic profile', <Crud key="c" res="clinics" title="Your clinic" hint="Name, contact details and logo." canAdd={false} canDelete={false} columns={clinicColumns()} fields={clinicFields}
      rowActions={(r, reload) => <LogoButton row={r} reload={reload} setError={() => {}} />} />],
    services: ['Services and prices', <Crud key="s" res="services" title="Services" hint="Prices and durations used by the assistant and the slot engine." columns={P([{ key: 'service_name', label: 'Service' }, { key: 'duration_min', label: 'Minutes' }, { key: 'price', label: 'Price', render: (r) => money(r.price) }, { key: 'is_active', label: 'Active', render: (r) => (r.is_active ? 'Yes' : 'No') }], 'service_id')}
      fields={[{ key: 'service_name', label: 'Service name', required: true }, { key: 'duration_min', label: 'Duration (minutes)', type: 'number' }, { key: 'price', label: 'Price (₱)', type: 'number', step: '0.01' }, { key: 'is_active', label: 'Active', type: 'select', options: [{ value: 1, label: 'Yes' }, { value: 0, label: 'No' }] }, { key: 'clinic_id', label: 'Clinic ID', type: 'number' }]} />],
    branches: ['Branches', <Crud key="b" res="branches" title="Branches" columns={P([{ key: 'branch_name', label: 'Branch' }, { key: 'address', label: 'Address' }, { key: 'contact_no', label: 'Contact' }, { key: 'open_time', label: 'Opens' }, { key: 'close_time', label: 'Closes' }], 'branch_id')}
      fields={[{ key: 'branch_name', label: 'Branch name', required: true }, { key: 'address', label: 'Address' }, { key: 'contact_no', label: 'Contact no.' }, { key: 'open_time', label: 'Opening time', type: 'time' }, { key: 'close_time', label: 'Closing time', type: 'time' }, { key: 'clinic_id', label: 'Clinic ID', type: 'number' }]} />],
    schedules: ['Dentist schedules', <Crud key="d" res="schedules" title="Working days, breaks and leave" hint="Add a row per working day. For leave, leave the day empty and set only the leave date."
      columns={P([{ key: 'dentist_id', label: 'Dentist ID' }, { key: 'branch_id', label: 'Branch' }, { key: 'day_of_week', label: 'Day', render: (r) => days.find((d) => d.value == r.day_of_week)?.label || 'LEAVE' }, { key: 'start_time', label: 'From' }, { key: 'end_time', label: 'To' }, { key: 'break_start', label: 'Break from' }, { key: 'break_end', label: 'Break to' }, { key: 'leave_date', label: 'Leave date' }], 'schedule_id')}
      fields={[{ key: 'dentist_id', label: 'Dentist', type: 'select', optionsFrom: 'dentist', required: true }, { key: 'branch_id', label: 'Branch', type: 'select', optionsFrom: 'branch', required: true }, { key: 'day_of_week', label: 'Day of week', type: 'select', options: days }, { key: 'start_time', label: 'Start', type: 'time' }, { key: 'end_time', label: 'End', type: 'time' }, { key: 'break_start', label: 'Break start', type: 'time' }, { key: 'break_end', label: 'Break end', type: 'time' }, { key: 'leave_date', label: 'Leave date', type: 'date' }]} />],
    dentists: ['Dentists', <Crud key="dn" res="dentists" title="Dentists" columns={P([{ key: 'full_name', label: 'Name' }, { key: 'specialization', label: 'Specialization' }, { key: 'license_no', label: 'License no.' }], 'dentist_id')}
      fields={[{ key: 'full_name', label: 'Full name', required: true }, { key: 'specialization', label: 'Specialization' }, { key: 'license_no', label: 'License no.' }, { key: 'user_id', label: 'User account ID', type: 'number' }]} />],
    info: ['Clinic info and hours', <ClinicInfoHours key="ci" />],
    rules: ['Reminders and rules', <Rules key="r" />],
    faq: ['Assistant answers', <Crud key="f" res="faq" title="Questions the assistant can answer" hint="The assistant matches patient questions against these entries. Add the common questions your patients ask."
      columns={P([{ key: 'question', label: 'Question' }, { key: 'answer', label: 'Answer' }, { key: 'category', label: 'Category' }], 'faq_id')}
      fields={[{ key: 'question', label: 'Question (use the words patients use)', required: true }, { key: 'answer', label: 'Answer', type: 'textarea', required: true }, { key: 'category', label: 'Category' }, { key: 'clinic_id', label: 'Clinic ID', type: 'number' }]} />],
  };
  return (
    <>
      <h1 className="page-title">Clinic settings</h1>
      <div className="tabs">{Object.entries(tabs).map(([k, [l]]) => <button key={k} className={tab === k ? 'on' : ''} onClick={() => setTab(k)}>{l}</button>)}</div>
      {tabs[tab][1]}
    </>
  );
}
