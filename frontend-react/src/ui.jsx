import React from 'react';

// "2026-11-09T10:00:00.000000Z" -> "2026-11-09 10:00" (no timezone shifting)
export const fmt = (s) => (s ? String(s).replace('T', ' ').slice(0, 16) : '-');

// A friendlier form of the same value: "Tue, Nov 24 · 3:00 PM" instead of
// "2026-11-24 15:00". Deliberately separate from fmt rather than a change to it: ten
// screens slice fmt's output by character position to pull out just the date or just
// the time, and they would all have broken quietly.
export const when = (s) => {
  if (!s) return '-';
  const m = String(s).replace('T', ' ').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ ](\d{2}):(\d{2}))?/);
  if (!m) return fmt(s);
  const [, y, mo, d, hh, mm] = m;
  const dt = new Date(Number(y), Number(mo) - 1, Number(d), Number(hh || 0), Number(mm || 0));
  if (Number.isNaN(dt.getTime())) return fmt(s);
  const date = dt.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' });
  return hh === undefined ? date : `${date} · ${dt.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })}`;
};

export const day = (s) => (s ? String(s).slice(0, 10) : '');

export const rel = (a, key) => a?.relationships?.[key]?.data?.id;

export function Badge({ status }) {
  return <span className={`badge badge-${status}`}>{String(status || '').replace('_', ' ')}</span>;
}

export function Alert({ kind = 'error', children }) {
  if (!children || (Array.isArray(children) && !children.length)) return null;
  return <div className={`alert alert-${kind}`}>{children}</div>;
}

export function Card({ title, right, children }) {
  return (
    <section className="card">
      {(title || right) && (
        <div className="card-head">
          <h2>{title}</h2>
          <div>{right}</div>
        </div>
      )}
      {children}
    </section>
  );
}

export function Empty({ children }) {
  return <p className="empty">{children}</p>;
}
