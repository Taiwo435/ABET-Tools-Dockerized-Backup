FROM python:3.11-slim

WORKDIR /usr/src/app

ARG CANVAS_FORMATTING_PORT=8001
ENV CANVAS_FORMATTING_PORT=$CANVAS_FORMATTING_PORT

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

# Copy requirements and install dependencies (source mounted via docker-compose volume)
COPY src/canvas_formatting_api/requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

# Start the API (port 8001 to avoid conflict with extraction_api on 8000)
CMD uvicorn canvas_formatting_api:app --host 0.0.0.0 --port $CANVAS_FORMATTING_PORT
