"""
Canvas formatting and module creation logic.
All functions accept headers and config as parameters for API use.
"""

import logging
import os
from collections import defaultdict
from typing import Dict, List, Optional, Tuple
from urllib.parse import urljoin

import requests

from create_html import WriteAbetHtml

logger = logging.getLogger(__name__)


def _get_api_base_url(canvas_domain: str) -> str:
    domain = str(canvas_domain).strip("/")
    if not domain.startswith("http"):
        domain = "https://" + domain
    return f"{domain}/api/v1/"


def _get_canvas_base_url(canvas_domain: str) -> str:
    domain = str(canvas_domain).strip("/")
    if not domain.startswith("http"):
        domain = "https://" + domain
    return domain + "/"


def fetch_all_course_files(canvas_domain: str, course_id: str, headers: dict) -> List[dict]:
    """
    Fetch ALL files in a course using /files (paginated).
    """
    all_files = []
    base_url = _get_api_base_url(canvas_domain)
    url = f"{base_url}courses/{course_id}/files?per_page=100"

    while url:
        r = requests.get(url, headers=headers)
        r.raise_for_status()
        all_files.extend(r.json())

        next_url = None
        link_header = r.headers.get("Link", "")
        for part in link_header.split(","):
            if 'rel="next"' in part:
                next_url = part[part.find("<") + 1 : part.find(">")]
                break
        url = next_url

    logger.info("TOTAL FILES FETCHED: %d", len(all_files))
    return all_files


def get_paginated_list(
    endpoint: str, headers: dict, api_base_url: str, params: Optional[dict] = None
) -> List:
    """Fetch paginated list from Canvas API."""
    all_items = []
    url = urljoin(api_base_url, endpoint)
    params = params or {}
    params["per_page"] = 100

    while url:
        response = requests.get(url, headers=headers, params=params)
        response.raise_for_status()
        all_items.extend(response.json())

        url = None
        if "Link" in response.headers:
            links = requests.utils.parse_header_links(response.headers["Link"])
            for link in links:
                if link.get("rel") == "next":
                    url = link["url"]
                    break
        params = None

    return all_items


def find_file_folder(
    course_id: str, semester: str, year: str, headers: dict, api_base_url: str
) -> List[dict]:
    """Find folders matching (year semester) in course."""
    logger.info("Finding all (%s %s) folders in course %s...", year, semester.capitalize(), course_id)
    endpoint = f"courses/{course_id}/folders"
    file_folders = get_paginated_list(endpoint, headers, api_base_url, params={"include[]": "folders"})
    return [f for f in file_folders if f"({year} {semester.capitalize()})" in f.get("full_name", "")]


def get_files(
    course_id: str,
    semester: str,
    year: str,
    file_folders: List[dict],
    headers: dict,
    api_base_url: str,
) -> Tuple[dict, str]:
    """Get files grouped by Syllabus/Assignments from folders."""
    if not file_folders:
        raise RuntimeError("No folders were returned from Canvas. Check permissions/course_id.")

    course_name = file_folders[0].get("full_name", "").split("/")[1] or "COURSE"

    all_files = get_paginated_list(f"courses/{course_id}/files", headers, api_base_url)

    files_by_folder: Dict[int, List[dict]] = defaultdict(list)
    for f in all_files:
        fid = f.get("folder_id")
        if fid is not None:
            files_by_folder[int(fid)].append(f)

    files: Dict[str, List[dict]] = {"Syllabus": [], "Assignments": []}

    for folder in file_folders:
        name = (folder.get("name") or "").lower()
        folder_id = int(folder.get("id"))
        folder_files = files_by_folder.get(folder_id, [])

        if "syllabus" in name:
            files["Syllabus"].extend(folder_files)
        elif "test_assignments" in name or "assign" in name:
            files["Assignments"].extend(folder_files)

    logger.info(
        "Collected: Syllabus files=%d, Assignments files=%d",
        len(files["Syllabus"]),
        len(files["Assignments"]),
    )
    return files, course_name


