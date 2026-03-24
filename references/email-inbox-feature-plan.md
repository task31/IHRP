# Email Inbox (Admin) — Feature Plan

_GoDaddy-provisioned Microsoft 365 mailboxes + Outlook. IHRP ingests via Microsoft Graph._

---

## 1. Org context (locked in for this plan)

| Fact | Implication |
|------|-------------|
| **Employee mailboxes** are created in **GoDaddy** (same as today) | The **ingest mailbox** (e.g. `hr-ingest@matchpointegroup.com`) is created **the same way** as any other user: GoDaddy Microsoft 365 / Email & Office dashboard — not a special IHRP step. |
| Staff use **Outlook** and **Microsoft services** | Mailboxes live in **Microsoft 365 (Exchange Online)**. Ingestion is implemented with **Microsoft Graph**, not “GoDaddy email API.” |
| **GoDaddy “controls” email** | Usually: **billing**, **domain/DNS**, and **mailbox provisioning UI**. It does **not** replace Graph; it’s the reseller/registrar layer. |

**Technical approach unchanged:** Laravel scheduled job + Graph client credentials → read dedicated mailbox → store metadata + attachments under `storage/app/uploads/` → admin UI.

---

## 2. Goals and non-goals

### Goals

- Ingest **new mail** (metadata + attachments) from **one mailbox** provisioned in GoDaddy like any employee mailbox.
- **Admin-only** Inbox UI: list, detail, secure download of stored files.
- **Main list (index):** each row shows a **small text preview** of the message (first ~120–200 characters of plain text, single line or `line-clamp-2`), so admins can scan without opening.
- **Detail (“View”):** show the **entire email body** — full plain text and/or sanitized HTML as received from Graph (see §6 and §9).
- **Idempotent** sync (no duplicate rows/files for the same Graph message id).
- **Audit** via existing `AppService::auditLog` patterns.

### Non-goals (v1)

- Auto-classify “this PDF is consultant X W-9” or auto-import timesheets without human review.
- Full mail client (compose, reply, all folders).
- IMAP fallback unless Graph + app registration is **blocked** after documented escalation (see §8).

---

## 3. Responsibility split (who does what)

| Task | Where / who |
|------|-------------|
| Create **`hr-ingest@…`** (or chosen address) | **GoDaddy** — same workflow as creating a new employee mailbox. Assign **Microsoft 365 license** if GoDaddy requires one for that mailbox type. |
| Decide who may **send** to that address (internal only vs clients) | **Org policy** (distribution lists, published address, transport rules in M365 if available through your admin path). |
| **App registration** in Entra ID (tenant ID, client ID, secret) | **Microsoft Entra / Azure portal** — often opened via **Microsoft 365 admin** (“Azure AD” / “Entra”). May **not** appear inside GoDaddy’s simplified “add user” screens. |
| Grant **application permissions** (e.g. `Mail.Read`) + **admin consent** | **Microsoft** global admin (or equivalent). |
| Restrict app to **one mailbox** (application access policy / shared mailbox pattern) | **Microsoft 365 / Exchange admin** — follow **current Microsoft docs** for “application access to specific mailbox” in your tenant. |
| **Cron** on Bluehost: `php artisan schedule:run` | **Deploy / ops** — same as other Laravel schedules. |
| **Secrets** in production `.env` | **Raf / deploy** — never commit; align with `.deploy.env` / server practices. |

**Documentation deliverable for the team:** one-page “GoDaddy vs Microsoft” checklist so the boss knows **mailbox = GoDaddy**, **API app = Microsoft**.

---

## 4. Architecture (v1: scheduled poll)

```
[Cron every 1–5 min]
  → php artisan inbound-mail:sync
      → OAuth2 client credentials → Graph token
      → GET …/users/{INGEST_UPN}/mailFolders/inbox/messages (filter / paging)
      → For each new message: DB row (incl. body preview + full body fields) + download attachments → storage
      → Optional: mark read OR move to folder "IHRP/Processed"
[Admin UI] → list with preview column / detail shows full body / download (authorize admin only)
```

**v2 (optional):** Graph **change notifications** (webhook) + subscription renewal — requires stable public HTTPS URL and more ops surface.

---

## 5. Configuration (environment)

Store in `.env` (production) — names illustrative:

- `AZURE_TENANT_ID`
- `AZURE_CLIENT_ID`
- `AZURE_CLIENT_SECRET`
- `INBOUND_MAILBOX_UPN` — the ingest address exactly as Graph expects (e.g. `hr-ingest@matchpointegroup.com`)

Optional later: feature flag `INBOUND_MAIL_SYNC_ENABLED=true` to disable sync without removing code.

