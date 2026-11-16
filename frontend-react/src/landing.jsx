
import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  DEFAULTS,
  FALLBACK_TESTIMONIALS,
  loadLanding
} from './landingDefaults.js';

const icons = ['✦', '✓', '♡', '✧', '✦', '✓'];

export default function SmileSyncLanding() {
  const [data, setData] = useState({
    content: DEFAULTS,
    testimonials: FALLBACK_TESTIMONIALS
  });

  useEffect(() => {
    let active = true;

    loadLanding()
      .then((result) => {
        if (active && result) {
          setData({
            content: { ...DEFAULTS, ...(result.content || {}) },
            testimonials: Array.isArray(result.testimonials)
              ? result.testimonials
              : FALLBACK_TESTIMONIALS
          });
        }
      })
      .catch(console.error);

    return () => { active = false; };
  }, []);

  const c = data.content;

  const services = Array.from({ length: 6 }, (_, i) => ({
    name: c[`svc.${i + 1}.name`],
    desc: c[`svc.${i + 1}.desc`],
    icon: icons[i]
  })).filter(item => item.name);

  const team = Array.from({ length: 3 }, (_, i) => ({
    name: c[`team.${i + 1}.name`],
    role: c[`team.${i + 1}.role`],
    years: c[`team.${i + 1}.years`]
  })).filter(item => item.name);

  const whyUs = Array.from({ length: 4 }, (_, i) => ({
    title: c[`why.${i + 1}.title`],
    desc: c[`why.${i + 1}.desc`]
  })).filter(item => item.title);

  return (
    <div className="ss-landing">
      <style>{`
        .ss-landing {
          --navy: #0b1a33;
          --gold: #d4af37;
          --cream: #f7f5f0;
          --muted: #64748b;
          font-family: inherit;
          background: white;
          color: var(--navy);
          min-height: 100vh;
        }
        .ss-landing * { box-sizing: border-box; }
        .ss-landing a { text-decoration: none; }
        .ss-container {
          width: min(1120px, 92%);
          margin: auto;
        }
        .ss-header {
          background: var(--navy);
          padding: 17px 0;
          position: sticky;
          top: 0;
          z-index: 20;
        }
        .ss-nav {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 24px;
          flex-wrap: wrap;
        }
        .ss-brand {
          display: flex;
          align-items: center;
          gap: 12px;
          color: white;
        }
        .ss-brand img {
          width: 42px;
          height: 42px;
          border-radius: 50%;
          object-fit: cover;
        }
        .ss-brand strong {
          display: block;
          font-size: 17px;
        }
        .ss-brand small { color: #b9c2d6; }
        .ss-links {
          display: flex;
          gap: 25px;
          flex-wrap: wrap;
        }
        .ss-links a {
          color: #f8fafc;
          font-size: 13px;
        }
        .ss-links a:hover { color: var(--gold); }
        .ss-btn {
          display: inline-block;
          background: var(--gold);
          color: var(--navy);
          border-radius: 8px;
          padding: 12px 22px;
          font-weight: 700;
          font-size: 13px;
          text-align: center;
        }
        .ss-outline {
          border: 1px solid #cbd5e1;
          background: transparent;
          color: white;
        }
        .ss-hero {
          background: linear-gradient(130deg, #0b1a33, #192e50);
          color: white;
          padding: 94px 0 105px;
          text-align: center;
        }
        .ss-eyebrow {
          color: var(--gold);
          font-size: 12px;
          font-weight: 700;
          letter-spacing: 2px;
          text-transform: uppercase;
        }
        .ss-hero h1 {
          font-size: clamp(32px, 4.5vw, 54px);
          max-width: 790px;
          line-height: 1.2;
          margin: 18px auto;
        }
        .ss-hero p {
          color: #d0d7e5;
          max-width: 630px;
          margin: 0 auto 27px;
          line-height: 1.8;
          font-size: 15px;
        }
        .ss-actions {
          display: flex;
          justify-content: center;
          gap: 12px;
          flex-wrap: wrap;
        }
        .ss-section { padding: 75px 0; }
        .ss-soft { background: var(--cream); }
        .ss-heading {
          text-align: center;
          margin-bottom: 36px;
        }
        .ss-heading span {
          color: #a47a18;
          font-weight: 700;
          font-size: 12px;
          letter-spacing: 1.5px;
          text-transform: uppercase;
        }
        .ss-heading h2 {
          font-size: clamp(25px, 3vw, 34px);
          margin: 10px 0;
        }
        .ss-heading p {
          font-size: 14px;
          color: var(--muted);
        }
        .ss-grid {
          display: grid;
          grid-template-columns: repeat(3, minmax(0, 1fr));
          gap: 20px;
        }
        .ss-card {
          background: white;
          padding: 27px;
          border: 1px solid #e5e7eb;
          border-radius: 12px;
          min-width: 0;
        }
        .ss-card h3 {
          margin: 14px 0 9px;
          font-size: 17px;
        }
        .ss-card p {
          color: var(--muted);
          font-size: 13px;
          line-height: 1.8;
          overflow-wrap: anywhere;
        }
        .ss-symbol {
          width: 44px;
          height: 44px;
          background: #f5eac7;
          color: #987113;
          display: grid;
          place-items: center;
          border-radius: 10px;
          font-size: 23px;
        }
        .ss-why {
          background: var(--navy);
          color: white;
        }
        .ss-why .ss-heading h2 { color: white; }
        .ss-why .ss-heading p { color: #b9c2d6; }
        .ss-why .ss-card {
          background: #162846;
          border-color: #293b56;
        }
        .ss-why .ss-card h3 { color: white; }
        .ss-why .ss-card p { color: #c4cedd; }
        .ss-team-card { text-align: center; }
        .ss-avatar {
          width: 72px;
          height: 72px;
          background: #f1e8cf;
          color: #987113;
          border-radius: 50%;
          display: grid;
          place-items: center;
          margin: 0 auto 14px;
          font-size: 27px;
        }
        .ss-team-card small {
          display: block;
          color: #a47a18;
          margin-top: 7px;
        }
        .ss-stars {
          color: #c59a25;
          letter-spacing: 3px;
          margin-bottom: 12px;
        }
        .ss-info {
          background: var(--cream);
          border-radius: 12px;
          padding: 22px;
          border: 1px solid #e5e7eb;
          overflow-wrap: anywhere;
        }
        .ss-info strong {
          display: block;
          margin-bottom: 10px;
          font-size: 15px;
        }
        .ss-info p {
          color: var(--muted);
          line-height: 1.8;
          font-size: 13px;
          margin: 0;
        }
        .ss-info a { color: var(--navy); }
        .ss-footer {
          background: var(--navy);
          color: #c4cedd;
          padding: 34px 0;
          text-align: center;
          font-size: 12px;
          line-height: 1.8;
        }
        @media (max-width: 850px) {
          .ss-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
          .ss-nav { justify-content: center; }
          .ss-hero { padding: 65px 0; }
        }
        @media (max-width: 560px) {
          .ss-grid { grid-template-columns: 1fr; }
          .ss-links { justify-content: center; gap: 14px; }
          .ss-section { padding: 48px 0; }
          .ss-card { padding: 20px; }
        }
      `}</style>

      {/* HEADER */}
      <header className="ss-header">
        <div className="ss-container ss-nav">
          <a href="#home" className="ss-brand">
            <img src="/logo.png" alt="Project Smile logo" />
            <div>
              <strong>PROJECT SMILE</strong>
              <small>Dental Clinic</small>
            </div>
          </a>

          <nav className="ss-links" aria-label="Main navigation">
            <a href="#services">Services</a>
            <a href="#team">Our Team</a>
            <a href="#testimonials">Testimonials</a>
            <a href="#hours">Hours</a>
            <a href="#contact">Contact</a>
          </nav>

          <Link to="/login" className="ss-btn">
            Login
          </Link>
        </div>
      </header>

      {/* HERO */}
      <section id="home" className="ss-hero">
        <div className="ss-container">
          <div className="ss-eyebrow">Welcome to Project Smile</div>
          <h1>{c['hero.title']}</h1>
          <p>
            {c['hero.tagline']}
            <br />
            {c['hero.desc']}
          </p>
          <div className="ss-actions">
            <a href="#services" className="ss-btn">
              Explore Services
            </a>
            <Link to="/login" className="ss-btn ss-outline">
              Patient Portal
            </Link>
          </div>
        </div>
      </section>

      {/* SERVICES */}
      <section id="services" className="ss-section">
        <div className="ss-container">
          <div className="ss-heading">
            <span>What We Offer</span>
            <h2>Our Dental Services</h2>
            <p>Quality dental care for your healthy smile.</p>
          </div>

          <div className="ss-grid">
            {services.map((service, i) => (
              <article className="ss-card" key={i}>
                <div className="ss-symbol">{service.icon}</div>
                <h3>{service.name}</h3>
                <p>{service.desc}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* WHY CHOOSE US */}
      <section className="ss-section ss-why">
        <div className="ss-container">
          <div className="ss-heading">
            <span>Our Commitment</span>
            <h2>Why Choose Project Smile?</h2>
            <p>Care that puts your comfort and health first.</p>
          </div>

          <div className="ss-grid">
            {whyUs.map((item, i) => (
              <article className="ss-card" key={i}>
                <div className="ss-symbol">✓</div>
                <h3>{item.title}</h3>
                <p>{item.desc}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* TEAM */}
      <section id="team" className="ss-section">
        <div className="ss-container">
          <div className="ss-heading">
            <span>Our Professionals</span>
            <h2>Meet Our Dental Team</h2>
            <p>Dedicated professionals caring for your smile.</p>
          </div>

          <div className="ss-grid">
            {team.map((member, i) => (
              <article className="ss-card ss-team-card" key={i}>
                <div className="ss-avatar">✚</div>
                <h3>{member.name}</h3>
                <p>{member.role}</p>
                <small>{member.years}</small>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* TESTIMONIALS */}
      <section id="testimonials" className="ss-section ss-soft">
        <div className="ss-container">
          <div className="ss-heading">
            <span>Patient Experience</span>
            <h2>What Our Patients Say</h2>
            <p>Feedback shared by our patients.</p>
          </div>

          <div className="ss-grid">
            {data.testimonials.map((item, i) => (
              <article className="ss-card" key={i}>
                {Number(item.rating) > 0 && (
                  <div className="ss-stars" aria-label={`${item.rating} out of 5 stars`}>
                    {'★'.repeat(Math.min(5, Math.max(0, Math.floor(Number(item.rating)))))}
                  </div>
                )}
                <p>“{item.quote}”</p>
                <h3>{item.name}</h3>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* HOURS */}
      <section id="hours" className="ss-section">
        <div className="ss-container">
          <div className="ss-heading">
            <span>Plan Your Visit</span>
            <h2>Clinic Operating Hours</h2>
            <p>Check our schedule before visiting.</p>
          </div>

          <div className="ss-grid">
            <div className="ss-info">
              <strong>Weekdays</strong>
              <p>{c['hours.weekday']}</p>
            </div>

            <div className="ss-info">
              <strong>Saturday</strong>
              <p>{c['hours.sat']}</p>
            </div>

            <div className="ss-info">
              <strong>Sunday</strong>
              <p>{c['hours.sun']}</p>
            </div>
          </div>
        </div>
      </section>

      {/* CONTACT */}
      <section id="contact" className="ss-section ss-soft">
        <div className="ss-container">
          <div className="ss-heading">
            <span>Get In Touch</span>
            <h2>Contact Our Clinic</h2>
            <p>Have questions? We're happy to help.</p>
          </div>

          <div className="ss-grid">
            <div className="ss-info">
              <strong>Clinic Address</strong>
              <p>{c['contact.address']}</p>
            </div>

            <div className="ss-info">
              <strong>Phone & Emergency</strong>
              <p>{c['contact.phone1']}</p>
              <p>{c['contact.phone2']}</p>
              <p>Emergency: {c['contact.emergency']}</p>
            </div>

            <div className="ss-info">
              <strong>Email Address</strong>
              <p>{c['contact.email']}</p>
            </div>
          </div>
        </div>
      </section>

      {/* FOOTER */}
      <footer className="ss-footer">
        <div className="ss-container">
          <strong>PROJECT SMILE DENTAL CLINIC</strong>
          <p>{c['footer.text']}</p>
          <p>© 2026 Project Smile Dental Clinic. All rights reserved.</p>
        </div>
      </footer>
    </div>
  );
}
