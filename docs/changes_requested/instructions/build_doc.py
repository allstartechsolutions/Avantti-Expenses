"""
Rebuild the client's Word file with a "Done — how to use it" block under every
finished request.

Always starts from source-original.docx (the file exactly as the client sent
it), so it can be re-run after each item: add the item to items.py, take its
screenshots into screenshots/, then from the project root:

    python3 docs/changes_requested/instructions/build_doc.py
"""

import re
import shutil
import struct
import sys
import tempfile
import zipfile
from pathlib import Path
from xml.sax.saxutils import escape

HERE = Path(__file__).resolve().parent
SOURCE = HERE / "source-original.docx"
TARGET = HERE.parent / "Alteracoes Expenses Avantti November.26.docx"
SHOTS = HERE / "screenshots"

sys.path.insert(0, str(HERE))
from items import ITEMS  # noqa: E402

EMU_PER_PX = 9525            # 96 dpi
MAX_W = 9144000              # 10 in: the landscape page between its margins
MAX_H = 5943600              # 6.5 in, leaves room for the caption on the page
GREEN, GREY, DARK = "15803D", "64748B", "1E293B"


def png_size(path: Path) -> tuple[int, int]:
    with open(path, "rb") as f:
        head = f.read(24)
    return struct.unpack(">II", head[16:24])


def run(text, bold=False, italic=False, color=None, size=None):
    props = ""
    if bold:
        props += "<w:b/>"
    if italic:
        props += "<w:i/>"
    if color:
        props += f'<w:color w:val="{color}"/>'
    if size:
        props += f'<w:sz w:val="{size}"/><w:szCs w:val="{size}"/>'
    props += '<w:lang w:val="en-US"/>'
    return f'<w:r><w:rPr>{props}</w:rPr><w:t xml:space="preserve">{escape(text)}</w:t></w:r>'


def para(runs, before=0, after=80, indent=None, hanging=None, shade=None, border=None, keep_next=False):
    ppr = ""
    if keep_next:
        ppr += "<w:keepNext/>"
    if border:
        ppr += f'<w:pBdr><w:left w:val="single" w:sz="24" w:space="8" w:color="{border}"/></w:pBdr>'
    if shade:
        ppr += f'<w:shd w:val="clear" w:color="auto" w:fill="{shade}"/>'
    ppr += f'<w:spacing w:before="{before}" w:after="{after}"/>'
    if indent is not None:
        ppr += f'<w:ind w:left="{indent}"' + (f' w:hanging="{hanging}"' if hanging else "") + "/>"
    return f"<w:p><w:pPr>{ppr}</w:pPr>{''.join(runs)}</w:p>"


def image_para(rid, pic_id, name, w_px, h_px):
    cx, cy = w_px * EMU_PER_PX, h_px * EMU_PER_PX
    scale = min(1.0, MAX_W / cx, MAX_H / cy)
    cx, cy = int(cx * scale), int(cy * scale)
    return (
        '<w:p><w:pPr><w:spacing w:before="0" w:after="200"/></w:pPr><w:r><w:rPr><w:noProof/></w:rPr><w:drawing>'
        f'<wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="{cx}" cy="{cy}"/>'
        '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
        f'<wp:docPr id="{pic_id}" name="{escape(name)}"/>'
        '<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
        '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        f'<pic:nvPicPr><pic:cNvPr id="{pic_id}" name="{escape(name)}"/><pic:cNvPicPr/></pic:nvPicPr>'
        f'<pic:blipFill><a:blip r:embed="{rid}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        f'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="{cx}" cy="{cy}"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        "</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>"
    )


def paragraph_text(p_xml: str) -> str:
    return "".join(re.findall(r"<w:t(?:\s[^>]*)?>([^<]*)</w:t>", p_xml))


def main() -> None:
    work = Path(tempfile.mkdtemp())
    with zipfile.ZipFile(SOURCE) as z:
        z.extractall(work)

    doc_path = work / "word" / "document.xml"
    rels_path = work / "word" / "_rels" / "document.xml.rels"
    doc = doc_path.read_text(encoding="utf-8")
    rels = rels_path.read_text(encoding="utf-8")

    next_rid = max(int(n) for n in re.findall(r'Id="rId(\d+)"', rels)) + 1
    media_rid: dict[str, str] = {}
    pic_id = 900001

    def rid_for(file: str) -> str:
        nonlocal next_rid, rels
        if file not in media_rid:
            src = SHOTS / file
            if not src.exists():
                sys.exit(f"Missing screenshot: {src}")
            dest = f"instr-{file}"
            shutil.copy(src, work / "word" / "media" / dest)
            rid = f"rId{next_rid}"
            next_rid += 1
            rels = rels.replace(
                "</Relationships>",
                f'<Relationship Id="{rid}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/{dest}"/></Relationships>',
            )
            media_rid[file] = rid
        return media_rid[file]

    # Paragraph spans of the body, so each request's own paragraph can be found by its text.
    for item in ITEMS:
        spans = [(m.start(), m.end()) for m in re.finditer(r"<w:p[ >].*?</w:p>", doc, flags=re.S)]
        hits = [(s, e) for s, e in spans if paragraph_text(doc[s:e]).strip().startswith(item["match"])]
        if len(hits) != 1:
            sys.exit(f"Item {item['tracker']}: expected one paragraph starting {item['match']!r}, found {len(hits)}")
        _, end = hits[0]

        block = [
            para([run(f"{item.get('status', '✔ DONE')} — {item['done']}", bold=True, color=item.get('color', GREEN), size=24),
                  run(f"   (tracker item #{item['tracker']})", color=GREY, size=18)],
                 before=160, after=60, shade=item.get('shade', 'ECFDF5'), border=item.get('color', GREEN), keep_next=True),
            para([run("How to use it", bold=True, color=DARK, size=22)], before=60, after=60, keep_next=True),
        ]
        for i, step in enumerate(item["steps"], 1):
            block.append(para([run(f"{i}.  ", bold=True, color=DARK), run(step, color=DARK)], after=40, indent=360, hanging=360))
        for note in item["notes"]:
            block.append(para([run("Note: ", bold=True, italic=True, color=GREY), run(note, italic=True, color=GREY)], before=40, after=40, indent=360))
        for file, caption in item["images"]:
            w, h = png_size(SHOTS / file)
            block.append(para([run(caption, bold=True, color=GREY, size=18)], before=160, after=60, keep_next=True))
            block.append(image_para(rid_for(file), pic_id, caption, w, h))
            pic_id += 1
        block.append(para([run("")], after=240))

        doc = doc[:end] + "".join(block) + doc[end:]

    doc_path.write_text(doc, encoding="utf-8")
    rels_path.write_text(rels, encoding="utf-8")

    tmp_out = work.parent / (work.name + ".docx")
    with zipfile.ZipFile(tmp_out, "w", zipfile.ZIP_DEFLATED) as z:
        for f in sorted(work.rglob("*")):
            if f.is_file():
                z.write(f, f.relative_to(work).as_posix())
    shutil.move(tmp_out, TARGET)
    shutil.rmtree(work)
    print(f"Wrote {TARGET} with {len(ITEMS)} how-to blocks.")


if __name__ == "__main__":
    main()