---

## 6. Data model (Laravel)

**`email_inbox_messages`**

- `graph_message_id` (unique), `internet_message_id` (nullable, indexed)
- `mailbox_upn`, from/subject/received timestamps, `has_attachments`, `status` (`new` / `processed` / `skipped` / `error`)
- **`body_preview`** — `string` (max ~255): short excerpt for the **main list** table (strip HTML, collapse whitespace, truncate with ellipsis). Populated at sync time from the plain-text body or from HTML stripped to text.
- **`body_plain`** — `longText` nullable: **full** plain-text body from Graph (`body.content` when `body.contentType == text`).
- **`body_html`** — `longText` nullable: **full** HTML body when Graph returns `contentType == html`. Used on the detail page only after **sanitization** (§9). If only HTML exists, derive `body_preview` from stripped text.
- Optional: `body_content_type` enum `text|html|both` for rendering logic; enforce a **max stored size** per body (e.g. 512KB–1MB) with truncation + flag if over limit.

**`email_inbox_attachments`**

- FK to message, `filename`, `content_type`, `size_bytes`, `storage_path` (relative), optional `sha256`

**Files:** e.g. `storage/app/uploads/inbound/{year}/{message_id}/{safe_filename}` — align with IHRP file rules (no public path without auth).

---

## 7. Application components

| Piece | Role |
|-------|------|
| `MicrosoftGraphTokenService` | Client credentials; cache token in-memory per request; no secret logging |
| `InboundMailSyncService` | Fetch messages, dedupe, write DB + files, optional mark read / move folder |
| `inbound-mail:sync` Artisan | Called from `schedule()` with `withoutOverlapping()` |
| Admin routes + controller | Inbox list + row data on admin page; **JSON `show`** (or inline payload) for drawer: full body + attachment metadata + download URLs; attachment download route unchanged |
| Tests | `Http::fake` for token + messages + attachment bytes; admin 403 for AM; assert preview truncation and drawer payload |

---

## 7a. UI / UX — layout, list, right drawer

### Placement (admin zone, below user directory)

- **Primary layout:** On the **Admin users** page (`/admin/users`, existing `admin.users.index`), **below** the user directory table and its controls, add a full-width section **`id="email-inbox"`** (for deep links) titled **Email inbox** (or **Inbox**).
- **Sidebar (`resources/views/layouts/app.blade.php`):** Under `@can('admin')`, add a nav link **Email inbox** **immediately below** the existing **Admin** link. It should navigate to the same admin hub with focus on the inbox, e.g. `route('admin.users.index')` + `#email-inbox`, *or* if the inbox is extracted to a partial used on both routes, `route('admin.inbox.index')` that reuses layout — **preferred implementation:** one page (`admin.users.index`) with users on top + inbox below, and sidebar **Email inbox** → `admin/users#email-inbox` so the “admin tab” cluster is **Admin** (users) then **Email inbox** (scroll to section). Alternative: dedicated `/admin/inbox` that **includes** only the inbox card if the combined page becomes too long (product call).
- **Visual separation:** Inbox sits inside a **white `rounded-lg shadow-sm` card** with its own heading and optional short help text (ingest address, last sync time).

### Main inbox area (inside that card)

| Column / element | Content |
|------------------|---------|
| **From** | Sender display name + email (truncated on small screens). |
| **Title** | Subject line (`subject`); monospace or semibold optional. |
| **Preview** | `body_preview` — muted text, **`line-clamp-2`** (or ~2 lines). |
| **Attachments** | **Labeled list** in-cell: each file on its own line or as **chips** — **filename** + optional icon by type (PDF, sheet, image). Show count badge if many (e.g. “3 files”) with full list in tooltip or always list up to N then “+2 more”. Match the clarity of the earlier mockup (clear filenames like `W-9_Jane_Doe.pdf`, `timesheet_week12.xlsx`). |
| **Action** | **View** control (text link or small button, indigo). |

- **Pagination** at bottom of inbox table when needed.
- **Optional:** “Sync now” (later phase) in the card header row.

### Detail — **right slide-over panel** (not a full page)

