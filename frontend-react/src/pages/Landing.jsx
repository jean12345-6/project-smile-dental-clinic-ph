import React from 'react';
import { Link } from 'react-router-dom';

const services = [
  { name: 'Cleaning & Prophylaxis', desc: 'Professional cleaning and preventive care to remove tartar and protect enamel.', icon: 'tooth' },
  { name: 'Fillings & Restorations', desc: "Composite fillings that match your natural tooth colour — durable and virtually invisible.", icon: 'check' },
  { name: 'Root Canal Therapy', desc: 'Pain-free endodontic treatment to save infected teeth and eliminate discomfort.', icon: 'heart' },
  { name: 'Crowns & Bridges', desc: 'High-quality porcelain crowns and bridges for a natural, beautiful smile.', icon: 'shield' },
  { name: 'Orthodontics', desc: 'Traditional braces and modern clear aligners for children, teens, and adults.', icon: 'tooth' },
  { name: 'Teeth Whitening', desc: 'Professional-grade whitening for noticeably brighter teeth — safe and effective.', icon: 'bolt' },
];

const whyUs = [
  { title: 'Safe & sterile environment', desc: 'Every instrument is sterilized to international standards. Your health and safety are non-negotiable.', icon: 'shield' },
  { title: 'Minimal wait times', desc: "We respect your schedule. Our streamlined booking system ensures you're seen on time, every time.", icon: 'clock' },
  { title: 'Gentle, compassionate care', desc: 'Anxious about the dentist? Our team is trained to keep you calm, informed, and comfortable.', icon: 'heart' },
  { title: 'Advanced technology', desc: 'Digital X-rays, intraoral cameras, and modern equipment for accurate diagnosis.', icon: 'bolt' },
];

const team = [
  { name: 'Dr. Maria Santos', role: 'General Dentistry & Orthodontics', years: '10+ years of experience' },
  { name: 'Dr. Juan Reyes', role: 'Pediatric & Prosthodontics', years: '15+ years of experience' },
  { name: 'Our clinical team', role: 'Dental assistants & staff', years: 'Dedicated to your comfort' },
];

const testimonials = [
  { quote: "Dr. Maria was incredibly professional and gentle. I've always had dental anxiety but I felt completely at ease. My smile has never looked better!", name: 'Sarah M.' },
  { quote: 'The clinic is spotless, modern, and the staff are genuinely caring. From booking to follow-up, everything was seamless.', name: 'Carlos R.' },
  { quote: 'Brought my daughter for her first filling and Dr. Reyes made the whole experience fun and stress-free. Highly recommend for families!', name: 'Ana L.' },
];

const navy = '#0b1a33';
const navy2 = '#13223f';
const gold = '#d4af37';
const cream = '#f5f1e8';

const card = { background: '#fff', borderRadius: 14, border: '1px solid #e5e0d4', padding: '1.5rem' };
const sectionTitle = { fontSize: 28, fontWeight: 700, textAlign: 'center', color: navy, margin: '0 0 8px' };
const sectionSub = { fontSize: 14, color: '#6b7280', textAlign: 'center', margin: '0 0 1.75rem' };

