#!/usr/bin/env python3
"""
MPG Resume Redaction Script
Redacts contact info and stamps MPG branding using PyMuPDF.
Called by ResumeRedactionService via proc_open().

Usage: python redact_pdf.py <config_json_path>
Config JSON keys: input_path, output_path, header_mode, logo_b64
Exit codes: 0 = success, 1 = failure
"""

import sys
import re
import json
import base64
import tempfile
import os

try:
    import fitz
except ImportError:
    print("PyMuPDF not installed. Run: pip install pymupdf", file=sys.stderr)
    sys.exit(1)

CONTACT_PATTERNS = [
    re.compile(r'[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}'),
    re.compile(r'(\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}'),
    re.compile(r'(?:https?://)?(?:www\.)?linkedin\.com/\S+', re.IGNORECASE),
    re.compile(r'https?://\S+', re.IGNORECASE),
]

MPG_RED = (0.753, 0.224, 0.169)


def line_has_contact(text):
    return any(p.search(text) for p in CONTACT_PATTERNS)


def process(config):
    doc = fitz.open(config['input_path'])
    header_mode = config.get('header_mode', 'text')
    logo_b64 = config.get('logo_b64', '')
    branding_done = False

    for page_idx, page in enumerate(doc):
        page_w = page.rect.width
        contact_rects = []

        blocks = page.get_text('rawdict', flags=fitz.TEXT_PRESERVE_WHITESPACE).get('blocks', [])
        for block in blocks:
            if block.get('type') != 0:
                continue
            for line in block.get('lines', []):
                line_text = ''.join(s.get('text', '') for s in line.get('spans', []))
                if not line_text.strip():
                    continue
                if line_has_contact(line_text):
                    x0, y0, x1, y1 = line['bbox']
                    contact_rects.append(fitz.Rect(0, y0 - 2, page_w, y1 + 2))

        for rect in contact_rects:
            page.add_redact_annot(rect, fill=(1, 1, 1))
        page.apply_redactions()

        if page_idx == 0 and contact_rects and not branding_done:
            top_rect = min(contact_rects, key=lambda r: r.y0)
            brand_y = top_rect.y0 + 2
            if header_mode == 'logo' and logo_b64.startswith('data:image/'):
                _insert_logo(page, logo_b64, brand_y)
            else:
                page.insert_text(
                    (5, brand_y + 9), 'MatchPointe Group',
                    fontname='helv', fontsize=9, color=MPG_RED,
                )
            branding_done = True

    doc.save(config['output_path'], garbage=4, deflate=True)
    doc.close()


def _insert_logo(page, logo_b64, y, max_w=80):
    try:
        header, data = logo_b64.split(',', 1)
        ext = 'png' if 'png' in header else ('gif' if 'gif' in header else 'jpg')
        img_bytes = base64.b64decode(data)
        with tempfile.NamedTemporaryFile(suffix='.' + ext, delete=False) as f:
            f.write(img_bytes)
            tmp = f.name
        try:
            page.insert_image(fitz.Rect(5, y, 5 + max_w, y + 20), filename=tmp)
        finally:
            os.unlink(tmp)
    except Exception as e:
        print("Logo failed, using text: " + str(e), file=sys.stderr)
        page.insert_text(
            (5, y + 9), 'MatchPointe Group',
            fontname='helv', fontsize=9, color=MPG_RED,
        )


if __name__ == '__main__':
    if len(sys.argv) != 2:
        print("Usage: redact_pdf.py <config_json>", file=sys.stderr)
        sys.exit(1)
    try:
        with open(sys.argv[1], 'r', encoding='utf-8') as f:
            cfg = json.load(f)
        process(cfg)
    except Exception as e:
        print("Error: " + str(e), file=sys.stderr)
        sys.exit(1)
    sys.exit(0)
