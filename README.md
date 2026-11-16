# SmileSync

An AI-powered dental clinic workflow system with a chatbot assistant, built for **Project Smile Dental Clinic PH** (Dr. Ma. Theresa Delen).

ITC 311 Application Development, BSIT 3-F3, Mindoro State University - Bongabong Campus.

**Group:** Jean Raven Magaling (leader, programmer), Lady Jane G. Laurente, Ellen M. Muros, Brian R. Jalos

## What it does

- **Booking and queue:** patients book online or through the chatbot; staff run the daily queue, walk-ins and follow-ups.
- **Chatbot assistant:** books, moves and cancels appointments in English or Filipino, answers FAQs from the clinic's own data, and sends unclear questions to staff. Rule-based, with a pluggable AI API for reading handwritten forms.
- **Patient records:** profiles, treatments, prescriptions, a 32-tooth dental chart, scanned form reading (OCR).
- **Billing:** invoices, payments, balances, sales and collection reports.
- **Dashboard:** sales and performance charts, dentist performance, most common procedures, appointment trend, low-stock alerts.
- **Clinic management:** services, branches, dentists, schedules, inventory, operating hours, landing page editor.
- **Waitlist and reminders:** offers freed slots to waiting patients; sends reminders and flags high no-show risk.
- **Patient portal:** own appointments, records, invoices and notifications.

## Roles

| Role | Can do |
| --- | --- |
| SuperAdmin | Everything, across all clinics. Registers clinics and staff. |
| Clinic admin | Runs one clinic: settings, staff, billing, reports. |
| Receptionist | Bookings, queue, patients, billing, inventory. |
| Dentist | Treatments, prescriptions, dental chart. |
| Patient | Own bookings and records. |

Every user stays inside their own clinic. A staff account with no clinic sees nothing.

## Tech

- **Backend:** Laravel 13, PHP 8.3, MySQL, JWT login, REST API v1, Scribe API docs
- **Frontend:** React 18, Vite, Poppins font, line icons
- **Layout:** monorepo with `backend-laravel/` and `frontend-react/`

## Run it locally

You need PHP 8.3, Composer, Node.js and MySQL (Laragon works).

```bash
# backend
cd backend-laravel
composer install
cp .env.example .env        # then set DB_* and JWT_SECRET
php artisan key:generate
php artisan migrate --seed
php artisan serve

# frontend (second terminal)
cd frontend-react
npm install
npm run dev
```

Open http://localhost:5173.

Optional in `.env`: `ANTHROPIC_API_KEY` for the AI form reader. Never commit `.env`.

## API documentation

```bash
cd backend-laravel
php artisan scribe:generate
```

The generated docs are served at `/docs` on the backend.

## Working as a team

Read `CONTRIBUTING.md` and `TEAM_SETUP.md`. Work on a `feature/<name>` branch and open a pull request into `main`. Do not push directly to `main`.
