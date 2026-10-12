from app.engine import Engine
from app.synthetic import make_interactions, make_products


def trained() -> Engine:
    products = make_products()
    return Engine().fit(products, make_interactions(products, n_users=120))


def test_known_user_gets_hybrid_without_seen_items():
    engine = trained()
    uid = engine.user_ids[0]
    seen = set(engine.user_item.getrow(engine.user_index[uid]).nonzero()[1])
    strategy, items = engine.for_user(uid, k=8)
    assert strategy == "hybrid"
    assert len(items) == 8
    assert not {engine.product_index[i["product_id"]] for i in items} & seen


def test_unknown_user_falls_back_to_popularity():
    strategy, items = trained().for_user(999999, k=5)
    assert strategy == "popular"
    assert len(items) == 5


def test_similar_items_stay_in_category():
    engine = trained()
    products = {p["id"]: p for p in make_products()}
    _, items = engine.similar(1, k=3)
    assert items and products[items[0]["product_id"]]["category"] == products[1]["category"]


def test_seed_recommendations_exclude_seeds():
    _, items = trained().for_seeds([1, 2], k=6)
    assert not {1, 2} & {i["product_id"] for i in items}


def test_exclude_list_is_respected():
    engine = trained()
    uid = engine.user_ids[0]
    _, items = engine.for_user(uid, k=8)
    banned = {items[0]["product_id"]}
    _, again = engine.for_user(uid, k=8, exclude=banned)
    assert not banned & {i["product_id"] for i in again}


def test_training_needs_products():
    import pytest
    with pytest.raises(ValueError):
        Engine().fit([], [])
