# Creates html pages to upload for courses & ABET reports

import io
from urllib.parse import unquote

import requests


class WriteAbetHtml:
    """Builds HTML content for course pages and ABET reports. Uses in-memory buffers."""

    def __init__(
        self, canvas_base_url: str = "https://canvas.asu.edu/", course_id: str = ""
    ):
        self.canvas_base_url = canvas_base_url.rstrip("/") + "/"
        self.course_id = str(course_id)
        self._course_buffer = io.StringIO()
        self._abet_buffer = io.StringIO()

    def write_to_page(self, content: str) -> None:
        self._course_buffer.write(content)

    def write_to_page_abet(self, content: str) -> None:
        self._abet_buffer.write(content)

    def get_course_html(self) -> str:
        """Return the accumulated course page HTML."""
        return self._course_buffer.getvalue()

    def get_abet_html(self) -> str:
        """Return the accumulated ABET page HTML."""
        return self._abet_buffer.getvalue()

    def reset(self) -> None:
        """Clear buffers for reuse."""
        self._course_buffer = io.StringIO()
        self._abet_buffer = io.StringIO()

    def add_abet_table_row(self, i, abet_outcome):
        student_outcome_cell = f"""
        <tr>
            <td>CSE({i})<br>{abet_outcome}</td>
            <td>"""
        self.write_to_page_abet(student_outcome_cell)

    def set_up_assess_cell(self, course_name, assessment_report, assessment_instr):
        assessment_name = assessment_report.get("display_name")
        assessment_id = assessment_report.get("id")
        assessment_instr_name = assessment_instr.get("display_name")
        assessment_instr_id = assessment_instr.get("id")
        assessment_link = "Invalid"
        assessment_instr_link = "Invalid"

        if assessment_id is not None:
            assessment_link = f"{self.canvas_base_url}courses/{self.course_id}/files/{assessment_id}"
        if assessment_instr_id is not None:
            assessment_instr_link = f"{self.canvas_base_url}courses/{self.course_id}/files/{assessment_instr_id}"

        content = f"""
                <p><b>{course_name} Assessment Report and Instrument:</b></p>
                        <ul>
                            <li><a href="{assessment_link}">{assessment_name}</a></li>
                            <li><a href="{assessment_instr_link}">{assessment_instr_name}</a></li>
                        </ul>
        """
        return content

    def set_up_sample_cell(self, name, low_samples, avg_samples, high_samples):
        student_sample_cell = f"""
                <b>{name}:</b><ul>"""

        for high_sample in high_samples:
            if high_sample is not None:
                high_name = high_sample.get("display_name")
                high_id = high_sample.get("id")
                if high_id is not None:
                    high_link = f"{self.canvas_base_url}courses/{self.course_id}/files/{high_id}"
                    student_sample_cell += f"""<li><a href="{high_link}">{high_name}</a></li>"""

        for avg_sample in avg_samples:
            if avg_sample is not None:
                avg_name = avg_sample.get("display_name")
                avg_id = avg_sample.get("id")
                if avg_id is not None:
                    avg_link = f"{self.canvas_base_url}courses/{self.course_id}/files/{avg_id}"
                    student_sample_cell += f"""<li><a href="{avg_link}">{avg_name}</a></li>"""

        for low_sample in low_samples:
            if low_sample is not None:
                low_name = low_sample.get("display_name")
                low_id = low_sample.get("id")
                if low_id is not None:
                    low_link = f"{self.canvas_base_url}courses/{self.course_id}/files/{low_id}"
                    student_sample_cell += f"""<li><a href="{low_link}">{low_name}</a></li>"""

        student_sample_cell += """</ul>"""
        return student_sample_cell

    def set_up_abet(self, file_folders, files, ABET_data, course_names):
        content = """
        <h3>Assessment Instruments and Student Samples</h3>
        <p>CSE-ABET Assessment Plan and Coverage.pdf</p>

        <table style="width: 100%;" border="1">
            <colgroup>
                <col style="width: 20%;">
                <col style="width: 35%;">
                <col>
            </colgroup>
            <thead>
                <tr>
                    <th>Student Outcome</th>
                    <th>Assessment Instruments</th>
                    <th>Student Work Samples</th>
                </tr>
            </thead>
            <tbody>
        """
        self.write_to_page_abet(content)

        abet_outcomes = [
            "an ability to identify, formulate, and solve complex engineering problems by applying principles of engineering, science, and mathematics.",
            "an ability to apply engineering design to produce solutions that meet specified needs with consideration of public health, safety, and welfare, as well as global, cultural, social, environmental, and economic factors.",
            "an ability to communicate effectively with a range of audiences.",
            "an ability to recognize ethical and professional responsibilities in engineering situations and make informed judgments, which must consider the impact of engineering solutions in global, economic, environmental, and societal contexts.",
            "an ability to function effectively on a team whose members together provide leadership, create a collaborative and inclusive environment, establish goals, plan tasks, and meet objectives.",
            "an ability to develop and conduct appropriate experimentation, analyze and interpret data, and use engineering judgment to draw conclusions.",
            "an ability to acquire and apply new knowledge as needed, using appropriate learning strategies.",
        ]

        assess_report = assess_instr = None
        assess_cell = ""
        samples_cell = ""
        low_samples, avg_samples, high_samples = [], [], []

        for i in range(1, 8):
            if ABET_data[str(i)]:
                self.add_abet_table_row(i, abet_outcomes[i - 1])
                for name in course_names:
                    if ABET_data[str(i)][name] is not None:
                        for file in ABET_data[str(i)][name]:
                            file_name = file.get("display_name")
                            if "ABET" in file_name:
                                assess_report = file
                            if "description" in file_name:
                                assess_instr = file
                            if "_low" in file_name:
                                low_samples.append(file)
                            if "_avg" in file_name:
                                avg_samples.append(file)
                            if "_high" in file_name:
                                high_samples.append(file)

                        if assess_instr is not None and assess_report is not None:
                            assess_cell += self.set_up_assess_cell(name, assess_report, assess_instr)
                            samples_cell += self.set_up_sample_cell(name, low_samples, avg_samples, high_samples)
                            assess_report = assess_instr = None
                            low_samples, avg_samples, high_samples = [], [], []

                self.write_to_page_abet(assess_cell)
                self.write_to_page_abet("</td><td>")
                self.write_to_page_abet(samples_cell)
                self.write_to_page_abet("</td></tr>")
                assess_cell = samples_cell = ""

        self.write_to_page_abet("</tbody></table>")

    def get_assignment_groups(self, file_folders, files):
        assignment_groups = []  #
        assignment_names = {}
        for folder in file_folders:
            f_name = folder.get("full_name")
            if f"Test_Assignments/" in f_name:
                split_name = f_name.split('Test_Assignments/', 1)[1]
                # add groups
                group_name = split_name.split('/', 1)[0]
                # print("GROUP", split_name)
                if group_name not in assignment_groups:
                    assignment_groups.append(group_name)
                # add assignments
                try:
                    assign_name = split_name.split('/', 1)[1]
                    assignment_names[folder.get("id")] = assign_name
                # print("ASSIGNMENTS", assign_name)
                except(IndexError):
                    continue

        return assignment_groups, assignment_names

    def get_assignments(self, group_name, file_folders, files):
        # Flatten ALL files from the dict into one list uniquely
        all_files = files.get("ALL_FILES", [])
        if not all_files:
            seen_ids = set()
            for v in files.values():
                if isinstance(v, list):
                    for f in v:
                        if f.get("id") not in seen_ids:
                            seen_ids.add(f.get("id"))
                            all_files.append(f)

        assignments = []

        for folder in file_folders:
            full_name = folder.get("full_name", "")

            # Only folders under Test_Assignments/<group_name>/...
            if f"Test_Assignments/{group_name}" in full_name:
                folder_id = folder.get("id")

                # Collect every file whose folder_id matches this folder
                for f in all_files:
                    if f.get("folder_id") == folder_id:
                        filename = (f.get("filename") or "").lower()

                        assignments.append(f)

        print(f"GROUP '{group_name}' → found {len(assignments)} files")
        return assignments

    def _is_hml_file(self, filename_lower: str) -> bool:
        return (
                "high" in filename_lower
                or "mid" in filename_lower
                or "avg" in filename_lower
                or "low" in filename_lower
        )

    def _hml_label(self, filename_lower: str) -> str:
        if "high" in filename_lower:
            return "High"
        if "avg" in filename_lower or "mid" in filename_lower:
            return "Mid"
        if "low" in filename_lower:
            return "Low"
        return ""
    def _is_solution_file(self, filename_lower: str) -> bool:
        return (
            "solution" in filename_lower
            or "_sol" in filename_lower
            or " sol " in filename_lower
        )

    def _is_marking_guide_file(self, filename_lower: str) -> bool:
        return (
            "marking" in filename_lower
            or "rubric" in filename_lower
            or "guide" in filename_lower
        )

    def set_up_course_page(self, file_folders, files, semester, year):
        # ----------------------------
        # 1) Syllabus
        # ----------------------------
        syllabus_link = "Invalid"
        try:
            syllabus_id = None
            for f in files.get("Syllabus", []):
                if (f.get("filename") or "").lower() in ["syllabus_body.pdf", "syllabus.pdf"]:
                    syllabus_id = f.get("id")
                    break

            if syllabus_id is not None:
                syllabus_link = f"{self.canvas_base_url}courses/{self.course_id}/files/{syllabus_id}"
        except Exception:
            syllabus_link = "Invalid"

        content = "<h3>Syllabus and Course Schedule</h3>\n"
        if syllabus_link != "Invalid":
            content += f"""
        <ul>
         <li><a href="{syllabus_link}">Syllabus.pdf</a></li>
        </ul>
        """
        else:
            content += "<ul><li>Syllabus is missing.</li></ul>\n"

        # ----------------------------
        # 2) Main Section Header
        # ----------------------------
        has_homework = "Assignments" in file_folders
        has_projects = "Projects" in file_folders
        has_quizzes = "Quizzes" in file_folders or "Lecture Quizzes" in file_folders
        has_exams = "Exams" in file_folders
        labels = []

        if has_homework:
            labels.append("Homework Assignments")
        if has_projects:
            labels.append("Projects")
        if has_quizzes:
            labels.append("Quizzes")
        if has_exams:
            labels.append("Exams")
        if len(labels) > 1:
            header_text = ", ".join(labels[:-1]) + ", and " + labels[-1]
        else:
            header_text = labels[0] if labels else ""

        content += f"<h3>{header_text}</h3>\n"
        self.write_to_page(content)

        # ----------------------------
        # 3) Build groups and assignment names dict from folders
        # ----------------------------
        assignment_groups, assignment_names = self.get_assignment_groups(file_folders, files)

        # ----------------------------
        # 4) Bulleted section format
        # ----------------------------
        for group in assignment_groups:
            group_files = self.get_assignments(group, file_folders, files)

            self.write_to_page(f"<ul><li><b>{group}</b><br><ul>")

            for f in group_files:
                fname = f.get("filename") or ""
                fl = fname.lower()

                # Add each assignment description (currently located in description.html files) and update name to match parent folder
                if "description" in fl and fl.endswith(".html"):
                    folder_id = f.get('folder_id')
                    assignment_name = assignment_names[folder_id]
                    link = f"{self.canvas_base_url}courses/{self.course_id}/files/{f.get('id')}"
                    self.write_to_page(f'<li><a href="{link}">{assignment_name}</a></li>')

            self.write_to_page("</ul></li></ul>")

        # ----------------------------
        # 5) Graded Work Section Header
        # ----------------------------
        content = "<h3>Graded Student Work</h3>\n"
        self.write_to_page(content)

        # ----------------------------
        # 6) Render each group in table format
        # ----------------------------
        for group in assignment_groups:
            group_files = self.get_assignments(group, file_folders, files)

            if not group_files:
                self.write_to_page(f"<ul><li><b>{group}</b> (no files found)</li></ul>")
                continue

            has_hml = any(self._is_hml_file((f.get("filename") or "").lower()) for f in group_files)

            # TABLE format
            if has_hml:
                rows = {}
                for f in group_files:
                    fname = f.get("filename") or ""
                    fl = fname.lower()

                    if "description" in fl and fl.endswith(".html"):
                        continue

                    folder_id = f.get("folder_id")

                    if folder_id not in assignment_names:
                        continue

                    folder_name = assignment_names[folder_id]

                    metadata = {}
                    for meta_file in group_files:
                        meta_name = (meta_file.get("filename") or "").lower()
                        meta_folder_id = meta_file.get("folder_id")
                        if meta_folder_id == folder_id and meta_name.endswith(".json"):
                            meta_link = f"{self.canvas_base_url}courses/{self.course_id}/files/{meta_file.get('id')}/download"
                            try:
                                response = requests.get(meta_link)
                                response.raise_for_status()
                                metadata = response.json()
                            except Exception:
                                metadata = {}
                            break

                    abet_value = str(metadata.get("abet", "")).strip()
                    row_name = abet_value if abet_value else folder_name

                    if row_name not in rows:
                        rows[row_name] = {
                            "High": "",
                            "Mid": "",
                            "Low": "",
                            "Solution": "",
                            "Marking Guide": "",
                            "Quiz Statistics": ""
                        }

                    link = f"{self.canvas_base_url}courses/{self.course_id}/files/{f.get('id')}"
                    file_link = f'<a href="{link}">{unquote(fname)}</a>'

                    if self._is_solution_file(fl):
                        rows[row_name]["Solution"] = file_link
                        continue

                    if self._is_marking_guide_file(fl):
                        rows[row_name]["Marking Guide"] = file_link
                        continue

                    label = self._hml_label(fl)
                    if label:
                        rows[row_name][label] = file_link

                self.write_to_page(f"<h4>{group}</h4>")
                self.write_to_page("""
<table style="width: 100%;" border="1">
  <thead>
    <tr>
      <th>Assessment</th>
      <th>High</th>
      <th>Mid</th>
      <th>Low</th>
      <th>Solution</th>
      <th>Marking Guide</th>
      <th>Quiz Statistics</th>
    </tr>
  </thead>
  <tbody>
""")

                for base_key in sorted(rows.keys()):
                    pretty_name = base_key
                    high = rows[base_key]["High"]
                    mid = rows[base_key]["Mid"]
                    low = rows[base_key]["Low"]
                    solution = rows[base_key]["Solution"]
                    marking_guide = rows[base_key]["Marking Guide"]

                    self.write_to_page(f"""
<tr>
  <td>{pretty_name}</td>
  <td>{high}</td>
  <td>{mid}</td>
  <td>{low}</td>
  <td>{solution}</td>
  <td>{marking_guide}</td>
  <td></td>
</tr>

""")

                self.write_to_page("</tbody></table>")
