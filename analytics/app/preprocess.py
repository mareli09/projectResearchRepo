"""Text preprocessing shared by screening and clustering (CLAUDE.md §7 M5).

Implemented once, here, and used for both stored records and new submissions:
1. Combine title, abstract and keywords. Title appears twice and keywords are
   repeated so they carry more weight (as in the prototype).
2. Lowercase; remove punctuation, numbers and English stop words
   (NLTK list plus a domain list).
3. Lemmatize with the WordNet lemmatizer.
"""

import os
import re
from functools import lru_cache
from pathlib import Path

import nltk

# Keep NLTK data inside the service folder so the server needs no global download.
_NLTK_DIR = Path(__file__).resolve().parent.parent / "nltk_data"
if _NLTK_DIR.exists() and str(_NLTK_DIR) not in nltk.data.path:
    nltk.data.path.insert(0, str(_NLTK_DIR))

DOMAIN_STOP_WORDS = frozenset({
    "study", "studies", "research", "thesis", "system", "paper", "capstone", "project",
    "result", "results", "method", "methods", "respondent", "respondents", "using", "based",
    "among", "toward", "towards", "objective", "objectives", "conclusion", "findings",
})

KEYWORD_REPEAT = 3
TITLE_REPEAT = 2

_TOKEN = re.compile(r"[a-z]+")


@lru_cache(maxsize=1)
def _stop_words() -> frozenset[str]:
    from nltk.corpus import stopwords

    return frozenset(stopwords.words("english")) | DOMAIN_STOP_WORDS


@lru_cache(maxsize=1)
def _lemmatizer():
    from nltk.stem import WordNetLemmatizer

    return WordNetLemmatizer()


def combine(title: str, abstract: str, keywords: list[str] | None = None) -> str:
    keywords = keywords or []
    parts = [title] * TITLE_REPEAT + [abstract] + [" ".join(keywords)] * KEYWORD_REPEAT
    return " ".join(p for p in parts if p)


def tokenize(text: str) -> list[str]:
    stop = _stop_words()
    lemmatize = _lemmatizer().lemmatize
    tokens = _TOKEN.findall(text.lower())  # drops punctuation and numbers
    return [lemmatize(t) for t in tokens if t not in stop and len(t) > 1 and lemmatize(t) not in stop]


def preprocess(text: str) -> str:
    """Return the cleaned text that the TfidfVectorizer receives."""
    return " ".join(tokenize(text))


if os.environ.get("ANALYTICS_WARM_NLTK") == "1":
    _stop_words()
    _lemmatizer()
