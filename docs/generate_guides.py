#!/usr/bin/env python3
import re
import sys
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.colors import HexColor
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen.canvas import Canvas
from reportlab.platypus import HRFlowable, Image, KeepTogether, PageBreak, Paragraph, Preformatted, SimpleDocTemplate, Spacer, Table, TableStyle

BASE = Path(__file__).parent
GUIDES = {
    'siteframe': ('GUIDE_SITEFRAME.md', 'GUIDE_SITEFRAME.pdf', 'Panduan SiteFrame'),
    'daliwidget': ('GUIDE_DALIWIDGET.md', 'GUIDE_DALIWIDGET.pdf', 'Panduan Dali AI Widget'),
    'aiquizgen': ('GUIDE_AIQUIZGEN.md', 'GUIDE_AIQUIZGEN.pdf', 'Panduan AI Quiz Generator'),
    'aigrading': ('GUIDE_AIGRADING.md', 'GUIDE_AIGRADING.pdf', 'Panduan AI Grading'),
    'ailessonplan': ('GUIDE_AILESSONPLAN.md', 'GUIDE_AILESSONPLAN.pdf', 'Panduan AI Lesson Plan'),
    'quiz-stats-cache': ('GUIDE_QUIZ_STATS_CACHE.md', 'GUIDE_QUIZ_STATS_CACHE.pdf', 'Panduan Quiz Statistics Cache'),
    'lightstats': ('GUIDE_LIGHTSTATS.md', 'GUIDE_LIGHTSTATS.pdf', 'Panduan Light Statistics'),
}
BLUE = HexColor('#1769AA')
DARK = HexColor('#17324D')
PALE = HexColor('#EAF3FA')
INK = HexColor('#263746')
MUTED = HexColor('#607487')
BORDER = HexColor('#B9CBD9')


def image_size(width, height, max_width=166 * mm, max_height=178 * mm):
    scale = min(1, max_width / width, max_height / height)
    return width * scale, height * scale


def clean(text):
    text = re.sub(r'[\U00010000-\U0010ffff\u2600-\u27ff]', '', text)
    text = text.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
    text = re.sub(r'\*\*(.+?)\*\*', r'<b>\1</b>', text)
    text = re.sub(r'`(.+?)`', r'<font face="Courier" color="#8A2D2D">\1</font>', text)
    return text


def make_styles():
    s = getSampleStyleSheet()
    s.add(ParagraphStyle('CoverTitle', parent=s['Title'], fontName='Helvetica-Bold', fontSize=27, leading=32, textColor=DARK, alignment=TA_LEFT, spaceAfter=7 * mm))
    s.add(ParagraphStyle('CoverSub', parent=s['Normal'], fontSize=11, leading=16, textColor=MUTED))
    s.add(ParagraphStyle('H1x', parent=s['Heading1'], fontName='Helvetica-Bold', fontSize=19, leading=23, textColor=DARK, spaceBefore=9 * mm, spaceAfter=4 * mm, keepWithNext=True))
    s.add(ParagraphStyle('H2x', parent=s['Heading2'], fontName='Helvetica-Bold', fontSize=14, leading=18, textColor=BLUE, spaceBefore=6 * mm, spaceAfter=3 * mm, keepWithNext=True))
    s.add(ParagraphStyle('Bodyx', parent=s['BodyText'], fontSize=9.3, leading=14, textColor=INK, alignment=TA_JUSTIFY, spaceAfter=2.2 * mm))
    s.add(ParagraphStyle('Listx', parent=s['BodyText'], fontSize=9.3, leading=14, textColor=INK, leftIndent=7 * mm, firstLineIndent=-4 * mm, spaceAfter=1.4 * mm))
    s.add(ParagraphStyle('Smallx', parent=s['BodyText'], fontSize=7.8, leading=10.5, textColor=INK, alignment=TA_LEFT))
    s.add(ParagraphStyle('TableHead', parent=s['BodyText'], fontName='Helvetica-Bold', fontSize=7.7, leading=10, textColor=colors.white))
    s.add(ParagraphStyle('Captionx', parent=s['BodyText'], fontSize=8, leading=11, textColor=MUTED, alignment=TA_CENTER, spaceBefore=1.5 * mm, spaceAfter=5 * mm))
    s.add(ParagraphStyle('Quotex', parent=s['BodyText'], fontSize=9, leading=13, textColor=DARK, backColor=PALE, borderColor=BLUE, borderWidth=.6, borderPadding=7, leftIndent=4 * mm, rightIndent=4 * mm, spaceBefore=2 * mm, spaceAfter=3 * mm))
    s.add(ParagraphStyle('Codex', parent=s['Code'], fontName='Courier', fontSize=7.5, leading=10, textColor=DARK, backColor=HexColor('#F4F7F9'), borderColor=BORDER, borderWidth=.5, borderPadding=7, spaceBefore=2 * mm, spaceAfter=3 * mm))
    return s