function Icon({ name, size = 20, color = navy }) {
  const paths = {
    tooth: 'M12 2c-1.7 0-2.6 1-3.5 1s-2-1-3.7-1C2.5 2 1 4 1 6.8c0 1.8.8 3.3 1.3 5 .4 1.4.7 2.6 1.6 2.6.9 0 1-1.2 1.4-2.6.3-1 .6-1.4 1.2-1.4s.9.4 1.2 1.4c.4 1.4.5 2.6 1.4 2.6.9 0 1.2-1.2 1.6-2.6.5-1.7 1.3-3.2 1.3-5C21 4 19.5 2 12 2z',
    shield: 'M12 2l8 3v6c0 5-3.5 8.5-8 11-4.5-2.5-8-6-8-11V5l8-3z',
    clock: 'M12 2a10 10 0 100 20 10 10 0 000-20zm1 5v5.6l4 2.3-1 1.7-5-3V7h2z',
    heart: 'M12 21s-7-4.4-9.5-8.8C.8 9 2 5.5 5.3 4.6c2-.5 3.9.3 5 1.9 1.1-1.6 3-2.4 5-1.9 3.3.9 4.5 4.4 2.8 7.6C19 16.6 12 21 12 21z',
    bolt: 'M13 2L3 14h7l-1 8 11-14h-7l0-6z',
    check: 'M12 2a10 10 0 100 20 10 10 0 000-20zm-1.5 14.5L5.5 11.5l1.4-1.4 3.6 3.6 7.6-7.6 1.4 1.4-9 9z',
    monitor: 'M3 4h18v12H3V4zm7 14h4v2h-4v-2zM3 18h18v0H3v0z',
    userCheck: 'M9 11a4 4 0 100-8 4 4 0 000 8zm-7 9a7 7 0 0114 0H2zm16.5-5.5l1.5 1.5 3-3 1.5 1.5-4.5 4.5-3-3 1.5-1.5z',
  };
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill={color} aria-hidden="true">
      <path d={paths[name] || paths.tooth} />
    </svg>
  );
}

