def get_data(questionnaire):
    """
    Fetch data for the sub_attest_to_comp section using the shared DB connection.
    """

    #Get the cursor from the shared database connection
    cursor = questionnaire.db.cursor()

    #Will add a database query here, currently will just pass a string to show how it works.
    query_result = """
    Only the Dean or the Dean’s Delegate can electronically submit the Self-Study Report.

    ABET considers the on-line submission as equivalent to that of an electronic signature of compliance attesting to the fact that the program has conducted an honest assessment of compliance and has provided a complete and accurate disclosure of timely information regarding compliance with ABET’s Criteria for Engineering Programs to include the General Criteria and any applicable Program Criteria, and the ABET Accreditation Policy and Procedure Manual.
    """

    return query_result

