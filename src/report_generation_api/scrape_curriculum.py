import json
import re
from typing import Any, Dict, List, Tuple

import requests
from bs4 import BeautifulSoup
from fastapi import FastAPI, HTTPException

app = FastAPI(title="ABET Curriculum API")

URL = "https://degrees.apps.asu.edu/major-map/ASU00/ESCSEBSE/null/ALL/2022"


def clean_ws(text: str) -> str:
    return re.sub(r"\s+", " ", (text or "")).strip()


def is_real_course_row(text: str) -> bool:
    """Return True for rows that represent curriculum items.
    Skip summary, completion, and non-course requirement rows."""
    text = clean_ws(text)

    if not text:
        return False

    skip_phrases = [
        "Complete Mathematics",
        "Complete MAT",
        "Complete ENG",
        "Complete First-year",
        "Minimum 2.00 GPA",
        "Term hours subtotal",
        "Critical course signified by",
    ]

    for phrase in skip_phrases:
        if phrase.lower() in text.lower():
            return False

    return True


def extract_course_text(td) -> str:
    return clean_ws(td.get_text(" ", strip=True))


def extract_hours(td) -> str:
    text = clean_ws(td.get_text(" ", strip=True))
    match = re.search(r"\d+", text)
    return match.group(0) if match else ""


def infer_required_or_elective(label: str) -> str:
    """Infer R, E, or SE from the row text.
    Course rows default to R when no elective pattern is found."""
    lowered = label.lower()

    selected_elective_signals = [
        "upper division elective",
        "technical elective",
        "social-behavioral sciences",
        "humanities, arts and design",
        "biology or chemistry course",
        "lab science",
        "global awareness",
        "historical awareness",
        "cultural diversity",
    ]

    for signal in selected_elective_signals:
        if signal in lowered:
            return "SE"

    if re.search(r"\belective\b", lowered):
        return "E"

    if re.match(r"^[A-Z/]{2,}\s+\d", label):
        return "R"

    return ""


def infer_category_hours(label: str, hours: str) -> Tuple[str, str, str]:
    """Assign hours into Math & Basic Sciences, Engineering Topics, or Other.
    Uses simple text rules based on subject prefixes and requirement labels."""
    lowered = label.lower()

    if not hours:
        return ("", "", "")

    math_basic_patterns = [
        lowered.startswith("mat "),
        lowered.startswith("phy "),
        lowered.startswith("bio "),
        lowered.startswith("chm "),
        "biology" in lowered,
        "chemistry" in lowered,
        "physics" in lowered,
        "lab science" in lowered,
        "probability and statistics" in lowered,
    ]

    other_patterns = [
        lowered.startswith("eng "),
        lowered.startswith("asu "),
        "humanities" in lowered,
        "arts and design" in lowered,
        "social-behavioral" in lowered,
        "historical awareness" in lowered,
        "global awareness" in lowered,
        "cultural diversity" in lowered,
    ]

    if any(math_basic_patterns):
        return (hours, "", "")

    if any(other_patterns):
        return ("", "", hours)

    return ("", hours, "")


def parse_major_map_html(html_doc: str) -> Dict[str, Any]:
    """Parse the major map page into semester-based curriculum data.
    Each row includes course text, hours, category columns, and R, E, or SE."""
    soup = BeautifulSoup(html_doc, "html.parser")

    semesters: List[Dict[str, Any]] = []
    math_total = 0
    eng_total = 0
    other_total = 0

    term_tables = soup.find_all("table", class_="termTbl")

    for term_table in term_tables:
        heading_row = term_table.find("tr", class_="termHeading")
        if not heading_row:
            continue

        term_span = heading_row.find("span", class_="term")
        if not term_span:
            continue

        term_name = clean_ws(term_span.get_text(strip=True))
        if not term_name:
            continue

        semester_name = term_name.replace("Term", "Semester")
        semester_rows: List[Dict[str, str]] = []

        nested_table = term_table.find("table", class_="termTblNested")
        if not nested_table:
            semesters.append({
                "semester": semester_name,
                "rows": semester_rows,
            })
            continue

        for tr in nested_table.find_all("tr"):
            tds = tr.find_all("td")
            if len(tds) < 3:
                continue

            course_text = extract_course_text(tds[1])

            if not is_real_course_row(course_text):
                continue

            hours = extract_hours(tds[2])
            required_or_elective = infer_required_or_elective(course_text)
            math_hours, eng_hours, other_hours = infer_category_hours(course_text, hours)

            if math_hours.isdigit():
                math_total += int(math_hours)
            if eng_hours.isdigit():
                eng_total += int(eng_hours)
            if other_hours.isdigit():
                other_total += int(other_hours)

            semester_rows.append({
                "course": course_text,
                "required_elective": required_or_elective,
                "math_basic_sciences": math_hours,
                "engineering_topics": eng_hours,
                "other": other_hours,
                "last_two_terms_offered": "",
                "max_enrollment_last_two_terms": "",
            })

        semesters.append({
            "semester": semester_name,
            "rows": semester_rows,
        })

    return {
        "source_url": URL,
        "semesters": semesters,
        "totals": {
            "math_basic_sciences": str(math_total) if math_total else "",
            "engineering_topics": str(eng_total) if eng_total else "",
            "other": str(other_total) if other_total else "",
        },
    }


def build_curriculum_from_url(url: str, timeout: int = 30) -> Dict[str, Any]:
    """Fetch the major map page and return parsed curriculum data."""
    headers = {
        "Accept": "text/html",
        "User-Agent": "Mozilla/5.0",
    }

    response = requests.get(url, headers=headers, timeout=timeout)
    response.raise_for_status()
    return parse_major_map_html(response.text)


@app.get("/health")
def health() -> Dict[str, str]:
    return {"status": "ok"}


@app.get("/curriculum/major-map")
def get_curriculum_major_map() -> Dict[str, Any]:
    """Return structured curriculum data from the ASU major map page."""
    try:
        return build_curriculum_from_url(URL)
    except requests.RequestException as exc:
        raise HTTPException(status_code=502, detail=f"Failed to fetch source page: {exc}") from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"Failed to build curriculum data: {exc}") from exc


if __name__ == "__main__":
    curriculum_data = build_curriculum_from_url(URL)
    print(json.dumps(curriculum_data, indent=2))