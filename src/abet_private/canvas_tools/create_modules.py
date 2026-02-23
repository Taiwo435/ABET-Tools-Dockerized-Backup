import json
import os
import shutil
import sys
from urllib.parse import urljoin

import requests

from create_html import WriteAbetHtml


def env_bool(name: str, default: bool = False) -> bool:
    v = os.getenv(name)
    if v is None:
        return default
    return str(v).strip().lower() in {"1", "true", "yes", "on"}


# CONFIGURATION (read from environment set by PHP backend/wrapper)
CANVAS_DOMAIN = os.getenv("CANVAS_DOMAIN", "canvas.asu.edu")
CANVAS_TOKEN = os.getenv("canvas_access_token", "")
SOURCE_COURSE_ID = os.getenv("CANVAS_SOURCE_COURSE_ID", "").strip()
DESTINATION_COURSE_ID = os.getenv("CANVAS_DEST_COURSE_ID", "").strip()
SEMESTER = os.getenv("CANVAS_SEMESTER", "Fall").strip()
YEAR = os.getenv("CANVAS_YEAR", "2025").strip()
DO_COURSE_PAGE = env_bool("CANVAS_DO_COURSE_PAGE", True)
DO_ABET_PAGE = env_bool("CANVAS_DO_ABET_PAGE", True)

API_BASE_URL = f"https://{CANVAS_DOMAIN}/api/v1/"
CANVAS_BASE_URL = f"https://{CANVAS_DOMAIN}/"
HEADERS = {"Authorization": f"Bearer {CANVAS_TOKEN}"}
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
TEMP_DIR = os.path.join(SCRIPT_DIR, "temp_html_files")


def require_env():
    missing = []
    if not CANVAS_TOKEN:
        missing.append("canvas_access_token")
    if not SOURCE_COURSE_ID:
        missing.append("CANVAS_SOURCE_COURSE_ID")
    if not DESTINATION_COURSE_ID:
        missing.append("CANVAS_DEST_COURSE_ID")
    if missing:
        raise RuntimeError(f"Missing required environment variables: {', '.join(missing)}")


def add_to_canvas(course_code, course_name, semester, year):
    local_path = os.path.join(TEMP_DIR, "test.html")
    with open(local_path, "r", encoding="utf-8") as f:
        html_content = f.read()

    page_data = {
        "wiki_page": {
            "title": f"{course_code}: {course_name} ({semester.capitalize()} {year})",
            "body": html_content
        }
    }

    upload_response = requests.post(
        url=f"{API_BASE_URL}courses/{DESTINATION_COURSE_ID}/pages",
        json=page_data,
        headers=HEADERS,
        timeout=60
    )
    upload_response.raise_for_status()
    print("  - Successfully uploaded course page")
    return upload_response.json()


def add_abet_to_canvas():
    local_path = os.path.join(TEMP_DIR, "abet.html")
    with open(local_path, "r", encoding="utf-8") as f:
        html_content = f.read()

    page_data = {
        "wiki_page": {
            "title": "CSE-ABET Assessment Instruments and Samples",
            "body": html_content
        }
    }

    upload_response = requests.post(
        url=f"{API_BASE_URL}courses/{DESTINATION_COURSE_ID}/pages",
        json=page_data,
        headers=HEADERS,
        timeout=60
    )
    upload_response.raise_for_status()
    print("  - Successfully uploaded ABET page")
    return upload_response.json()


def get_paginated_list(endpoint, params=None):
    all_items = []
    url = urljoin(API_BASE_URL, endpoint)
    params = params or {}
    params["per_page"] = 100

    while url:
        try:
            response = requests.get(url, headers=HEADERS, params=params, timeout=60)
            response.raise_for_status()

            payload = response.json()
            if isinstance(payload, list):
                all_items.extend(payload)
            else:
                # Some endpoints may not return a list; normalize
                break

            url = None
            if "Link" in response.headers:
                links = requests.utils.parse_header_links(response.headers["Link"])
                for link in links:
                    if link.get("rel") == "next":
                        url = link["url"]
                        break

            params = None  # next URL already contains params
        except requests.exceptions.RequestException as e:
            print(f"API Error on GET {url}: {e}")
            if getattr(e, "response", None) is not None:
                try:
                    print(f"Response body: {e.response.text}")
                except Exception:
                    pass
            break

    return all_items


def upload_module_to_canvas(course_id, module_name):
    print(f"Uploading module '{module_name}' to Canvas...")
    module_data = {
        "module": {
            "name": module_name,
            "position": 1
        }
    }
    response = requests.post(
        f"{API_BASE_URL}courses/{course_id}/modules",
        headers=HEADERS,
        json=module_data,
        timeout=60
    )
    response.raise_for_status()
    print(f"  - Module created (HTTP {response.status_code})")
    return response.json()


