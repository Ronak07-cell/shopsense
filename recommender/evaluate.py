"""Offline evaluation: compares popularity, CF, content and the hybrid blend.

For every shopper with enough purchases, one purchased product is held out. The
model is trained on everything else and must rank the held-out product highly.

Run:  python evaluate.py            (writes results/metrics.md)
"""
from __future__ import annotations

import math
from pathlib import Path

import numpy as np

from app.engine import Engine
from app.synthetic import make_interactions, make_products

K = 10
MODES = {"popular": "Popularity (baseline)", "cf": "Item-item CF", "content": "Content (TF-IDF)", "hybrid": "Hybrid"}


def split(events: list[dict]) -> tuple[list[dict], dict[int, int]]:
    """Hold out each shopper's last purchase."""
    last: dict[int, int] = {}
    for i, e in enumerate(events):
        if e["type"] == "purchase" and e["user_id"] is not None:
            last[e["user_id"]] = i
    held = {uid: events[i]["product_id"] for uid, i in last.items()}
    drop = set(last.values())
    train = [e for i, e in enumerate(events) if i not in drop]
    # Only keep users who still have history after the split.
    counts: dict[int, int] = {}
    for e in train:
        counts[e["user_id"]] = counts.get(e["user_id"], 0) + 1
    held = {u: p for u, p in held.items() if counts.get(u, 0) >= 3}
    return train, held


def evaluate(seed: int = 42) -> dict[str, dict[str, float]]:
    products = make_products()
    events = make_interactions(products, seed=seed)
    train, held = split(events)
    engine = Engine().fit(products, train)

    results = {}
    for mode in MODES:
        hits = ndcg = prec = rec = 0.0
        covered: set[int] = set()
        for uid, target in held.items():
            scores = engine.score_user(uid, mode)
            vec = engine._user_vector(uid)
            if vec is not None:  # never recommend what they already have
                scores[np.nonzero(vec)[0]] = -np.inf
            top = np.argsort(-scores)[:K]
            ids = [engine.product_ids[i] for i in top]
            covered.update(ids)
            if target in ids:
                rank = ids.index(target)
                hits += 1
                ndcg += 1 / math.log2(rank + 2)
                prec += 1 / K
                rec += 1
        n = max(len(held), 1)
        results[mode] = {
            f"HitRate@{K}": hits / n,
            f"Precision@{K}": prec / n,
            f"Recall@{K}": rec / n,
            f"NDCG@{K}": ndcg / n,
            "Coverage": len(covered) / len(products),
        }
    results["_n"] = {"shoppers": float(len(held))}
    return results


def to_markdown(results: dict) -> str:
    n = int(results.pop("_n")["shoppers"])
    cols = list(next(iter(results.values())))
    lines = [f"Held-out purchase for {n} shoppers, K={K}.", "", "| Model | " + " | ".join(cols) + " |", "|---|" + "---|" * len(cols)]
    for mode, label in MODES.items():
        lines.append(f"| {label} | " + " | ".join(f"{results[mode][c]:.3f}" for c in cols) + " |")
    return "\n".join(lines) + "\n"


if __name__ == "__main__":
    import sys

    results = evaluate()
    lift = results["hybrid"][f"NDCG@{K}"] / max(results["popular"][f"NDCG@{K}"], 1e-9)
    md = to_markdown(results)
    print(md)
    print(f"Hybrid NDCG@{K} is {lift:.2f}x the popularity baseline.")
    out = Path("results")
    out.mkdir(exist_ok=True)
    (out / "metrics.md").write_text(md)
    sys.exit(0 if lift > 1.0 else 1)  # CI fails if the engine stops beating the baseline
