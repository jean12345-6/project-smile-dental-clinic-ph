import React from 'react';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Crud } from '../components.jsx';

export default function Feedback() {
  const { name } = useAuth();
  return (
    <>
      <h1 className="page-title">Feedback</h1>
      <Crud
        res="feedback"
        title="Patient feedback"
        hint="Publish shows the review on the public landing page. Mark read/unread just tracks what you've reviewed."
        canAdd={false}
        canEdit={false}
        canDelete={true}
        columns={Object.assign([
          { key: 'patient_id', label: 'Patient', render: (r) => name.patient(r.patient_id) || '-' },
          { key: 'rating', label: 'Rating', render: (r) => `${r.rating ?? '-'} / 5` },
          { key: 'comment', label: 'Comment' },
          { key: 'date_submitted', label: 'Date' },
          { key: 'is_read', label: 'Status', render: (r) => (Number(r.is_read) ? 'Read' : 'New') },
          { key: 'published', label: 'Landing page', render: (r) => (Number(r.published) ? 'Published' : 'Hidden') },
        ], { pk: 'feedback_id' })}
        fields={[]}
        rowActions={(r, reload) => (
          <>
            <button className="btn btn-ghost btn-sm" onClick={async () => { await api(`/r/feedback/${r.feedback_id}`, { method: 'PATCH', body: { is_read: r.is_read ? 0 : 1 } }); reload(); }}>
              {Number(r.is_read) ? 'Mark unread' : 'Mark read'}
            </button>
            <button className="btn btn-primary btn-sm" onClick={async () => { await api(`/r/feedback/${r.feedback_id}`, { method: 'PATCH', body: { published: r.published ? 0 : 1 } }); reload(); }}>
              {Number(r.published) ? 'Unpublish' : 'Publish'}
            </button>
          </>
        )}
      />
    </>
  );
}
