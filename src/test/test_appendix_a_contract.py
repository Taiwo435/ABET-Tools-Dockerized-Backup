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
        in {"Required", "Optional", "Derived"}
        for field_schema in course_schema["properties"].values()
    )
    assert course_schema["properties"]["delivery_type"]["x-field-status"] == "Required"
    assert "unspecified" not in course_schema["properties"]["delivery_type"]["enum"]


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


def test_duplicate_course_selection_is_rejected_after_contracts_are_combined():
    payload = load_fixture()
    payload["courses"].append(json.loads(json.dumps(payload["courses"][0])))

    with pytest.raises(AppendixAContractError) as error:
        build_context(payload)

    assert "course_code duplicates CSE 423" in str(error.value)


def test_delivery_type_must_come_from_the_selected_lifecycle_target():
    payload = load_fixture()
    payload["courses"][0]["delivery_type"] = "unspecified"

    with pytest.raises(AppendixAContractError) as error:
        build_context(payload)

    assert "delivery_type must be one of: hybrid, in_person, online" in str(error.value)


class DatabaseMustNotBeUsed:
    def cursor(self):
        raise AssertionError("Appendix A must not query syllabus storage")


class FakeQuestionnaire:
    def __init__(self, contract):
        self.db = DatabaseMustNotBeUsed()
        self.appendix_a_contract = contract


def test_appendix_builder_accepts_contract_payload_without_querying_database():
    questionnaire = FakeQuestionnaire(load_fixture())

    context = appendix_a.build(questionnaire)

    assert len(context["course_syllabi"]) == 3


def test_appendix_builder_rejects_missing_contract_instead_of_querying_database():
    with pytest.raises(AppendixAContractError) as error:
        appendix_a.build(FakeQuestionnaire(None))

    assert "root must be an object" in str(error.value)
