"""TUA analytics service (CLAUDE.md §11).

Listens on 127.0.0.1 only. Every endpoint is POST + JSON and needs a valid
HMAC signature. It receives text fields only, returns only scores, cluster
assignments and top terms, and has no database access.

Run:  uvicorn app.main:app --host 127.0.0.1 --port 8001 --workers 1
"""

import os

import psutil
from fastapi import Depends, FastAPI
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from .index import INDEX
from .security import require_signature

app = FastAPI(
    title="TUA analytics service",
    docs_url=None,  # no public docs; the service is internal
    redoc_url=None,
    openapi_url=None,
    dependencies=[Depends(require_signature)],
)


class RecordIn(BaseModel):
    id: int | str
    text: str | None = None
    title: str | None = None
    abstract: str | None = None
    keywords: list[str] = Field(default_factory=list)


class FitIn(BaseModel):
    records: list[RecordIn]


class ScreenIn(BaseModel):
    text: str | None = None
    title: str | None = None
    abstract: str | None = None
    keywords: list[str] = Field(default_factory=list)
    k: int = Field(default=5, ge=1, le=20)
    exclude_id: int | str | None = None


class RelatedIn(BaseModel):
    id: int | str
    k: int = Field(default=5, ge=1, le=20)


class PairIn(BaseModel):
    title: str | None = None
    abstract: str | None = None
    keywords: list[str] = Field(default_factory=list)
    record_id: int | str


class ClusterIn(BaseModel):
    k_min: int = Field(default=2, ge=2, le=30)
    k_max: int = Field(default=15, ge=2, le=30)
    k: int | None = Field(default=None, ge=2, le=30)


class TopTermsIn(BaseModel):
    text: str | None = None
    title: str | None = None
    abstract: str | None = None
    keywords: list[str] = Field(default_factory=list)
    n: int = Field(default=5, ge=1, le=20)


def _not_fitted() -> JSONResponse:
    return JSONResponse(status_code=409, content={"error": "not_fitted", "detail": "Call /fit with the published records first."})


@app.post("/health")
def health() -> dict:
    mem_mb = psutil.Process(os.getpid()).memory_info().rss / 1_048_576
    return {"ok": True, "vectorizer_version": INDEX.version, "n_docs": len(INDEX.ids), "mem_mb": round(mem_mb, 1)}


@app.post("/fit")
def fit(body: FitIn):
    if len(body.records) < 2:
        return JSONResponse(status_code=422, content={"error": "too_few_records", "detail": "At least 2 records are needed."})
    return INDEX.fit([r.model_dump() for r in body.records])


@app.post("/screen")
def screen(body: ScreenIn):
    if not INDEX.ready:
        return _not_fitted()
    return INDEX.screen(body.model_dump(), k=body.k, exclude_id=body.exclude_id)


@app.post("/pair")
def pair(body: PairIn):
    if not INDEX.ready:
        return _not_fitted()
    out = INDEX.pair(body.model_dump(exclude={"record_id"}), body.record_id)
    if out is None:
        return JSONResponse(status_code=404, content={"error": "unknown_record", "detail": "The record is not in the index."})
    return out


@app.post("/related")
def related(body: RelatedIn):
    if not INDEX.ready:
        return _not_fitted()
    return INDEX.related(body.id, k=body.k)


@app.post("/top-terms")
def top_terms(body: TopTermsIn):
    return {"terms": INDEX.top_terms(body.model_dump(), n=body.n)}


@app.post("/cluster")
def cluster(body: ClusterIn):
    if not INDEX.ready:
        return _not_fitted()
    if len(INDEX.ids) < 3:
        return JSONResponse(status_code=422, content={"error": "too_few_records", "detail": "At least 3 records are needed."})
    return INDEX.cluster(k_min=body.k_min, k_max=max(body.k_min, body.k_max), k=body.k)