def split_row(line):
    return [cell.strip() for cell in line.strip().strip('|').split('|')]


def is_separator(line):
    cells = split_row(line)
    return bool(cells) and all(re.fullmatch(r':?-{3,}:?', cell.replace(' ', '')) for cell in cells)


def render_table(lines, styles):
    rows = [split_row(line) for line in lines]
    if len(rows) < 2 or not is_separator(lines[1]):
        return [Paragraph(clean(line), styles['Bodyx']) for line in lines]
    headers, body = rows[0], rows[2:]
    count = len(headers)
    normalized = [headers] + [(row + [''] * count)[:count] for row in body]
    data = [[Paragraph(clean(cell), styles['TableHead'] if r == 0 else styles['Smallx']) for cell in row] for r, row in enumerate(normalized)]
    lengths = [max(5, max(len(row[c]) for row in normalized)) for c in range(count)]
    total = sum(min(length, 45) for length in lengths)
    widths = [166 * mm * min(length, 45) / total for length in lengths]
    table = Table(data, colWidths=widths, repeatRows=1, hAlign='LEFT')
    table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), BLUE), ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'), ('GRID', (0, 0), (-1, -1), .45, BORDER),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, HexColor('#F4F8FB')]),
        ('LEFTPADDING', (0, 0), (-1, -1), 5), ('RIGHTPADDING', (0, 0), (-1, -1), 5),
        ('TOPPADDING', (0, 0), (-1, -1), 5), ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ]))
    return [table, Spacer(1, 3 * mm)]


class GuideCanvas(Canvas):
    def __init__(self, *args, guide_title='', **kwargs):
        self.guide_title = guide_title
        super().__init__(*args, **kwargs)

    def _footer(self):
        if self._pageNumber <= 1:
            return
        self.setStrokeColor(BORDER); self.line(22 * mm, 14 * mm, A4[0] - 22 * mm, 14 * mm)
        self.setFont('Helvetica', 7.5); self.setFillColor(MUTED)
        self.drawString(22 * mm, 9 * mm, self.guide_title)
        self.drawRightString(A4[0] - 22 * mm, 9 * mm, f'Halaman {self._pageNumber}')

    def showPage(self):
        self._footer(); super().showPage()

    def save(self):
        self._footer(); super().save()


