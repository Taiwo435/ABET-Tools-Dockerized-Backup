import json
import re
import requests
from typing import Dict, List, Optional, Tuple
from bs4 import BeautifulSoup

# This code is valid for 2025 and 2026 checksheet versions.
# It may need adjustments for future checksheet format changes.
# cyber_url = f"https://degrees.apps.asu.edu/checksheet/{year}/CES/ESCSEIBSE/null"
# cse_url = f"https://degrees.apps.asu.edu/checksheet/{year}/CES/ESCSEBSE/null"


def _normalize_space(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


def extract_cse_technical_electives_from_html(html_doc: str) -> List[str]:
    """
    Extract CSE 4xx course lines from the expandable list named:
    'Upper Division CSE Technical Electives'.
    """
    if not html_doc:
        return []

    soup = BeautifulSoup(html_doc, "html.parser")
    target = _normalize_space("Upper Division CSE Technical Electives")
    cse_4xx_pattern = re.compile(r"^CSE\s+4\d{2}[A-Z]?\b", re.IGNORECASE)

    for label in soup.select("span.course-list-name"):
        label_text = _normalize_space(label.get_text(" ", strip=True))
        if label_text != target:
            continue

        connector = label.find_parent("span", class_="connector-container")
        if not connector:
            continue

        course_list_content = connector.find_next_sibling(
            "div", class_="courseListContent"
        )

        if not course_list_content:
            parent = connector.parent
            if parent:
                course_list_content = parent.find("div", class_="courseListContent")

        if not course_list_content:
            return []

        courses: List[str] = []
        for course_row in course_list_content.select("div.courseListRow"):
            text = _normalize_space(course_row.get_text(" ", strip=True)).strip(' "')
            if text and cse_4xx_pattern.match(text):
                courses.append(text)

        return courses

    return []


def extract_cybersecurity_required_courses_from_html(html_doc: str) -> List[str]:
    """
    Extract required courses under:
    'Computer Systems Engineering (Cybersecurity) Concentration Requirements'

    Includes:
    - direct required course rows (example: CSE 365 Information Assurance)
    - rows inside 'Upper Division Cybersecurity Focus Courses'

    Excludes:
    - rows under 'Cybersecurity Elective'
    - CSE technical elective section
    """
    if not html_doc:
        return []

    soup = BeautifulSoup(html_doc, "html.parser")
    subsection_target = _normalize_space(
        "Computer Systems Engineering (Cybersecurity) Concentration Requirements"
    )

    header_row = None
    for td in soup.select("td.subsection-name"):
        if _normalize_space(td.get_text(" ", strip=True)) == subsection_target:
            header_row = td.find_parent("tr")
            break

    if not header_row:
        return []

    courses: List[str] = []
    seen = set()

    current_row = header_row.find_next_sibling("tr")
    while current_row:
        row_classes = current_row.get("class") or []

        if "subsection-header" in row_classes:
            break

        if "checksheet-requirement" in row_classes:
            td = current_row.find("td")
            has_list = bool(current_row.find("span", class_="connector-container"))
            if td and not has_list:
                direct_anchor = td.find("a", class_="ttCourse")
                if direct_anchor:
                    direct_text = _normalize_space(direct_anchor.get_text(" ", strip=True)).strip(' "')
                    if direct_text and direct_text not in seen:
                        seen.add(direct_text)
                        courses.append(direct_text)

            for label in current_row.select("span.course-list-name"):
                list_name = _normalize_space(label.get_text(" ", strip=True)).lower()
                if "focus courses" not in list_name:
                    continue

                connector = label.find_parent("span", class_="connector-container")
                if not connector:
                    continue

                course_list_content = connector.find_next_sibling(
                    "div", class_="courseListContent"
                )
                if not course_list_content:
                    parent = connector.parent
                    if parent:
                        course_list_content = parent.find("div", class_="courseListContent")

                if not course_list_content:
                    continue

                for course_row in course_list_content.select("div.courseListRow"):
                    text = _normalize_space(course_row.get_text(" ", strip=True)).strip(' "')
                    if text and text not in seen:
                        seen.add(text)
                        courses.append(text)

        current_row = current_row.find_next_sibling("tr")

    return courses


def extract_cse_technical_electives_from_url(url: str, timeout: int = 30) -> List[str]:
    """Fetch URL HTML and extract CSE 4xx technical elective lines."""
    headers = {"User-Agent": "Mozilla/5.0", "Accept": "text/html"}
    response = requests.get(url, headers=headers, timeout=timeout)
    response.raise_for_status()
    return extract_cse_technical_electives_from_html(response.text)


def extract_cybersecurity_required_courses_from_url(url: str, timeout: int = 30) -> List[str]:
    """Fetch URL HTML and extract cybersecurity required course lines."""
    headers = {"User-Agent": "Mozilla/5.0", "Accept": "text/html"}
    response = requests.get(url, headers=headers, timeout=timeout)
    response.raise_for_status()
    return extract_cybersecurity_required_courses_from_html(response.text)


def find_specific_course_line(html_doc: str, needle: str) -> Optional[str]:
    """Case-insensitive search for one course line within the CSE technical elective list."""
    if not needle:
        return None

    needle_lower = needle.lower()
    for course in extract_cse_technical_electives_from_html(html_doc):
        if needle_lower in course.lower():
            return course
    return None


def _parse_course_line(course_line: str) -> Optional[Dict[str, str]]:
    """
    Convert a line like:
    'CSE 478 Foundations of Data Visualization'
    into:
    {"code": "CSE", "number": "478", "name": "Foundations of Data Visualization", "required_for": ""}
    """
    if not course_line:
        return None

    match = re.match(
        r"^(?P<code>[A-Z]{2,5})\s+(?P<number>\d{3}[A-Z]?)\s+(?P<name>.+)$",
        _normalize_space(course_line),
        re.IGNORECASE,
    )
    if not match:
        return None

    return {
        "code": match.group("code").upper(),
        "number": match.group("number").upper(),
        "name": match.group("name").strip(),
        "required_for": "",
    }


def _course_key(course_obj: Dict[str, str]) -> Tuple[str, str, str]:
    return (
        course_obj["code"],
        course_obj["number"],
        _normalize_space(course_obj["name"]).lower(),
    )


def build_merged_courses_json_from_html(html_doc: str, cyber_html_doc: str) -> List[Dict[str, str]]:
    """
    Build merged JSON from:
    - CSE technical elective list
    - Cybersecurity required list

    Rules:
    - dedupe exact same course (code, number, name)
    - if a course appears in cybersecurity list, set required_for = 'Cbs'
    """
    technical_courses = extract_cse_technical_electives_from_html(html_doc)
    cybersecurity_courses = extract_cybersecurity_required_courses_from_html(cyber_html_doc)

    merged: Dict[Tuple[str, str, str], Dict[str, str]] = {}

    for line in technical_courses:
        parsed = _parse_course_line(line)
        if not parsed:
            continue
        merged[_course_key(parsed)] = parsed

    for line in cybersecurity_courses:
        parsed = _parse_course_line(line)
        if not parsed:
            continue

        key = _course_key(parsed)
        if key in merged:
            merged[key]["required_for"] = "Cbs"
        else:
            parsed["required_for"] = "Cbs"
            merged[key] = parsed

    return sorted(
        merged.values(),
        key=lambda x: (x["code"], x["number"], x["name"].lower()),
    )


def build_merged_cse_courses_json_from_url(year: int, timeout: int = 30) -> List[Dict[str, str]]:
    """Fetch checksheet HTML and return merged, deduplicated course JSON list."""
    headers = {"User-Agent": "Mozilla/5.0", "Accept": "text/html"}
    cyber_url = f"https://degrees.apps.asu.edu/checksheet/{year}/CES/ESCSEIBSE/null"
    cse_url = f"https://degrees.apps.asu.edu/checksheet/{year}/CES/ESCSEBSE/null"
    response = requests.get(cse_url, headers=headers, timeout=timeout)
    response.raise_for_status()
    cyber_response = requests.get(cyber_url, headers=headers, timeout=timeout)
    cyber_response.raise_for_status()
    return build_merged_courses_json_from_html(response.text, cyber_response.text)


if __name__ == "__main__":
    year = 2026
    merged_courses = build_merged_cse_courses_json_from_url(year)
    print(json.dumps(merged_courses, indent=2))
