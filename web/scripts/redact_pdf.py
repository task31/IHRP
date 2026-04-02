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

# Order: linkedin before generic URL; street / city-state-zip align with PHP redactContactInfo()
CONTACT_PATTERNS = [
    re.compile(r"[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"),
    re.compile(r"(\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}"),
    re.compile(r"(?:https?://)?(?:www\.)?linkedin\.com/\S+", re.IGNORECASE),
    re.compile(r"https?://\S+", re.IGNORECASE),
    re.compile(r"\d+\s+\w.*(?:St|Ave|Blvd|Rd|Dr|Ln|Ct|Way|Pl)\b", re.IGNORECASE),
    re.compile(r"[A-Z][a-z]+,\s*[A-Z]{2}\s+\d{5}"),
]

MPG_RED = (0.753, 0.224, 0.169)


def line_has_contact(text: str) -> bool:
    return any(p.search(text) for p in CONTACT_PATTERNS)


def process(config: dict) -> None:
    doc = fitz.open(config["input_path"])
    try:
        header_mode = config.get("header_mode", "text")
        logo_b64 = config.get("logo_b64", "") or ""
        branding_done = False

        for page_idx, page in enumerate(doc):
            page_w = page.rect.width
            contact_rects: list = []

            blocks = page.get_text("dict", flags=fitz.TEXT_PRESERVE_WHITESPACE).get(
                "blocks", []
            )
            for block in blocks:
                if block.get("type") != 0:
                    continue
                for line in block.get("lines", []):
                    line_text = "".join(s.get("text", "") for s in line.get("spans", []))
                    if not line_text.strip():
                        continue
                    if line_has_contact(line_text):
                        _x0, y0, _x1, y1 = line["bbox"]
                        contact_rects.append(fitz.Rect(0, y0 - 2, page_w, y1 + 2))

            for rect in contact_rects:
                page.add_redact_annot(rect, fill=(1, 1, 1))
            page.apply_redactions()

            if page_idx == 0 and contact_rects and not branding_done:
                if header_mode == "logo" and logo_b64.strip():
                    _insert_logo(page, logo_b64, 5)
                else:
                    page.insert_text(
                        (5, 15),
                        "MatchPointe Group",
                        fontname="helv",
                        fontsize=9,
                        color=MPG_RED,
                    )
                branding_done = True

        doc.save(config["output_path"], garbage=4, deflate=True)
    finally:
        doc.close()


def _insert_logo(page, logo_b64: str, y: float, max_w: float = 80) -> None:
    """Decode logo_b64 (data URI or raw base64), temp file, insert_image; on failure stamp text."""
    img_bytes = None
    ext = "png"
    s = logo_b64.strip()
    try:
        if s.startswith("data:image/"):
            header, data = s.split(",", 1)
            if "png" in header.lower():
                ext = "png"
            elif "gif" in header.lower():
                ext = "gif"
            else:
                ext = "jpg"
            img_bytes = base64.b64decode(data, validate=False)
        else:
            img_bytes = base64.b64decode(s, validate=False)
            ext = "png"
    except Exception as e:
        print("Logo decode failed, using text: " + str(e), file=sys.stderr)
        page.insert_text(
            (5, y + 9),
            "MatchPointe Group",
            fontname="helv",
            fontsize=9,
            color=MPG_RED,
        )
        return

    if not img_bytes:
        page.insert_text(
            (5, y + 9),
            "MatchPointe Group",
            fontname="helv",
            fontsize=9,
            color=MPG_RED,
        )
        return

    try:
        with tempfile.NamedTemporaryFile(suffix="." + ext, delete=False) as f:
            f.write(img_bytes)
            tmp = f.name
        try:
            page.insert_image(fitz.Rect(5, y, 5 + max_w, y + 20), filename=tmp)
        finally:
            os.unlink(tmp)
    except Exception as e:
        print("Logo failed, using text: " + str(e), file=sys.stderr)
        page.insert_text(
            (5, y + 9),
            "MatchPointe Group",
            fontname="helv",
            fontsize=9,
            color=MPG_RED,
        )


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: redact_pdf.py <config_json>", file=sys.stderr)
        sys.exit(1)
    try:
        with open(sys.argv[1], encoding="utf-8") as f:
            cfg = json.load(f)
        process(cfg)
    except Exception as e:
        print("Error: " + str(e), file=sys.stderr)
        sys.exit(1)
    sys.exit(0)
