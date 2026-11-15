// Light is the default (navy and gold on white). The choice is remembered on this device.
const KEY = 'smilesync-theme';

export function initTheme() {
  let t = 'light';
  try { t = localStorage.getItem(KEY) || 'light'; } catch { /* ignore */ }
  document.documentElement.dataset.theme = t;
}

export function toggleTheme() {
  const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
  document.documentElement.dataset.theme = next;
  try { localStorage.setItem(KEY, next); } catch { /* ignore */ }
}
