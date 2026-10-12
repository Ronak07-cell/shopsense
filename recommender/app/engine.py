"""Hybrid recommendation engine.

Three signals are combined:

* popularity  - what sells and gets viewed most (the cold-start fallback)
* item-item collaborative filtering - "people who bought this also bought that"
* content similarity - TF-IDF over name, description and category

The blend shifts toward content and popularity when a shopper has little history.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timezone

import numpy as np
from scipy import sparse
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

# How much each kind of event says about interest.
EVENT_WEIGHTS = {"view": 1.0, "cart": 3.0, "purchase": 5.0}


def _scale(scores: np.ndarray) -> np.ndarray:
    """Scale a score vector into [0, 1] so different signals can be blended."""
    top = scores.max() if scores.size else 0.0
    if top <= 0:
        return np.zeros_like(scores, dtype=float)
    return scores / top


@dataclass
class Engine:
    product_ids: list[int] = field(default_factory=list)
    user_ids: list[int] = field(default_factory=list)
    product_index: dict[int, int] = field(default_factory=dict)
    user_index: dict[int, int] = field(default_factory=dict)
    user_item: sparse.csr_matrix | None = None
    popularity: np.ndarray | None = None
    cf_sim: np.ndarray | None = None
    content_sim: np.ndarray | None = None
    trained_at: str | None = None
    pop_counts: dict[int, float] = field(default_factory=dict)

    # ---- training -------------------------------------------------------
    def fit(self, products: list[dict], interactions: list[dict], neighbours: int = 30) -> "Engine":
        if not products:
            raise ValueError("at least one product is required to train")

        self.pop_counts = {}
        self.product_ids = [int(p["id"]) for p in products]
        self.product_index = {pid: i for i, pid in enumerate(self.product_ids)}
        n_items = len(self.product_ids)

        # Content similarity from text. Category is repeated so it carries weight.
        docs = [
            f"{p.get('name', '')} {p.get('description', '') or ''} "
            f"{((p.get('category') or '') + ' ') * 3}"
            for p in products
        ]
        tfidf = TfidfVectorizer(stop_words="english", ngram_range=(1, 2), min_df=1)
        matrix = tfidf.fit_transform(docs)
        self.content_sim = cosine_similarity(matrix)
        np.fill_diagonal(self.content_sim, 0.0)

        # User x item matrix from weighted events.
        rows, cols, vals = [], [], []
        users: dict[int, int] = {}
        for event in interactions:
            pid = int(event["product_id"])
            uid = event.get("user_id")
            weight = EVENT_WEIGHTS.get(event.get("type", "view"), 1.0)
            if pid not in self.product_index:
                continue
            self.popularity_add(pid, weight)
            if uid is None:
                continue  # guest events still count toward popularity only
            uid = int(uid)
            users.setdefault(uid, len(users))
            rows.append(users[uid])
            cols.append(self.product_index[pid])
            vals.append(weight)

        self.user_ids = list(users)
        self.user_index = users
        if rows:
            ui = sparse.csr_matrix((vals, (rows, cols)), shape=(len(users), n_items), dtype=float)
            ui.sum_duplicates()
            ui.data = np.log1p(ui.data)  # damp heavy repeat viewing
        else:
            ui = sparse.csr_matrix((0, n_items), dtype=float)
        self.user_item = ui

        # Item-item cosine similarity, keeping only the strongest neighbours.
        if ui.shape[0] > 0:
            sim = cosine_similarity(ui.T.tocsr())
            np.fill_diagonal(sim, 0.0)
            for i in range(n_items):
                row = sim[i]
                if neighbours < n_items:
                    cutoff = np.partition(row, -neighbours)[-neighbours]
                    row[row < cutoff] = 0.0
            self.cf_sim = sim
        else:
            self.cf_sim = np.zeros((n_items, n_items))

        self.popularity = _scale(self._pop_counts())
        self.trained_at = datetime.now(timezone.utc).isoformat(timespec="seconds")
        return self

    def popularity_add(self, pid: int, weight: float) -> None:
        self.pop_counts[pid] = self.pop_counts.get(pid, 0.0) + weight

    def _pop_counts(self) -> np.ndarray:
        arr = np.zeros(len(self.product_ids))
        for pid, value in self.pop_counts.items():
            arr[self.product_index[pid]] = value
        return arr

    # ---- serving --------------------------------------------------------
    def _user_vector(self, user_id: int) -> np.ndarray | None:
        idx = self.user_index.get(user_id)
        if idx is None or self.user_item is None:
            return None
        return self.user_item.getrow(idx).toarray().ravel()

    def _rank(self, scores: np.ndarray, exclude: set[int], k: int) -> list[dict]:
        scores = scores.copy()
        for pid in exclude:
            i = self.product_index.get(pid)
            if i is not None:
                scores[i] = -np.inf
        order = np.argsort(-scores)[:k]
        return [
            {"product_id": self.product_ids[i], "score": round(float(scores[i]), 6)}
            for i in order
            if np.isfinite(scores[i])
        ]

    def for_user(self, user_id: int, k: int = 8, exclude: set[int] | None = None) -> tuple[str, list[dict]]:
        """Return (strategy, ranked items) for a known or unknown shopper."""
        exclude = set(exclude or ())
        vec = self._user_vector(user_id)
        if vec is None or not vec.any():
            return "popular", self._rank(self.popularity, exclude, k)

        seen = {self.product_ids[i] for i in np.nonzero(vec)[0]}
        history = int((vec > 0).sum())
        cf = _scale(self.cf_sim @ vec)
        content = _scale(self.content_sim @ vec)
        pop = self.popularity

        # Less history -> lean on content and popularity. More -> trust the crowd.
        crowd = min(0.6, 0.15 + 0.05 * history)
        blend = crowd * cf + (0.8 - crowd) * content + 0.2 * pop
        return "hybrid", self._rank(blend, exclude | seen, k)

    def for_seeds(self, product_ids: list[int], k: int = 8, exclude: set[int] | None = None) -> tuple[str, list[dict]]:
        """Recommendations for a guest from the products they recently looked at."""
        idx = [self.product_index[p] for p in product_ids if p in self.product_index]
        exclude = set(exclude or ()) | set(product_ids)
        if not idx:
            return "popular", self._rank(self.popularity, exclude, k)
        vec = np.zeros(len(self.product_ids))
        vec[idx] = 1.0
        blend = 0.45 * _scale(self.cf_sim @ vec) + 0.4 * _scale(self.content_sim @ vec) + 0.15 * self.popularity
        return "seeded", self._rank(blend, exclude, k)

    def similar(self, product_id: int, k: int = 6) -> tuple[str, list[dict]]:
        i = self.product_index.get(product_id)
        if i is None:
            return "popular", self._rank(self.popularity, set(), k)
        blend = 0.5 * _scale(self.cf_sim[i]) + 0.5 * _scale(self.content_sim[i]) + 0.0 * self.popularity
        return "similar", self._rank(blend, {product_id}, k)

    # ---- single-signal helpers used by the evaluation script ------------
    def score_user(self, user_id: int, mode: str) -> np.ndarray:
        vec = self._user_vector(user_id)
        if mode == "popular" or vec is None or not vec.any():
            return self.popularity.copy()
        if mode == "cf":
            return _scale(self.cf_sim @ vec) + 1e-6 * self.popularity
        if mode == "content":
            return _scale(self.content_sim @ vec) + 1e-6 * self.popularity
        history = int((vec > 0).sum())
        crowd = min(0.6, 0.15 + 0.05 * history)
        return crowd * _scale(self.cf_sim @ vec) + (0.8 - crowd) * _scale(self.content_sim @ vec) + 0.2 * self.popularity
