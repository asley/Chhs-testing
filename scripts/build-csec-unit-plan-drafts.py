#!/usr/bin/env python3
"""
Build reviewable CSEC unit-plan drafts from local syllabus PDFs.

This script does not write to the Gibbon database. It extracts text from each
syllabus PDF, finds likely syllabus sections/units, and writes draft JSON and
Markdown files that can be reviewed before creating a database seed script.

Usage from the repository root:
  python3 scripts/build-csec-unit-plan-drafts.py
  python3 scripts/build-csec-unit-plan-drafts.py --output-dir docs/unit-plan-drafts
  python3 scripts/build-csec-unit-plan-drafts.py --pdf "docs/syllabus copy/CSEC-Biology-Syllabus.pdf"
"""

from __future__ import annotations

import argparse
import json
import re
import shutil
import subprocess
import sys
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable


REPO_ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUTPUT_DIR = REPO_ROOT / "docs" / "unit-plan-drafts"
DEFAULT_SYLLABUS_DIR = REPO_ROOT / "docs" / "syllabus copy"


STOP_HEADINGS = {
    "RATIONALE",
    "AIMS",
    "CANDIDATE POPULATION",
    "SUGGESTED TIMETABLE ALLOCATION",
    "ORGANISATION OF THE SYLLABUS",
    "FORMAT OF THE EXAMINATIONS",
    "CERTIFICATION",
    "DEFINITION OF PROFILES",
    "REGULATIONS FOR PRIVATE CANDIDATES",
    "REGULATIONS FOR RESIT CANDIDATES",
    "SCHOOL-BASED ASSESSMENT",
    "GUIDELINES FOR SCHOOL-BASED ASSESSMENT",
    "RECOMMENDED TEXTS",
    "GLOSSARY",
    "APPENDIX",
}

OBJECTIVE_START_VERBS = {
    "add",
    "analyse",
    "analyze",
    "apply",
    "assess",
    "calculate",
    "classify",
    "compare",
    "complete",
    "construct",
    "create",
    "define",
    "demonstrate",
    "derive",
    "describe",
    "determine",
    "differentiate",
    "discuss",
    "distinguish",
    "draw",
    "evaluate",
    "explain",
    "factorise",
    "identify",
    "interpret",
    "list",
    "make",
    "manipulate",
    "outline",
    "perform",
    "prepare",
    "represent",
    "select",
    "solve",
    "state",
    "subtract",
    "suggest",
    "use",
    "write",
}


@dataclass
class Objective:
    sequence: str
    title: str
    content: str = ""


@dataclass
class UnitDraft:
    sequence: str
    title: str
    general_objectives: list[str] = field(default_factory=list)
    specific_objectives: list[Objective] = field(default_factory=list)
    source_excerpt: str = ""
    warnings: list[str] = field(default_factory=list)


