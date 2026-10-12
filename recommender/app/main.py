"""FastAPI service that trains and serves the recommendation engine."""
from __future__ import annotations

import os
from pathlib import Path
from threading import Lock

import joblib
from fastapi import FastAPI, HTTPException, Query
from pydantic import BaseModel, Field

from .engine import Engine

MODEL_PATH = Path(os.getenv("MODEL_PATH", "model/engine.joblib"))

app = FastAPI(title="ShopSense Recommender", version="1.0.0")
_lock = Lock()
_engine: Engine | None = None


class Product(BaseModel):
    id: int
    name: str
    description: str | None = ""
    category: str | None = ""
    price: float | None = None


class Interaction(BaseModel):
    user_id: int | None = None
    product_id: int
    type: str = Field("view", pattern="^(view|cart|purchase)$")


class TrainRequest(BaseModel):
    products: list[Product]
    interactions: list[Interaction] = []


def _load() -> Engine | None:
    global _engine
    if _engine is None and MODEL_PATH.exists():
        _engine = joblib.load(MODEL_PATH)
    return _engine


def _require() -> Engine:
    engine = _load()
    if engine is None:
        raise HTTPException(status_code=503, detail="model not trained yet; POST /train first")
    return engine


def _parse_ids(raw: str | None) -> list[int]:
    if not raw:
        return []
    try:
        return [int(x) for x in raw.split(",") if x.strip()]
    except ValueError:
        raise HTTPException(status_code=422, detail="ids must be comma-separated integers")


@app.get("/health")
def health() -> dict:
    engine = _load()
    return {
        "status": "ok",
        "trained": engine is not None,
        "trained_at": engine.trained_at if engine else None,
        "products": len(engine.product_ids) if engine else 0,
        "users": len(engine.user_ids) if engine else 0,
    }


@app.post("/train")
def train(payload: TrainRequest) -> dict:
    global _engine
    try:
        engine = Engine().fit(
            [p.model_dump() for p in payload.products],
            [i.model_dump() for i in payload.interactions],
        )
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc))
    with _lock:
        _engine = engine
        MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
        joblib.dump(engine, MODEL_PATH)
    return {
        "status": "trained",
        "products": len(engine.product_ids),
        "users": len(engine.user_ids),
        "interactions": len(payload.interactions),
        "trained_at": engine.trained_at,
    }


@app.get("/recommend/user/{user_id}")
def recommend_user(user_id: int, k: int = Query(8, ge=1, le=50), exclude: str | None = None) -> dict:
    strategy, items = _require().for_user(user_id, k, set(_parse_ids(exclude)))
    return {"user_id": user_id, "strategy": strategy, "items": items}


@app.get("/recommend/seed")
def recommend_seed(product_ids: str, k: int = Query(8, ge=1, le=50)) -> dict:
    """For guests: recommendations from the product ids they recently viewed."""
    strategy, items = _require().for_seeds(_parse_ids(product_ids), k)
    return {"strategy": strategy, "items": items}


@app.get("/similar/{product_id}")
def similar(product_id: int, k: int = Query(6, ge=1, le=50)) -> dict:
    strategy, items = _require().similar(product_id, k)
    return {"product_id": product_id, "strategy": strategy, "items": items}
