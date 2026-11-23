import React, { useEffect, useRef, useState } from 'react';
import { api } from '../api.js';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth.jsx';
import { Alert } from '../ui.jsx';

export default function Login() {
  const { user, login, loginWithGoogle } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  // Sign in with Google: show Google's own button when the server has a client ID set
  const googleBox = useRef(null);
  const [googleReady, setGoogleReady] = useState(false);
  useEffect(() => {
    if (user) return undefined;
    let cancelled = false;
    (async () => {
      let clientId = null;
      try {
        const res = await api('/auth/google/config');
        clientId = res?.data?.clientId || null;
      } catch { /* no Google button if the setting cannot be read */ }
      if (!clientId || cancelled) return;
      const start = () => {
        if (cancelled || !window.google?.accounts?.id || !googleBox.current) return;
        window.google.accounts.id.initialize({
          client_id: clientId,
          callback: async ({ credential }) => {
            setError('');
            setBusy(true);
            try {
              await loginWithGoogle(credential);
              navigate('/');
            } catch (err) {
              setError(err.message);
            } finally {
              setBusy(false);
            }
          },
        });
        window.google.accounts.id.renderButton(googleBox.current, { theme: 'outline', size: 'large', text: 'signin_with', shape: 'pill', width: 320 });
        setGoogleReady(true);
      };
      if (window.google?.accounts?.id) { start(); return; }
      const tag = document.createElement('script');
      tag.src = 'https://accounts.google.com/gsi/client';
      tag.async = true;
      tag.onload = start;
      document.head.appendChild(tag);
    })();
    return () => { cancelled = true; };
  }, [user]); // eslint-disable-line react-hooks/exhaustive-deps

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
        <div className="login-or" style={{ display: googleReady ? 'flex' : 'none' }}><span>or</span></div>
        <div ref={googleBox} className="login-google" />
        <p className="muted center-text">New patient? <Link to="/register">Create an account</Link>{googleReady ? ' or sign in with Google.' : ''}</p>
      </form>
    </div>
  );
}
