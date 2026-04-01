# Phase 14 Plan -- Resume Redact: Replace FPDI with PyMuPDF
_Created: 2026-04-01_
_Mode: SEQUENTIAL_

## Context

Phase 13 fixed the silent DomPDF fallback. But the root cause of the FPDI failure
was diagnosed during testing: FPDI free parser throws CrossReferenceException on
PDF 1.5+ compressed cross-reference streams -- which is how every Word-generated
resume is saved. FPDI is fundamentally broken for this use case.

Local test result: Jamieson Soleno PDF (159KB, Word-generated) fails immediately at
setSourceFile. PyMuPDF (fitz 1.27) opens and processes it correctly.

Fix: replace FPDI with a Python script (PyMuPDF) called from PHP via proc_open().
PyMuPDF handles any PDF type, redacts text by coordinates, and inserts branding
while preserving the original format exactly.

## Dependency

Phase 13 complete. No DB changes. No new Composer packages.
Python 3 + PyMuPDF must be on the server (pip install pymupdf).

---

## To-Dos

- [ ] [Phase 14] Read and refine web/scripts/redact_pdf.py (draft already written
  during testing -- do NOT rewrite from scratch, just verify and clean up):
  - Reads JSON config from argv[1]: input_path, output_path, header_mode, logo_b64
  - Opens PDF with fitz.open()
  - For every page: iterate rawdict blocks -> lines -> check each line text against
    CONTACT_PATTERNS (email, phone, LinkedIn, https URLs)
  - Matching lines: add_redact_annot(full-width Rect, white fill) then apply_redactions()
  - Page 0 only: after redactions, stamp MPG branding at topmost cleared line y:
    - text mode: page.insert_text 'MatchPointe Group' in MPG red (#c0392b)
    - logo mode: decode logo_b64, write temp file, page.insert_image(); fallback to text
  - Save with garbage=4, deflate=True. Exit 0/1.

- [ ] [Phase 14] ResumeRedactionService -- replace overlayWithFpdi with overlayWithPython:
  1. Detect Python: try proc_open(['python3', '--version']) then ['python', '--version'].
     If neither works -> throw RuntimeException('Python is not available on this server.')
  2. Write JSON config to tempnam() file
  3. Call via proc_open([$python, $scriptPath, $configPath], descriptors, pipes)
     -- array form, NOT shell string -- no injection risk
  4. Read stdout/stderr, check exit code. Non-zero or empty output -> RuntimeException
  5. Read output file, return string. Finally: unlink both temp files.

- [ ] [Phase 14] ResumeRedactionService -- remove dead FPDI code:
  - Delete overlayWithFpdi() private method
  - Delete placeLogoInPdf() private method
  - Delete deduplicateYPositions() private method
  - Remove "use setasign\Fpdi\Fpdi;" import
  - Keep buildPdf() (still a public method, tested independently)

- [ ] [Phase 14] Remove FPDI packages from composer:
  Run: composer remove setasign/fpdf setasign/fpdi
  Keep: smalot/pdfparser (used by extractLines)

- [ ] [Phase 14] Run php artisan test -- must pass 177 tests, 0 failures.

---

## Acceptance Criteria

- [ ] Jamieson Soleno PDF -> produces PDF with contact line blanked, MPG text added,
  all other formatting identical to original
- [ ] Malformed/encrypted PDF -> user-facing error (not 500)
- [ ] Python not found -> user-facing error (not 500)
- [ ] 177 tests pass
- [ ] setasign/fpdf and setasign/fpdi gone from vendor/

## Files

- web/scripts/redact_pdf.py (refine existing draft)
- web/app/Services/ResumeRedactionService.php
- web/composer.json + composer.lock
