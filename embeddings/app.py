"""
Lightweight HuggingFace embeddings microservice.

Exposes:
  GET  /health       — readiness check (returns model name + dim once loaded)
  POST /embed        — { "texts": [...] } -> { "embeddings": [[...], ...], "dim": int }

The model is loaded lazily so the container can boot fast and the healthcheck
reports `loading` until weights are ready. The Laravel backend pings /health
before issuing embed calls and falls back to lexical search if unavailable.
"""

from __future__ import annotations

import logging
import os
import threading
from typing import List, Optional

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
log = logging.getLogger("embeddings")

MODEL_NAME = os.getenv("MODEL_NAME", "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2")
MAX_LENGTH = int(os.getenv("MAX_LENGTH", "512"))

app = FastAPI(title="Tours Embeddings", version="1.0.0")

_model = None
_model_lock = threading.Lock()
_model_dim: Optional[int] = None
_load_error: Optional[str] = None


def _load_model():
    """Load the sentence-transformers model exactly once."""
    global _model, _model_dim, _load_error
    if _model is not None or _load_error is not None:
        return
    with _model_lock:
        if _model is not None or _load_error is not None:
            return
        try:
            log.info("Loading model %s", MODEL_NAME)
            from sentence_transformers import SentenceTransformer
            model = SentenceTransformer(MODEL_NAME)
            model.max_seq_length = MAX_LENGTH
            dim = int(model.get_sentence_embedding_dimension())
            _model = model
            _model_dim = dim
            log.info("Model loaded, dim=%d", dim)
        except Exception as exc:  # noqa: BLE001
            _load_error = str(exc)
            log.exception("Model load failed")


@app.on_event("startup")
def _startup() -> None:
    # Kick off load in the background so /health can answer immediately.
    threading.Thread(target=_load_model, daemon=True).start()


class EmbedRequest(BaseModel):
    texts: List[str] = Field(..., min_length=1)
    normalize: bool = True


class EmbedResponse(BaseModel):
    embeddings: List[List[float]]
    dim: int
    model: str


@app.get("/health")
def health():
    if _load_error:
        return {"status": "error", "error": _load_error, "model": MODEL_NAME}
    if _model is None:
        return {"status": "loading", "model": MODEL_NAME}
    return {"status": "ok", "model": MODEL_NAME, "dim": _model_dim}


@app.post("/embed", response_model=EmbedResponse)
def embed(req: EmbedRequest):
    if _load_error:
        raise HTTPException(status_code=503, detail=f"model unavailable: {_load_error}")
    if _model is None:
        _load_model()
        if _model is None:
            raise HTTPException(status_code=503, detail="model not ready")

    cleaned = [(t or "").strip() for t in req.texts]
    if not any(cleaned):
        raise HTTPException(status_code=400, detail="all texts are empty")

    vectors = _model.encode(
        cleaned,
        normalize_embeddings=req.normalize,
        convert_to_numpy=True,
        show_progress_bar=False,
    )
    return EmbedResponse(
        embeddings=[v.tolist() for v in vectors],
        dim=int(vectors.shape[1]),
        model=MODEL_NAME,
    )