def run() -> int:
    args = parse_args()
    output_dir = resolve_path(args.output_dir)
    pdfs = [resolve_path(path) for path in args.pdf] if args.pdf else discover_default_pdfs()

    output_dir.mkdir(parents=True, exist_ok=True)

    results = []
    for pdf_path in pdfs:
        if not pdf_path.exists():
            print(f"Missing PDF: {pdf_path}", file=sys.stderr)
            results.append({"pdf": str(pdf_path), "status": "missing"})
            continue

        text = extract_pdf_text(pdf_path)
        draft = build_subject_draft(pdf_path, text)
        base_name = slugify(draft["subject"])

        json_path = output_dir / f"{base_name}.json"
        md_path = output_dir / f"{base_name}.md"
        json_path.write_text(json.dumps(draft, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        md_path.write_text(render_markdown(draft), encoding="utf-8")

        unit_count = len(draft["units"])
        objective_count = sum(len(unit["specificObjectives"]) for unit in draft["units"])
        warning_count = len(draft["warnings"]) + sum(len(unit["warnings"]) for unit in draft["units"])
        print(f"Wrote {display_path(md_path)} ({unit_count} units, {objective_count} objectives, {warning_count} warnings)")
        results.append(
            {
                "pdf": str(pdf_path),
                "markdown": str(md_path),
                "json": str(json_path),
                "units": unit_count,
                "specificObjectives": objective_count,
                "warnings": warning_count,
            }
        )

    index = {
        "generatedAt": now_iso(),
        "source": "scripts/build-csec-unit-plan-drafts.py",
        "results": results,
    }
    index_path = output_dir / "index.json"
    index_path.write_text(json.dumps(index, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {display_path(index_path)}")

    return 0


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--pdf",
        action="append",
        help="PDF to process. Can be passed more than once. Defaults to every CSEC syllabus PDF in docs/syllabus copy.",
    )
    parser.add_argument(
        "--output-dir",
        default=str(DEFAULT_OUTPUT_DIR),
        help="Directory for generated JSON and Markdown drafts.",
    )
    return parser.parse_args()


def discover_default_pdfs() -> list[Path]:
    pdfs = [
        path
        for path in DEFAULT_SYLLABUS_DIR.glob("CSEC*.pdf")
        if "syllabus" in path.name.lower()
    ]

    return sorted(pdfs, key=lambda path: path.name.lower())


def resolve_path(path: str | Path) -> Path:
    path = Path(path)
    if path.is_absolute():
        return path
    return (REPO_ROOT / path).resolve()


def display_path(path: Path) -> str:
    try:
        return str(path.relative_to(REPO_ROOT))
    except ValueError:
        return str(path)


def extract_pdf_text(pdf_path: Path) -> str:
    pdftotext = shutil.which("pdftotext")
    if pdftotext:
        completed = subprocess.run(
            [pdftotext, "-layout", str(pdf_path), "-"],
            check=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
        )
        return completed.stdout

    try:
        from pypdf import PdfReader
    except ImportError as exc:
        raise RuntimeError("Install poppler pdftotext or Python package pypdf to extract PDF text.") from exc

    reader = PdfReader(str(pdf_path))
    pages = []
    for page_number, page in enumerate(reader.pages, start=1):
        pages.append(f"\n\n[Page {page_number}]\n{page.extract_text() or ''}")
    return "\n".join(pages)


def build_subject_draft(pdf_path: Path, raw_text: str) -> dict:
    text = normalize_text(raw_text)
    subject = infer_subject(pdf_path, text)
    units, warnings = extract_units(text)

    if not units:
        warnings.append("No section/unit headings were detected. Output contains one fallback unit from the syllabus body.")
        units = [fallback_unit(text)]

    return {
        "subject": subject,
        "sourcePdf": str(pdf_path.relative_to(REPO_ROOT) if pdf_path.is_relative_to(REPO_ROOT) else pdf_path),
        "generatedAt": now_iso(),
        "intendedUse": "Review draft for Gibbon Planner unit and block seeding. Do not import without human verification.",
        "mapping": {
            "unit": "gibbonUnit candidate",
            "generalObjective": "gibbonOutcome candidate",
            "specificObjective": "gibbonUnitBlock candidate",
        },
        "warnings": warnings,
        "units": [unit_to_dict(unit) for unit in units],
    }


def normalize_text(text: str) -> str:
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    text = text.replace("\x0c", "\n\n")
    text = re.sub(r"[ \t]+$", "", text, flags=re.MULTILINE)
    text = re.sub(r"\n{4,}", "\n\n\n", text)
    return text.strip()


def infer_subject(pdf_path: Path, text: str) -> str:
    name = pdf_path.stem
    name = re.sub(r"(?i)^CSEC[-_ ]+", "", name)
    name = re.sub(r"(?i)[-_ ]*Syllabus(?:[-_ ]*Revised)?(?:[-_ ]*2024)?", "", name)
    name = name.replace("-", " ").replace("_", " ")
    name = re.sub(r"\bcopy\b", "", name, flags=re.IGNORECASE)
    name = re.sub(r"\s+", " ", name).strip()
    if name:
        return f"CSEC {name}"

    match = re.search(r"SYLLABUS\s+([A-Z][A-Z &-]{2,})", text)
    return f"CSEC {title_case(match.group(1))}" if match else "CSEC Syllabus"


def extract_units(text: str) -> tuple[list[UnitDraft], list[str]]:
    warnings: list[str] = []
    lines = text.splitlines()
    starts = filter_unit_starts(lines, find_unit_starts(lines))

    if not starts:
        return [], warnings

    units: list[UnitDraft] = []
    for index, (line_index, sequence, title) in enumerate(starts):
        next_line_index = starts[index + 1][0] if index + 1 < len(starts) else len(lines)
        body = "\n".join(lines[line_index + 1 : next_line_index]).strip()
        unit = parse_unit(sequence, title, body)
        units.append(unit)

    if len(units) < 2:
        warnings.append("Only one section/unit was detected. Check whether this syllabus uses non-standard headings.")

    return units, warnings


def find_unit_starts(lines: list[str]) -> list[tuple[int, str, str]]:
    starts: list[tuple[int, str, str]] = []

    for index, original_line in enumerate(lines):
        line = clean_line(original_line)
        if not line or is_contents_line(line):
            continue
        if is_non_unit_heading(line):
            continue

        match = re.match(
            r"^(?:\S+\s+)?(SECTION|UNIT|MODULE)\s+([A-Z]|\d{1,2}|[IVX]{1,6})\s*(?:[:.-]\s+|\s+-\s+)(.+)$",
            line,
            flags=re.IGNORECASE,
        )
        if not match:
            continue

        label, sequence, title = match.group(1).upper(), match.group(2).upper(), clean_heading(match.group(3))
        if not title or title.upper() in STOP_HEADINGS:
            continue
        if is_continuation_heading(title):
            continue

        starts.append((index, sequence, f"{label.title()} {sequence}: {title}"))

    return starts


def filter_unit_starts(lines: list[str], starts: list[tuple[int, str, str]]) -> list[tuple[int, str, str]]:
    if not starts:
        return starts

    scored: list[tuple[int, int, int, int, str, str]] = []
    for index, (line_index, sequence, title) in enumerate(starts):
        next_line_index = starts[index + 1][0] if index + 1 < len(starts) else len(lines)
        body_lines = lines[line_index + 1 : next_line_index]
        body = "\n".join(body_lines)
        body_head = "\n".join(body_lines[:40])
        exact_signal = len(
            re.findall(r"^\s*(GENERAL OBJECTIVES?|SPECIFIC OBJECTIVES?|Students should be able to:?)\s*$", body_head, flags=re.IGNORECASE | re.MULTILINE)
        )
        signal = len(re.findall(r"\b(GENERAL OBJECTIVES?|SPECIFIC OBJECTIVES?|Students should be able to)\b", body, flags=re.IGNORECASE))
        nearby_signal = len(
            re.findall(r"\b(GENERAL OBJECTIVES?|SPECIFIC OBJECTIVES?|Students should be able to)\b", body_head, flags=re.IGNORECASE)
        )
        body_length = len(normalize_space(body))
        scored.append((exact_signal, nearby_signal, signal, body_length, line_index, sequence, title))

    candidates: list[tuple[int, int, int, int, str, str]] = []
    for exact_signal, nearby_signal, signal, body_length, line_index, sequence, title in scored:
        if nearby_signal == 0:
            continue
        if signal == 0 and body_length < 300:
            continue
        candidates.append((exact_signal, nearby_signal, body_length, line_index, sequence, title))

    if not candidates:
        return starts

    best_by_title: dict[tuple[str, str], tuple[int, int, int, int, str, str]] = {}
    for candidate in candidates:
        exact_signal, nearby_signal, body_length, line_index, sequence, title = candidate
        key = (sequence, canonical_unit_title(title))
        current = best_by_title.get(key)
        if current is None or (exact_signal, nearby_signal, body_length, line_index) > (current[0], current[1], current[2], current[3]):
            best_by_title[key] = candidate

    filtered = [(line_index, sequence, title) for _, _, _, line_index, sequence, title in best_by_title.values()]
    filtered.sort(key=lambda item: item[0])

    return filtered


def parse_unit(sequence: str, title: str, body: str) -> UnitDraft:
    body_lines = [clean_line(line) for line in body.splitlines()]
    body_lines = [line for line in body_lines if line and not looks_like_page_footer(line)]

    unit = UnitDraft(sequence=sequence, title=title, source_excerpt=make_excerpt(body_lines))
    unit.general_objectives = extract_general_objectives(body_lines)
    unit.specific_objectives = extract_specific_objectives(body_lines)

    if not unit.general_objectives:
        unit.warnings.append("No general objectives were detected for this unit.")
    if not unit.specific_objectives:
        unit.warnings.append("No specific objectives were detected for this unit.")

    return unit


def extract_general_objectives(lines: list[str]) -> list[str]:
    start = find_heading_index(lines, r"^GENERAL OBJECTIVES?$")
    if start is None:
        return []

    stop = find_next_heading_index(
        lines,
        start + 1,
        [
            r"^SPECIFIC OBJECTIVES?",
            r"^CONTENT\b",
            r"^SUGGESTED PRACTICAL",
            r"^SUGGESTED TEACHING",
            r"^SKILLS AND ABILITIES",
        ],
    )
    chunk = lines[start + 1 : stop]
    return extract_numbered_items(chunk)


def extract_specific_objectives(lines: list[str]) -> list[Objective]:
    start = find_heading_index(lines, r"^SPECIFIC OBJECTIVES?")
    search_lines = lines[start + 1 :] if start is not None else lines
    search_lines = expand_embedded_objective_starts(search_lines)

    objectives: list[Objective] = []
    current: Objective | None = None
    for line in search_lines:
        if is_terminal_syllabus_heading(line):
            break
        if re.match(r"^(CONTENT|EXPLANATORY NOTES|SUGGESTED PRACTICAL|SUGGESTED TEACHING)\b", line, re.IGNORECASE):
            continue

        match = re.match(r"^(\d+[a-z]?(?:\.\d+)?|\([a-z]\))[\).]?\s+(.+)$", line)
        if match and is_likely_objective_text(match.group(2)):
            if current:
                objectives.append(current)
            sequence = match.group(1).strip("()")
            title = clean_objective_title(match.group(2))
            current = Objective(sequence=sequence, title=title)
            continue

        if current and line:
            current.content = append_content(current.content, line)

    if current:
        objectives.append(current)

    return dedupe_objectives(objectives)


def extract_numbered_items(lines: list[str]) -> list[str]:
    items: list[str] = []
    current = ""
    for line in lines:
        if is_terminal_syllabus_heading(line):
            break
        match = re.match(r"^(\d+|[a-z]|\([a-z]\))[\).]\s+(.+)$", line, flags=re.IGNORECASE)
        if match:
            if current:
                items.append(normalize_space(current))
            current = match.group(2)
        elif current and line:
            current = append_content(current, line)

    if current:
        items.append(normalize_space(current))

    return [item for item in items if len(item) > 12]


def expand_embedded_objective_starts(lines: list[str]) -> list[str]:
    expanded: list[str] = []
    verb_pattern = "|".join(sorted(OBJECTIVE_START_VERBS))
    splitter = re.compile(rf"\s+((?:\d+[a-z]?)(?:\.\d+)?[\).]?\s+(?:{verb_pattern})\b)", flags=re.IGNORECASE)

    for line in lines:
        parts = splitter.split(line)
        if len(parts) == 1:
            expanded.append(line)
            continue

        current = parts[0].strip()
        if current:
            expanded.append(current)
        for index in range(1, len(parts), 2):
            objective_start = parts[index]
            rest = parts[index + 1] if index + 1 < len(parts) else ""
            expanded.append(normalize_space(objective_start + rest))

    return expanded


def find_heading_index(lines: list[str], pattern: str) -> int | None:
    regex = re.compile(pattern, flags=re.IGNORECASE)
    for index, line in enumerate(lines):
        if regex.match(line):
            return index
    return None


def find_next_heading_index(lines: list[str], start: int, patterns: Iterable[str]) -> int:
    regexes = [re.compile(pattern, flags=re.IGNORECASE) for pattern in patterns]
    for index in range(start, len(lines)):
        if any(regex.match(lines[index]) for regex in regexes):
            return index
    return len(lines)


def fallback_unit(text: str) -> UnitDraft:
    lines = [clean_line(line) for line in text.splitlines()]
    body_start = 0
    for index, line in enumerate(lines):
        if line.upper() == "ORGANISATION OF THE SYLLABUS":
            body_start = index + 1
            break
    excerpt = [line for line in lines[body_start:] if line and not looks_like_page_footer(line)][:120]
    return UnitDraft(
        sequence="1",
        title="Syllabus Structure Review Required",
        source_excerpt=make_excerpt(excerpt),
        warnings=["Fallback unit only. Manually review the source PDF and refine extraction rules for this syllabus."],
    )


def unit_to_dict(unit: UnitDraft) -> dict:
    return {
        "sequence": unit.sequence,
        "title": unit.title,
        "generalObjectives": unit.general_objectives,
        "specificObjectives": [
            {
                "sequence": objective.sequence,
                "title": objective.title,
                "content": objective.content,
            }
            for objective in unit.specific_objectives
        ],
        "sourceExcerpt": unit.source_excerpt,
        "warnings": unit.warnings,
    }


def render_markdown(draft: dict) -> str:
    lines = [
        f"# Unit Plan Draft - {draft['subject']}",
        "",
        f"Source: `{draft['sourcePdf']}`",
        f"Generated: `{draft['generatedAt']}`",
        "",
        "This is an extraction draft for human review before any Planner database seed script is created.",
        "",
    ]

    if draft["warnings"]:
        lines.extend(["## Document Warnings", ""])
        for warning in draft["warnings"]:
            lines.append(f"- {warning}")
        lines.append("")

    for unit in draft["units"]:
        lines.extend([f"## {unit['title']}", ""])
        if unit["warnings"]:
            for warning in unit["warnings"]:
                lines.append(f"- Warning: {warning}")
            lines.append("")

        lines.extend(["### General Objectives", ""])
        if unit["generalObjectives"]:
            for index, objective in enumerate(unit["generalObjectives"], start=1):
                lines.append(f"{index}. {objective}")
        else:
            lines.append("_None detected._")
        lines.append("")

        lines.extend(["### Specific Objectives", ""])
        if unit["specificObjectives"]:
            for objective in unit["specificObjectives"]:
                content = f" Content: {objective['content']}" if objective["content"] else ""
                lines.append(f"- **{objective['sequence']}** {objective['title']}{content}")
        else:
            lines.append("_None detected._")
        lines.append("")

        lines.extend(["### Source Excerpt", "", "```text", unit["sourceExcerpt"], "```", ""])

    return "\n".join(lines).rstrip() + "\n"


def clean_line(line: str) -> str:
    line = line.replace("\u00a0", " ")
    line = line.replace("", "")
    return normalize_space(line)


def clean_heading(text: str) -> str:
    text = re.sub(r"\.{2,}\s*\d+\s*$", "", text)
    text = re.sub(r"\s+\d+\s*$", "", text)
    return title_case(normalize_space(text))


def clean_objective_title(text: str) -> str:
    text = re.sub(r"\s{2,}.+$", "", text)
    return normalize_space(text)


def normalize_space(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


def append_content(existing: str, addition: str) -> str:
    addition = normalize_space(addition)
    if not addition:
        return existing
    return normalize_space(f"{existing} {addition}" if existing else addition)


def dedupe_objectives(objectives: list[Objective]) -> list[Objective]:
    deduped: list[Objective] = []
    seen: set[tuple[str, str]] = set()
    for objective in objectives:
        key = (objective.sequence, objective.title.lower())
        if key in seen:
            continue
        seen.add(key)
        deduped.append(objective)
    return deduped


def make_excerpt(lines: list[str], limit: int = 2500) -> str:
    excerpt = "\n".join(lines)
    if len(excerpt) <= limit:
        return excerpt
    return excerpt[:limit].rsplit("\n", 1)[0].rstrip() + "\n[excerpt truncated]"


def is_contents_line(line: str) -> bool:
    if re.search(r"[.\u2026]{3,}", line):
        return True
    return bool(re.search(r"\.{3,}\s*\d+\s*$", line))


def is_non_unit_heading(line: str) -> bool:
    upper = line.upper()
    return any(upper.startswith(heading) for heading in STOP_HEADINGS)


def is_terminal_syllabus_heading(line: str) -> bool:
    upper = line.upper()
    if re.match(r"^(SECTION|UNIT|MODULE)\s+([A-Z]|\d+|[IVX]+)\b", upper):
        return True
    return any(upper.startswith(heading) for heading in STOP_HEADINGS)


def is_continuation_heading(title: str) -> bool:
    title = title.lower()
    return bool(re.search(r"\bcont(?:['’.\s]*d|inued)?\b", title))


def canonical_unit_title(title: str) -> str:
    title = re.sub(r"\bcont(?:['’.\s]*d|inued)?\b", "", title, flags=re.IGNORECASE)
    title = re.sub(r"[^a-z0-9]+", " ", title.lower())
    return normalize_space(title)


def looks_like_page_footer(line: str) -> bool:
    if re.match(r"^CXC\s+\d+/.+", line, flags=re.IGNORECASE):
        return True
    if line.lower() == "www.cxc.org":
        return True
    return bool(re.match(r"^\d+$", line))


def is_likely_objective_text(text: str) -> bool:
    if len(text) < 12:
        return False
    first_word = text.split()[0].lower().strip(":;,")
    return first_word in OBJECTIVE_START_VERBS or first_word.endswith("ing")


def slugify(text: str) -> str:
    text = text.lower()
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-")


def title_case(text: str) -> str:
    small_words = {"and", "of", "the", "in", "for", "to", "a", "an", "or"}
    words = normalize_space(text).lower().split()
    titled = []
    for index, word in enumerate(words):
        if index > 0 and word in small_words:
            titled.append(word)
        else:
            titled.append(word[:1].upper() + word[1:])
    return " ".join(titled)


def now_iso() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


if __name__ == "__main__":
    raise SystemExit(run())
