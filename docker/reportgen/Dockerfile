FROM python:3.11-slim

WORKDIR /usr/src/app

ARG REPORTGEN_PORT=8002
ENV REPORTGEN_PORT=$REPORTGEN_PORT

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

# System Dependencies for PDF DOCX libraries
RUN apt-get update && apt-get install -y \
    build-essential \
    libjpeg-dev \
    zlib1g-dev \
    pkg-config \
    libcairo2-dev \
    && rm -rf /var/lib/apt/lists/*

# Copy the requirements file into container
COPY src/report_generation_api/requirements.txt ./

# Install the Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Start the API
CMD uvicorn report_generation_api:app --host 0.0.0.0 --port $REPORTGEN_PORT