#!/usr/bin/env python3
"""Build every docs/guides/*/README.md into a branded manual.pdf."""

import re
import sys
from pathlib import Path
from xml.etree import ElementTree

from reportlab.lib import colors
from reportlab.lib.colors import HexColor
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfgen.canvas import Canvas
from reportlab.platypus import HRFlowable, PageBreak, Paragraph, Preformatted, SimpleDocTemplate, Spacer, Table, TableStyle
from reportlab.platypus.tableofcontents import TableOfContents

BASE = Path(__file__).parent
GUIDES = BASE / "guides"
LOGO = BASE.parent.parent / "app" / "public" / "logo.svg"
BLUE = HexColor("#2563EB")
RED = HexColor("#FF2D20")
DARK = HexColor("#111827")
INK = HexColor("#263746")
MUTED = HexColor("#64748B")
PALE = HexColor("#EFF6FF")
BORDER = HexColor("#CBD5E1")


def clean(text):
    text = re.sub(r"[\U00010000-\U0010ffff\u2600-\u27ff]", "", text)
    text = text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
    text = re.sub(r"\[([^]]+)]\(([^)]+)\)", r'<link href="\2" color="#2563EB">\1</link>', text)
    text = re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", text)
    return re.sub(r"`(.+?)`", r'<font face="Courier" color="#991B1B">\1</font>', text)


def logo_text():
    if not LOGO.exists():
        return "DALI"
    root = ElementTree.parse(LOGO).getroot()
    colors_used = {node.attrib.get("fill", "").upper() for node in root.iter()}
    return "DALI" if "#FF2D20" in colors_used else "Dali"


def styles():
    s = getSampleStyleSheet()
    s.add(ParagraphStyle("CoverTitle", parent=s["Title"], fontName="Helvetica-Bold", fontSize=28, leading=33, textColor=DARK, alignment=TA_LEFT, spaceAfter=7 * mm))
    s.add(ParagraphStyle("CoverBrand", parent=s["Normal"], fontName="Helvetica-Bold", fontSize=20, leading=24, textColor=RED, spaceAfter=22 * mm))
    s.add(ParagraphStyle("CoverSub", parent=s["Normal"], fontSize=11, leading=16, textColor=MUTED))
    s.add(ParagraphStyle("H1x", parent=s["Heading1"], fontName="Helvetica-Bold", fontSize=18, leading=22, textColor=DARK, spaceBefore=8 * mm, spaceAfter=4 * mm, keepWithNext=True))
    s.add(ParagraphStyle("H2x", parent=s["Heading2"], fontName="Helvetica-Bold", fontSize=13, leading=17, textColor=BLUE, spaceBefore=5 * mm, spaceAfter=3 * mm, keepWithNext=True))
    s.add(ParagraphStyle("Bodyx", parent=s["BodyText"], fontSize=9.4, leading=14, textColor=INK, spaceAfter=2.2 * mm))
    s.add(ParagraphStyle("Listx", parent=s["BodyText"], fontSize=9.4, leading=14, textColor=INK, leftIndent=7 * mm, firstLineIndent=-4 * mm, spaceAfter=1.4 * mm))
    s.add(ParagraphStyle("Smallx", parent=s["BodyText"], fontSize=7.8, leading=10.5, textColor=INK))
    s.add(ParagraphStyle("TableHead", parent=s["BodyText"], fontName="Helvetica-Bold", fontSize=7.8, leading=10, textColor=colors.white))
    s.add(ParagraphStyle("Quotex", parent=s["BodyText"], fontSize=9, leading=13, textColor=DARK, backColor=PALE, borderColor=BLUE, borderWidth=.6, borderPadding=7, leftIndent=4 * mm, rightIndent=4 * mm, spaceAfter=3 * mm))
    s.add(ParagraphStyle("Codex", parent=s["Code"], fontName="Courier", fontSize=7.5, leading=10, textColor=DARK, backColor=HexColor("#F8FAFC"), borderColor=BORDER, borderWidth=.5, borderPadding=7, spaceAfter=3 * mm))
    return s


def split_row(line):
    return [cell.strip() for cell in line.strip().strip("|").split("|")]


def table(lines, s):
    rows = [split_row(line) for line in lines]
    if len(rows) < 2 or not all(re.fullmatch(r":?-{3,}:?", c.replace(" ", "")) for c in rows[1]):
        return [Paragraph(clean(line), s["Bodyx"]) for line in lines]
    rows = [rows[0]] + rows[2:]
    count = len(rows[0])
    rows = [(row + [""] * count)[:count] for row in rows]
    data = [[Paragraph(clean(cell), s["TableHead"] if r == 0 else s["Smallx"]) for cell in row] for r, row in enumerate(rows)]
    lengths = [max(5, max(len(row[c]) for row in rows)) for c in range(count)]
    total = sum(min(x, 45) for x in lengths)
    widths = [166 * mm * min(x, 45) / total for x in lengths]
    result = Table(data, colWidths=widths, repeatRows=1, hAlign="LEFT")
    result.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, 0), BLUE), ("VALIGN", (0, 0), (-1, -1), "TOP"), ("GRID", (0, 0), (-1, -1), .45, BORDER), ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, HexColor("#F8FAFC")]), ("LEFTPADDING", (0, 0), (-1, -1), 5), ("RIGHTPADDING", (0, 0), (-1, -1), 5), ("TOPPADDING", (0, 0), (-1, -1), 5), ("BOTTOMPADDING", (0, 0), (-1, -1), 5)]))
    return [result, Spacer(1, 3 * mm)]


