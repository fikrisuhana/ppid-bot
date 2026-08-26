import time
from typing import Optional, Dict, Any

class ResponseCache:
    """In-memory cache untuk menyimpan jawaban pertanyaan yang sering ditanyakan."""
    def __init__(self, ttl: int = 86400):
        self._cache: Dict[str, Dict[str, Any]] = {}
        self.ttl = ttl

    def _normalize(self, text: str) -> str:
        return " ".join(text.lower().strip().split())

    def get(self, query: str) -> Optional[Dict[str, Any]]:
        key = self._normalize(query)
        entry = self._cache.get(key)
        if entry:
            if time.time() - entry["timestamp"] < self.ttl:
                return entry["data"]
            else:
                del self._cache[key]
        return None

    def set(self, query: str, data: Dict[str, Any]):
        key = self._normalize(query)
        self._cache[key] = {
            "data": data,
            "timestamp": time.time()
        }

cache_instance = ResponseCache()
