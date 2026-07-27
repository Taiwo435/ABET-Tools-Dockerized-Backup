import json
import sys
from pathlib import Path

import pytest

PROJECT_ROOT = Path(__file__).resolve().parents[2]
REPORT_API_ROOT = PROJECT_ROOT / "src" / "report_generation_api"
sys.path.insert(0, str(REPORT_API_ROOT))

from report.appendices import appendix_a
from report.contracts.appendix_a import (
    AppendixAContractError,
    build_context,
    normalize_database_row,
)

FIXTURE_PATH = Path(__file__).parent / "fixtures" / "appendix_a_courses_v1.json"
SCHEMA_PATH = REPORT_API_ROOT / "report" / "contracts" / "appendix_a_v1.schema.json"


def load_fixture():
    return json.loads(FIXTURE_PATH.read_text(encoding="utf-8"))


def test_schema_is_versioned_and_classifies_every_course_field():
    schema = json.loads(SCHEMA_PATH.read_text(encoding="utf-8"))

    assert schema["properties"]["schema_version"]["const"] == "1.0"
    course_schema = schema["properties"]["courses"]["items"]
    assert set(course_schema["properties"]) == {
        "course_code",
        "course_name",
        "credits",
        "contact_hours",
        "credit_category",
        "delivery_type",
        "instructors",
        "textbooks",
        "catalog_description",
        "prerequisites",
        "course_type",
        "specific_goals",
        "student_outcomes",
        "topics_covered",
    }
    assert all(
        field_schema.get("x-field-status")
        in {"Required", "Optional", "Derived", "Missing from workflow"}
        for field_schema in course_schema["properties"].values()
    )


def test_representative_fixture_builds_multiple_courses_and_delivery_types():
    context = build_context(load_fixture())

    assert context["schema_version"] == "1.0"
    assert len(context["course_syllabi"]) == 3
    assert set(context["courses_by_delivery"]) == {"in_person", "online", "hybrid"}
    assert context["course_syllabi"][0]["course_number_and_name"] == (
        "CSE 423 Systems Capstone Project I"
    )
    assert "Requirements" in context["course_syllabi"][0]["topics_text"]


def test_missing_required_field_has_actionable_path():
    payload = load_fixture()
    del payload["courses"][0]["catalog_description"]

    with pytest.raises(AppendixAContractError) as error:
        build_context(payload)

    assert "courses[0].catalog_description is required" in str(error.value)


def test_incorrect_field_type_has_actionable_path():
    payload = load_fixture()
    payload["courses"][1]["credits"] = "three"

    with pytest.raises(AppendixAContractError) as error:
        build_context(payload)

    assert "courses[1].credits must be a positive number" in str(error.value)


def test_database_row_normalization_derives_code_and_decodes_json_columns():
    row = {
        "course_subject": "CSE",
        "course_number": "423",
        "course_name": "Systems Capstone Project I",
        "credits": 3,
        "contact_hours": "Three hours per week",
        "credit_categorization": "Engineering topics",
        "instructor_name": '["Alex Rivera"]',
        "textbook": "[]",
        "catalog_description": "Team-based engineering capstone.",
        "prerequisites": "Senior standing",
        "course_type": "R",
        "specific_goals": '["Apply an iterative process"]',
        "student_outcomes": '["Communicate effectively"]',
        "topics_covered": '["Requirements", "Testing"]',
    }

    course = normalize_database_row(row)

    assert course["course_code"] == "CSE 423"
    assert course["delivery_type"] == "unspecified"
    assert course["instructors"] == ["Alex Rivera"]
    assert course["topics_covered"] == ["Requirements", "Testing"]


class FakeCursor:
    def __init__(self, rows):
        self.rows = rows
        self.sql = None
        self.params = None

    def execute(self, sql, params):
        self.sql = sql
        self.params = params

    def fetchall(self):
        return self.rows


class FakeDatabase:
    def __init__(self, rows):
        self.cursor_instance = FakeCursor(rows)

    def cursor(self):
        return self.cursor_instance


class FakeQuestionnaire:
    def __init__(self, rows):
        self.db = FakeDatabase(rows)
        self.year = 2026
        self.department = "Computer Systems Engineering"
        self.degree_type = "BSE"


def test_appendix_builder_consumes_database_rows_without_symfony_entities():
    row = {
        "course_subject": "CSE",
        "course_number": "423",
        "course_name": "Systems Capstone Project I",
        "credits": 3,
        "contact_hours": "Three hours per week",
        "credit_categorization": "Engineering topics",
        "instructor_name": ["Alex Rivera"],
        "textbook": [],
        "catalog_description": "Team-based engineering capstone.",
        "prerequisites": "Senior standing",
        "course_type": "R",
        "specific_goals": ["Apply an iterative process"],
        "student_outcomes": ["Communicate effectively"],
        "topics_covered": ["Requirements", "Testing"],
    }
    questionnaire = FakeQuestionnaire([row])

    context = appendix_a.build(questionnaire)

    assert context["course_syllabi"][0]["course_code"] == "CSE 423"
    assert "JOIN programs" in questionnaire.db.cursor_instance.sql
    assert questionnaire.db.cursor_instance.params == (
        2026,
        "Computer Systems Engineering",
        "BSE",
    )


def test_appendix_builder_accepts_contract_payload_without_querying_database():
    questionnaire = FakeQuestionnaire([])
    questionnaire.appendix_a_contract = load_fixture()

    context = appendix_a.build(questionnaire)

    assert len(context["course_syllabi"]) == 3
    assert questionnaire.db.cursor_instance.sql is None


def test_appendix_builder_allows_reports_with_no_syllabus_rows_yet():
    context = appendix_a.build(FakeQuestionnaire([]))

    assert context["schema_version"] == "1.0"
    assert context["course_syllabi"] == []
    assert context["courses_by_type"] == {}
    assert context["courses_by_delivery"] == {}