class GuideDoc(SimpleDocTemplate):
    def afterFlowable(self, flowable):
        if isinstance(flowable, Paragraph) and flowable.style.name in {"H1x", "H2x"}:
            level = 0 if flowable.style.name == "H1x" else 1
            text = flowable.getPlainText()
            anchor = "section-" + self.seq.nextf("heading")
            self.canv.bookmarkPage(anchor)
            self.canv.addOutlineEntry(text, anchor, level=level, closed=False)
            self.notify("TOCEntry", (level, text, self.page, anchor))


class GuideCanvas(Canvas):
    def __init__(self, *args, title="", **kwargs):
        self.title = title
        super().__init__(*args, **kwargs)

    def _decorate(self):
        if self._pageNumber == 1:
            return
        self.setStrokeColor(BORDER)
        self.line(22 * mm, 14 * mm, A4[0] - 22 * mm, 14 * mm)
        self.setFont("Helvetica", 7.5)
        self.setFillColor(MUTED)
        self.drawString(22 * mm, 9 * mm, self.title)
        self.drawRightString(A4[0] - 22 * mm, 9 * mm, f"Halaman {self._pageNumber}")

    def showPage(self):
        self._decorate(); super().showPage()

    def save(self):
        super().save()


def build(md, pdf):
    lines = md.read_text(encoding="utf-8").splitlines()
    title = next((line[2:] for line in lines if line.startswith("# ")), md.parent.name)
    s = styles()
    toc = TableOfContents()
    toc.levelStyles = [ParagraphStyle("TOC1", fontName="Helvetica", fontSize=10, leading=15, leftIndent=0, firstLineIndent=0, textColor=DARK), ParagraphStyle("TOC2", fontName="Helvetica", fontSize=9, leading=13, leftIndent=8 * mm, firstLineIndent=0, textColor=MUTED)]
    story = [Spacer(1, 22 * mm), Paragraph(logo_text(), s["CoverBrand"]), HRFlowable(width=28 * mm, thickness=4, color=RED, hAlign="LEFT"), Spacer(1, 8 * mm), Paragraph(clean(title), s["CoverTitle"]), Paragraph("Panduan operasional dan teknis plugin Moodle", s["CoverSub"]), Spacer(1, 8 * mm), Paragraph("DALI  |  Administrator, pengajar, pengguna, dan developer", s["CoverSub"]), PageBreak(), Paragraph("Daftar Isi", s["H1x"]), toc, PageBreak()]
    in_code, code, i = False, [], 0
    while i < len(lines):
        line = lines[i]
        if line.startswith("```"):
            if in_code and code: story.append(Preformatted("\n".join(code), s["Codex"])); code = []
            in_code = not in_code; i += 1; continue
        if in_code: code.append(line); i += 1; continue
        if line.strip().startswith("|"):
            rows = []
            while i < len(lines) and lines[i].strip().startswith("|"): rows.append(lines[i]); i += 1
            story.extend(table(rows, s)); continue
        if line.startswith("# "): pass
        elif line.startswith("## "): story.append(Paragraph(clean(line[3:]), s["H1x"]))
        elif line.startswith("### "): story.append(Paragraph(clean(line[4:]), s["H2x"]))
        elif re.match(r"^\d+\. ", line): story.append(Paragraph(clean(line), s["Listx"]))
        elif line.startswith("- "): story.append(Paragraph("• " + clean(line[2:]), s["Listx"]))
        elif line.startswith("> "): story.append(Paragraph(clean(line[2:]), s["Quotex"]))
        elif line.strip(): story.append(Paragraph(clean(line), s["Bodyx"]))
        else: story.append(Spacer(1, 1.2 * mm))
        i += 1
    doc = GuideDoc(str(pdf), pagesize=A4, leftMargin=22 * mm, rightMargin=22 * mm, topMargin=18 * mm, bottomMargin=20 * mm, title=title, author="DALI")
    doc.multiBuild(story, canvasmaker=lambda *a, **k: GuideCanvas(*a, title=title, **k))
    print(f"[OK] {pdf}")


def main():
    guides = sorted(path.parent for path in GUIDES.glob("*/README.md"))
    if len(guides) != 9:
        raise RuntimeError(f"Expected 9 guides, found {len(guides)}")
    if len(sys.argv) == 2:
        guides = [GUIDES / sys.argv[1]]
        if not (guides[0] / "README.md").exists():
            raise RuntimeError(f"Unknown guide: {sys.argv[1]}")
    elif len(sys.argv) > 2:
        raise RuntimeError("Usage: generate_guides.py [slug]")
    for guide in guides:
        build(guide / "README.md", guide / "manual.pdf")


if __name__ == "__main__":
    main()
