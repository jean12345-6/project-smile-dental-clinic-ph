// Dark is the default (navy and gold). The choice is remembered on this device.
export function initTheme() {
  let t = 'dark';
  try { t = localStorage.getItem('theme') || 'dark'; } catch { /* ignore */ }
  document.documentElement.dataset.theme = t;
}
export function toggleTheme() {
  const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
  document.documentElement.dataset.theme = next;
  try { localStorage.setItem('theme', next); } catch { /* ignore */ }
}