def build_pdf(md_path, pdf_path, title):
    lines = md_path.read_text(encoding='utf-8').splitlines()
    styles = make_styles()
    story = [Spacer(1, 35 * mm), HRFlowable(width=24 * mm, thickness=4, color=BLUE, hAlign='LEFT'), Spacer(1, 7 * mm), Paragraph(clean(title), styles['CoverTitle']), Paragraph('Panduan operasional lengkap untuk Moodle', styles['CoverSub']), Spacer(1, 8 * mm), Paragraph('Dali AI  |  Administrator, Guru, dan Pengguna', styles['CoverSub']), PageBreak(), Paragraph('Daftar Isi', styles['H1x'])]
    headings = [(len(m.group(1)), m.group(2)) for line in lines if (m := re.match(r'^(#{2,3})\s+(.+)', line))]
    for level, text in headings:
        story.append(Paragraph(clean(('    ' if level == 3 else '') + text), styles['Listx']))
    story.append(PageBreak())
    in_code, code, i = False, [], 0
    while i < len(lines):
        line = lines[i]
        if line.startswith('```'):
            if in_code and code:
                story.append(Preformatted('\n'.join(code), styles['Codex'])); code = []
            in_code = not in_code; i += 1; continue
        if in_code:
            code.append(line); i += 1; continue
        if line.strip().startswith('|'):
            table_lines = []
            while i < len(lines) and lines[i].strip().startswith('|'):
                table_lines.append(lines[i]); i += 1
            story.extend(render_table(table_lines, styles)); continue
        image_match = re.fullmatch(r'!\[(.*?)\]\((.+?)\)', line.strip())
        if image_match:
            image_path = (md_path.parent / image_match.group(2)).resolve()
            try:
                reader = ImageReader(str(image_path)); width, height = image_size(*reader.getSize())
            except Exception as exc:
                raise RuntimeError(f'{image_path}: {exc}') from exc
            flows = [Spacer(1, 2 * mm), Image(str(image_path), width=width, height=height, hAlign='CENTER')]
            if i + 2 < len(lines) and not lines[i + 1].strip() and lines[i + 2].startswith('**Gambar'):
                flows.append(Paragraph(clean(lines[i + 2]), styles['Captionx'])); i += 2
            story.append(KeepTogether(flows))
        elif line.startswith('# '):
            pass
        elif line.startswith('## '):
            story.append(Paragraph(clean(line[3:]), styles['H1x']))
        elif line.startswith('### '):
            story.append(Paragraph(clean(line[4:]), styles['H2x']))
        elif re.match(r'^\d+\. ', line):
            story.append(Paragraph(clean(line), styles['Listx']))
        elif line.startswith('- '):
            story.append(Paragraph('• ' + clean(line[2:]), styles['Listx']))
        elif line.startswith('> '):
            story.append(Paragraph(clean(line[2:]), styles['Quotex']))
        elif line.strip() == '---':
            story.append(HRFlowable(width='100%', thickness=.5, color=BORDER, spaceBefore=2 * mm, spaceAfter=2 * mm))
        elif line.strip():
            story.append(Paragraph(clean(line), styles['Bodyx']))
        else:
            story.append(Spacer(1, 1.2 * mm))
        i += 1
    doc = SimpleDocTemplate(str(pdf_path), pagesize=A4, leftMargin=22 * mm, rightMargin=22 * mm, topMargin=18 * mm, bottomMargin=20 * mm, title=title, author='Dali AI')
    doc.build(story, canvasmaker=lambda *a, **k: GuideCanvas(*a, guide_title=title, **k))
    print(f'[OK] {pdf_path}')


def self_check():
    for size in [(2000, 1000), (1000, 2000), (100, 100)]:
        width, height = image_size(*size)
        assert width <= 166 * mm and height <= 178 * mm
        assert abs(width / height - size[0] / size[1]) < 1e-9
        assert width <= size[0] and height <= size[1]
    assert is_separator('|---|:---:|---:|')


def main():
    self_check()
    if len(sys.argv) > 2 or (len(sys.argv) == 2 and sys.argv[1] not in GUIDES):
        print('Valid slugs: ' + ', '.join(GUIDES), file=sys.stderr); return 2
    slugs = [sys.argv[1]] if len(sys.argv) == 2 else list(GUIDES)
    for slug in slugs:
        md, pdf, title = GUIDES[slug]; directory = BASE / 'guides' / slug
        build_pdf(directory / md, directory / pdf, title)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
