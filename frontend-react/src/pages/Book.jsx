import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api.js';
import { useAuth } from '../auth.jsx';
import { Alert, Card, Empty, fmt } from '../ui.jsx';

const today = () => new Date().toISOString().slice(0, 10);

export default function Book() {
  const { lookups, isPatient, name } = useAuth();
  const navigate = useNavigate();

  const [patientId, setPatientId] = useState(isPatient ? String(lookups?.patientId || '') : '');
  const [serviceId, setServiceId] = useState('');
  const [branchId, setBranchId] = useState('');
  const [dentistId, setDentistId] = useState('');
  const [date, setDate] = useState('');
  const [slots, setSlots] = useState(null);
  const [slot, setSlot] = useState(null);
  const [loadingSlots, setLoadingSlots] = useState(false);
  const [error, setError] = useState([]);
  const [info, setInfo] = useState('');
  const [busy, setBusy] = useState(false);

  const ready = serviceId && branchId && dentistId && date;

  useEffect(() => {
    setSlot(null);
    setSlots(null);
    if (!ready) return;
    setLoadingSlots(true);
    setError([]);
    api('/slots', { params: { dentistId, serviceId, branchId, date } })
      .then((res) => setSlots(res.data || []))
      .catch((e) => setError(e.messages))
      .finally(() => setLoadingSlots(false));
  }, [serviceId, branchId, dentistId, date]);

  const book = async () => {
    setError([]);
    setInfo('');
    if (!patientId) return setError(['Please choose a patient.']);
    setBusy(true);
    try {
      await api('/appointments', {
        method: 'POST',
        body: {
          data: {
            attributes: {
              startTime: slot.startTime,
              endTime: slot.endTime,
              status: 'pending',
              bookedVia: 'portal',
              followUpOf: null,
            },
            relationships: {
              patient: { data: { id: Number(patientId) } },
              dentist: { data: { id: Number(dentistId) } },
              branch: { data: { id: Number(branchId) } },
              service: { data: { id: Number(serviceId) } },
            },
          },
        },
      });
      navigate(isPatient ? '/my-appointments' : '/appointments');
    } catch (e) {
      setError(e.messages);
      // the slot may have just been taken: reload the list
      api('/slots', { params: { dentistId, serviceId, branchId, date } })
        .then((res) => setSlots(res.data || []))
        .catch(() => {});
      setSlot(null);
    } finally {
      setBusy(false);
    }
  };

  const joinWaitlist = async () => {
    setError([]);
    setInfo('');
    if (!patientId && !isPatient) return setError(['Please choose a patient.']);
    setBusy(true);
    try {
      await api('/waitlist', {
        method: 'POST',
        body: {
          ...(isPatient ? {} : { patientId: Number(patientId) }),
          serviceId: Number(serviceId),
          branchId: Number(branchId),
          dentistId: Number(dentistId),
          preferredDate: date,
        },
      });
      setInfo('You are on the waitlist. We will offer you a slot as soon as one opens up.');
    } catch (e) {
      setError(e.messages);
    } finally {
      setBusy(false);
    }
  };

  const service = lookups?.services?.find((s) => String(s.service_id) === String(serviceId));

  return (
    <>
      <h1 className="page-title">{isPatient ? 'Book an appointment' : 'Book for a patient'}</h1>
      <Card title="1. Choose the details">
        <Alert>{error.map((m, i) => <div key={i}>{m}</div>)}</Alert>
        <Alert kind="success">{info}</Alert>

        <div className="grid">
          {!isPatient && (
            <div>
              <label>Patient</label>
              <select value={patientId} onChange={(e) => setPatientId(e.target.value)}>
                <option value="">Select patient…</option>
                {(lookups?.patients || []).map((p) => (
                  <option key={p.patient_id} value={p.patient_id}>{p.name}</option>
                ))}
              </select>
            </div>
          )}
          <div>
            <label>Service</label>
            <select value={serviceId} onChange={(e) => setServiceId(e.target.value)}>
              <option value="">Select service…</option>
              {(lookups?.services || []).map((s) => (
                <option key={s.service_id} value={s.service_id}>
                  {s.service_name} ({s.duration_min} min)
                </option>
              ))}
            </select>
          </div>
          <div>
            <label>Branch</label>
            <select value={branchId} onChange={(e) => setBranchId(e.target.value)}>
              <option value="">Select branch…</option>
              {(lookups?.branches || []).map((b) => (
                <option key={b.branch_id} value={b.branch_id}>{name.branch(b.branch_id)}</option>
              ))}
            </select>
          </div>
          <div>
            <label>Dentist</label>
            <select value={dentistId} onChange={(e) => setDentistId(e.target.value)}>
              <option value="">Select dentist…</option>
              {(lookups?.dentists || []).map((d) => (
                <option key={d.dentist_id} value={d.dentist_id}>{name.dentist(d.dentist_id)}</option>
              ))}
            </select>
          </div>
          <div>
            <label>Date</label>
            <input type="date" min={today()} value={date} onChange={(e) => setDate(e.target.value)} />
          </div>
        </div>
      </Card>

      <Card title="2. Pick a time">
        {!ready && <Empty>Fill in the service, branch, dentist and date to see the free times.</Empty>}
        {ready && loadingSlots && <Empty>Looking for free times…</Empty>}
        {ready && !loadingSlots && slots && slots.length === 0 && (
          <div>
            <Empty>
              No free times for this dentist on this date (the dentist may be off, or fully booked, or works at another
              branch).
            </Empty>
            <button className="btn btn-primary" disabled={busy} onClick={joinWaitlist}>
              Join the waitlist for this date
            </button>
          </div>
        )}
        {slots && slots.length > 0 && (
          <>
            <div className="slots">
              {slots.map((s) => (
                <button
                  key={s.startTime}
                  className={`slot ${slot?.startTime === s.startTime ? 'slot-on' : ''}`}
                  onClick={() => setSlot(s)}
                >
                  {s.startTime.slice(11, 16)}
                </button>
              ))}
            </div>
            {slot && (
              <div className="confirm">
                <p>
                  <strong>{service?.service_name}</strong> with <strong>{name.dentist(dentistId)}</strong> on{' '}
                  <strong>{fmt(slot.startTime)}</strong> to <strong>{slot.endTime.slice(11, 16)}</strong>
                </p>
                <button className="btn btn-primary" disabled={busy} onClick={book}>
                  {busy ? 'Booking…' : 'Confirm booking'}
                </button>
              </div>
            )}
          </>
        )}
      </Card>
    </>
  );
}
