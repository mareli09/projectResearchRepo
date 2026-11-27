"""TF-IDF index, similarity screening and K-Means clustering (CLAUDE.md §7 M5, M6, §11).

TF-IDF: TfidfVectorizer(sublinear_tf=False, norm='l2', min_df=1, ngram_range=(1, 2)) on the
preprocessed text (title twice, abstract, keywords three times). smooth_idf=False gives
idf = log(N/df) + 1, scikit-learn's form of w(t,d) = tf × log(N/df) (the +1 keeps terms that
appear in every document from vanishing). Rows are L2-normalised, so cosine = dot product.

The fitted vectorizer and matrix are stored with joblib and versioned.
"""

from __future__ import annotations

import hashlib
import os
import threading
import time
from dataclasses import dataclass, field
from pathlib import Path

import joblib
import numpy as np
from scipy import sparse
from sklearn.cluster import KMeans, MiniBatchKMeans
from sklearn.decomposition import TruncatedSVD
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics import davies_bouldin_score, silhouette_score

from .preprocess import combine, preprocess

RANDOM_STATE = 42


def model_file() -> Path:
    """Where the fitted index is stored (ANALYTICS_MODEL_DIR overrides it, e.g. in tests)."""
    base = os.environ.get("ANALYTICS_MODEL_DIR") or str(Path(__file__).resolve().parent.parent / "models")
    return Path(base) / "index.joblib"


def record_text(r: dict) -> str:
    """Accepts {text} or {title, abstract, keywords}; returns the preprocessed text."""
    if r.get("text"):
        raw = str(r["text"])
    else:
        raw = combine(str(r.get("title", "")), str(r.get("abstract", "")), [str(k) for k in r.get("keywords", [])])
    return preprocess(raw)


