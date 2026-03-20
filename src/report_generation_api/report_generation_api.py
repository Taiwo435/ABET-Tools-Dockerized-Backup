from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse
from pydantic import BaseModel
from typing import Optional, Dict, Any

import getProfessorWorkload
import getElectiveCourses
import getdatabaseConnection
import os
import tempfile
from docx import Document as DocxDocument

from report.report_builder import ReportBuilder as ReportBuilderClass
from pathlib import Path

app = FastAPI()



BASE_DIR = Path(__file__).resolve().parent

TEMPLATE_PATH = BASE_DIR / "template" / "QuestTemplate.docx"

#This is just to make sure the shape of the json is correct. The get_professor_workload function expects a string. 
class WorkloadRequest(BaseModel):
    asurite_id: str

class ElectiveCoursesRequest(BaseModel):
    year: Optional[int] = 2026  # Default to 2026 if not provided

class ReportBuilder(BaseModel):
    year: Optional[int] = 2026  # Default to 2026 if not provided
    department: str
    degree_type: str



# Endpoint to get professor workload information for all semesters taught by the professor with the given ASURITE ID.
@app.post("/professor-workload")
def professor_workload(request: WorkloadRequest):
    result = getProfessorWorkload.get_professor_workload(request.asurite_id)

    return {"data": result or {}}

# Endpoint to get merged CSE technical elective courses for a given year.
@app.post("/cse-elective-courses")
def elective_courses(request: ElectiveCoursesRequest):
    try:
        result = getElectiveCourses.build_merged_cse_courses_json_from_url(request.year)
        return {"data": result}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# Endpoint to generate a report based on the provided year, department, and degree type. 
# The report is built using the ReportBuilder class and saved to a specified output path. 
@app.post("/generate-report")
def generate_report(request: ReportBuilder):
    try:
        template_path = str(TEMPLATE_PATH)
        #if template is missing, create a minimal temporary DOCX so report building can proceed prevents crashing inceidents error messages when creating report
        if not os.path.isfile(template_path):
            #tmpfd allows for program to open a file and the close prevents leakage and tmp name is the file path using it also for file security purposes to prevent name collisions and ensure proper cleanup
            tmpfd, tmpname = tempfile.mkstemp(suffix='.docx')
            os.close(tmpfd)

        report_builder = ReportBuilderClass(
            template_path=template_path,
            db= getdatabaseConnection.get_database_connection(),
            year=request.year,
            department=request.department,
            degree_type=request.degree_type
        )

        OUTPUT_PATH = BASE_DIR / "output"/ "Long_report.docx"
        # ensure output directory exists so writing the report won't fail
        OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True) 

        report_builder.build(str(OUTPUT_PATH))
        return FileResponse(path=OUTPUT_PATH, media_type='application/vnd.openxmlformats-officedocument.wordprocessingml.document', filename="Long_report.docx")
        #return {"message": "Report generated successfully", "output_path": str(OUTPUT_PATH)}
    
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/health")
def health():
    return {"ok": True}