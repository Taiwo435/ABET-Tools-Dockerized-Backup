from .questionnaire import Questionnaire

from .general import background
from .general import sub_attest_to_comp

from .criteria import accred_policies_procedures_man
from .criteria import criterion_cont_improv
from .criteria import criterion_curriculum
from .criteria import criterion_faculty
from .criteria import criterion_facilities
from .criteria import criterion_inst_support
from .criteria import criterion_program_edu_obj
from .criteria import criterion_students
from .criteria import program_criteria

from .appendices import appendix_a
from .appendices import appendix_b
from .appendices import appendix_c
from .appendices import appendix_d
from .appendices import appendix_e


SECTIONS = [
    ("Background", background),
    ("Criterion 1 - Students", criterion_students),
    ("Criterion 2 - Program Educational Objectives", criterion_program_edu_obj),
    ("Criterion 3 - Continuous Improvement", criterion_cont_improv),
    ("Criterion 4 - Curriculum", criterion_curriculum),
    ("Criterion 5 - Faculty", criterion_faculty),
    ("Criterion 6 - Facilities", criterion_facilities),
    ("Criterion 7 - Institutional Support", criterion_inst_support),
    ("Program Criteria", program_criteria),
    ("Accreditation Policies and Procedures", accred_policies_procedures_man),
    ("Submission Attestation to Compliance", sub_attest_to_comp),
    ("Appendix A", appendix_a),
    ("Appendix B", appendix_b),
    ("Appendix C", appendix_c),
    ("Appendix D", appendix_d),
    ("Appendix E", appendix_e),
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
        for section_name, section_module in SECTIONS:
            print(f"Building {section_name}")
            section_module.build(self.questionnaire)

        self.questionnaire.render()
        self.questionnaire.save(output_path)
        return output_path