import os
from urllib.parse import unquote


class WriteAbetHtml:
    def __init__(self, source_course_id: str, canvas_base_url: str = "https://canvas.asu.edu/"):
        self.temp_dir = "temp_html_files"
        self.canvas_base_url = canvas_base_url.rstrip("/") + "/"
        self.source_course_id = str(source_course_id)

    def _append(self, filename: str, content: str):
        try:
            local_path = os.path.join(self.temp_dir, filename)
            with open(local_path, "a", encoding="utf-8") as f:
                f.write(content)
        except IOError as e:
            print(f"Error writing to {filename}: {e}")

    def write_to_page(self, content: str):
        self._append("test.html", content)

    def write_to_page_abet(self, content: str):
        self._append("abet.html", content)

    def add_abet_table_row(self, i, abet_outcome):
        student_outcome_cell = f"""
        <tr>
            <td>CSE({i+1})<br>{abet_outcome}</td>
            <td>
                <p>CSE Placeholder Assessment Report and Instrument:</p>
                <ul>
                    <li>CSE Placeholder Assessment Report.pdf</li>
                    <li>CSE Placeholder Homework.pdf</li>
                </ul>
                <p>CSE Placeholder Assessment Report and Instrument:</p>
                <ul>
                    <li>CSE Placeholder Assessment Report.pdf</li>
                    <li>CSE Placeholder Project.pdf</li>
                </ul>
            </td>
            <td>CSE Placeholder:</td>
        </tr>
        """
        self.write_to_page_abet(student_outcome_cell)

    def set_up_abet_page(self):
        content = """
        <h1 class="page-title">CSE-ABET Assessment Instruments and Samples</h1>
        <h3>Assessment Instruments and Student Samples</h3>
        <p>CSE-ABET Assessment Plan and Coverage.pdf</p>

        <table style="width: 100%;" border="1">
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
            "an ability to acquire and apply new knowledge as needed, using appropriate learning strategies."
        ]

        for i in range(7):
            self.add_abet_table_row(i, abet_outcomes[i])

        self.write_to_page_abet("</tbody></table>")

    def add_graded_work_course_page(self, file_folders, files, lab_projects, exams):
        table_set_up = """<h3>Graded Student Work</h3>
            <p>Lab Projects</p>
            <table style="width: 100%;" border="1">
                <thead>
                    <tr>
                        <th>Assessment</th>
                        <th>High</th>
                        <th>Mid</th>
                        <th>Low</th>
                    </tr>
                </thead>
            """
        self.write_to_page(table_set_up)

        for lab in lab_projects:
            lab_high = ""
            lab_low = ""
            lab_mid = ""
            lab_link = f"{self.canvas_base_url}courses/{self.source_course_id}/files/{lab.get('id')}"

            for folder in file_folders:
                if lab.get("folder_id") == folder.get("id"):
                    folders_files = files.get(folder.get("name"), [])
                    for file in folders_files:
                        filename = file.get("filename", "").lower()
                        link = f"{self.canvas_base_url}courses/{self.source_course_id}/files/{file.get('id')}"
                        if "high.pdf" in filename or "high.txt" in filename:
                            lab_high = f'<a href="{link}">{filename}</a>'
                        if "low.pdf" in filename or "low.txt" in filename:
                            lab_low = f'<a href="{link}">{filename}</a>'
                        if "avg.pdf" in filename or "avg.txt" in filename:
                            lab_mid = f'<a href="{link}">{filename}</a>'

            row = f"""
            <tbody>
                <tr>
                    <td><a href="{lab_link}">{unquote(lab.get('filename', 'Unknown File'))}</a></td>
                    <td>{lab_high}</td>
                    <td>{lab_mid}</td>
                    <td>{lab_low}</td>
                </tr>
            </tbody>
            """
            self.write_to_page(row)

        self.write_to_page("</table>")

        exam_set_up = """
        <p>Exams</p>
        <table style="width: 100%;" border="1">
            <thead>
                <tr>
                    <th>Assessment</th>
                    <th>High</th>
                    <th>Mid</th>
                    <th>Low</th>
                </tr>
            </thead>
        """
        self.write_to_page(exam_set_up)

        for exam in exams:
            exam_high = ""
            exam_low = ""
            exam_mid = ""
            exam_link = f"{self.canvas_base_url}courses/{self.source_course_id}/files/{exam.get('id')}"

            for folder in file_folders:
                if exam.get("folder_id") == folder.get("id"):
                    folders_files = files.get(folder.get("name"), [])
                    for file in folders_files:
                        filename = file.get("filename", "").lower()
                        link = f"{self.canvas_base_url}courses/{self.source_course_id}/files/{file.get('id')}"
                        if "high.pdf" in filename or "high.txt" in filename:
                            exam_high = f'<a href="{link}">{filename}</a>'
                        if "low.pdf" in filename or "low.txt" in filename:
                            exam_low = f'<a href="{link}">{filename}</a>'
                        if "avg.pdf" in filename or "avg.txt" in filename:
                            exam_mid = f'<a href="{link}">{filename}</a>'

            row = f"""
            <tbody>
                <tr>
                    <td><a href="{exam_link}">{unquote(exam.get('filename', 'Unknown Exam'))}</a></td>
                    <td>{exam_high}</td>
                    <td>{exam_mid}</td>
                    <td>{exam_low}</td>
                </tr>
            </tbody>
            """
            self.write_to_page(row)

        self.write_to_page("</table>")

    def get_lab_projects(self, file_folders, files):
        lab_projects = []
        for folder in file_folders:
            full_name = (folder.get("full_name") or "").lower()
            short_name = (folder.get("name") or "").lower()

            if "assignments" in full_name and "assignment" in short_name:
                folders_files = files.get(folder.get("name"), [])
                for file in folders_files:
                    filename = (file.get("filename") or "").lower()
                    if "assignment" in filename and all(x not in filename for x in ("avg", "high", "low")):
                        lab_projects.append(file)
        return lab_projects

    def get_exams(self, file_folders, files):
        exams = []
        for folder in file_folders:
            full_name = (folder.get("full_name") or "").lower()
            short_name = (folder.get("name") or "").lower()

            if "assignments" in full_name and ("quiz" in short_name or "exam" in short_name or "test" in short_name):
                folders_files = files.get(folder.get("name"), [])
                for file in folders_files:
                    filename = file.get("filename", "")
                    if "description" in filename.lower():
                        exams.append(file)
        return exams

    def set_up_course_page(self, file_folders, files, course_code, course_name, semester, year):
        syllabus_id = None
        for file in files.get("Syllabus", []):
            if (file.get("filename") or "").lower() == "syllabus_body.pdf":
                syllabus_id = file.get("id")
                break

        if syllabus_id:
            syllabus_link = f"{self.canvas_base_url}courses/{self.source_course_id}/files/{syllabus_id}"
            syllabus_html = f'<li><a href="{syllabus_link}">{course_code}_syllabus_and_schedule.pdf</a></li><br>'
        else:
            syllabus_html = "<li>Syllabus file not found.</li><br>"

        content = f"""
        <h3>Syllabus and Course Schedule</h3>
        <ul>
            {syllabus_html}
        </ul>
        <h3>Lab Projects, Quizzes, and Exams</h3>
        <ul>
            <li>Lab Projects<br>
                <ul>
        """
        self.write_to_page(content)

        lab_projects = self.get_lab_projects(file_folders, files)
        for file in lab_projects:
            link = f"{self.canvas_base_url}courses/{self.source_course_id}/files/{file.get('id')}"
            self.write_to_page(f'<li><a href="{link}">{unquote(file.get("filename", "Unnamed Lab"))}</a></li>')

        self.write_to_page("""
                </ul>
            </li>
            <li>Exams<br>
                <ul>
        """)

        exams = self.get_exams(file_folders, files)
        for file in exams:
            link = f"{self.canvas_base_url}courses/{self.source_course_id}/files/{file.get('id')}"
            self.write_to_page(f'<li><a href="{link}">{unquote(file.get("filename", "Unnamed Exam"))}</a></li>')

        self.write_to_page("""
                </ul>
            </li>
        </ul>
        """)

        self.add_graded_work_course_page(file_folders, files, lab_projects, exams)