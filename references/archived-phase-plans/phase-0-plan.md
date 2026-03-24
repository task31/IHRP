# Phase 0 Plan — Scaffold + Auth
_Created: 2026-03-19_
_Mode: SEQUENTIAL_

## Context

This phase creates the foundation everything else builds on: a running Laravel 11 app
with MySQL migrations for all 14 tables, role-based authentication, and a working login +
admin user management page. Nothing from the existing Electron app is ported yet —
this phase is purely about having a working, deployable skeleton.

Source schema reference: `C:\Users\zobel\Claude-Workspace\projects\Payroll\src\main\database.js`

## Dependency

- Nothing required before this phase
- Phase 1 (backend port) cannot start until this phase is complete and verified

---

## To-Dos

### Setup

- [ ] [Phase 0] Confirm PHP 8.2+ is available locally (`php -v`)
- [ ] [Phase 0] Confirm Composer is installed (`composer -v`)
- [ ] [Phase 0] Initialize Laravel 11: `composer create-project laravel/laravel .` (run inside IHRP/ folder)
- [ ] [Phase 0] Install Laravel Breeze: `composer require laravel/breeze --dev` then `php artisan breeze:install blade`
- [ ] [Phase 0] Install Livewire: `composer require livewire/livewire`
- [ ] [Phase 0] Install dompdf: `composer require barryvdh/laravel-dompdf`
- [ ] [Phase 0] Install Alpine.js via CDN in `resources/views/layouts/app.blade.php` (no npm needed)

### Environment

- [ ] [Phase 0] Configure `.env` for local MySQL:
  ```
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=ihrp_local
  DB_USERNAME=root
  DB_PASSWORD=
  APP_URL=http://localhost:8000
  ```
- [ ] [Phase 0] Create `.env.example` with same keys but empty values (commit this, not `.env`)

### Database Migrations

Read `C:\Users\zobel\Claude-Workspace\projects\Payroll\src\main\database.js` fully before writing migrations.
All money REAL fields → `DECIMAL(12,4)`. All INTEGER booleans → `boolean()` (Laravel maps to TINYINT).

- [ ] [Phase 0] `php artisan make:migration create_settings_table`
  - `key` string unique, `value` text nullable
- [ ] [Phase 0] `php artisan make:migration create_clients_table`
  - id, name, billing_contact_name, billing_address, email, smtp_email
  - payment_terms string default 'Net 30'
  - total_budget DECIMAL(12,4) nullable
  - budget_alert_warning_sent boolean default false
  - budget_alert_critical_sent boolean default false
  - po_number string nullable
  - active boolean default true
  - timestamps
- [ ] [Phase 0] `php artisan make:migration create_consultants_table`
  - id, full_name, pay_rate DECIMAL(12,4), bill_rate DECIMAL(12,4)
  - state string, industry_type string
  - client_id unsignedBigInteger FK → clients
  - project_start_date date nullable, project_end_date date nullable
  - w9_on_file boolean default false, w9_file_path string nullable
  - active boolean default true, timestamps
- [ ] [Phase 0] `php artisan make:migration create_consultant_onboarding_items_table`
  - id, consultant_id FK, item_key string, completed boolean default false, timestamps
  - unique(consultant_id, item_key)
- [ ] [Phase 0] `php artisan make:migration create_timesheets_table`
  - All fields from database.js including immutable rate snapshots and OT breakdown
  - pay_rate_snapshot, bill_rate_snapshot DECIMAL(12,4)
  - week1/week2 regular/ot/dt hours and pay as DECIMAL(12,4)
  - total_consultant_cost, total_client_billable, gross_revenue, gross_margin_dollars DECIMAL(12,4)
  - gross_margin_percent DECIMAL(8,4)
  - invoice_id unsignedBigInteger nullable, invoice_status string nullable
  - source_file_path string nullable
  - unique(consultant_id, pay_period_start, pay_period_end)
- [ ] [Phase 0] `php artisan make:migration create_timesheet_daily_hours_table`
  - id, timesheet_id FK, day_of_week string, week_number tinyint, hours DECIMAL(6,2)
- [ ] [Phase 0] `php artisan make:migration create_invoice_sequence_table`
  - id, prefix string, next_number integer, fiscal_year_start string nullable
- [ ] [Phase 0] `php artisan make:migration create_invoices_table`
  - All billing snapshot fields, po_number, notes
  - subtotal, total_amount_due DECIMAL(12,4)
  - status enum(['pending','sent','paid']) default 'pending'
  - sent_date date nullable, paid_date date nullable
  - pdf_path string nullable, timestamps
- [ ] [Phase 0] `php artisan make:migration create_invoice_line_items_table`
  - id, invoice_id FK, week_number tinyint nullable
  - description string, hours DECIMAL(6,2), rate DECIMAL(12,4)
  - multiplier DECIMAL(4,2) default 1.00, amount DECIMAL(12,4), sort_order integer
