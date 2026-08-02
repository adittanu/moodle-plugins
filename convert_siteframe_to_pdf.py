#!/usr/bin/env python3
"""Convert SiteFrame markdown guide to PDF.

Uses the same engine as convert_guides_to_pdf.py.
"""

import re
import os
import sys

from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.colors import HexColor, black, white
from reportlab.lib.units import mm
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_JUSTIFY
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    PageBreak, Preformatted, HRFlowable, Flowable
)
from reportlab.pdfgen.canvas import Canvas

# ── Fonts ──────────────────────────────────────────────────────────
FONT_NAME = 'Helvetica'
FONT_BOLD = 'Helvetica-Bold'
FONT_CODE = 'Courier'

# ── Colors ─────────────────────────────────────────────────────────
BLUE       = HexColor('#1a73e8')
DARK_BLUE  = HexColor('#0d47a1')
GREEN      = HexColor('#2e7d32')
LIGHT_GREEN= HexColor('#e8f5e9')
RED        = HexColor('#c62828')
LIGHT_RED  = HexColor('#ffebee')
GREY       = HexColor('#616161')
LIGHT_GREY = HexColor('#f5f5f5')
VL_GREY    = HexColor('#fafafa')
BORDER     = HexColor('#cccccc')
TEAL       = HexColor('#00796b')
LIGHT_TEAL = HexColor('#e0f2f1')


# ── Emoji → text ───────────────────────────────────────────────────
EMOJI = {
    '\u2705':'[v]','\u274c':'[x]','\u26a0\ufe0f':'[!]','\u26a0':'[!]',
    '\U0001f4a1':'[tip]','\U0001f512':'[key]','\U0001f310':'[web]',
    '\U0001f4c1':'[file]','\U0001f4cb':'[list]','\U0001f4dd':'[edit]',
    '\U0001f4ca':'[chart]','\U0001f3af':'[target]','\U0001f41b':'[bug]',
    '\U0001f4f7':'[cam]','\U0001f4f9':'[video]','\U0001f50d':'[search]',
    '\U0001f6ab':'[block]','\U0001f468':'[user]','\U0001f465':'[users]',
    '\U0001f5bc':'[img]','\U0001f4be':'[disk]','\U0001f4e6':'[pkg]',
    '\U0001f4d6':'[book]','\U0001f4da':'[books]','\u2b50':'[*]',
    '\u2139\ufe0f':'[i]','\u2139':'[i]','\u26a1':'[fast]',
    '\U0001f680':'[rocket]','\U0001f525':'[fire]','\U0001f4af':'[100]',
    '\U0001f4ac':'[msg]','\U0001f4ad':'[thought]','\U0001f48e':'[gem]',
    '\U0001f44d':'[+]','\U0001f44e':'[-]','\U0001f44f':'[clap]',
    '\U0001f44b':'[wave]','\U0001f449':'[>]','\U0001f448':'[<]',
    '\U0001f446':'[^]','\U0001f447':'[v2]','\u270c':'[peace]',
    '\U0001f91d':'[handshake]','\U0001f3b6':'[music]','\U0001f3b5':'[note]',
    '\U0001f504':'[sync]','\U0001f3c6':'[trophy]','\U0001f308':'[rainbow]',
    '\U0001f31f':'[star]','\u2600':'[sun]','\u2601':'[cloud]',
    '\U0001f505':'[dim]','\U0001f506':'[bright]','\u23f0':'[clock]',
    '\u23f1':'[timer]','\u23f1\ufe0f':'[timer]',
}

def replace_emoji(t):
    for e,r in EMOJI.items(): t = t.replace(e, r)
    t = re.sub(r'[\U0001f000-\U0001ffff]', '', t)
    t = re.sub(r'[\u2600-\u27bf]', '', t)
    t = re.sub(r'[\u2300-\u23ff]', '', t)
    return t

def esc(t):
    return t.replace('&','&amp;').replace('<','&lt;').replace('>','&gt;')

def fmt(t):
    t = re.sub(r'\*\*\*(.+?)\*\*\*', r'<b><i>\1</i></b>', t)
    t = re.sub(r'\*\*(.+?)\*\*', r'<b>\1</b>', t)
    t = re.sub(r'\*(.+?)\*', r'<i>\1</i>', t)
    t = re.sub(r'`(.+?)`', r'<font face="Courier" size="8" color="#c62828"> \1 </font>', t)
    t = re.sub(r'\[(.+?)\]\((.+?)\)', r'<font color="#1a73e8"><u>\1</u></font>', t)
    return t