@dataclass
class Index:
    vectorizer: TfidfVectorizer | None = None
    matrix: sparse.csr_matrix | None = None
    ids: list = field(default_factory=list)
    version: str | None = None
    lock: threading.Lock = field(default_factory=threading.Lock, repr=False)

    # ---------- persistence ----------
    def load(self) -> None:
        if model_file().exists():
            data = joblib.load(model_file())
            self.vectorizer, self.matrix, self.ids, self.version = data["vectorizer"], data["matrix"], data["ids"], data["version"]

    def save(self) -> None:
        model_file().parent.mkdir(parents=True, exist_ok=True)
        joblib.dump({"vectorizer": self.vectorizer, "matrix": self.matrix, "ids": self.ids, "version": self.version}, model_file())

    @property
    def ready(self) -> bool:
        return self.vectorizer is not None and self.matrix is not None and len(self.ids) > 0

    # ---------- fit ----------
    def fit(self, records: list[dict]) -> dict:
        texts = [record_text(r) for r in records]
        vec = TfidfVectorizer(sublinear_tf=False, norm="l2", min_df=1, ngram_range=(1, 2), smooth_idf=False, dtype=np.float32)
        matrix = vec.fit_transform(texts).tocsr()
        version = time.strftime("%Y%m%d%H%M%S") + "-" + hashlib.sha1("|".join(map(str, sorted(r["id"] for r in records))).encode()).hexdigest()[:8]
        with self.lock:
            self.vectorizer, self.matrix, self.ids, self.version = vec, matrix, [r["id"] for r in records], version
            self.save()
        return {"vectorizer_version": version, "n_docs": matrix.shape[0], "n_terms": matrix.shape[1]}

    # ---------- screening ----------
    def _vector(self, r: dict) -> sparse.csr_matrix:
        assert self.vectorizer is not None
        return self.vectorizer.transform([record_text(r)]).tocsr()

    def _shared_terms(self, q: sparse.csr_matrix, row: int, n: int = 8) -> list[str]:
        assert self.matrix is not None and self.vectorizer is not None
        d = self.matrix.getrow(row)
        common = np.intersect1d(q.indices, d.indices)
        if common.size == 0:
            return []
        weights = np.array([q[0, i] * d[0, i] for i in common])
        names = self.vectorizer.get_feature_names_out()
        order = common[np.argsort(-weights)]
        return [str(names[i]) for i in order[:n]]

    def top_terms_of(self, q: sparse.csr_matrix, n: int) -> list[str]:
        assert self.vectorizer is not None
        if q.nnz == 0:
            return []
        names = self.vectorizer.get_feature_names_out()
        order = q.indices[np.argsort(-q.data)]
        return [str(names[i]) for i in order[:n]]

    def screen(self, r: dict, k: int = 5, exclude_id=None) -> dict:
        q = self._vector(r)
        scores = (self.matrix @ q.T).toarray().ravel()  # cosine, rows are L2-normalised
        if exclude_id is not None and exclude_id in self.ids:
            scores[self.ids.index(exclude_id)] = -1.0
        top = np.argsort(-scores)[:k]
        return {
            "matches": [
                {"id": self.ids[i], "score": round(float(max(scores[i], 0.0)), 4), "shared_terms": self._shared_terms(q, int(i))}
                for i in top if scores[i] > 0
            ],
            "top_terms": self.top_terms_of(q, 10),
            "vectorizer_version": self.version,
        }

    def related(self, record_id, k: int = 5) -> dict:
        assert self.matrix is not None
        if record_id not in self.ids:
            return {"matches": []}
        row = self.ids.index(record_id)
        scores = (self.matrix @ self.matrix.getrow(row).T).toarray().ravel()
        scores[row] = -1.0
        top = np.argsort(-scores)[:k]
        return {"matches": [{"id": self.ids[i], "score": round(float(scores[i]), 4)} for i in top if scores[i] > 0]}

    def top_terms(self, r: dict, n: int = 5) -> list[str]:
        if self.ready:
            return self.top_terms_of(self._vector(r), n)
        # Not fitted yet: most frequent terms of the text itself.
        words = record_text(r).split()
        counts: dict[str, int] = {}
        for w in words:
            counts[w] = counts.get(w, 0) + 1
        return [w for w, _ in sorted(counts.items(), key=lambda x: -x[1])[:n]]

    # ---------- clustering ----------
    def cluster(self, k_min: int = 2, k_max: int = 15, k: int | None = None) -> dict:
        """K-Means (k-means++, n_init=10, fixed random_state) for k = k_min..k_max on the TF-IDF matrix.
        Scores: inertia (elbow), average silhouette, Davies–Bouldin. Suggests the k with the best silhouette."""
        assert self.matrix is not None and self.vectorizer is not None
        X = self.matrix
        n = X.shape[0]
        k_max = max(k_min, min(k_max, n - 1))
        Model = MiniBatchKMeans if n > 10_000 else KMeans  # memory budget on the 4 GB server (§14)

        # Dense low-rank copy for silhouette / Davies–Bouldin and the 2-D theme map.
        svd = TruncatedSVD(n_components=min(100, max(2, X.shape[1] - 1)), random_state=RANDOM_STATE)
        reduced = svd.fit_transform(X).astype(np.float32)
        sample = min(n, 3000)

        scores, models = [], {}
        for kk in range(k_min, k_max + 1):
            m = Model(n_clusters=kk, init="k-means++", n_init=10, random_state=RANDOM_STATE)
            labels = m.fit_predict(X)
            sil = float(silhouette_score(X, labels, metric="cosine", sample_size=sample, random_state=RANDOM_STATE)) if kk > 1 else 0.0
            db = float(davies_bouldin_score(reduced, labels))
            scores.append({"k": kk, "inertia": round(float(m.inertia_), 3), "silhouette": round(sil, 4), "db": round(db, 4)})
            models[kk] = (m, labels)

        chosen = k if k and k in models else max(scores, key=lambda s: s["silhouette"])["k"]
        model, labels = models[chosen]
        names = self.vectorizer.get_feature_names_out()
        xy = reduced[:, :2]
        clusters = []
        for c in range(chosen):
            centroid = np.asarray(model.cluster_centers_[c]).ravel()
            top = [str(names[i]) for i in np.argsort(-centroid)[:10]]
            members = np.where(labels == c)[0]
            # The 5 records closest to the centroid (sent with the terms for label suggestions).
            # Squared distance to the centroid: |x|^2 - 2 x·c + |c|^2, with |x| = 1 (L2-normalised rows).
            dists = (1.0 - 2.0 * np.asarray(X[members] @ centroid).ravel() + float(centroid @ centroid)) if members.size else np.array([])
            nearest = [self.ids[int(members[i])] for i in np.argsort(dists)[:5]] if members.size else []
            cx, cy = (xy[members].mean(axis=0) if members.size else (0.0, 0.0))
            clusters.append({"n": c + 1, "top_terms": top, "size": int(members.size), "x": round(float(cx), 4), "y": round(float(cy), 4), "nearest_ids": nearest})

        return {
            "scores": scores,
            "chosen_k": chosen,
            "assignments": [{"id": self.ids[i], "cluster": int(labels[i]) + 1, "x": round(float(xy[i, 0]), 4), "y": round(float(xy[i, 1]), 4)} for i in range(n)],
            "clusters": clusters,
            "vectorizer_version": self.version,
        }


INDEX = Index()
INDEX.load()
