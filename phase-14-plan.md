# Phase 14 Plan -- Resume Redact: Replace FPDI with PyMuPDF
_Created: 2026-04-01_
_Updated: 2026-04-01 (distinct proc_open vs Python-missing error paths)_
_Mode: SEQUENTIAL_

## Context

Phase 13 surfaced FPDI failures as user errors. Diagnosed root cause: FPDI free
parser throws CrossReferenceException on PDF 1.5+ compressed cross-reference streams,
which many modern PDF generators produce (Word, Google Docs, LibreOffice, and others).

Fix: replace the FPDI overlay path with a Python worker script (PyMuPDF) called from
PHP via proc_open() in array form. Keep extractLines(), redactContactInfo(), and
buildPdf() intact -- no cleanup of those in this phase.

## Dependency

Phase 13 complete. No DB changes. No new Composer packages.
Python 3 + PyMuPDF must be available on the server.

---

## To-Dos

- [ ] [Phase 14] Refine web/scripts/redact_pdf.py (draft exists -- read it, do not
  rewrite from scratch). Verify all of the following:
  - Reads JSON config from argv[1]: input_path, output_path, header_mode, logo_b64
  - Opens PDF with fitz.open()
  - For each page: extract lines via rawdict, check each line text against CONTACT_PATTERNS:
    email, phone, LinkedIn URL, generic https URL, street-address line, city/state/ZIP line
  - Matched lines: add_redact_annot(full-width Rect, white fill) then apply_redactions()
  - Page 0 only: after redactions, stamp MPG branding at topmost cleared line y-position:
    - header_mode=text: page.insert_text 'MatchPointe Group' in MPG red (#c0392b)
    - header_mode=logo: decode logo_b64 -> temp file -> page.insert_image(); fallback to text
  - Save with garbage=4, deflate=True. Exit 0 on success, 1 on failure (errors to stderr)

- [ ] [Phase 14] Add overlayWithPython() private method to ResumeRedactionService:
  Step 1 -- Detect Python:
    Try proc_open(['python3', '--version'], ...) then ['python', '--version'] in array form.
    If both fail (exit non-zero or binary not found) -> throw RuntimeException(
      'Python is not available on this server. Contact your administrator.')

  Step 2 -- Check proc_open availability:
    Before any proc_open call, verify the function exists and is callable.
    Call proc_open() and check return value explicitly:
      if ($process === false) -> throw RuntimeException(
        'Server configuration does not allow PDF processing subprocesses. '
        . 'Contact your administrator.')
    This is a DISTINCT branch from Python-not-found. Do not collapse them.

  Step 3 -- Write JSON config to tempnam() file:
    Keys: input_path, output_path (new tempnam .pdf), header_mode, logo_b64

  Step 4 -- Run script via proc_open([$python, $scriptPath, $configPath], ...):
    MUST use array form -- not a shell string. No escapeshellarg needed.
    Capture stderr. On proc_open returning false: RuntimeException (server config)
    On non-zero exit or output file missing/empty: RuntimeException(
      'PDF processing failed. The file may be encrypted or unsupported.')

  Step 5 -- Read output file, return contents.
  Step 6 -- finally: unlink config temp file and output temp file.

- [ ] [Phase 14] buildRedactedPdf() stays as a single-line call to overlayWithPython().
  Remove overlayWithFpdi(), placeLogoInPdf(), deduplicateYPositions() -- dead code.
  Remove: use setasign\Fpdi\Fpdi;
  Keep unchanged: buildPdf(), extractLines(), redactContactInfo()

- [ ] [Phase 14] composer remove setasign/fpdf setasign/fpdi
  After removal, check if anything else uses get_magic_quotes_runtime polyfill.
  If nothing depends on it, remove bootstrap/php8_polyfills.php and the "files"
  autoload entry from composer.json. Run composer dump-autoload after.
  Keep smalot/pdfparser.

- [ ] [Phase 14] Update user-facing RuntimeException messages to NOT claim
  "re-save as standard PDF" fixes everything. Messages should describe the failure
  without prescribing a fix that may not apply (encrypted PDFs, unsupported structure).

- [ ] [Phase 14] Add unit tests to ResumeRedactionServiceTest (or a new test class):
  - Python binary not found -> RuntimeException with 'not available' in message
  - proc_open disabled/returns false -> RuntimeException with 'Server configuration' in message
  - Worker exits non-zero -> RuntimeException with 'processing failed' in message
  - Worker exits 0 but output file missing -> RuntimeException

- [ ] [Phase 14] Update feature test test_process_shows_error_when_service_throws
  -- verify it still passes with the updated service (it mocks buildRedactedPdf so
  should be unaffected, just confirm).

- [ ] [Phase 14] Run php artisan test -- must pass all 177 tests + new unit tests.

---

## Acceptance Criteria

- [ ] Jamieson Soleno PDF (Word-generated, previously failed FPDI) -> downloads cleanly
  with contact line blanked and MPG branding added, all other content preserved
- [ ] Python not found -> user error: 'Python is not available on this server.'
- [ ] proc_open disabled -> user error: 'Server configuration does not allow...'
- [ ] Encrypted/malformed PDF -> user error: 'PDF processing failed...'
- [ ] 177 + new unit tests pass
- [ ] setasign/fpdf and setasign/fpdi removed from vendor/
- [ ] php8_polyfills.php removed if no longer needed

## Files

- web/scripts/redact_pdf.py (refine existing draft)
- web/app/Services/ResumeRedactionService.php
- web/tests/Unit/ResumeRedactionServiceTest.php (or new file)
- web/composer.json + composer.lock
- web/bootstrap/php8_polyfills.php (remove if unneeded)
