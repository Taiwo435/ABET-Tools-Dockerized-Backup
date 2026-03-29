FROM python:3.11-slim

WORKDIR /usr/src/app

ARG EXTRACTION_PORT=8000
ENV EXTRACTION_PORT=$EXTRACTION_PORT

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1
ENV PYTHONPATH=/usr/src/app

# System Dependencies for PDF DOCX libraries
RUN apt-get update && apt-get install -y \
    build-essential \
    libjpeg-dev \
    zlib1g-dev \
    pkg-config \
    libcairo2-dev \
    && rm -rf /var/lib/apt/lists/*

# Copy the requirements file into container
COPY src/shared/requirements.txt ./requirements-shared.txt
COPY src/extraction_api/requirements.txt ./requirements-extraction.txt

# Install the Python dependencies
RUN pip install --no-cache-dir \
    -r requirements-shared.txt \
    -r requirements-extraction.txt

# Start the API
CMD uvicorn assignment_extraction_api:app --host 0.0.0.0 --port "$EXTRACTION_PORT"