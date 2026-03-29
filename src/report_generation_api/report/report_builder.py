from report.questionnaire import Questionnaire

from report.general import background
from report.general import sub_attest_to_comp

from report.criteria import accred_policies_procedures_man
from report.criteria import criterion_cont_improv
from report.criteria import criterion_curriculum
from report.criteria import criterion_faculty
from report.criteria import criterion_facilities
from report.criteria import criterion_inst_support
from report.criteria import criterion_program_edu_obj
from report.criteria import criterion_students
from report.criteria import program_criteria
from report.criteria import general_criteria
from report.criteria import criterion_student_outcomes

from report.appendices import appendix_a
from report.appendices import appendix_b
from report.appendices import appendix_c
from report.appendices import appendix_d
from report.appendices import appendix_e


SECTIONS = [
    ("Background Information", "background", background),
    ("General Criteria", "general_criteria", general_criteria),
    ("Criterion 1 - Students", "criterion_students", criterion_students),
    ("Criterion 2 - Program Educational Objectives", "criterion_program_edu_obj", criterion_program_edu_obj),
    ("Criterion 3 - Student Outcomes", "criterion_student_outcomes", criterion_student_outcomes),
    ("Criterion 4 - Continuous Improvement", "criterion_cont_improv", criterion_cont_improv),
    ("Criterion 5 - Curriculum", "criterion_curriculum", criterion_curriculum),
    ("Criterion 6 - Faculty", "criterion_faculty", criterion_faculty),
    ("Criterion 7 - Facilities", "criterion_facilities", criterion_facilities),
    ("Criterion 8 - Institutional Support", "criterion_inst_support", criterion_inst_support),
    ("Program Criteria", "program_criteria", program_criteria),
    ("Accreditation Policies and Procedures Manual", "accred_policies_procedures_man", accred_policies_procedures_man),
    ("Appendix A", "appendix_a", appendix_a),
    ("Appendix B", "appendix_b", appendix_b),
    ("Appendix C", "appendix_c", appendix_c),
    ("Appendix D", "appendix_d", appendix_d),
    ("Appendix E", "appendix_e", appendix_e),
    ("Submission Attesting to Compliance", "sub_attest_to_comp", sub_attest_to_comp),
]


class ReportBuilder:
    def __init__(self, template_path, db, year, department, degree_type):
        self.questionnaire = Questionnaire(
            template_path=template_path,
            db=db,
            year=year,
            department=department,
            degree_type=degree_type
        )

    def build(self, output_path):
        context = {}

        for section_name, context_key, section_module in SECTIONS:
            print(f"Building {section_name}")

            # Each section module has a build function that takes the questionnaire as input and returns the data for that section.
            section_data = section_module.build(self.questionnaire)
            # Add the section data to the context using the specified context key. If section_data is None, use an empty dictionary.
            context[context_key] = section_data or {}
        # After building the context for all sections, render the document and save it to the specified output path.
        self.questionnaire.render(context)
        
        # Save the rendered document to the specified output path and return the path.
        self.questionnaire.save(output_path)
        appendix_c.apply_post_render_formatting(output_path)

        return output_path
