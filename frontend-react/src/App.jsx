import React from 'react';
import { Navigate, NavLink, Route, Routes, useNavigate } from 'react-router-dom';
import { useAuth } from './auth.jsx';
import { toggleTheme } from './theme.js';
import Icon from './Icon.jsx';
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
import Treatments from './pages/Treatments.jsx';
import Prescriptions from './pages/Prescriptions.jsx';
import DentalChart from './pages/DentalChart.jsx';
import Landing from './pages/Landing.jsx';
import LandingEditor from './pages/LandingEditor.jsx';
import MyBilling from './pages/MyBilling.jsx';
import PatientDashboard from './pages/PatientDashboard.jsx';
import MyNotifications, { NotifBadge } from './pages/MyNotifications.jsx';
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
        ['/my-dashboard', 'dashboard', 'Dashboard'],
        ['#chat', 'chat', 'Assistant'],
        ['/my-appointments', 'calendar', 'My appointments'],
        ['/my-records', 'tooth', 'My records'],
        ['/my-invoices', 'receipt', 'My invoices'],
        ['/my-transactions', 'card', 'My transactions'],
        ['/my-notifications', 'bell', 'Notifications'],
        ['/waitlist', 'clock', 'My waitlist'],
      ]
    : [
        ['/dashboard', 'dashboard', 'Dashboard'],
        ['/queue', 'list', 'Daily queue'],
        ['/appointments', 'calendar', 'Appointments'],
        front && ['/book', 'plus', 'Book for patient'],
        ['/patients', 'users', 'Patients'],
        ['/schedule', 'schedule', 'Doctor schedule'],
        ['/treatments', 'clipboard', 'Treatments'],
        ['/prescriptions', 'pill', 'Prescriptions'],
        ['/dental-chart', 'tooth', 'Dental chart'],
        front && ['/billing', 'card', 'Billing'],
        front && ['/waitlist', 'clock', 'Waitlist'],
        ['/inventory', 'box', 'Inventory'],
        admin && ['/dentists', 'tooth', 'Dentists'],
        admin && ['/feedback', 'star', 'Feedback'],
        front && ['/ocr', 'file', 'Scanned forms'],
        ['/reports', 'chart', 'Reports'],
        admin && ['/settings', 'settings', 'Clinic settings'],
        admin && ['/logs', 'shield', 'Logs'],
        role === 'superadmin' && ['/clinics', 'building', 'Clinics'],
        admin && ['/landing-editor', 'globe', 'Landing page'],
        admin && ['/users', 'key', 'Users'],
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
              <span className="ico"><Icon name={icon} /></span> {label}
            </a>
          ) : (
            <NavLink key={to} to={to} className={({ isActive }) => (isActive ? 'active' : '')}>
              <span className="ico"><Icon name={icon} /></span> {label}{to === '/my-notifications' && <NotifBadge />}
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
  return <Navigate to={isPatient ? '/my-dashboard' : '/dashboard'} replace />;
}

function Root() {
  const { user, loading } = useAuth();
  if (loading) return <div className="center">Loading...</div>;
  if (!user) return <Landing />;
  return <Protected><Home /></Protected>;
}

export default function App() {
  return (
    <>
    <Routes>
      <Route path="/login" element={<Login />} />
         <Route path="/welcome" element={<Landing />} />
      <Route path="/register" element={<Register />} />
      <Route path="/clinics" element={<Protected roles={['superadmin']}><Clinics /></Protected>} />
      <Route path="/" element={<Root />} />
      <Route path="/landing" element={<Landing />} />
      <Route path="/landing-editor" element={<Protected roles={ADMIN}><LandingEditor /></Protected>} />
      <Route path="/assistant" element={<Navigate to="/my-appointments" replace />} />
      <Route path="/my-records" element={<Protected roles={PATIENT}><MyRecords /></Protected>} />
      <Route path="/my-invoices" element={<Protected roles={PATIENT}><MyBilling view="invoices" /></Protected>} />
      <Route path="/my-transactions" element={<Protected roles={PATIENT}><MyBilling view="transactions" /></Protected>} />
      <Route path="/my-dashboard" element={<Protected roles={PATIENT}><PatientDashboard /></Protected>} />
      <Route path="/my-notifications" element={<Protected roles={PATIENT}><MyNotifications /></Protected>} />
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
      <Route path="/treatments" element={<Protected roles={STAFF}><Treatments /></Protected>} />
      <Route path="/prescriptions" element={<Protected roles={STAFF}><Prescriptions /></Protected>} />
      <Route path="/dental-chart" element={<Protected roles={STAFF}><DentalChart /></Protected>} />
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