- [ ] [Phase 0] `php artisan make:migration create_audit_log_table`
  - id, timestamp datetime, table_name string, record_id unsignedBigInteger
  - action_type string, field_changed string nullable
  - old_value text nullable, new_value text nullable, description text nullable
  - user_id unsignedBigInteger nullable FK → users (add after users migration)
- [ ] [Phase 0] `php artisan make:migration create_backups_table`
  - id, file_path string, file_size integer nullable, created_at timestamp
- [ ] [Phase 0] **New tables:**
  - `php artisan make:migration create_users_table` — id, name, email unique,
    password, role enum(['admin','account_manager','employee']),
    consultant_id nullable FK → consultants, active boolean default true, timestamps
  - `php artisan make:migration create_daily_call_reports_table`
  - `php artisan make:migration create_placements_table`
- [ ] [Phase 0] Run `php artisan migrate` — confirm all 14 tables created with zero errors

### Authentication + Roles

- [ ] [Phase 0] Laravel Breeze installs `resources/views/auth/login.blade.php` — customize with Matchpointe branding (logo placeholder, color)
- [ ] [Phase 0] Add `role` to the User model `$fillable` array
- [ ] [Phase 0] Create `app/Http/Middleware/RequireRole.php`:
  ```php
  public function handle(Request $request, Closure $next, string ...$roles): Response
  {
      if (!in_array(auth()->user()?->role, $roles)) {
          abort(403);
      }
      return $next($request);
  }
  ```
- [ ] [Phase 0] Register middleware alias `'role'` in `bootstrap/app.php`
- [ ] [Phase 0] Add role-based Gate definitions in `AppServiceProvider::boot()`:
  ```php
  Gate::define('admin', fn($user) => $user->role === 'admin');
  Gate::define('account_manager', fn($user) => in_array($user->role, ['admin','account_manager']));
  ```

### Seed

- [ ] [Phase 0] `database/seeders/DatabaseSeeder.php` — create first admin user:
  ```php
  User::create([
      'name' => 'Admin',
      'email' => 'admin@matchpointegroup.com',
      'password' => Hash::make('changeme123'),
      'role' => 'admin',
  ]);
  ```
- [ ] [Phase 0] Run `php artisan db:seed` — confirm admin user created

### Admin User Management

- [ ] [Phase 0] `php artisan make:controller AdminUserController --resource`
- [ ] [Phase 0] Routes in `routes/web.php`:
  ```php
  Route::middleware(['auth', 'role:admin'])->group(function () {
      Route::resource('admin/users', AdminUserController::class);
  });
  ```
- [ ] [Phase 0] Blade views:
  - `resources/views/admin/users/index.blade.php` — table of users with role badge, active status, edit/deactivate actions
  - `resources/views/admin/users/create.blade.php` — form: name, email, password, role dropdown, consultant link
  - `resources/views/admin/users/edit.blade.php` — same form, pre-filled
- [ ] [Phase 0] Add `authorize()` check in every `AdminUserController` method (defense-in-depth beyond middleware)

### Shell Layout

- [ ] [Phase 0] `resources/views/layouts/app.blade.php`:
  - Include Alpine.js via CDN
  - Include Tailwind CSS via CDN (temporary — replace with compiled in Phase 5)
  - Sidebar placeholder with nav links (Dashboard, Clients, Consultants, Timesheets, Invoices, Reports, Ledger, Settings)
  - Use `@can('admin')` / role checks to show/hide nav items
  - Flash message display (success/error)

---

## Acceptance Criteria

- [ ] `php artisan migrate` — zero errors, 14 tables present
- [ ] `php artisan db:seed` — admin user created
- [ ] `php artisan serve` starts without errors
- [ ] Login with seeded credentials works, session persists
- [ ] `Auth::user()->role` returns `'admin'`
- [ ] `/admin/users` — unauthenticated request redirects to `/login`
- [ ] `/admin/users` with employee role → 403
- [ ] Admin can create a new user with role 'employee' — appears in user list

## Files Planned

```
app/Http/Controllers/AdminUserController.php
app/Http/Middleware/RequireRole.php
app/Models/User.php (modify $fillable)
bootstrap/app.php (middleware alias)
app/Providers/AppServiceProvider.php (Gate definitions)
database/migrations/[14 migration files]
database/seeders/DatabaseSeeder.php
resources/views/layouts/app.blade.php
resources/views/auth/login.blade.php (Breeze, customized)
resources/views/admin/users/index.blade.php
resources/views/admin/users/create.blade.php
resources/views/admin/users/edit.blade.php
routes/web.php
.env.example
```
