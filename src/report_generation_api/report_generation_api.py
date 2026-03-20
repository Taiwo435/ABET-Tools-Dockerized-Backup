from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Optional, Dict, Any

import getProfessorWorkload
import getElectiveCourses
import getdatabaseConnection

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
        report_builder = ReportBuilderClass(
            template_path=str(TEMPLATE_PATH),
            db= getdatabaseConnection.get_database_connection(),
            year=request.year,
            department=request.department,
            degree_type=request.degree_type
        )

        OUTPUT_PATH = BASE_DIR / "output" / f"report_{request.department}_{request.degree_type}_{request.year}.docx"
        report_builder.build(str(OUTPUT_PATH))
        return {"message": "Report generated successfully", "output_path": str(OUTPUT_PATH)}
    
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/health")
def health():
    return {"ok": True}