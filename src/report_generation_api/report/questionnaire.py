from docx import Document


class Questionnaire:
    def __init__(self, template_path, db, year, department, degree_type):
        # Load the Word template
        self.document = Document(template_path)

        # Shared database connection for all sections
        self.db = db

        # Report metadata
        self.year = year
        self.department = department
        self.degree_type = degree_type

        # Optional cache for reused query results
        self.cache = {}

        
    def get_cached(self, key):
        return self.cache.get(key)

    def set_cached(self, key, value):
        self.cache[key] = value

    def save(self, output_path):
        self.document.save(output_path)