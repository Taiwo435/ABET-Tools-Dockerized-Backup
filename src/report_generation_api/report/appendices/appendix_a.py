from report.contracts.appendix_a import (
    SCHEMA_VERSION,
    build_context,
    empty_context,
    normalize_database_row,
)
from report.data import appendix_a_data


def build_from_contract(payload):
    """Build Appendix A without depending on application entities."""
    return build_context(payload)


def build(questionnaire):
    contract_override = getattr(questionnaire, "appendix_a_contract", None)
    if contract_override is not None:
        return build_from_contract(contract_override)

    rows = appendix_a_data.get_data(questionnaire)
    if not rows:
        return empty_context()

    return build_from_contract(
        {
            "schema_version": SCHEMA_VERSION,
            "courses": [normalize_database_row(row) for row in rows],
        }
    )
