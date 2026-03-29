from report.data.sub_attest_to_comp_data import get_data

def build(questionnaire):

    doc = questionnaire.document

    # Fetch data for this section
    data = get_data(questionnaire)

    # Add data to the context for rendering
    context = {
        "attest": data
    }

    return context

