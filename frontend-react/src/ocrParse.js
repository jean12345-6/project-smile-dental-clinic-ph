// Turns the raw text of a scanned patient form into clean text and into patient fields.

const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

/** Remove OCR noise: brackets, box lines, leader dots and stray symbols. */
export function cleanText(raw) {
  return String(raw || '')
    .replace(/[\[\]{}|\\~^`<>]/g, ' ')
    .replace(/[_.\-=*]{3,}/g, ' ')
    .split('\n')
    .map((l) => l.replace(/[ \t]+/g, ' ').replace(/(^|\s)[^\w\s]{1,2}(?=\s|$)/g, ' ').replace(/\s+/g, ' ').trim())
    .filter((l) => /[A-Za-z0-9]/.test(l))
    .join('\n');
}

const digitsFix = (s) => s.replace(/[Oo]/g, '0').replace(/[lI|]/g, '1');

export function toIsoDate(s) {
  if (!s) return '';
  const t = s.trim().replace(/,/g, ' ').replace(/\s+/g, ' ');
  let m = t.match(/(\d{4})[\/.\-](\d{1,2})[\/.\-](\d{1,2})/);
  if (m) return fmt(+m[1], +m[2], +m[3]);
  m = digitsFix(t).match(/(\d{1,2})[\/.\-\s](\d{1,2})[\/.\-\s](\d{2,4})/);
  if (m) {
    let [a, b, y] = [+m[1], +m[2], +m[3]];
    if (y < 100) y += y > 30 ? 1900 : 2000;
    return a > 12 ? fmt(y, b, a) : fmt(y, a, b); // month/day/year is the usual Philippine form
  }
  m = t.match(/([A-Za-z]{3,})\.?\s+(\d{1,2})\s+(\d{4})/);
  if (m && MONTHS.includes(m[1].slice(0, 3).toLowerCase())) return fmt(+m[3], MONTHS.indexOf(m[1].slice(0, 3).toLowerCase()) + 1, +m[2]);
  m = t.match(/(\d{1,2})\s+([A-Za-z]{3,})\.?\s+(\d{4})/);
  if (m && MONTHS.includes(m[2].slice(0, 3).toLowerCase())) return fmt(+m[3], MONTHS.indexOf(m[2].slice(0, 3).toLowerCase()) + 1, +m[1]);
  return '';
}
function fmt(y, mo, d) {
  if (y < 1900 || y > new Date().getFullYear() || mo < 1 || mo > 12 || d < 1 || d > 31) return '';
  return `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

export function toPhone(s) {
  const d = digitsFix(s || '').replace(/[^\d+]/g, '');
  const m = d.replace(/^\+?63/, '0').match(/0?9\d{9}/);
  if (m) return m[0].startsWith('0') ? m[0] : '0' + m[0];
  const other = d.replace(/\D/g, '');
  return other.length >= 7 ? other : '';
}

const LABELS = [
  ['full_name', /^(?:patient'?s?\s*)?(?:full\s*)?name|^pangalan/i],
  ['birthdate', /^(?:date\s*of\s*)?birth\s*(?:date|day)?|^birthday|^dob|^b-?day|^kapanganakan/i],
  ['contact_no', /^(?:contact|mobile|phone|cell(?:phone)?|tel(?:ephone)?)(?:\s*(?:no\.?|number|#))?/i],
  ['email', /^e-?mail(?:\s*address)?/i],
  ['address', /^(?:home\s*|complete\s*)?address|^tirahan/i],
  ['medical_history', /^(?:medical\s*(?:history|condition[s]?)|health\s*(?:history|condition[s]?)|allerg(?:y|ies)|medications?|conditions?)/i],
];

/** Reads the cleaned text and returns { full_name, birthdate, contact_no, email, address, medical_history }. */
export function parseForm(text) {
  const lines = cleanText(text).split('\n');
  const out = { full_name: '', birthdate: '', contact_no: '', email: '', address: '', medical_history: '' };
  const med = [];
  for (let i = 0; i < lines.length; i++) {
    for (const [key, re] of LABELS) {
      const m = lines[i].match(re);
      if (!m) continue;
      let val = lines[i].slice(m[0].length).replace(/^\s*[:;\-–]\s*/, '').trim();
      if (!val && lines[i + 1] && !LABELS.some(([, r]) => r.test(lines[i + 1]))) val = lines[i + 1].trim();
      val = val.replace(/\s+(?:age|sex|gender|civil status|birth\s*date|contact|email|address)\b.*$/i, (x) => (key === 'address' || key === 'medical_history' ? x : '')).trim();
      if (!val) break;
      if (key === 'medical_history') med.push(val);
      else if (!out[key]) out[key] = val;
      break;
    }
  }
  out.medical_history = med.join('; ');
  out.birthdate = toIsoDate(out.birthdate) || toIsoDate((text.match(/\b\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}\b/) || [''])[0]);
  const mail = (out.email || text).match(/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/);
  out.email = mail ? mail[0].toLowerCase() : '';
  out.contact_no = toPhone(out.contact_no) || toPhone((text.match(/(?:\+?63|0)\s?9\d{2}[\s-]?\d{3}[\s-]?\d{4}/) || [''])[0]);
  out.full_name = out.full_name.replace(/[^A-Za-z.,'\-\sñÑ]/g, ' ').replace(/\s+/g, ' ').trim();
  return out;
}

/** Finds the patient in the list whose name matches the scanned name. */
export function matchPatient(name, patients) {
  const tok = (s) => String(s || '').toLowerCase().replace(/[^a-zñ\s]/g, ' ').split(/\s+/).filter((w) => w.length > 1);
  const t = tok(name);
  if (t.length < 2) return null;
  let best = null;
  let score = 0;
  for (const p of patients) {
    const pt = tok(p.label);
    const hit = t.filter((w) => pt.includes(w)).length;
    const s = hit / Math.max(t.length, pt.length);
    if (hit >= 2 && s > score) { best = p; score = s; }
  }
  return score >= 0.6 ? best : null;
}

/** Grayscale + bigger image + more contrast: Tesseract reads this much better. */
export async function prepareImage(file) {
  try {
    const bmp = await createImageBitmap(file);
    const scale = bmp.width < 1800 ? 1800 / bmp.width : 1;
    const c = document.createElement('canvas');
    c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
    const ctx = c.getContext('2d');
    ctx.drawImage(bmp, 0, 0, c.width, c.height);
    const img = ctx.getImageData(0, 0, c.width, c.height);
    const d = img.data;
    let lo = 255; let hi = 0;
    for (let i = 0; i < d.length; i += 4) { const g = (d[i] * 0.3 + d[i + 1] * 0.59 + d[i + 2] * 0.11); d[i] = g; if (g < lo) lo = g; if (g > hi) hi = g; }
    const span = Math.max(1, hi - lo);
    for (let i = 0; i < d.length; i += 4) { const v = Math.max(0, Math.min(255, ((d[i] - lo) * 255) / span)); d[i] = d[i + 1] = d[i + 2] = v; }
    ctx.putImageData(img, 0, 0);
    return await new Promise((r) => c.toBlob(r, 'image/png'));
  } catch { return file; }
}

/** Smaller colour JPEG for the AI reader (keeps the upload fast). */
export async function shrinkImage(file) {
  try {
    const bmp = await createImageBitmap(file);
    const scale = Math.min(1, 1800 / Math.max(bmp.width, bmp.height));
    const c = document.createElement('canvas');
    c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    return await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.9));
  } catch { return file; }
}
