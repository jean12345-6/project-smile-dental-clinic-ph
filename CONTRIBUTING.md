# Contributing to SmileSync

Rules so the system stays clean. Please follow them.

## 1. Never push to `main`
`main` is protected. Everything goes through a Pull Request (PR) reviewed by the lead (Jean).

## 2. One feature = one branch
```
git checkout main
git pull origin main
git checkout -b feature/<your-name>-<feature>
```
Example: `feature/ellen-faq-seed`

## 3. Only edit your own files
Each member works on NEW files for their feature. Do NOT edit these shared files:
- `backend-laravel/routes/*.php`
- `frontend-react/src/App.jsx`
- `frontend-react/src/styles.css`
- `frontend-react/src/components.jsx`
- anything in `app/Support/`, migrations that already exist, `.env`

If your feature needs a route or a menu link, write the exact line in your PR description. The lead will add it.

## 4. Commit small, with clear messages
`feat: add landing page hero section`
`fix: wrong column name in feedback list`

## 5. Open a PR
```
git add <your files only>
git commit -m "feat: ..."
git push origin feature/<your-name>-<feature>
```
Then open a Pull Request on GitHub into `main`. Fill in the PR template.

## 6. Before you open a PR
- Backend: `php artisan route:list` runs with no error.
- Frontend: `npm run build` finishes with no error.
- You tested it by hand and attached a screenshot.

## 7. Conflicts
If GitHub says there is a conflict, do not guess. Message the lead.