def san(t): return fmt(esc(replace_emoji(t)))


# ── Custom flowables ───────────────────────────────────────────────
class AnchorFlowable(Flowable):
    def __init__(self, key):
        Flowable.__init__(self)
        self.key = key
        self.width = self.height = 0
    def wrap(self, aw, ah): return 0, 0
    def draw(self):
        self.canv.bookmarkPage(self.key)


class BookmarkCanvas(Canvas):
    def __init__(self, *args, **kwargs):
        Canvas.__init__(self, *args, **kwargs)
        self._outlineAdded = set()

    def bookmarkPage(self, key, *args, **kwargs):
        if key not in self._outlineAdded:
            self._outlineAdded.add(key)
            display = key.rsplit("_", 1)[0].replace("_", " ")
            try:
                self.addOutlineEntry(display, key, level=0, closed=False)
            except Exception:
                pass
        Canvas.bookmarkPage(self, key, *args, **kwargs)


# ── Styles (singleton) ─────────────────────────────────────────────
_S = None
def S():
    global _S
    if _S: return _S
    _S = getSampleStyleSheet()
    add = _S.add
    add(ParagraphStyle('DocTitle',   parent=_S['Title'],    fontName=FONT_BOLD, fontSize=22, textColor=DARK_BLUE, spaceAfter=6*mm))
    add(ParagraphStyle('DocSub',     parent=_S['Normal'],   fontName=FONT_NAME, fontSize=11, textColor=GREY, alignment=TA_CENTER, spaceAfter=4*mm))
    add(ParagraphStyle('H1',         parent=_S['Heading1'], fontName=FONT_BOLD, fontSize=18, textColor=DARK_BLUE, spaceBefore=12*mm, spaceAfter=4*mm))
    add(ParagraphStyle('H2',         parent=_S['Heading2'], fontName=FONT_BOLD, fontSize=15, textColor=BLUE,      spaceBefore=8*mm,  spaceAfter=3*mm))
    add(ParagraphStyle('H3',         parent=_S['Heading3'], fontName=FONT_BOLD, fontSize=12, textColor=HexColor('#333'), spaceBefore=5*mm, spaceAfter=2*mm))
    add(ParagraphStyle('H4',         parent=_S['Heading4'], fontName=FONT_BOLD, fontSize=11, textColor=HexColor('#444'), spaceBefore=4*mm, spaceAfter=2*mm))
    add(ParagraphStyle('Body',       parent=_S['Normal'],   fontName=FONT_NAME, fontSize=9.5, leading=14, spaceAfter=2*mm, alignment=TA_JUSTIFY))
    add(ParagraphStyle('Bul',     parent=_S['Normal'],   fontName=FONT_NAME, fontSize=9.5, leading=14, leftIndent=8*mm, bulletIndent=3*mm, spaceAfter=1*mm))
    add(ParagraphStyle('CBlock',       parent=_S['Code'],     fontName=FONT_CODE, fontSize=8, leading=11, backColor=LIGHT_GREY, borderWidth=.5, borderColor=BORDER, borderPadding=4*mm, leftIndent=4*mm, rightIndent=4*mm, spaceAfter=3*mm, spaceBefore=2*mm))
    add(ParagraphStyle('TH',         parent=_S['Normal'],   fontName=FONT_BOLD, fontSize=9,   textColor=white, alignment=TA_LEFT))
    add(ParagraphStyle('TD',         parent=_S['Normal'],   fontName=FONT_NAME, fontSize=8.5, leading=12, alignment=TA_LEFT))
    add(ParagraphStyle('Tip',        parent=_S['Normal'],   fontName=FONT_NAME, fontSize=9,   leading=13, leftIndent=4*mm, rightIndent=4*mm, textColor=GREEN, backColor=LIGHT_GREEN, borderWidth=.5, borderColor=GREEN, borderPadding=3*mm, spaceAfter=3*mm, spaceBefore=2*mm))
    add(ParagraphStyle('Warn',       parent=_S['Normal'],   fontName=FONT_NAME, fontSize=9,   leading=13, leftIndent=4*mm, rightIndent=4*mm, textColor=RED,   backColor=LIGHT_RED,   borderWidth=.5, borderColor=RED,   borderPadding=3*mm, spaceAfter=3*mm, spaceBefore=2*mm))
    add(ParagraphStyle('TOC_H1',     parent=_S['Normal'],   fontName=FONT_BOLD, fontSize=11, textColor=DARK_BLUE, spaceBefore=3*mm, spaceAfter=1*mm))
    add(ParagraphStyle('TOC_H2',     parent=_S['Normal'],   fontName=FONT_NAME, fontSize=10, textColor=BLUE, leftIndent=6*mm, spaceAfter=1*mm))
    return _S


