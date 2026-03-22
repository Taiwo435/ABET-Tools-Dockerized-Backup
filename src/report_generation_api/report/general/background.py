from report.data.background_data import get_data

"""
This is just for example
"""

SECTION_NAME = "Background Information"


def build(questionnaire):
    """
    Build the background section of the report.
    """
    doc = questionnaire.document
    data = get_data(questionnaire)

    doc.add_heading(SECTION_NAME, level=1)

    doc.add_paragraph(f"Institution: {data['institution_name']}")
    doc.add_paragraph(f"College: {data['college_name']}")
    doc.add_paragraph(f"Department: {data['department']}")
    doc.add_paragraph(f"Degree Type: {data['degree_type']}")
    doc.add_paragraph(f"Report Year: {data['year']}")

    doc.add_paragraph(
        "This section provides the general institutional and program background "
        "for the remainder of the report."
    )
