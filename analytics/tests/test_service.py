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