def add_page_to_canvas(
    html_content: str,
    page_title: str,
    destination_course_id: str,
    headers: dict,
    api_base_url: str,
) -> dict:
    """Upload HTML content as a Canvas wiki page."""
    page_data = {
        "wiki_page": {
            "title": page_title,
            "body": html_content,
        }
    }
    response = requests.post(
        url=f"{api_base_url}courses/{destination_course_id}/pages",
        json=page_data,
        headers=headers,
    )
    response.raise_for_status()
    logger.info("Successfully uploaded page: %s", page_title)
    return response.json()


def publish_module(course_id: str, module_id: int, headers: dict, api_base_url: str) -> dict:
    """Publish a Canvas module."""
    module_data = {"module": {"published": "true"}}
    response = requests.put(
        f"{api_base_url}courses/{course_id}/modules/{module_id}",
        headers=headers,
        json=module_data,
    )
    response.raise_for_status()
    logger.info("Module Published Status: %s", response.status_code)
    return response.json()


def upload_module_to_canvas(
    course_id: str, module_name: str, headers: dict, api_base_url: str
) -> dict:
    """Create or get existing module by name."""
    endpoint = f"courses/{course_id}/modules"
    all_mods = get_paginated_list(endpoint, headers, api_base_url)

    for module in all_mods:
        if module.get("name") == module_name:
            logger.info("Module %s already exists with id %s", module_name, module.get("id"))
            return module

    logger.info("Uploading '%s' module to Canvas...", module_name)
    module_data = {"module": {"name": module_name, "position": 1}}
    response = requests.post(
        f"{api_base_url}courses/{course_id}/modules",
        headers=headers,
        json=module_data,
    )
    response.raise_for_status()
    logger.info("Module created: %s", response.status_code)
    return response.json()


def add_single_module_item(
    course_id: str, module_id: int, page: dict, headers: dict, api_base_url: str
) -> None:
    """Add a page as an item to a module."""
    module_item_data = {
        "module_item": {
            "title": page.get("title"),
            "type": "Page",
            "page_url": page.get("url"),
        }
    }
    response = requests.post(
        f"{api_base_url}courses/{course_id}/modules/{module_id}/items",
        headers=headers,
        json=module_item_data,
    )
    response.raise_for_status()
    logger.info("Added module item: %s", response.status_code)


def run_formatting_pipeline(
    *,
    canvas_access_token: str,
    source_course_id: str,
    destination_course_id: Optional[str] = None,
    semester: str = "fall",
    year: str = "2023",
    canvas_domain: str = "canvas.asu.edu",
) -> dict:
    """
    Run the full formatting pipeline: fetch data, build HTML, upload pages, create module.

    Returns dict with created page info and module info.
    """
    if not canvas_access_token or not str(canvas_access_token).strip():
        raise ValueError("Canvas access token is required.")

    dest_id = destination_course_id or source_course_id
    api_base_url = _get_api_base_url(canvas_domain)
    canvas_base_url = _get_canvas_base_url(canvas_domain)
    headers = {"Authorization": f"Bearer {canvas_access_token}"}

    # 1) Find folder structure for the term
    file_folders = find_file_folder(source_course_id, semester, year, headers, api_base_url)
    if not file_folders:
        raise RuntimeError(
            f"No folders found for ({year} {semester.capitalize()}). "
            "Ensure course has folder structure with that term in the path."
        )

    # 2) Get grouped files
    files_by_group, course_name = get_files(
        source_course_id, semester, year, file_folders, headers, api_base_url
    )

    # 3) Fetch all files for writer's matching by folder_id
    all_files = fetch_all_course_files(canvas_domain, source_course_id, headers)

    # 4) Build the final "files" dict the writer expects
    syllabus_list = [
        f
        for f in all_files
        if (f.get("display_name") or f.get("filename") or "").lower()
        in ["syllabus_body.pdf", "syllabus.pdf"]
    ]

    files = dict(files_by_group)
    files["ALL_FILES"] = all_files
    files["Syllabus"] = syllabus_list or files.get("Syllabus", [])

    # 5) Build HTML (in-memory)
    html_writer = WriteAbetHtml(
        canvas_base_url=canvas_base_url,
        course_id=source_course_id,
    )
    html_writer.set_up_course_page(file_folders, files, semester, year)
    course_html = html_writer.get_course_html()

    # 6) Create module
    module_name = f"Courses - Course Folders and Student Work Samples ({semester.capitalize()} {year})"
    module = upload_module_to_canvas(dest_id, module_name, headers, api_base_url)

    # 7) Upload page and add to module
    page = add_page_to_canvas(course_html, course_name, dest_id, headers, api_base_url)
    add_single_module_item(dest_id, module["id"], page, headers, api_base_url)

    # 8) Publish the module
    publish_module(dest_id, module["id"], headers, api_base_url)

    return {
        "course_page": page,
        "module": module,
        "course_name": course_name,
    }


