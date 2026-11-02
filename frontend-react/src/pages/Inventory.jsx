import React from 'react';
import { api } from '../api.js';
import { Crud, useOptions } from '../components.jsx';

export default function Inventory() {
  const opt = useOptions();
  const bn = (id) => (opt.branch.find((b) => String(b.value) === String(id)) || {}).label || id;
  return (
    <>
      <h1 className="page-title">Inventory</h1>
      <Crud
        res="inventory"
        title="Dental supplies"
        hint="Items at or below their reorder level are marked red and shown on the dashboard."
        columns={Object.assign([
          { key: 'item_name', label: 'Item' }, { key: 'unit', label: 'Unit' },
          { key: 'quantity', label: 'In stock', render: (r) => <strong className={Number(r.quantity) <= Number(r.reorder_level) ? 'low' : ''}>{r.quantity}{Number(r.quantity) <= Number(r.reorder_level) ? ' · reorder' : ''}</strong> },
          { key: 'reorder_level', label: 'Reorder level' }, { key: 'branch_id', label: 'Branch', render: (r) => bn(r.branch_id) },
        ], { pk: 'item_id' })}
        fields={[
          { key: 'item_name', label: 'Item name', required: true }, { key: 'unit', label: 'Unit (box, pcs…)' },
          { key: 'quantity', label: 'Quantity', type: 'number' }, { key: 'reorder_level', label: 'Reorder level', type: 'number' },
          { key: 'branch_id', label: 'Branch', type: 'select', optionsFrom: 'branch' },
        ]}
        rowActions={(r, reload) => (
          <button className="btn btn-ghost btn-sm" disabled={Number(r.quantity) <= 0} onClick={async () => { await api(`/r/inventory/${r.item_id}`, { method: 'PATCH', body: { quantity: Number(r.quantity) - 1 } }); reload(); }}>Use 1</button>
        )}
      />
    </>
  );
}
