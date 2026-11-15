import React from 'react';
import { Navigate, NavLink, Route, Routes, useNavigate } from 'react-router-dom';
import { useAuth } from './auth.jsx';
import { toggleTheme } from './theme.js';
import Login from './pages/Login.jsx';
import Book from './pages/Book.jsx';
import MyAppointments from './pages/MyAppointments.jsx';
import Waitlist from './pages/Waitlist.jsx';
import Dashboard from './pages/Dashboard.jsx';
import Appointments from './pages/Appointments.jsx';
import Users from './pages/Users.jsx';
import ChatDock from './ChatDock.jsx';
import Queue from './pages/Queue.jsx';
import Billing from './pages/Billing.jsx';
import Patients from './pages/Patients.jsx';
import Inventory from './pages/Inventory.jsx';
import Reports from './pages/Reports.jsx';
import Logs from './pages/Logs.jsx';
import Settings from './pages/Settings.jsx';
import OCR from './pages/OCR.jsx';
import MyRecords from './pages/MyRecords.jsx';
import Register from './pages/Register.jsx';
import Clinics from './pages/Clinics.jsx';
import Schedule from './pages/Schedule.jsx';
   import Landing from './pages/Landing.jsx';
import Dentists from './pages/Dentists.jsx';
import Feedback from './pages/Feedback.jsx';

function Shell({ children }) {
  const { user, logout, isPatient } = useAuth();
  const navigate = useNavigate();

  const role = user?.role;
  const admin = ['superadmin', 'clinic_admin'].includes(role);
  const front = admin || role === 'receptionist';
  const links = isPatient
    ? [
        ['#chat', '💬', 'Assistant'],
        ['/my-appointments', '📅', 'My appointments'],
        ['/my-records', '🦷', 'My records'],
        ['/waitlist', '🔔', 'My waitlist'],
      ]
    : [
        ['/dashboard', '📊', 'Dashboard'],
        ['/queue', '🧑‍⚕️', 'Daily queue'],
        ['/appointments', '📅', 'Appointments'],
        front && ['/book', '➕', 'Book for patient'],
        ['/patients', '👥', 'Patients'],
        ['/schedule', '🗓', 'Doctor schedule'],
        front && ['/billing', '💳', 'Billing'],
        front && ['/waitlist', '🔔', 'Waitlist'],
        ['/inventory', '📦', 'Inventory'],
        admin && ['/dentists', '🦷', 'Dentists'],
        admin && ['/feedback', '💬', 'Feedback'],
        front && ['/ocr', '📄', 'Scanned forms'],
        ['/reports', '📈', 'Reports'],
        admin && ['/settings', '⚙️', 'Clinic settings'],
        admin && ['/logs', '🛡️', 'Logs'],
        role === 'superadmin' && ['/clinics', '🏥', 'Clinics'],
        admin && ['/users', '🔑', 'Users'],
      ].filter(Boolean);

  return (
    <div className="shell">
      <aside className="sidebar">
        <div className="brand">
          <img src="/logo.png" alt="Project Smile Dental Clinic" />
          <div>
            <div className="brand-name">PROJECT SMILE</div>
            <div className="brand-sub">Dental Clinic</div>
          </div>
        </div>
        <nav>
          {links.map(([to, icon, label]) => to === '#chat' ? (
            <a key={to} href="#chat" onClick={(e) => { e.preventDefault(); window.dispatchEvent(new Event('open-chat')); }}>
              <span className="ico">{icon}</span> {label}
            </a>
          ) : (
            <NavLink key={to} to={to} className={({ isActive }) => (isActive ? 'active' : '')}>
              <span className="ico">{icon}</span> {label}
            </NavLink>
          ))}
        </nav>
        <div className="me">
          <div className="me-name">{user?.name}</div>
          <div className="me-role">{String(user?.role).replace('_', ' ')}</div>
          <div className="actions" style={{ justifyContent: 'stretch' }}>
            <button className="btn btn-ghost btn-sm" style={{ flex: 1 }} onClick={toggleTheme}>Toggle theme</button>
            <button
              className="btn btn-ghost btn-sm"
              style={{ flex: 1 }}
              onClick={async () => {
                await logout();
                navigate('/login');
              }}
            >
              Log out
            </button>
          </div>
        </div>
        <div className="credit">Dr. Ma. Theresa Delen<span>Dentist in charge</span></div>
      </aside>
      <main className="main"><div className="content">{children}</div></main>
    </div>
  );
}

function Protected({ children, roles }) {
  const { user, loading } = useAuth();
  if (loading) return <div className="center">Loading…</div>;
  if (!user) return <Navigate to="/login" replace />;
  if (roles && !roles.includes(user.role)) return <Navigate to="/" replace />;
  return <Shell>{children}</Shell>;
}
const PATIENT = ['patient'];
const STAFF = ['superadmin', 'clinic_admin', 'receptionist', 'dentist'];
const FRONT = ['superadmin', 'clinic_admin', 'receptionist'];
const ADMIN = ['superadmin', 'clinic_admin'];

function Home() {
  const { isPatient } = useAuth();
  return <Navigate to={isPatient ? '/my-appointments' : '/dashboard'} replace />;
}

export default function App() {
  return (
    <>
    <Routes>
      <Route path="/login" element={<Login />} />
         <Route path="/welcome" element={<Landing />} />
      <Route path="/register" element={<Register />} />
      <Route path="/clinics" element={<Protected roles={['superadmin']}><Clinics /></Protected>} />
      <Route path="/" element={<Protected><Home /></Protected>} />
      <Route path="/assistant" element={<Navigate to="/my-appointments" replace />} />
      <Route path="/my-records" element={<Protected roles={PATIENT}><MyRecords /></Protected>} />
      <Route path="/queue" element={<Protected roles={STAFF}><Queue /></Protected>} />
      <Route path="/billing" element={<Protected roles={FRONT}><Billing /></Protected>} />
      <Route path="/patients" element={<Protected roles={STAFF}><Patients /></Protected>} />
      <Route path="/inventory" element={<Protected roles={STAFF}><Inventory /></Protected>} />
      <Route path="/reports" element={<Protected roles={STAFF}><Reports /></Protected>} />
      <Route path="/logs" element={<Protected roles={ADMIN}><Logs /></Protected>} />
      <Route path="/settings" element={<Protected roles={ADMIN}><Settings /></Protected>} />
      <Route path="/ocr" element={<Protected roles={FRONT}><OCR /></Protected>} />
      <Route path="/book" element={<Protected roles={FRONT}><Book /></Protected>} />
      <Route path="/my-appointments" element={<Protected roles={PATIENT}><MyAppointments /></Protected>} />
      <Route path="/schedule" element={<Protected roles={STAFF}><Schedule /></Protected>} />
      <Route path="/waitlist" element={<Protected><Waitlist /></Protected>} />
      <Route path="/dashboard" element={<Protected roles={STAFF}><Dashboard /></Protected>} />
      <Route path="/appointments" element={<Protected roles={STAFF}><Appointments /></Protected>} />
      <Route path="/users" element={<Protected roles={ADMIN}><Users /></Protected>} />
      <Route path="*" element={<Navigate to="/" replace />} />
            <Route path="/dentists" element={<Protected roles={ADMIN}><Dentists /></Protected>} />
      <Route path="/feedback" element={<Protected roles={ADMIN}><Feedback /></Protected>} />
    </Routes>
    <ChatDock />
    </>
  );
}
