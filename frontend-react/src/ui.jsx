import React from 'react';

// "2026-11-09T10:00:00.000000Z" -> "2026-11-09 10:00" (no timezone shifting)
export const fmt = (s) => (s ? String(s).replace('T', ' ').slice(0, 16) : '-');
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
