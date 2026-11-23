import React, { useState } from 'react';

/*
 * Small SVG charts for the dashboards. No chart library: each chart is plain SVG drawn
 * from the rows it is given, so it follows the light and dark theme through CSS
 * variables and adds nothing to the bundle size.
 *
 * Which chart for which data:
 *   ColumnChart  two amounts per month, compared side by side (sales vs paid)
 *   LineChart    a count over time, where the shape of the trend matters
 *   Donut        parts of one whole (appointment statuses, paid vs pending)
 */

const AXIS = 'var(--muted)';
const GRID = 'var(--line)';

// 1234567 -> "1.2M", 45000 -> "45K", for the axis only; exact amounts are in the tooltip.
const short = (v) => {
  const n = Math.abs(v);
  if (n >= 1e6) return `${(v / 1e6).toFixed(n >= 1e7 ? 0 : 1)}M`;
  if (n >= 1e3) return `${(v / 1e3).toFixed(n >= 1e4 ? 0 : 1)}K`;
  return String(Math.round(v));
};

// A "nice" top for the axis: 138,800 -> 150,000, so the gridlines fall on round numbers.
const niceMax = (v) => {
  if (v <= 0) return 1;
  const p = 10 ** Math.floor(Math.log10(v));
  const f = v / p;
  return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * p;
};

export function Legend({ items }) {
  return (
    <div className="chart-legend">
      {items.map((it) => (
        <span key={it.label}><i style={{ background: it.color }} />{it.label}{it.value !== undefined && <b>{it.value}</b>}</span>
      ))}
    </div>
  );
}

// The value under the pointer, shown above the chart. SVG <title> tooltips are slow and
// do not appear on a phone, so the chart keeps its own.
function useHover() {
  const [hover, setHover] = useState(null);
  return [hover, setHover];
}

/**
 * Grouped columns. rows: [{label, a, b}], series: [{key:'a', label, color}, ...]
 */
export function ColumnChart({ rows, series, fmt = (v) => v, height = 220 }) {
  const [hover, setHover] = useHover();
  if (!rows.length) return null;
  const W = 640, H = height, L = 46, R = 8, T = 12, B = 28;
  const top = niceMax(Math.max(...rows.flatMap((r) => series.map((s) => Number(r[s.key]) || 0))));
  const plotW = W - L - R, plotH = H - T - B;
  const slot = plotW / rows.length;
  const barW = Math.min(28, (slot * 0.7) / series.length);
  const y = (v) => T + plotH - (v / top) * plotH;
  const ticks = [0, 0.25, 0.5, 0.75, 1].map((f) => f * top);

  return (
    <div className="chart">
      <Legend items={series} />
      <div className="chart-hover">{hover ? `${hover.label}: ${series.map((s) => `${s.label} ${fmt(hover[s.key])}`).join(' · ')}` : ' '}</div>
      <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label="Column chart">
        {ticks.map((t) => (
          <g key={t}>
            <line x1={L} x2={W - R} y1={y(t)} y2={y(t)} stroke={GRID} strokeWidth="1" />
            <text x={L - 6} y={y(t) + 4} textAnchor="end" fontSize="11" fill={AXIS}>{short(t)}</text>
          </g>
        ))}
        {rows.map((r, i) => {
          const x0 = L + i * slot + (slot - barW * series.length) / 2;
          return (
            <g key={r.label} onMouseEnter={() => setHover(r)} onMouseLeave={() => setHover(null)} onClick={() => setHover(r)} style={{ cursor: 'pointer' }}>
              <rect x={L + i * slot} y={T} width={slot} height={plotH} fill="transparent" />
              {series.map((s, j) => {
                const v = Number(r[s.key]) || 0;
                return <rect key={s.key} x={x0 + j * barW} y={y(v)} width={barW - 2} height={Math.max(0, T + plotH - y(v))} rx="3" fill={s.color} opacity={hover && hover !== r ? 0.45 : 1} />;
              })}
              <text x={L + i * slot + slot / 2} y={H - 8} textAnchor="middle" fontSize="11" fill={AXIS}>{r.label}</text>
            </g>
          );
        })}
      </svg>
    </div>
  );
}

/**
 * Lines with points. rows: [{label, a, b}], series as in ColumnChart.
 */
