import logging

from report.data import appendix_b_data

logger = logging.getLogger(__name__)


_RANK_LABELS: dict[str, str] = {
    "P": "Professor",
    "ASC": "Associate Professor",
    "AST": "Assistant Professor",
    "I": "Instructor",
    "A": "Adjunct",
    "O": "Other",
}


def build(questionnaire) -> dict:
    """
    Appendix B: Faculty vitae.

    Returns a context dict intended for the report template. The primary key is:
      - faculty_vitae: list of faculty vitae records

    Each record includes both list and pre-joined text variants for JSON fields
    to make it easier to render in docxtpl templates.
    """
    rows = appendix_b_data.get_data(questionnaire)
    faculty_vitae = []

    for row in rows:
        #display_name = (row.get("display_name") or "").strip()
        first = (row.get("first_name") or "").strip()
        last = (row.get("last_name") or "").strip()
        rank_code = (row.get("faculty_rank") or "").strip()
        rank = _RANK_LABELS.get(rank_code, rank_code or "N/A")

        certifications = appendix_b_data.json_to_list(row.get("certifications"))
        memberships = appendix_b_data.json_to_list(row.get("professional_memberships"))
        honors = appendix_b_data.json_to_list(row.get("honors_and_awards"))
        service = appendix_b_data.json_to_list(row.get("service_activities"))
        pubs = appendix_b_data.json_to_list(row.get("publications_presentations"))
        prof_dev = appendix_b_data.json_to_list(row.get("professional_development"))

        certifications_text = "\n".join(certifications).strip() or "NA"
        memberships_text = "\n".join(memberships).strip() or "NA"
        honors_text = "\n".join(honors).strip() or "NA"
        service_text = "\n".join(service).strip() or "NA"
        pubs_text = "\n".join(pubs).strip() or "NA"
        prof_dev_text = "\n".join(prof_dev).strip() or "NA"

        name = f"{first} {last}".strip()

        faculty_vitae.append(
            {
                "name": name or "N/A",
                "rank": rank,
                "name_line": (
                    f"{name}, {rank}".strip(", ").strip() if name else rank
                )
                or "N/A",
                "education": (row.get("education") or "").strip(),
                "academic_experience": (row.get("academic_experience") or "").strip(),
                "non_academic_experience": (row.get("non_academic_experience") or "").strip(),
                "certifications": certifications,
                "certifications_text": certifications_text,
                "professional_memberships": memberships,
                "professional_memberships_text": memberships_text,
                "honors_and_awards": honors,
                "honors_and_awards_text": honors_text,
                "service_activities": service,
                "service_activities_text": service_text,
                "publications_presentations": pubs,
                "publications_presentations_text": pubs_text,
                "professional_development": prof_dev,
                "professional_development_text": prof_dev_text,
            }
        )

    return {"faculty_vitae": faculty_vitae}
