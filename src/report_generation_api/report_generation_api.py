from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Optional, Dict, Any

import getProfessorWorkload

app = FastAPI()

#This is just to make sure the shape of the json is correct. The get_professor_workload function expects a string. 
class WorkloadRequest(BaseModel):
    asurite_id: str

# Endpoint to get professor workload information for all semesters taught by the professor with the given ASURITE ID.
@app.post("/professor-workload")
def professor_workload(request: WorkloadRequest):
    result = getProfessorWorkload.get_professor_workload(request.asurite_id)

    return {"data": result or {}}

@app.get("/health")
def health():
    return {"ok": True}