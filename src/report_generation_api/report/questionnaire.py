from docxtpl import DocxTemplate


class Questionnaire:
    def __init__(
        self,
        template_path,
        db,
        year,
        department,
        degree_type,
        appendix_a_contract=None,
    ):
        # Load the Word template
        self.document = DocxTemplate(template_path)

        # Shared database connection for all sections
        self.db = db

        # Report metadata
        self.year = year
        self.department = department
        self.degree_type = degree_type
        self.appendix_a_contract = appendix_a_contract

        # Optional cache for reused query results
        self.cache = {}
        
    def get_cached(self, key):
        return self.cache.get(key)

    def set_cached(self, key, value):
        self.cache[key] = value

    def render(self, context):
        self.document.render(context)

    def save(self, output_path):
        self.document.save(output_path)