def add_single_module_item(course_id, module_id, page):
    module_item_data = {
        "module_item": {
            "title": page.get("title"),
            "type": "Page",
            "page_url": page.get("url")
        }
    }

    response = requests.post(
        f"{API_BASE_URL}courses/{course_id}/modules/{module_id}/items",
        headers=HEADERS,
        json=module_item_data,
        timeout=60
    )
    response.raise_for_status()
    print(f"  - Added page '{page.get('title')}' to module (HTTP {response.status_code})")
    return response.json()


def find_file_folder(course_id, course_code, semester, year):
    """
    Finds relevant file folders. Tries a term/year+course match first, then falls back to course_code only.
    """
    print(f"Finding file folders for course '{course_code}' in source course {course_id}...")
    endpoint = f"courses/{course_id}/folders"
    file_folders = get_paginated_list(endpoint, params={"include[]": "folders"})

    semester_l = semester.lower()
    year_s = str(year)
    term_matches = []

    for f in file_folders:
        full_name = (f.get("full_name") or "")
        full_name_l = full_name.lower()

        if course_code.lower() in full_name_l and semester_l in full_name_l and year_s in full_name_l:
            term_matches.append(f)

    if term_matches:
        print(f"Found {len(term_matches)} folders matching course + semester + year.")
        return term_matches

    fallback = []
    for f in file_folders:
        full_name = (f.get("full_name") or "")
        if course_code.lower() in full_name.lower():
            fallback.append(f)

    print(f"No term-specific folder match found. Falling back to course-code-only match ({len(fallback)} folders).")
    return fallback


def get_files(course_id, course_code, semester, year, file_folders):
    print(f"Searching files for '{course_code}' in source course {course_id}...")
    endpoint = f"courses/{course_id}/files"
    all_files = get_paginated_list(endpoint)

    folder_to_files = {}
    for folder in file_folders:
        full_folder_name = folder.get("full_name") or ""
        abbrv_name = folder.get("name") or ""
        folder_id = folder.get("id")

        # Skip the top-level main course folder if it is just the course code label
        if course_code.lower() in (abbrv_name or "").lower():
            continue

        if course_code.lower() in full_folder_name.lower():
            found_files = [f for f in all_files if f.get("folder_id") == folder_id]
            folder_to_files[abbrv_name] = found_files
            print(f"  - Folder: {full_folder_name} | Id: {folder_id} | Files: {len(found_files)}")

    return folder_to_files


def main():
    require_env()

    print("=== Canvas ABET Generator Start ===")
    print(f"Source Course ID: {SOURCE_COURSE_ID}")
    print(f"Destination Course ID: {DESTINATION_COURSE_ID}")
    print(f"Semester/Year: {SEMESTER} {YEAR}")
    print(f"Generate Course Page: {DO_COURSE_PAGE}")
    print(f"Generate ABET Page: {DO_ABET_PAGE}")
    print("Canvas token received: [hidden]")

    if os.path.exists(TEMP_DIR):
        shutil.rmtree(TEMP_DIR)
    os.makedirs(TEMP_DIR, exist_ok=True)

    try:
        html_writer = WriteAbetHtml(source_course_id=SOURCE_COURSE_ID, canvas_base_url=CANVAS_BASE_URL)

        # Pull source course info
        course_info_resp = requests.get(
            url=f"{API_BASE_URL}courses/{SOURCE_COURSE_ID}",
            headers=HEADERS,
            timeout=60
        )
        course_info_resp.raise_for_status()
        course_info = course_info_resp.json()

        course_code = (course_info.get("course_code") or f"Course {SOURCE_COURSE_ID}").strip()
        course_name = (course_info.get("name") or "Untitled Course").strip()

        print(f"Source course resolved: {course_code} | {course_name}")

        file_folders = find_file_folder(SOURCE_COURSE_ID, course_code, SEMESTER, YEAR)
        files = get_files(SOURCE_COURSE_ID, course_code, SEMESTER, YEAR, file_folders)

        if DO_COURSE_PAGE:
            module_name = f"Courses - Course Folders and Student Work Samples ({SEMESTER.capitalize()} {YEAR})"
            module = upload_module_to_canvas(DESTINATION_COURSE_ID, module_name)

            html_writer.set_up_course_page(file_folders, files, course_code, course_name, SEMESTER, YEAR)
            page = add_to_canvas(course_code, course_name, SEMESTER, YEAR)
            add_single_module_item(DESTINATION_COURSE_ID, module.get("id"), page)
        else:
            print("Skipped course page generation.")

        if DO_ABET_PAGE:
            module_name = "Assessment Instruments and Student Work Samples"
            module = upload_module_to_canvas(DESTINATION_COURSE_ID, module_name)

            html_writer.set_up_abet_page()
            abet_page = add_abet_to_canvas()
            add_single_module_item(DESTINATION_COURSE_ID, module.get("id"), abet_page)
        else:
            print("Skipped ABET page generation.")

        print("=== Process finished successfully ===")

    finally:
        if os.path.exists(TEMP_DIR):
            shutil.rmtree(TEMP_DIR, ignore_errors=True)


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(f"FATAL ERROR: {e}")
        sys.exit(1)