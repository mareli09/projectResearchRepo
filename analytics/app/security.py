"""HMAC request signing between Laravel and this service (CLAUDE.md §3).

Laravel sends:
  X-Timestamp: unix seconds
  X-Signature: hex HMAC-SHA256 of f"{timestamp}.{raw_body}" with ANALYTICS_HMAC_SECRET

This must match App\\Services\\AnalyticsClient\\HttpAnalyticsClient::sign().
"""

import hashlib
import hmac
import os
import time

from fastapi import HTTPException, Request

MAX_SKEW_SECONDS = 60


def get_secret() -> bytes:
    secret = os.environ.get("ANALYTICS_HMAC_SECRET", "")
    if not secret:
        raise RuntimeError("ANALYTICS_HMAC_SECRET is not set")
    return secret.encode()


def sign(body: bytes, secret: bytes, timestamp: int) -> str:
    return hmac.new(secret, f"{timestamp}.".encode() + body, hashlib.sha256).hexdigest()


def verify(body: bytes, timestamp: str | None, signature: str | None, secret: bytes, now: float | None = None) -> bool:
    if not timestamp or not signature or not timestamp.isdigit():
        return False
    now = time.time() if now is None else now
    if abs(now - int(timestamp)) > MAX_SKEW_SECONDS:
        return False
    expected = sign(body, secret, int(timestamp))
    return hmac.compare_digest(expected, signature)


async def require_signature(request: Request) -> None:
    """FastAPI dependency: reject any request that is not signed and fresh."""
    body = await request.body()
    ok = verify(
        body,
        request.headers.get("X-Timestamp"),
        request.headers.get("X-Signature"),
        get_secret(),
    )
    if not ok:
        raise HTTPException(status_code=401, detail={"error": "unauthorized", "detail": "Bad or expired signature"})