export function LineChart({ rows, series, height = 230, width = 440 }) {
  const [hover, setHover] = useHover();
  if (!rows.length) return null;
  // narrower drawing than the column chart: it sits in the side column, so its text stays readable
  const W = width, H = height, L = 36, R = 16, T = 12, B = 28;
  const top = niceMax(Math.max(...rows.flatMap((r) => series.map((s) => Number(r[s.key]) || 0))));
  const plotW = W - L - R, plotH = H - T - B;
  const x = (i) => L + (rows.length === 1 ? plotW / 2 : (i / (rows.length - 1)) * plotW);
  const y = (v) => T + plotH - (v / top) * plotH;
  const ticks = [0, 0.5, 1].map((f) => f * top);

  return (
    <div className="chart">
      <Legend items={series} />
      <div className="chart-hover">{hover ? `${hover.label}: ${series.map((s) => `${s.label} ${hover[s.key]}`).join(' · ')}` : ' '}</div>
      <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label="Line chart">
        {ticks.map((t) => (
          <g key={t}>
            <line x1={L} x2={W - R} y1={y(t)} y2={y(t)} stroke={GRID} strokeWidth="1" />
            <text x={L - 6} y={y(t) + 4} textAnchor="end" fontSize="11" fill={AXIS}>{short(t)}</text>
          </g>
        ))}
        {series.map((s) => {
          const pts = rows.map((r, i) => `${x(i)},${y(Number(r[s.key]) || 0)}`).join(' ');
          return (
            <g key={s.key}>
              {/* a light area under the first series makes the overall shape easy to read */}
              {s === series[0] && rows.length > 1 && (
                <polygon points={`${x(0)},${y(0)} ${pts} ${x(rows.length - 1)},${y(0)}`} fill={s.color} opacity="0.10" />
              )}
              <polyline points={pts} fill="none" stroke={s.color} strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
              {rows.map((r, i) => <circle key={i} cx={x(i)} cy={y(Number(r[s.key]) || 0)} r={hover === r ? 5 : 3.5} fill={s.color} />)}
            </g>
          );
        })}
        {rows.map((r, i) => (
          <g key={r.label}>
            <rect x={x(i) - plotW / Math.max(2, rows.length) / 2} y={T} width={plotW / Math.max(2, rows.length)} height={plotH} fill="transparent"
              onMouseEnter={() => setHover(r)} onMouseLeave={() => setHover(null)} onClick={() => setHover(r)} style={{ cursor: 'pointer' }} />
            <text x={x(i)} y={H - 8} textAnchor="middle" fontSize="11" fill={AXIS}>{r.label}</text>
          </g>
        ))}
      </svg>
    </div>
  );
}

/**
 * Donut with a legend. parts: [{label, value, color, display?, short?}]. center: big text in the middle.
 * display is how the legend writes the value (e.g. money); short is the centre text on hover.
 */
export function Donut({ parts, center, sub, size = 170 }) {
  const [hover, setHover] = useHover();
  const total = parts.reduce((a, p) => a + (Number(p.value) || 0), 0);
  const r = 60, c = 2 * Math.PI * r;
  let offset = 0;
  const shown = hover ? (hover.short ?? hover.display ?? hover.value) : center;
  const shownSub = hover ? hover.label : sub;

  return (
    <div className="donut-wrap">
      <svg viewBox="0 0 160 160" width={size} height={size} role="img" aria-label="Donut chart">
        <circle cx="80" cy="80" r={r} fill="none" stroke={GRID} strokeWidth="22" />
        {total > 0 && parts.map((p) => {
          const v = Number(p.value) || 0;
          if (!v) return null;
          const len = (v / total) * c;
          const el = (
            <circle key={p.label} cx="80" cy="80" r={r} fill="none" stroke={p.color} strokeWidth={hover === p ? 26 : 22}
              strokeDasharray={`${Math.max(0, len - 1.5)} ${c}`} strokeDashoffset={-offset} transform="rotate(-90 80 80)"
              onMouseEnter={() => setHover(p)} onMouseLeave={() => setHover(null)} onClick={() => setHover(p)} style={{ cursor: 'pointer' }} />
          );
          offset += len;
          return el;
        })}
        <text x="80" y="78" textAnchor="middle" fontSize="24" fontWeight="700" fill="var(--ink)">{shown}</text>
        <text x="80" y="98" textAnchor="middle" fontSize="11" fill={AXIS}>{shownSub}</text>
      </svg>
      <div className="donut-legend">
        {parts.map((p) => (
          <div key={p.label} className={hover === p ? 'on' : ''} onMouseEnter={() => setHover(p)} onMouseLeave={() => setHover(null)}>
            <i style={{ background: p.color }} />
            <span>{p.label}</span>
            <b>{p.display ?? p.value}</b>
            <em>{total ? Math.round(((Number(p.value) || 0) / total) * 100) : 0}%</em>
          </div>
        ))}
      </div>
    </div>
  );
}