def generate_course_html(
    *,
    canvas_access_token: str,
    source_course_id: str,
    semester: str = "fall",
    year: str = "2023",
    canvas_domain: str = "canvas.asu.edu",
) -> dict:
    """
    Generate course page HTML and ABET HTML without uploading to Canvas.
    Returns dict with course_html and abet_html.
    """
    if not canvas_access_token or not str(canvas_access_token).strip():
        raise ValueError("Canvas access token is required.")

    api_base_url = _get_api_base_url(canvas_domain)
    canvas_base_url = _get_canvas_base_url(canvas_domain)
    headers = {"Authorization": f"Bearer {canvas_access_token}"}

    file_folders = find_file_folder(source_course_id, semester, year, headers, api_base_url)
    if not file_folders:
        raise RuntimeError(
            f"No folders found for ({year} {semester.capitalize()}). "
            "Ensure course has folder structure with that term in the path."
        )

    files_by_group, _ = get_files(
        source_course_id, semester, year, file_folders, headers, api_base_url
    )
    all_files = fetch_all_course_files(canvas_domain, source_course_id, headers)

    syllabus_list = [
        f
        for f in all_files
        if (f.get("display_name") or f.get("filename") or "").lower()
        in ["syllabus_body.pdf", "syllabus.pdf"]
    ]

    files = dict(files_by_group)
    files["ALL_FILES"] = all_files
    files["Syllabus"] = syllabus_list or files.get("Syllabus", [])

    html_writer = WriteAbetHtml(
        canvas_base_url=canvas_base_url,
        course_id=source_course_id,
    )
    html_writer.set_up_course_page(file_folders, files, semester, year)
    html_writer.set_up_abet_page()

    return {
        "course_html": html_writer.get_course_html(),
        "abet_html": html_writer.get_abet_html(),
    }


# CLI entry point (uses env when run directly)
if __name__ == "__main__":
    from dotenv import load_dotenv

    load_dotenv()

    canvas_token = os.getenv("CANVAS_TOKEN") or os.getenv("canvas_access_token")
    source_id = os.getenv("SOURCE_COURSE_ID", "240102")
    dest_id = os.getenv("DESTINATION_COURSE_ID") or source_id
    canvas_domain = os.getenv("CANVAS_DOMAIN", "canvas.asu.edu")

    if not canvas_token:
        raise RuntimeError("Missing Canvas API token. Set CANVAS_TOKEN in your environment or .env file.")

    logging.basicConfig(level=logging.INFO, format="%(levelname)s - %(message)s")

    result = run_formatting_pipeline(
        canvas_access_token=canvas_token,
        source_course_id=source_id,
        destination_course_id=dest_id,
        semester="fall",
        year="2023",
        canvas_domain=canvas_domain,
    )
    print("Process finished.", result)
