import json
import time

import pytest
from fastapi.testclient import TestClient

SECRET = "test-secret"


@pytest.fixture(autouse=True)
def _secret(monkeypatch):
    monkeypatch.setenv("ANALYTICS_HMAC_SECRET", SECRET)


@pytest.fixture
def client():
    from app.main import app

    return TestClient(app)


def signed(body: dict, ts: int | None = None, secret: str = SECRET):
    from app.security import sign

    raw = json.dumps(body).encode()
    ts = int(time.time()) if ts is None else ts
    return raw, {"X-Timestamp": str(ts), "X-Signature": sign(raw, secret.encode(), ts), "Content-Type": "application/json"}


def test_health_with_valid_signature(client):
    raw, headers = signed({})
    r = client.post("/health", content=raw, headers=headers)
    assert r.status_code == 200
    assert r.json()["ok"] is True


def test_rejects_missing_signature(client):
    assert client.post("/health", json={}).status_code == 401


def test_rejects_wrong_secret(client):
    raw, headers = signed({}, secret="other")
    assert client.post("/health", content=raw, headers=headers).status_code == 401


def test_rejects_stale_timestamp(client):
    raw, headers = signed({}, ts=int(time.time()) - 120)
    assert client.post("/health", content=raw, headers=headers).status_code == 401


def test_rejects_tampered_body(client):
    raw, headers = signed({"text": "a"})
    assert client.post("/health", content=b'{"text": "b"}', headers=headers).status_code == 401


def test_matches_laravel_signature_format():
    """Same input as tests/Unit/AnalyticsSignatureTest.php must give the same hex."""
    from app.security import sign

    assert sign(b'{"k":5}', b"secret", 1700000000) == (
        __import__("hmac").new(b"secret", b'1700000000.{"k":5}', "sha256").hexdigest()
    )


def test_preprocess_removes_stop_words_numbers_and_lemmatizes():
    from app.preprocess import preprocess

    out = preprocess("A Study of 25 Nurses' Coping Strategies in Hospitals, 2024!").split()
    assert "study" not in out and "of" not in out
    assert not any(t.isdigit() for t in out)
    assert "nurse" in out and "strategy" in out and "hospital" in out


def test_combine_weights_title_and_keywords():
    from app.preprocess import combine

    text = combine("Flood Risk", "Abstract text", ["random forest"])
    assert text.count("Flood Risk") == 2
    assert text.count("random forest") == 3


# ---------------- TF-IDF screening and clustering (CLAUDE.md §7 M5 acceptance, M6) ----------------

DOCS = [
    {"id": 1, "title": "Flood Risk Prediction Using Random Forest", "abstract": "A random forest model predicts flood risk for barangays from rainfall and elevation.", "keywords": ["flood risk", "random forest", "prediction"]},
    {"id": 2, "title": "Telemedicine Readiness of Rural Health Units", "abstract": "Nurses and patients were surveyed on telemedicine readiness and digital health records.", "keywords": ["telemedicine", "health", "rural"]},
    {"id": 3, "title": "Social Media Marketing of Milk Tea Shops", "abstract": "The study measures social media marketing effectiveness among small food businesses.", "keywords": ["marketing", "social media", "msme"]},
    {"id": 4, "title": "Blended Learning and Academic Motivation", "abstract": "Senior high school students in blended learning reported their academic motivation.", "keywords": ["blended learning", "motivation", "students"]},
    {"id": 5, "title": "Crop Disease Detection Using Convolutional Neural Networks", "abstract": "Images of rice leaves are classified with a convolutional neural network to detect disease.", "keywords": ["crop disease", "cnn", "classification"]},
    {"id": 6, "title": "Hand Hygiene Compliance of Staff Nurses", "abstract": "Observation of hand hygiene compliance among staff nurses in medical wards.", "keywords": ["hand hygiene", "nursing", "compliance"]},
]


@pytest.fixture
def index(tmp_path, monkeypatch):
    monkeypatch.setenv("ANALYTICS_MODEL_DIR", str(tmp_path))
    from app.index import Index

    idx = Index()
    idx.fit(DOCS)
    return idx


def test_same_text_scores_one_and_unrelated_scores_low(index):
    same = index.screen(DOCS[0], k=1)
    assert same["matches"][0]["id"] == 1
    assert same["matches"][0]["score"] == pytest.approx(1.0, abs=1e-4)

    unrelated = index.screen({"title": "Structural assessment of pedestrian footbridges", "abstract": "Load testing of steel footbridges along a boulevard.", "keywords": ["footbridge", "structural"]}, k=1)
    top = unrelated["matches"][0]["score"] if unrelated["matches"] else 0.0
    assert top < 0.2


def test_screen_returns_top_k_with_shared_terms(index):
    r = index.screen({"title": "Flood prediction with random forest for barangays", "abstract": "Rainfall data and a random forest model.", "keywords": ["flood", "random forest"]}, k=3)
    assert r["matches"][0]["id"] == 1
    assert len(r["matches"]) <= 3
    assert any("random forest" in t or "forest" in t for t in r["matches"][0]["shared_terms"])
    assert r["top_terms"]


def test_related_excludes_itself(index):
    r = index.related(1, k=3)
    assert all(m["id"] != 1 for m in r["matches"])


def test_index_is_persisted_and_reloaded(index, tmp_path):
    from app.index import Index

    again = Index()
    again.load()
    assert again.ready and again.version == index.version and again.ids == [d["id"] for d in DOCS]


def test_cluster_scores_every_k_and_assigns_every_record(index):
    r = index.cluster(k_min=2, k_max=4)
    assert [s["k"] for s in r["scores"]] == [2, 3, 4]
    assert r["chosen_k"] == max(r["scores"], key=lambda s: s["silhouette"])["k"]
    assert len(r["assignments"]) == len(DOCS)
    assert sum(c["size"] for c in r["clusters"]) == len(DOCS)
    assert all(len(c["top_terms"]) == 10 for c in r["clusters"])


def test_cluster_is_repeatable(index):
    a = index.cluster(k_min=3, k_max=3)
    b = index.cluster(k_min=3, k_max=3)
    assert [x["cluster"] for x in a["assignments"]] == [x["cluster"] for x in b["assignments"]]


def test_endpoints_need_fit_first(client, monkeypatch, tmp_path):
    monkeypatch.setenv("ANALYTICS_MODEL_DIR", str(tmp_path / "empty"))
    from app import main

    main.INDEX.vectorizer = None
    main.INDEX.matrix = None
    main.INDEX.ids = []
    raw, headers = signed({"title": "x", "abstract": "y"})
    assert client.post("/screen", content=raw, headers=headers).status_code == 409

    raw, headers = signed({"records": DOCS})
    r = client.post("/fit", content=raw, headers=headers)
    assert r.status_code == 200 and r.json()["n_docs"] == 6

    raw, headers = signed({"title": "Flood risk random forest", "abstract": "rainfall", "keywords": [], "k": 2})
    r = client.post("/screen", content=raw, headers=headers)
    assert r.status_code == 200 and r.json()["matches"][0]["id"] == 1
