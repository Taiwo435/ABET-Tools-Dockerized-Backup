from report.contracts.appendix_a import build_context


def build_from_contract(payload):
    """Build Appendix A without depending on application entities."""
    return build_context(payload)


def build(questionnaire):
    return build_from_contract(questionnaire.appendix_a_contract)
