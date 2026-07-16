import re
import sys
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET


NS = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}


def paragraph_text(paragraph):
    parts = []
    for node in paragraph.iter():
        if node.tag == f"{{{NS['w']}}}t" and node.text:
            parts.append(node.text)
        elif node.tag == f"{{{NS['w']}}}tab":
            parts.append("\t")
        elif node.tag == f"{{{NS['w']}}}br":
            parts.append("\n")
    return "".join(parts).strip()


def extract(path):
    with zipfile.ZipFile(path) as zf:
        xml = zf.read("word/document.xml")
    root = ET.fromstring(xml)
    lines = []
    for paragraph in root.findall(".//w:p", NS):
        text = paragraph_text(paragraph)
        if text:
            text = re.sub(r"\s+", " ", text).strip()
            lines.append(text)
    return lines


def main():
    inputs = sys.argv[1:]
    if not inputs:
        inputs = [str(path) for path in Path("documentation").rglob("*.docx")]
    else:
        expanded = []
        docs = [path for path in Path("documentation").rglob("*.docx")]
        for raw in inputs:
            path = Path(raw)
            matches = list(Path(".").glob(raw)) if any(ch in raw for ch in "*?[]") else []
            if path.exists():
                expanded.append(str(path))
            elif matches:
                expanded.extend(str(match) for match in matches)
            else:
                needle = raw.lower()
                expanded.extend(str(path) for path in docs if needle in str(path).lower())
        inputs = expanded

    for raw in inputs:
        path = Path(raw)
        print(f"===== {path} =====")
        try:
            lines = extract(path)
        except Exception as exc:
            print(f"[extract failed: {exc}]")
            continue
        for line in lines:
            print(line)
        print()


if __name__ == "__main__":
    main()
