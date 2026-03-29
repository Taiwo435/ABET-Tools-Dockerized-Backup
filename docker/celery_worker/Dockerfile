FROM python:3.11-slim

WORKDIR /usr/src/app

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1
# Makes "from shared.celery_app import ..." and
# "from extraction_api.tasks import ..." work inside the container hopefully.
ENV PYTHONPATH=/usr/src/app:/usr/src/app/extraction_api

# System deps for PDF/DOCX processing (extraction_api needs these, add more if your servive needs them)
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libjpeg-dev \
    zlib1g-dev \
    pkg-config \
    libcairo2-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Python deps from all services the worker executes tasks for
COPY src/shared/requirements.txt ./requirements-shared.txt
COPY src/extraction_api/requirements.txt ./requirements-extraction.txt
# Uncomment as services add Celery tasks:
# COPY src/canvas_formatting_api/requirements.txt ./requirements-formatting.txt
# COPY src/report_generation_api/requirements.txt ./requirements-reportgen.txt

RUN pip install --no-cache-dir \
    -r requirements-shared.txt \
    -r requirements-extraction.txt
# -r requirements-formatting.txt
# -r requirements-reportgen.txt

# Copy source code for all services
COPY src/shared/ ./shared/
COPY src/extraction_api/ ./extraction_api/
# Uncomment as services add Celery tasks:
# COPY src/canvas_formatting_api/ ./canvas_formatting_api/
# COPY src/report_generation_api/ ./report_generation_api/

# Default: single worker consuming all queues, concurrency=2
# For per-queue workers, override `command` in docker-compose.yml
CMD ["celery", "-A", "shared.celery_app:celery_app", "worker", \
    "--loglevel=info", "--concurrency=2", \
    "-Q", "extraction,formatting,reportgen,default", \
    "-n", "worker@%h"]
