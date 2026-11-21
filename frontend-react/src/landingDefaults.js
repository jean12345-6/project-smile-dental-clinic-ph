// Default landing page text. The admin editor saves overrides; anything not saved falls back to these.
export const DEFAULTS = {
  'hero.title': 'Trusted dental care in the Philippines',
  'hero.tagline': 'Your smile is our priority',
  'hero.desc': 'Experience compassionate, world-class dental care from our team of expert dentists. We combine cutting-edge technology with a gentle touch — because your comfort matters.',
  'svc.1.name': 'Cleaning & Prophylaxis', 'svc.1.desc': 'Professional cleaning and preventive care to remove tartar and protect enamel.',
  'svc.2.name': 'Fillings & Restorations', 'svc.2.desc': 'Composite fillings that match your natural tooth colour — durable and virtually invisible.',
  'svc.3.name': 'Root Canal Therapy', 'svc.3.desc': 'Pain-free endodontic treatment to save infected teeth and eliminate discomfort.',
  'svc.4.name': 'Crowns & Bridges', 'svc.4.desc': 'High-quality porcelain crowns and bridges for a natural, beautiful smile.',
  'svc.5.name': 'Orthodontics', 'svc.5.desc': 'Traditional braces and modern clear aligners for children, teens, and adults.',
  'svc.6.name': 'Teeth Whitening', 'svc.6.desc': 'Professional-grade whitening for noticeably brighter teeth — safe and effective.',
  'why.1.title': 'Safe & sterile environment', 'why.1.desc': 'Every instrument is sterilized to international standards. Your health and safety are non-negotiable.',
  'why.2.title': 'Minimal wait times', 'why.2.desc': "We respect your schedule. Our streamlined booking system ensures you're seen on time, every time.",
  'why.3.title': 'Gentle, compassionate care', 'why.3.desc': 'Anxious about the dentist? Our team is trained to keep you calm, informed, and comfortable.',
  'why.4.title': 'Advanced technology', 'why.4.desc': 'Digital X-rays, intraoral cameras, and modern equipment for accurate diagnosis.',
  'team.1.name': 'Dr. Maria Santos', 'team.1.role': 'General Dentistry & Orthodontics', 'team.1.years': '10+ years of experience',
  'team.2.name': 'Dr. Juan Reyes', 'team.2.role': 'Pediatric & Prosthodontics', 'team.2.years': '15+ years of experience',
  'team.3.name': 'Our clinical team', 'team.3.role': 'Dental assistants & staff', 'team.3.years': 'Dedicated to your comfort',
  'contact.address': '123 Dental Plaza, Metro Manila, Philippines',
  'contact.phone1': '(02) 8123-4567', 'contact.phone2': '0917-123-4567', 'contact.emergency': '0917-123-4567',
  'contact.email': 'info@projectsmile.com',
  'hours.weekday': 'Monday – Friday: 8:00 AM – 7:00 PM', 'hours.sat': 'Saturday: 8:00 AM – 3:00 PM', 'hours.sun': 'Sunday: Closed',
  'footer.text': 'Your trusted partner for comprehensive dental care and beautiful smiles in the Philippines.',
};

// Used when no feedback has been published yet
export const FALLBACK_TESTIMONIALS = [
  { quote: "Dr. Maria was incredibly professional and gentle. I've always had dental anxiety but I felt completely at ease. My smile has never looked better!", name: 'Sarah M.' },
  { quote: 'The clinic is spotless, modern, and the staff are genuinely caring. From booking to follow-up, everything was seamless.', name: 'Carlos R.' },
  { quote: 'Brought my daughter for her first filling and Dr. Reyes made the whole experience fun and stress-free. Highly recommend for families!', name: 'Ana L.' },
];

const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1';
/** Public call, no login needed. Falls back to the defaults if the server is down. */
export async function loadLanding() {
  try {
    const j = await fetch(API + '/public/landing', { headers: { Accept: 'application/json' } }).then((r) => r.json());
    return { content: { ...DEFAULTS, ...(j.content || {}) }, testimonials: (j.testimonials || []).length ? j.testimonials : FALLBACK_TESTIMONIALS, openNow: j.openNow || null };
  } catch {
    return { content: { ...DEFAULTS }, testimonials: FALLBACK_TESTIMONIALS, openNow: null };
  }
}
