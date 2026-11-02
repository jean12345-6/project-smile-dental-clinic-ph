import React, { useEffect, useState } from 'react';
import { api } from '../api.js';
import { Alert, Card, Empty } from '../ui.jsx';

export default function Users() {
  const [rows, setRows] = useState(null);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(null);
  const [error, setError] = useState([]);

  useEffect(() => {
    api('/users', { params: { page } })
      .then((res) => {
        setRows(res.data || []);
        setMeta(res.meta || null);
      })
      .catch((e) => setError(e.messages));
  }, [page]);

  return (
    <>
      <h1 className="page-title">Users</h1>
      <Card title="Clinic accounts">
        <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
        {rows && rows.length === 0 && <Empty>No users.</Empty>}
        {rows && rows.length > 0 && (
          <div className="table-wrap">
            <table>
              <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>
              </thead>
              <tbody>
                {rows.map((u) => (
                  <tr key={u.id}>
                    <td>{u.attributes.name}</td>
                    <td>{u.attributes.email}</td>
                    <td>{String(u.attributes.role).replace('_', ' ')}</td>
                    <td>{u.attributes.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {meta?.last_page > 1 && (
          <div className="pager">
            <button className="btn btn-ghost btn-sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>← Prev</button>
            <span>Page {meta.current_page} of {meta.last_page}</span>
            <button className="btn btn-ghost btn-sm" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Next →</button>
          </div>
        )}
      </Card>
    </>
  );
}
