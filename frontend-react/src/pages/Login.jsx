import React, { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth.jsx';
import { Alert } from '../ui.jsx';

export default function Login() {
  const { user, login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  if (user) return <Navigate to="/" replace />;

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      await login(email, password);
      navigate('/');
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="login-wrap">
      <form className="login-card" onSubmit={submit}>
        <img className="login-img" src="/logo.png" alt="Project Smile Dental Clinic" />
        <h1>SmileSync</h1>
        <p className="muted">AI-powered dental clinic workflow</p>
        {new URLSearchParams(window.location.search).get('registered') && <Alert kind="success">Account created. Please sign in.</Alert>}
        {new URLSearchParams(window.location.search).get('timeout') && <Alert kind="warn">You were signed out after 30 minutes of inactivity. Please sign in again.</Alert>}
        <Alert>{error}</Alert>
        <label>Email</label>
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus />
        <label>Password</label>
        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        <button className="btn btn-primary" disabled={busy}>
          {busy ? 'Signing in…' : 'Sign in'}
        </button>
        <p className="muted center-text">New patient? <Link to="/register">Create an account</Link></p>
      </form>
    </div>
  );
}
