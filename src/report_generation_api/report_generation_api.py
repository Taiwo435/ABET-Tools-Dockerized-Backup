from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Optional, Dict, Any

import getProfessorWorkload
import getElectiveCourses
import getdatabaseConnection

app = FastAPI()

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
        from .report.report_builder import ReportBuilder as ReportBuilderClass
        report_builder = ReportBuilderClass(
            template_path="src/report_generation_api/report/template.docx",
            db= getdatabaseConnection.get_database_connection(),
            year=request.year,
            department=request.department,
            degree_type=request.degree_type
        )
        output_path = f"report_{request.department}_{request.degree_type}_{request.year}.docx"
        report_builder.build(output_path)
        return {"message": "Report generated successfully", "output_path": output_path}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/health")
def health():
    return {"ok": True}