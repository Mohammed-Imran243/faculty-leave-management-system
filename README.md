# Faculty Leave Management System (CAHCET)

Production-ready leave management for faculty: apply leave, substitute acceptance, HoD/Principal approval, PDF generation, and analytics.

## Project structure

```
client/                 # Frontend (static HTML + JS)
  index.html            # Login
  dashboard.html        # Main app (role-based views)
  app.js                # Single app bundle (auth, API, views)
  style.css
server/
  config.php            # DB + CORS (loads config.local.php if present)
  config.example.php    # Template for config.local.php
  config.local.php      # Your credentials (create from example, not in git)
  audit.php             # Audit logging helper
  helpers.php           # create_notification()
  SimpleJWT.php         # JWT encode/decode
  api/                  # REST-style endpoints
    auth.php            # POST /login, /register
    users.php           # CRUD users, list faculty
    leaves.php          # Apply, my-leaves, substitutions, approvals
    notifications.php   # GET list, PUT /:id/read
    analytics.php       # Dashboard stats
    upload_signature.php
    generate_pdf.php
  vendor/               # Composer (mpdf, etc.)
```

## Setup

1. **Database**
   - Create MySQL DB `faculty_system` and run your schema (users, leave_requests, leave_substitutions, notifications, audit_logs, approvals, etc.).

2. **Server config**
   - Copy `server/config.example.php` to `server/config.local.php`.
   - Set `$host`, `$db_name`, `$username`, `$password`.
   - Set `JWT_SECRET` (or env `JWT_SECRET`) for production.

3. **Web server**
   - Point document root to project root (so `client/` and `server/` are both accessible).
   - Client uses `../server/api` for API base; adjust `API_URL` in `client/app.js` if your API base URL differs.

4. **PHP**
   - PHP 7.4+ with PDO MySQL, mbstring. Composer: `cd server && composer install` (for mpdf).

## Usage

- Open `client/index.html` (or your login URL), log in, then use the dashboard.
- Roles: `faculty`, `hod`, `principal`, `admin`. Sidebar and views depend on role.

## Production checklist

- Use `config.local.php` (or env) for DB and JWT secret; never commit secrets.
- Restrict CORS in `config.php` to your front-end origin if needed.
- Ensure `display_errors` is off and errors are logged.
- Keep only needed files under document root; do not expose debug/setup scripts.