# ── Table parser ───────────────────────────────────────────────────
def parse_table(buf):
    if len(buf) < 2: return None, None
    hdr = buf[0].strip()
    if not hdr.startswith('|'): return None, None
    headers = [c.strip() for c in hdr.split('|')[1:-1]]
    rows = []
    for line in buf[2:]:
        line = line.strip()
        if line.startswith('|'):
            rows.append([c.strip() for c in line.split('|')[1:-1]])
    return headers, rows


# ── Builder ────────────────────────────────────────────────────────
def build_pdf(md_path, pdf_path, title, subtitle):
    st = S()

    with open(md_path, encoding='utf-8') as f:
        lines = f.read().split('\n')

    # Collect headings for TOC
    headings = []
    cnt = [0]
    def mk(text):
        cnt[0] += 1
        return re.sub(r'[^a-zA-Z0-9]+','_',text).strip('_')[:40] + f'_{cnt[0]}'
    for line in lines:
        s = line.strip()
        if s.startswith('# ') and not s.startswith('## '):
            headings.append(('h1', s[2:].strip(), mk(s[2:].strip())))
        elif s.startswith('## '):
            headings.append(('h2', s[3:].strip(), mk(s[3:].strip())))

    story = []

    # Title page
    story += [Spacer(1,30*mm), Paragraph(esc(title), st['DocTitle']),
              Paragraph(esc(subtitle), st['DocSub']), Spacer(1,10*mm),
              HRFlowable(width="80%", thickness=1, color=BLUE), Spacer(1,10*mm),
              Paragraph(esc("Panduan lengkap untuk plugin SiteFrame di Moodle. "
                  "Mencakup konfigurasi admin, penggunaan guru, dan 6 display modes."), st['Body']),
              PageBreak()]

    # TOC page
    story += [Paragraph('Daftar Isi', st['H1']), Spacer(1,4*mm)]
    for lv, txt, key in headings:
        clean = esc(replace_emoji(re.sub(r'\[(.+?)\]\(.+?\)', r'\1', txt)))
        style = st['TOC_H1'] if lv == 'h1' else st['TOC_H2']
        color = '#0d47a1' if lv == 'h1' else '#1a73e8'
        story.append(Paragraph(f'<a href="#{key}" color="{color}">{clean}</a>', style))
    story.append(PageBreak())

    # Content pass
    hidx = 0
    in_code = False; code_buf = []
    in_table = False; tbl_buf = []

    def flush_tbl():
        nonlocal in_table, tbl_buf
        if not tbl_buf: in_table = False; return
        hdrs, rows = parse_table(tbl_buf)
        if not hdrs or not rows: in_table = False; tbl_buf = []; return
        nc = len(hdrs); cw = min(170*mm/nc, 40*mm)
        data = [[Paragraph(esc(replace_emoji(h)), st['TH']) for h in hdrs]]
        for row in rows:
            data.append([Paragraph(san(row[j] if j<len(row) else ''), st['TD']) for j in range(nc)])
        t = Table(data, colWidths=[cw]*nc)
        t.setStyle(TableStyle([
            ('BACKGROUND',(0,0),(-1,0),TEAL),('TEXTCOLOR',(0,0),(-1,0),white),
            ('FONTNAME',(0,0),(-1,-1),FONT_NAME),('FONTSIZE',(0,0),(-1,-1),8.5),
            ('ALIGN',(0,0),(-1,-1),'LEFT'),('VALIGN',(0,0),(-1,-1),'TOP'),
            ('GRID',(0,0),(-1,-1),.5,BORDER),
            ('ROWBACKGROUNDS',(0,1),(-1,-1),[white,LIGHT_TEAL]),
            ('TOPPADDING',(0,0),(-1,-1),3),('BOTTOMPADDING',(0,0),(-1,-1),3),
            ('LEFTPADDING',(0,0),(-1,-1),4),('RIGHTPADDING',(0,0),(-1,-1),4),
        ]))
        story.append(t); story.append(Spacer(1,3*mm))
        in_table = False; tbl_buf = []

    i = 0
    while i < len(lines):
        line = lines[i]
        s = line.strip()

        # Code fence
        if s.startswith('```'):
            if in_code:
                ct = '\n'.join(code_buf)
                if ct.strip(): story.append(Preformatted(esc(replace_emoji(ct)), st['CBlock']))
                code_buf = []; in_code = False
            else: in_code = True; code_buf = []
            i += 1; continue
        if in_code: code_buf.append(line); i += 1; continue

        # Table
        if s.startswith('|') and '|' in s[1:]:
            if not in_table: in_table = True; tbl_buf = []
            tbl_buf.append(line); i += 1; continue
        elif in_table: flush_tbl(); continue

        if not s: story.append(Spacer(1,2*mm)); i += 1; continue

        # HR
        if s in ('---','***','- - -'):
            story.append(HRFlowable(width="100%",thickness=.5,color=BORDER))
            story.append(Spacer(1,2*mm)); i += 1; continue

        # Image placeholder
        m = re.match(r'!\[(.*?)\]\((.+?)\)', s)
        if m:
            alt = m.group(1) or 'Image'
            story.append(Spacer(1,2*mm))
            i += 1; continue

        # H1
        if s.startswith('# ') and not s.startswith('## '):
            txt = s[2:].strip()
            if hidx < len(headings): story.append(AnchorFlowable(headings[hidx][2])); hidx += 1
            story.append(Paragraph(san(txt), st['H1'])); i += 1; continue

        # H2
        if s.startswith('## '):
            txt = s[3:].strip()
            if hidx < len(headings): story.append(AnchorFlowable(headings[hidx][2])); hidx += 1
            story.append(Paragraph(san(txt), st['H2'])); i += 1; continue

        # H3
        if s.startswith('### '):
            story.append(Paragraph(san(s[4:]), st['H3'])); i += 1; continue

        # H4
        if s.startswith('#### '):
            story.append(Paragraph(san(s[5:]), st['H4'])); i += 1; continue

        # Bullet
        if s.startswith('- ') or s.startswith('* '):
            story.append(Paragraph(f'\u2022 {san(s[2:])}', st['Bul'])); i += 1; continue

        # Numbered
        nm = re.match(r'^(\d+)\.\s+(.+)', s)
        if nm:
            story.append(Paragraph(f'{nm.group(1)}. {san(nm.group(2))}', st['Bul']))
            i += 1; continue

        # Blockquote
        if s.startswith('> '):
            txt = s[2:]
            low = txt.lower()
            if any(w in low for w in ['warning','peringatan','[!]']):
                story.append(Paragraph(f'[!] {san(txt)}', st['Warn']))
            else:
                story.append(Paragraph(f'[tip] {san(txt)}', st['Tip']))
            i += 1; continue

        # Body
        story.append(Paragraph(san(s), st['Body']))
        i += 1

    if in_table: flush_tbl()

    doc = SimpleDocTemplate(pdf_path, pagesize=A4,
        leftMargin=20*mm, rightMargin=20*mm, topMargin=20*mm, bottomMargin=20*mm,
        title=title, author='Dali AI')
    doc.build(story, canvasmaker=BookmarkCanvas)
    print(f'[OK] {pdf_path}')


def main():
    base = os.path.dirname(os.path.abspath(__file__))
    build_pdf(
        os.path.join(base, "GUIDE_SITEFRAME.md"),
        os.path.join(base, "GUIDE_SITEFRAME.pdf"),
        "SiteFrame Plugin \u2014 Panduan Lengkap",
        "Panduan konfigurasi admin dan penggunaan guru untuk plugin SiteFrame Moodle"
    )

if __name__ == '__main__':
    main()
