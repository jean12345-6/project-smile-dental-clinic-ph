# SmileSync: Team Brief and Setup Guide

(You can paste this whole file into your own Claude and say: "Help me do this step by step.")

## 1. The project
SmileSync is an AI-powered dental clinic workflow system for **Project Smile Dental Clinic** (ITC 311 Application Development, MinSU Bongabong, BSIT 3-F3).
- Repo: https://github.com/jean12345-6/project-smile-dental-clinic-ph (private, branch `main`)
- Backend: Laravel 13 / PHP 8.3, MySQL, JWT login, REST API at `/api/v1` (folder `backend-laravel`)
- Frontend: React 18 + Vite + react-router, plain CSS (folder `frontend-react`)
- Roles: superadmin, clinic_admin, receptionist, dentist, patient
- The lead and owner of `main` is Jean. Jean merges everything.

## 2. Rules (very important)
1. NEVER push to `main`. Always use your own branch.
2. Create NEW files for your feature. Do NOT edit shared files: `routes/*.php`, `src/App.jsx`, `src/styles.css`, `src/components.jsx`, `app/Support/*`, existing migrations, `.env`.
3. If your page needs a route or a menu link, write the exact line in your Pull Request. Jean adds it.
4. Commit small, with clear messages, like `feat: add reminders page`.
5. Do not commit `.env`, `vendor`, or `node_modules`.
6. If you get a merge conflict, stop and ask Jean. Do not guess.

## 3. Tools you need (Windows)
- Laragon (includes PHP 8.3, MySQL, Composer). Download: laragon.org. Use the Full version.
- Node.js 20 or newer
- Git
- VS Code

## 4. Accept the invite and clone
1. Accept the GitHub invite (check your email or github.com/notifications).
2. Open the VS Code terminal (PowerShell):
```
cd C:\laragon\www
git clone https://github.com/jean12345-6/project-smile-dental-clinic-ph.git smilesync
cd smilesync
git checkout -b feature/<yourname>-<feature>
```
Example branch name: `feature/ellen-chatlogs`

## 5. Set up the backend
1. Start Laragon (Start All). MySQL must be running.
2. Ask Jean for the file `smilesync.sql` (the database). Create the database and import it:
```
mysql -u root -e "CREATE DATABASE IF NOT EXISTS smilesync"
mysql -u root smilesync < C:\path\to\smilesync.sql
```
(If `mysql` is not found, open the Laragon terminal and run the same commands there. Or use Laragon > Database > HeidiSQL and import the file.)
3. Install and configure:
```
cd backend-laravel
composer install
copy .env.example .env
php artisan key:generate
php artisan jwt:secret
```
4. Open `backend-laravel\.env` and set:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smilesync
DB_USERNAME=root
DB_PASSWORD=
```
5. Run:
```
php artisan optimize:clear
php artisan migrate
php artisan serve
```
Keep this terminal open. The API is at http://127.0.0.1:8000. If `migrate` says "Nothing to migrate", that is fine.

## 6. Set up the frontend
Open a NEW terminal:
```
cd C:\laragon\www\smilesync\frontend-react
npm install
npm install tesseract.js@5.1.1
npm run dev
```
Open http://localhost:5173

## 7. Log in to test (password for all: `password`)
- Admin / staff: `admin.smile@smilesync.test`
- Patient: `patient14.smile@smilesync.test`
- Superadmin: `superadmin@smilesync.test`

Staff pages (like yours) are visible when you log in as the admin.

## 8. How to build your page
1. Look at `frontend-react/src/pages/Inventory.jsx`. It is the simplest example. Copy its style.
2. The generic API is `GET /api/v1/r/<resource>` (needs the login token; `src/api.js` already handles this).
3. Use the shared `Crud` component from `src/components.jsx`. Read its props first (`res`, `title`, `hint`, `columns`, `fields`, `rowActions`). Use the same CSS classes already in `styles.css` so your page looks like the rest of the system (navy and gold theme, cards, badges, buttons).
4. To see the real column names of a table, run in `backend-laravel`:
```
php artisan db:table <table_name>
```
5. Test your page by hand. To view it before Jean adds the menu link, you may temporarily add a route in your own local `App.jsx`. Do NOT commit that change.

## 9. Your tasks
Each task is one NEW file. The backend already exists.

**Lady Jane: Reminders page** -> `frontend-react/src/pages/Reminders.jsx`
- Show the list of reminders (API resource `reminders`, endpoint `/api/v1/r/reminders`).
- Columns: patient, appointment, channel (SMS or email), send time, status.
- Add a search box and a status filter.

**Ellen: Chat logs page** -> `frontend-react/src/pages/ChatLogs.jsx`
- Show all chatbot conversations (resource `chat-logs`, endpoint `/api/v1/r/chat-logs`).
- Columns: time, patient, message, bot reply, whether it was escalated to staff.
- Highlight escalated chats (use the `badge` style).
- Bonus: write 30 or more FAQ Q&A (English and Filipino) in `backend-laravel/database/seeders/FaqSeeder.php` for the table `faq_entries`.

**Brian: Treatments and prescriptions history page** -> `frontend-react/src/pages/Treatments.jsx`
- Show treatments (`/api/v1/r/treatments`) and prescriptions (`/api/v1/r/prescriptions`), with two tabs.
- Columns for treatments: date, patient, dentist, service, cost. For prescriptions: medicine, dose, notes.
- Show the cost with the `money` helper from `components.jsx`.

## 10. Send your work
```
git status
git add <your new files only>
git commit -m "feat: add <feature>"
git push origin feature/<yourname>-<feature>
```
Then go to GitHub and click **Compare & pull request** (base: `main`). Fill in the template. Add:
- A screenshot of your page.
- The route and menu line Jean must add, for example:
  `<Route path="/reminders" element={<Reminders/>} />` and a menu item "Reminders".

Before you open the PR, check that `npm run build` (in `frontend-react`) has no error.

## 11. Keep your copy fresh
Before you start working each day:
```
git checkout main
git pull origin main
git checkout feature/<yourname>-<feature>
git merge main
```

## 12. Need help?
Message Jean in the group chat with a screenshot of the error.