function IconCircle({ name, bg, color }) {
  return (
    <div style={{ width: 40, height: 40, borderRadius: '50%', background: bg, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <Icon name={name} color={color} size={18} />
    </div>
  );
}

export default function Landing() {
  return (
    <div style={{ background: cream, minHeight: '100vh' }}>
      {/* HEADER */}
      <header style={{ background: navy, padding: '18px 40px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', position: 'sticky', top: 0, zIndex: 10 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <img src="/logo.png" alt="Project Smile" style={{ width: 36, height: 36, borderRadius: '50%' }} />
          <div>
            <div style={{ color: '#fff', fontWeight: 700, fontSize: 15, letterSpacing: 1 }}>PROJECT SMILE</div>
            <div style={{ color: '#b9c2d6', fontSize: 10 }}>Dental Clinic</div>
          </div>
        </div>
        <nav style={{ display: 'flex', gap: 28 }}>
          <a href="#services" style={{ color: '#fff', textDecoration: 'none', fontSize: 14 }}>Services</a>
          <a href="#why-us" style={{ color: '#fff', textDecoration: 'none', fontSize: 14 }}>Why Us</a>
          <a href="#team" style={{ color: '#fff', textDecoration: 'none', fontSize: 14 }}>Our Team</a>
          <a href="#contact" style={{ color: '#fff', textDecoration: 'none', fontSize: 14 }}>Contact</a>
        </nav>
        <Link to="/login" style={{ background: gold, color: navy, fontWeight: 700, fontSize: 14, padding: '10px 22px', borderRadius: 20, textDecoration: 'none' }}>Login</Link>
      </header>

      {/* HERO */}
      <section style={{ background: navy2, padding: '64px 40px 48px', textAlign: 'center', position: 'relative', overflow: 'hidden' }}>
        <svg width="420" height="420" viewBox="0 0 24 24" fill={gold} style={{ position: 'absolute', top: -90, left: -110, opacity: 0.06, transform: 'rotate(-18deg)' }} aria-hidden="true">
          <path d="M12 2c-1.7 0-2.6 1-3.5 1s-2-1-3.7-1C2.5 2 1 4 1 6.8c0 1.8.8 3.3 1.3 5 .4 1.4.7 2.6 1.6 2.6.9 0 1-1.2 1.4-2.6.3-1 .6-1.4 1.2-1.4s.9.4 1.2 1.4c.4 1.4.5 2.6 1.4 2.6.9 0 1.2-1.2 1.6-2.6.5-1.7 1.3-3.2 1.3-5C21 4 19.5 2 12 2z" />
        </svg>
        <svg width="320" height="320" viewBox="0 0 24 24" fill={gold} style={{ position: 'absolute', bottom: -80, right: -80, opacity: 0.06, transform: 'rotate(22deg)' }} aria-hidden="true">
          <path d="M12 2c-1.7 0-2.6 1-3.5 1s-2-1-3.7-1C2.5 2 1 4 1 6.8c0 1.8.8 3.3 1.3 5 .4 1.4.7 2.6 1.6 2.6.9 0 1-1.2 1.4-2.6.3-1 .6-1.4 1.2-1.4s.9.4 1.2 1.4c.4 1.4.5 2.6 1.4 2.6.9 0 1.2-1.2 1.6-2.6.5-1.7 1.3-3.2 1.3-5C21 4 19.5 2 12 2z" />
        </svg>
        <div style={{ position: 'relative', zIndex: 1 }}>
          <h1 style={{ color: '#fff', fontSize: 44, fontWeight: 700, margin: '0 0 12px', maxWidth: 820, marginLeft: 'auto', marginRight: 'auto' }}>
            Trusted dental care in the Philippines
          </h1>
          <p style={{ color: gold, fontSize: 19, fontWeight: 600, margin: '0 0 14px' }}>Your smile is our priority</p>
          <p style={{ color: '#b9c2d6', fontSize: 15, maxWidth: 620, margin: '0 auto 28px' }}>
            Experience compassionate, world-class dental care from our team of expert dentists.
            We combine cutting-edge technology with a gentle touch — because your comfort matters.
          </p>
          <div style={{ display: 'flex', gap: 16, justifyContent: 'center', marginBottom: 36 }}>
            <a href="#services" style={{ background: gold, color: navy, fontWeight: 700, fontSize: 14, padding: '14px 30px', borderRadius: 26, textDecoration: 'none' }}>Our Services</a>
            <Link to="/login" style={{ border: '1.5px solid #fff', color: '#fff', fontWeight: 700, fontSize: 14, padding: '14px 30px', borderRadius: 26, textDecoration: 'none' }}>Patient Login</Link>
          </div>
          <div style={{ display: 'flex', gap: 64, justifyContent: 'center' }}>
            {[['Expert dentists', 'userCheck'], ['Modern equipment', 'monitor'], ['Patient-first care', 'heart']].map(([t, icon]) => (
              <div key={t} style={{ textAlign: 'center' }}>
                <div style={{ width: 44, height: 44, borderRadius: '50%', background: gold, margin: '0 auto 8px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <Icon name={icon} color={navy} size={20} />
                </div>
                <div style={{ color: '#fff', fontSize: 13 }}>{t}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CLINIC HOURS STRIP */}
      <section style={{ maxWidth: 1240, margin: '-30px auto 0', padding: '0 40px', position: 'relative', zIndex: 2 }}>
        <div style={{ ...card, display: 'flex', gap: 48, flexWrap: 'wrap', justifyContent: 'space-between' }}>
          <div>
            <div style={{ fontSize: 15, fontWeight: 700, color: navy, marginBottom: 6 }}>Clinic hours</div>
            <span style={{ background: '#dff3e4', color: '#1d7a3c', fontSize: 11, padding: '3px 10px', borderRadius: 10 }}>Open today</span>
            <div style={{ fontSize: 13, color: '#6b7280', marginTop: 8 }}>Monday – Friday: 8:00 AM – 7:00 PM</div>
            <div style={{ fontSize: 13, color: '#6b7280' }}>Saturday: 8:00 AM – 3:00 PM</div>
          </div>
          <div>
            <div style={{ fontSize: 15, fontWeight: 700, color: navy, marginBottom: 6 }}>Emergency line</div>
            <div style={{ fontSize: 13, color: '#6b7280' }}>0917-123-4567</div>
          </div>
          <div>
            <div style={{ fontSize: 15, fontWeight: 700, color: navy, marginBottom: 6 }}>Location</div>
            <div style={{ fontSize: 13, color: '#6b7280' }}>123 Dental Plaza, Metro Manila, Philippines</div>
          </div>
        </div>
      </section>

      {/* SERVICES */}
      <section id="services" style={{ maxWidth: 1240, margin: '0 auto', padding: '64px 40px 44px' }}>
        <h2 style={sectionTitle}>Our dental services</h2>
        <p style={sectionSub}>From routine check-ups to advanced cosmetic treatments — comprehensive dental care for the whole family.</p>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))', gap: 22 }}>
          {services.map((s) => (
            <div key={s.name} style={{ ...card, padding: '1.5rem' }}>
              <IconCircle name={s.icon} bg="#f3e9c9" color="#a9781c" />
              <div style={{ fontSize: 17, fontWeight: 700, color: navy, margin: '14px 0 6px' }}>{s.name}</div>
              <div style={{ fontSize: 13.5, color: '#6b7280', lineHeight: 1.6 }}>{s.desc}</div>
            </div>
          ))}
        </div>
      </section>

      {/* WHY US */}
      <section id="why-us" style={{ background: navy, padding: '56px 40px' }}>
        <h2 style={{ ...sectionTitle, color: '#fff' }}>Why choose Project Smile?</h2>
        <p style={{ ...sectionSub, color: '#b9c2d6' }}>We go beyond dentistry — we build relationships built on trust, comfort, and exceptional results.</p>
        <div style={{ maxWidth: 1240, margin: '0 auto', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 22 }}>
          {whyUs.map((w) => (
            <div key={w.title} style={{ background: navy2, borderRadius: 14, padding: '1.5rem' }}>
              <IconCircle name={w.icon} bg={gold} color={navy} />
              <div style={{ fontSize: 16, fontWeight: 700, color: '#fff', margin: '14px 0 6px' }}>{w.title}</div>
              <div style={{ fontSize: 12.5, color: '#b9c2d6', lineHeight: 1.6 }}>{w.desc}</div>
            </div>
          ))}
        </div>
      </section>

      {/* TEAM */}
      <section id="team" style={{ maxWidth: 1240, margin: '0 auto', padding: '56px 40px 40px' }}>
        <h2 style={sectionTitle}>Meet our expert team</h2>
        <p style={sectionSub}>Experienced, caring professionals dedicated to your oral health and a great dental experience.</p>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 22 }}>
          {team.map((t) => (
            <div key={t.name} style={{ ...card, textAlign: 'center', padding: '2rem 1.5rem' }}>
              <div style={{ width: 90, height: 90, borderRadius: '50%', background: '#e9e2cc', margin: '0 auto 18px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Icon name="userCheck" color="#a9781c" size={34} />
              </div>
              <div style={{ fontSize: 17, fontWeight: 700, color: navy }}>{t.name}</div>
              <div style={{ fontSize: 13, color: '#6b7280', margin: '5px 0' }}>{t.role}</div>
              <div style={{ fontSize: 12.5, color: gold, fontWeight: 600 }}>{t.years}</div>
            </div>
          ))}
        </div>
      </section>

      {/* TESTIMONIALS */}
      <section style={{ background: '#f0ead9', padding: '56px 40px' }}>
        <h2 style={sectionTitle}>What our patients say</h2>
        <p style={sectionSub}>Real feedback from real patients who trust us with their smiles.</p>
        <div style={{ maxWidth: 1240, margin: '0 auto', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 22 }}>
          {testimonials.map((t) => (
            <div key={t.name} style={card}>
              <div style={{ fontSize: 28, color: gold, lineHeight: 1 }}>&ldquo;</div>
              <div style={{ fontSize: 13, color: '#6b7280', lineHeight: 1.6, margin: '8px 0 16px' }}>{t.quote}</div>
              <div style={{ fontSize: 13, fontWeight: 700, color: navy }}>— {t.name}, Patient</div>
            </div>
          ))}
        </div>
      </section>

      {/* CONTACT */}
      <section id="contact" style={{ maxWidth: 1240, margin: '0 auto', padding: '56px 40px 40px' }}>
        <h2 style={sectionTitle}>Find & reach us</h2>
        <p style={sectionSub}>We're easy to find and always happy to help. Walk in or call ahead.</p>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 22 }}>
          <div style={card}>
            <div style={{ fontSize: 13, fontWeight: 700, color: navy, marginBottom: 8 }}>Our address</div>
            <div style={{ fontSize: 12, color: '#6b7280' }}>123 Dental Plaza, Metro Manila, Philippines</div>
          </div>
          <div style={card}>
            <div style={{ fontSize: 13, fontWeight: 700, color: navy, marginBottom: 8 }}>Call us</div>
            <div style={{ fontSize: 12, color: '#6b7280' }}>(02) 8123-4567</div>
            <div style={{ fontSize: 12, color: '#6b7280' }}>0917-123-4567</div>
          </div>
          <div style={card}>
            <div style={{ fontSize: 13, fontWeight: 700, color: navy, marginBottom: 8 }}>Email us</div>
            <div style={{ fontSize: 12, color: '#6b7280' }}>info@projectsmile.com</div>
          </div>
          <div style={card}>
            <div style={{ fontSize: 13, fontWeight: 700, color: navy, marginBottom: 8 }}>Opening hours</div>
            <div style={{ fontSize: 12, color: '#6b7280' }}>Mon–Fri: 8:00 AM – 7:00 PM</div>
            <div style={{ fontSize: 12, color: '#6b7280' }}>Saturday: 8:00 AM – 3:00 PM</div>
            <div style={{ fontSize: 12, color: '#6b7280' }}>Sunday: Closed</div>
          </div>
        </div>
      </section>

      {/* FOOTER */}
      <footer style={{ background: navy, padding: '48px 40px 28px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 24, maxWidth: 1240, margin: '0 auto' }}>
          <div>
            <div style={{ color: gold, fontWeight: 700, fontSize: 17, marginBottom: 8 }}>PROJECT SMILE</div>
            <div style={{ color: '#b9c2d6', fontSize: 12, maxWidth: 260 }}>Your trusted partner for comprehensive dental care and beautiful smiles in the Philippines.</div>
          </div>
          <div>
            <div style={{ color: '#fff', fontSize: 13, fontWeight: 700, marginBottom: 10 }}>Quick links</div>
            <div style={{ color: '#b9c2d6', fontSize: 12, lineHeight: 2 }}>
              <a href="#services" style={{ color: '#b9c2d6', display: 'block', textDecoration: 'none' }}>Our Services</a>
              <a href="#why-us" style={{ color: '#b9c2d6', display: 'block', textDecoration: 'none' }}>Why Choose Us</a>
              <a href="#team" style={{ color: '#b9c2d6', display: 'block', textDecoration: 'none' }}>Our Team</a>
              <a href="#contact" style={{ color: '#b9c2d6', display: 'block', textDecoration: 'none' }}>Contact & Hours</a>
            </div>
          </div>
          <div>
            <div style={{ color: '#fff', fontSize: 13, fontWeight: 700, marginBottom: 10 }}>Contact</div>
            <div style={{ color: '#b9c2d6', fontSize: 12, lineHeight: 2 }}>
              (02) 8123-4567<br />info@projectsmile.com<br />123 Dental Plaza, Metro Manila, Philippines
            </div>
          </div>
        </div>
        <div style={{ borderTop: '1px solid #26344f', marginTop: 32, paddingTop: 18, textAlign: 'center', color: '#8a93a8', fontSize: 11 }}>
          © 2026 Project Smile Dental Clinic. All rights reserved. | Managed by Dr. Ma. Theresa Delen
        </div>
      </footer>
    </div>
  );
}