- Clicking **View** opens a **drawer fixed to the right** of the viewport (same pattern family as invoice preview modal, but **slides in from the right** instead of centered).
- **Behavior:**
  - **`fixed inset-y-0 right-0`**, width e.g. **`max-w-xl` or `max-w-2xl`**, `w-full`, white background, **shadow-xl**, **z-50** (above main content).
  - **Backdrop:** `fixed inset-0 bg-black/50 z-40` behind the panel. **Closing the drawer (required):** (1) click the **✕** control in the drawer header; (2) click **outside** the drawer on the backdrop (any click on the dimmed area, not on the slide-over panel). Do not rely on clicking the main page content behind the backdrop unless that area is covered by the backdrop — the backdrop should fill the viewport so “click out” is unambiguous. **Optional:** `Escape` key also closes (keyboard / accessibility); implement `@keydown.escape.window` if desired in addition to the two required exits.
  - **Transition:** `translate-x-full` → `translate-x-0` with `transition-transform` (Alpine `x-show` + classes or `x-transition`).
- **Drawer content (scrollable body inside panel):**
  1. **Header strip:** subject (title) + close button.
  2. **Meta:** From, To (ingest address), received date/time.
  3. **Email body:** full **`body_plain`** and/or sanitized **`body_html`** in a scrollable region (`flex-1 min-h-0 overflow-y-auto`).
  4. **Attachments (labeled):** section title **Attachments**; each row: **filename**, type/size, **Download** link (authorized route). Same labeling style as the list column for consistency.
- **Data loading:** Prefer **lazy load** when opening drawer: `GET /admin/inbox/{id}` as **JSON** (admin-only) returning body fields + attachment list + download URLs, to keep initial `/admin/users` payload small. Alternatively embed minimal ids in row and fetch on open.

### Stack alignment

- **Blade + Alpine.js** (already on layout) — no new SPA required.
- Reuse IHRP patterns: `x-cloak`, `@keydown.escape.window`, `apiFetch` for JSON detail if used.

---

### Graph (body fetch)

Use `GET …/messages/{id}?$select=…` including body, or dedicated body fetch per [Graph message resource](https://learn.microsoft.com/en-us/graph/api/resources/message) — confirm `body` shape (`contentType`, `content`).

---

## 8. Prerequisites gate (before coding)

1. **Create ingest mailbox in GoDaddy** (confirm address + license).
2. **Confirm access to Microsoft Entra / Azure** to create app registration and consent permissions.
3. If step 2 is **not** available in your GoDaddy bundle: **GoDaddy support** or **Microsoft 365 admin handoff** to enable full admin / app registration — **stop and resolve** before building webhook or advanced flows.

---

## 9. Security

- Allowlisted extensions and max sizes; no execution of attachments.
- Admin-only UI; download routes must verify attachment belongs to stored message.
- **Email body / XSS:** never echo raw `body_html` unescaped. Run through a trusted HTML **sanitizer** (e.g. `ezyang/htmlpurifier` or `stevebauman/purify`) allowing a safe subset of tags, or **plain-text only** on detail if HTML is too risky for v1. Prefer `Content-Security-Policy` on admin pages as defense in depth.
- **Storage / privacy:** full body increases DB size; cap body length; restrict admin access; consider retention policy later.
- Audit log for sync batches and notable failures (no tokens in logs).

---

## 10. Rollout phases

| Phase | Outcome |
|-------|---------|
| **P0** | GoDaddy mailbox live; Azure app + consent; env on staging; sync command writes DB (**preview + full body** from Graph) + files |
| **P1** | Admin Inbox UI: list with **preview** column, **View** with **full body** (sanitized HTML or plain) + secure download + cron on production |
| **P2** | Manual “Sync now” button; mark processed / move to Processed folder |
| **P3** | Link helpers (W-9, timesheet triage); optional Graph webhooks |

---

## 11. TASKLIST linkage

Tracked as **T026** in `TASKLIST.md` (P3). This document is the SSOT for scope and org assumptions until the phase is closed.

### Implementation (2026-03-25)

| Area | Location |
|------|-----------|
| Migrations / models | `database/migrations/2026_03_25_180000_create_email_inbox_tables.php`, `EmailInboxMessage`, `EmailInboxAttachment` |
| Graph + sync | `MicrosoftGraphService`, `InboundMailSyncService`, `inbound-mail:sync`, `config/inbound_mail.php` |
| Schedule | `bootstrap/app.php` → `everyFiveMinutes()->withoutOverlapping(10)` |
| HTTP | `EmailInboxController` — `admin.inbox.message.json`, `admin.inbox.attachments.download` |
| Sanitizer | `App\Support\EmailHtmlSanitizer` (ezyang/htmlpurifier) |
| UI | `resources/views/admin/partials/email-inbox.blade.php`, included from `admin/users/index.blade.php`; nav `layouts/app.blade.php` |
| Tests | `tests/Feature/EmailInboxTest.php` |

**Production:** set `.env` Graph variables, grant `Mail.Read` (application) + admin consent, run migrations, ensure cron runs `php artisan schedule:run` every minute.
