import React from 'react';
import { Link } from 'react-router-dom';

const services = [
  { name: 'Cleaning & Prophylaxis', desc: 'Professional cleaning and preventive care to remove tartar and protect enamel.' },
  { name: 'Fillings & Restorations', desc: "Composite fillings that match your natural tooth colour — durable and virtually invisible." },
  { name: 'Root Canal Therapy', desc: 'Pain-free endodontic treatment to save infected teeth and eliminate discomfort.' },
  { name: 'Crowns & Bridges', desc: 'High-quality porcelain crowns and bridges for a natural, beautiful smile.' },
  { name: 'Orthodontics', desc: 'Traditional braces and modern clear aligners for children, teens, and adults.' },
  { name: 'Teeth Whitening', desc: 'Professional-grade whitening for noticeably brighter teeth — safe and effective.' },
];

const whyUs = [
  { title: 'Safe & sterile environment', desc: 'Every instrument is sterilized to international standards. Your health and safety are non-negotiable.' },
  { title: 'Minimal wait times', desc: "We respect your schedule. Our streamlined booking system ensures you're seen on time, every time." },
  { title: 'Gentle, compassionate care', desc: 'Anxious about the dentist? Our team is trained to keep you calm, informed, and comfortable.' },
  { title: 'Advanced technology', desc: 'Digital X-rays, intraoral cameras, and modern equipment for accurate diagnosis.' },
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
const sectionTitle = { fontSize: 30, fontWeight: 700, textAlign: 'center', color: navy, margin: '0 0 8px' };
const sectionSub = { fontSize: 14, color: '#6b7280', textAlign: 'center', margin: '0 0 2.5rem' };

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
      <section style={{ background: navy2, padding: '80px 24px 60px', textAlign: 'center' }}>
        <h1 style={{ color: '#fff', fontSize: 40, fontWeight: 700, margin: '0 0 12px', maxWidth: 760, marginLeft: 'auto', marginRight: 'auto' }}>
          Trusted dental care in the Philippines
        </h1>
        <p style={{ color: gold, fontSize: 20, fontWeight: 600, margin: '0 0 16px' }}>Your smile is our priority</p>
        <p style={{ color: '#b9c2d6', fontSize: 15, maxWidth: 560, margin: '0 auto 32px' }}>
          Experience compassionate, world-class dental care from our team of expert dentists.
          We combine cutting-edge technology with a gentle touch — because your comfort matters.
        </p>
        <div style={{ display: 'flex', gap: 16, justifyContent: 'center', marginBottom: 48 }}>
          <a href="#services" style={{ background: gold, color: navy, fontWeight: 700, fontSize: 14, padding: '14px 28px', borderRadius: 26, textDecoration: 'none' }}>Our Services</a>
          <Link to="/login" style={{ border: '1.5px solid #fff', color: '#fff', fontWeight: 700, fontSize: 14, padding: '14px 28px', borderRadius: 26, textDecoration: 'none' }}>Patient Login</Link>
        </div>
        <div style={{ display: 'flex', gap: 60, justifyContent: 'center' }}>
          {['Expert dentists', 'Modern equipment', 'Patient-first care'].map((t) => (
            <div key={t} style={{ textAlign: 'center' }}>
              <div style={{ width: 44, height: 44, borderRadius: '50%', background: gold, margin: '0 auto 10px' }} />
              <div style={{ color: '#fff', fontSize: 13 }}>{t}</div>
            </div>
          ))}
        </div>
      </section>

      {/* CLINIC HOURS STRIP */}
      <section style={{ maxWidth: 1040, margin: '-40px auto 0', padding: '0 24px', position: 'relative', zIndex: 2 }}>
        <div style={{ ...card, display: 'flex', gap: 48, flexWrap: 'wrap' }}>
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
      <section id="services" style={{ maxWidth: 1100, margin: '0 auto', padding: '100px 24px 60px' }}>
        <h2 style={sectionTitle}>Our dental services</h2>
        <p style={sectionSub}>From routine check-ups to advanced cosmetic treatments — comprehensive dental care for the whole family.</p>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 20 }}>
          {services.map((s) => (
            <div key={s.name} style={card}>
              <div style={{ width: 36, height: 36, borderRadius: '50%', background: '#f3e9c9', marginBottom: 14 }} />
              <div style={{ fontSize: 16, fontWeight: 700, color: navy, marginBottom: 8 }}>{s.name}</div>
              <div style={{ fontSize: 13, color: '#6b7280', lineHeight: 1.5 }}>{s.desc}</div>
            </div>
          ))}
        </div>
      </section>

      {/* WHY US */}
      <section id="why-us" style={{ background: navy, padding: '70px 24px' }}>
        <h2 style={{ ...sectionTitle, color: '#fff' }}>Why choose Project Smile?</h2>
        <p style={{ ...sectionSub, color: '#b9c2d6' }}>We go beyond dentistry — we build relationships built on trust, comfort, and exceptional results.</p>
        <div style={{ maxWidth: 1100, margin: '0 auto', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 20 }}>
          {whyUs.map((w) => (
            <div key={w.title} style={{ background: navy2, borderRadius: 14, padding: '1.5rem' }}>
              <div style={{ width: 40, height: 40, borderRadius: '50%', background: gold, marginBottom: 16 }} />
              <div style={{ fontSize: 15, fontWeight: 700, color: '#fff', marginBottom: 8 }}>{w.title}</div>
              <div style={{ fontSize: 12, color: '#b9c2d6', lineHeight: 1.5 }}>{w.desc}</div>
            </div>
          ))}
        </div>
      </section>

      {/* TEAM */}
      <section id="team" style={{ maxWidth: 1100, margin: '0 auto', padding: '80px 24px 60px' }}>
        <h2 style={sectionTitle}>Meet our expert team</h2>
        <p style={sectionSub}>Experienced, caring professionals dedicated to your oral health and a great dental experience.</p>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 20 }}>
          {team.map((t) => (
            <div key={t.name} style={{ ...card, textAlign: 'center' }}>
              <div style={{ width: 80, height: 80, borderRadius: '50%', background: '#e9e2cc', margin: '0 auto 16px' }} />
              <div style={{ fontSize: 16, fontWeight: 700, color: navy }}>{t.name}</div>
              <div style={{ fontSize: 12, color: '#6b7280', margin: '4px 0' }}>{t.role}</div>
              <div style={{ fontSize: 12, color: gold, fontWeight: 600 }}>{t.years}</div>
            </div>
          ))}
        </div>
      </section>

      {/* TESTIMONIALS */}
      <section style={{ background: '#f0ead9', padding: '70px 24px' }}>
        <h2 style={sectionTitle}>What our patients say</h2>
        <p style={sectionSub}>Real feedback from real patients who trust us with their smiles.</p>
        <div style={{ maxWidth: 1100, margin: '0 auto', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 20 }}>
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
      <section id="contact" style={{ maxWidth: 1100, margin: '0 auto', padding: '80px 24px 60px' }}>
        <h2 style={sectionTitle}>Find & reach us</h2>
        <p style={sectionSub}>We're easy to find and always happy to help. Walk in or call ahead.</p>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 20 }}>
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
        <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 24, maxWidth: 1100, margin: '0 auto' }}>
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
