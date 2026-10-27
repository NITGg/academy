# Speech-to-text service for local_nit_ai. See README.md beside this file.
FROM python:3.11-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

RUN pip install --no-cache-dir faster-whisper==1.1.1 requests==2.32.3

# The model downloads on first use into this cache; mount a volume here so it
# survives a rebuild instead of fetching 1.5 GB again.
ENV HF_HOME=/models
VOLUME ["/models", "/data"]

WORKDIR /app
COPY app.py /app/

EXPOSE 9000
CMD ["python", "-u", "app.py"]
