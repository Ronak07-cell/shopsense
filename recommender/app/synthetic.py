"""Synthetic shop data with real structure, used for the demo and for offline evaluation.

Each shopper prefers one or two categories. They mostly view and buy there, with a
little random browsing elsewhere. A good recommender should therefore beat plain
popularity, and the evaluation script measures by how much.
"""
from __future__ import annotations

import numpy as np

CATEGORIES = {
    "Electronics": ["headphones", "charger", "speaker", "keyboard", "mouse", "webcam", "powerbank", "monitor"],
    "Books": ["novel", "biography", "cookbook", "textbook", "comic", "poetry", "guide", "journal"],
    "Fashion": ["shirt", "jeans", "jacket", "sneakers", "scarf", "cap", "kurta", "watch"],
    "Home": ["lamp", "cushion", "bottle", "bedsheet", "clock", "mug", "organizer", "vase"],
    "Sports": ["yoga mat", "dumbbell", "cricket bat", "football", "skipping rope", "shaker", "racket", "gloves"],
    "Beauty": ["face wash", "moisturizer", "perfume", "lip balm", "shampoo", "sunscreen", "hair oil", "serum"],
}


def make_products(per_category: int = 8) -> list[dict]:
    products, pid = [], 1
    for category, nouns in CATEGORIES.items():
        for noun in nouns[:per_category]:
            products.append(
                {
                    "id": pid,
                    "name": f"{noun.title()} Pro" if pid % 2 else f"{noun.title()} Classic",
                    "description": f"A reliable {noun} for everyday {category.lower()} needs.",
                    "category": category,
                    "price": float(199 + (pid * 37) % 1800),
                }
            )
            pid += 1
    return products


def make_interactions(products: list[dict], n_users: int = 300, seed: int = 42) -> list[dict]:
    rng = np.random.default_rng(seed)
    by_cat: dict[str, list[int]] = {}
    for p in products:
        by_cat.setdefault(p["category"], []).append(p["id"])
    cats = list(by_cat)
    # a few products are far more popular than the rest
    hot = set(rng.choice([p["id"] for p in products], size=6, replace=False).tolist())

    events = []
    for uid in range(1, n_users + 1):
        favourites = rng.choice(cats, size=rng.integers(1, 3), replace=False)
        for _ in range(int(rng.integers(8, 22))):
            if rng.random() < 0.8:
                cat = favourites[int(rng.integers(len(favourites)))]
                pid = int(rng.choice(by_cat[cat]))
            else:
                pid = int(rng.choice([p["id"] for p in products]))
            if pid not in hot and rng.random() < 0.15:
                pid = int(rng.choice(sorted(hot)))
            kind = rng.choice(["view", "cart", "purchase"], p=[0.7, 0.18, 0.12])
            events.append({"user_id": uid, "product_id": pid, "type": str(kind)})
    return events
