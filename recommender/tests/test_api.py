import importlib

import pytest
from fastapi.testclient import TestClient

from app.synthetic import make_interactions, make_products


@pytest.fixture()
def client(tmp_path, monkeypatch):
    monkeypatch.setenv("MODEL_PATH", str(tmp_path / "engine.joblib"))
    import app.main as main
    importlib.reload(main)
    return TestClient(main.app)


def payload():
    products = make_products()
    return {"products": products, "interactions": make_interactions(products, n_users=80)}


def test_untrained_service_returns_503(client):
    assert client.get("/health").json()["trained"] is False
    assert client.get("/recommend/user/1").status_code == 503


def test_train_then_recommend(client):
    res = client.post("/train", json=payload())
    assert res.status_code == 200 and res.json()["status"] == "trained"
    body = client.get("/recommend/user/1?k=5").json()
    assert len(body["items"]) == 5 and body["strategy"] in {"hybrid", "popular"}
    assert client.get("/similar/1?k=3").json()["items"]
    assert client.get("/recommend/seed?product_ids=1,2").json()["strategy"] == "seeded"


def test_model_survives_restart(client, tmp_path, monkeypatch):
    client.post("/train", json=payload())
    import app.main as main
    importlib.reload(main)
    fresh = TestClient(main.app)
    assert fresh.get("/health").json()["trained"] is True


def test_bad_event_type_rejected(client):
    bad = payload()
    bad["interactions"][0]["type"] = "teleport"
    assert client.post("/train", json=bad).status_code == 422
