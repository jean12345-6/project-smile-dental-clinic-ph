import React from 'react';
import { Crud, useOptions } from '../components.jsx';

const when = (r) => {
  const d = new Date(String(r.timestamp || r.created_at || '').replace(' ', 'T'));
  return isNaN(d) ? '-' : d.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
};

const isEscalated = (r) => r.escalated === true || Number(r.escalated) > 0;

// Button codes from the chatbot, shown as plain words
const CODES = {
  menu: 'Opened menu',
  book_slot: 'Picked a time',
  book_date: 'Picked a date',
  resched: 'Wants to reschedule',
};

const plain = (text) => {
  const m = String(text || '').trim().match(/^\[(.+)\]$/);
  if (!m) return text || '-';
  const code = m[1];
  if (CODES[code]) return CODES[code];
  return 'Chose: ' + code.replace(/_/g, ' ');
};

export default function ChatLogs() {
  const opt = useOptions();
  const patientName = (id) =>
    id ? (opt.patient.find((p) => String(p.value) === String(id)) || {}).label || `Patient #${id}` : 'Guest';

  return (
    <>
      <h1 className="page-title">Chat logs</h1>
      <div className="table-wrap">
        <div style={{ minWidth: 980 }}>
          <Crud
            res="chat-logs"
            title="Chatbot conversations"
            hint="Chats the bot could not answer are marked Escalated so staff can follow up."
            canAdd={false}
            canEdit={false}
            canDelete={false}
            defaultSort={{ key: 'timestamp', dir: 'desc' }}
            columns={Object.assign([
              { key: 'timestamp', label: 'Time', sortable: true, render: (r) => <span style={{ whiteSpace: 'nowrap' }}>{when(r)}</span> },
              { key: 'patient_id', label: 'Patient', render: (r) => patientName(r.patient_id) },
              { key: 'message_in', label: 'Message', render: (r) => { const t = plain(r.message_in); return <div className="clip" title={t}>{t}</div>; } },
              { key: 'message_out', label: 'Bot reply', render: (r) => <div className="clip" title={r.message_out}>{r.message_out || '-'}</div> },
              { key: 'intent', label: 'Intent', render: (r) => r.intent || '-' },
              {
                key: 'escalated',
                label: 'Escalated',
                sortable: true,
                render: (r) => (isEscalated(r)
                  ? <span className="badge badge-pending">Escalated</span>
                  : <span className="badge badge-completed">Bot handled</span>),
              },
            ], { pk: 'chat_id' })}
            fields={[]}
          />
        </div>
      </div>
    </>
  );
}