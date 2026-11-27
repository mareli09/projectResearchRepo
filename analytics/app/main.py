"""TUA analytics service (CLAUDE.md §11).

Listens on 127.0.0.1 only. Every endpoint is POST + JSON and needs a valid
HMAC signature. It receives text fields only and has no database access.

Run:  uvicorn app.main:app --host 127.0.0.1 --port 8001 --workers 1
"""

import os

import psutil
from fastapi import Depends, FastAPI
from fastapi.responses import JSONResponse

from .security import require_signature

app = FastAPI(
    title="TUA analytics service",
    docs_url=None,  # no public docs; the service is internal
    redoc_url=None,
    openapi_url=None,
    dependencies=[Depends(require_signature)],
)

STATE = {"vectorizer_version": None, "n_docs": 0}


def _not_ready(name: str, sprint: int) -> JSONResponse:
    return JSONResponse(
        status_code=501,
        content={"error": "not_implemented", "detail": f"/{name} is built in Sprint {sprint}"},
    )


@app.post("/health")
def health() -> dict:
    mem_mb = psutil.Process(os.getpid()).memory_info().rss / 1_048_576
    return {
        "ok": True,
        "vectorizer_version": STATE["vectorizer_version"],
        "n_docs": STATE["n_docs"],
        "mem_mb": round(mem_mb, 1),
    }


@app.post("/fit")
def fit():
    return _not_ready("fit", 4)


@app.post("/screen")
def screen():
    return _not_ready("screen", 4)


@app.post("/related")
def related():
    return _not_ready("related", 4)


@app.post("/top-terms")
def top_terms():
    return _not_ready("top-terms", 6)


@app.post("/cluster")
def cluster():
    return _not_ready("cluster", 6)
