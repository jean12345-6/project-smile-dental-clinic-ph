import React, { useEffect, useState } from 'react';
import { api } from './api.js';
import { useAuth } from './auth.jsx';
import { Alert, Card, Empty } from './ui.jsx';

export function Modal({ title, onClose, children, wide }) {
  return (
    <div className="modal-back" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className={`modal ${wide ? 'modal-wide' : ''}`}>
        <div className="modal-head">
          <h2>{title}</h2>
          <button className="x" onClick={onClose} aria-label="Close">×</button>
        </div>
        {children}
      </div>
    </div>
  );
}

export const money = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export function useOptions() {
  const { lookups, name } = useAuth();
  return {
    service: (lookups?.services || []).map((s) => ({ value: s.service_id, label: s.service_name })),
    dentist: (lookups?.dentists || []).map((d) => ({ value: d.dentist_id, label: name.dentist(d.dentist_id) })),
    branch: (lookups?.branches || []).map((b) => ({ value: b.branch_id, label: name.branch(b.branch_id) })),
    patient: (lookups?.patients || []).map((p) => ({ value: p.patient_id, label: p.name })),
  };
}

export function Field({ f, value, onChange, options }) {
  const common = { value: value ?? '', onChange: (e) => onChange(e.target.value) };
  let input;
  if (f.type === 'select') {
    const opts = f.options || options[f.optionsFrom] || [];
    input = (
      <select {...common}>
        <option value="">{f.placeholder || 'Choose…'}</option>
        {opts.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    );
  } else if (f.type === 'textarea') input = <textarea rows={3} {...common} />;
  else input = <input type={f.type || 'text'} step={f.step} {...common} />;
  return (
    <div>
      <label>{f.label}{f.required ? ' *' : ''}</label>
      {input}
    </div>
  );
}

/** A table with search, paging, add / edit / delete in a pop-up. Works for any /r/{res} resource. */
export function Crud({ res, title, columns, fields, canAdd = true, canEdit = true, canDelete = true, deleteLabel = 'Delete', deleteConfirm = 'Delete this record?', rowActions, fixed = {}, hint, defaultSort }) {
  const options = useOptions();
  const [rows, setRows] = useState(null);
  const [meta, setMeta] = useState({ total: 0, page: 1, perPage: 50 });
  const [q, setQ] = useState('');
  const [sort, setSort] = useState(defaultSort || null); // { key, dir }
  const [page, setPage] = useState(1);
  const [error, setError] = useState([]);
  const [edit, setEdit] = useState(null); // {} for new, row for existing
  const [form, setForm] = useState({});
  const [saving, setSaving] = useState(false);
  const pk = columns.pk;

  const load = (over = {}) =>
    api('/r/' + res, { params: { q, page, ...(sort ? { sort: sort.key, dir: sort.dir } : {}), ...fixed, ...over } })
      .then((r) => { setRows(r.data); setMeta(r.meta); setError([]); })
      .catch((e) => setError(e.messages));

  useEffect(() => { load(); }, [page, res, sort]); // eslint-disable-line
  const search = () => { setPage(1); load({ page: 1 }); };
  const clear = () => { setQ(''); setPage(1); load({ q: '', page: 1 }); };
  const sortBy = (key) => { setPage(1); setSort((s) => (s?.key === key ? { key, dir: s.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'asc' })); };

  const open = (row) => { setEdit(row || {}); setForm(row ? { ...row } : { ...fixed }); };
  const save = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const id = edit?.[pk];
      await api(id ? `/r/${res}/${id}` : '/r/' + res, { method: id ? 'PATCH' : 'POST', body: form });
      setEdit(null);
      load();
    } catch (err) { setError(err.messages); setEdit(null); }
    setSaving(false);
  };
  const del = async (row) => {
    if (!window.confirm(deleteConfirm)) return;
    try { await api(`/r/${res}/${row[pk]}`, { method: 'DELETE' }); load(); } catch (err) { setError(err.messages); }
  };
  const pages = Math.max(1, Math.ceil((meta.total || 0) / (meta.perPage || 50)));

  return (
    <Card
      title={title}
      right={
        <div className="filters">
          <input placeholder="Search…" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && search()} />
          <button className="btn btn-ghost btn-sm" onClick={search}>Search</button>
          {q && <button className="btn btn-ghost btn-sm" onClick={clear}>Clear</button>}
          {canAdd && <button className="btn btn-primary btn-sm" onClick={() => open(null)}>+ Add</button>}
        </div>
      }
    >
      {hint && <p className="muted">{hint}</p>}
      <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
      {rows && rows.length === 0 && <Empty>Nothing here yet.</Empty>}
      {rows && rows.length > 0 && (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>{columns.map((c) => (
                c.render && !c.sortable
                  ? <th key={c.key}>{c.label}</th>
                  : <th key={c.key} style={{ cursor: 'pointer', userSelect: 'none' }} title="Click to sort A–Z" onClick={() => sortBy(c.key)}>{c.label}{sort?.key === c.key ? (sort.dir === 'asc' ? ' ▲' : ' ▼') : ''}</th>
              ))}<th /></tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r[pk]}>
                  {columns.map((c) => <td key={c.key}>{c.render ? c.render(r) : String(r[c.key] ?? '-')}</td>)}
                  <td className="actions">
                    {rowActions && rowActions(r, load)}
                    {canEdit && <button className="btn btn-ghost btn-sm" onClick={() => open(r)}>Edit</button>}
                    {canDelete && <button className="btn btn-danger btn-sm" onClick={() => del(r)}>{deleteLabel}</button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {pages > 1 && (
        <div className="pager">
          <button className="btn btn-ghost btn-sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</button>
          <span>Page {page} of {pages}</span>
          <button className="btn btn-ghost btn-sm" disabled={page >= pages} onClick={() => setPage(page + 1)}>Next</button>
        </div>
      )}
      {edit && (
        <Modal title={edit[pk] ? 'Edit' : 'Add'} onClose={() => setEdit(null)}>
          <form onSubmit={save}>
            <div className="grid">
              {fields.map((f) => <Field key={f.key} f={f} value={form[f.key]} options={options} onChange={(v) => setForm({ ...form, [f.key]: v })} />)}
            </div>
            <div className="modal-foot">
              <button type="button" className="btn btn-ghost" onClick={() => setEdit(null)}>Cancel</button>
              <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
            </div>
          </form>
        </Modal>
      )}
    </Card>
  );
}

export function csv(filename, rows) {
  if (!rows?.length) return;
  const keys = Object.keys(rows[0]);
  const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
  const text = [keys.join(','), ...rows.map((r) => keys.map((k) => esc(r[k])).join(','))].join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([text], { type: 'text/csv' }));
  a.download = filename;
  a.click();
}
