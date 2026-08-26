import os
import json
import re
import requests
from typing import Dict, Any, List, Optional
from difflib import SequenceMatcher
from app.config import settings

class RAGEngine:
    """Engine pencarian semantik dan hybrid RAG (FAQ Database + DeepSeek LLM Fallback)."""
    def __init__(self, data_path: str):
        self.data_path = data_path
        self.knowledge = self._load_data()

    def _load_data(self) -> Dict[str, Any]:
        if not os.path.exists(self.data_path):
            return {"faqs": [], "organization": {}, "template_buttons": []}
        with open(self.data_path, "r", encoding="utf-8") as f:
            return json.load(f)

    def _clean_text(self, text: str) -> str:
        text = text.lower().strip()
        text = re.sub(r"[^a-zA-Z0-9\s]", " ", text)
        return " ".join(text.split())

    def _calculate_similarity(self, a: str, b: str) -> float:
        return SequenceMatcher(None, a, b).ratio()

    def search_faq(self, query: str) -> Optional[Dict[str, Any]]:
        cleaned_query = self._clean_text(query)
        query_words = set(cleaned_query.split())
        
        best_match = None
        highest_score = 0.0

        for faq in self.knowledge.get("faqs", []):
            score = 0.0
            
            # 1. Cek keyword match
            keywords = faq.get("keywords", [])
            for kw in keywords:
                cleaned_kw = self._clean_text(kw)
                kw_words = set(cleaned_kw.split())
                if cleaned_kw in cleaned_query:
                    score += 0.7
                overlap = len(query_words.intersection(kw_words))
                if overlap > 0:
                    score += (overlap / len(kw_words)) * 0.4

            # 2. Cek kemiripan kalimat pertanyaan
            q_similarity = self._calculate_similarity(cleaned_query, self._clean_text(faq.get("question", "")))
            score = max(score, q_similarity)

            if score > highest_score:
                highest_score = score
                best_match = faq

        if highest_score >= settings.SIMILARITY_THRESHOLD and best_match:
            return {
                "answer": best_match["answer"],
                "links": best_match.get("links", []),
                "confidence": round(highest_score, 2),
                "matched_question": best_match["question"],
                "source": "knowledge_base"
            }
        return None

    def query_deepseek_llm(self, query: str) -> Optional[str]:
        """Panggil DeepSeek API jika pertanyaan tidak persis cocok di FAQ database."""
        api_key = settings.DEEPSEEK_API_KEY
        if not api_key:
            return None

        org = self.get_org_info()
        system_prompt = (
            "Anda adalah PPIDbot, asisten AI resmi pelayanan informasi publik untuk PPID Utama Pemerintah Kabupaten Purbalingga (Dinkominfo Kab. Purbalingga).\n"
            "Tugas Anda:\n"
            "1. Menjawab pertanyaan pemohon informasi dengan sopan, ramah, dan ringkas (Bahasa Indonesia baku).\n"
            "2. Layanan informasi PPID Purbalingga adalah GRATIS (Rp 0).\n"
            "3. Jangka waktu permohonan informasi adalah 10 hari kerja (dapat diperpanjang 7 hari kerja).\n"
            "4. Alamat kantor di Jl. Letkol Isdiman No. 17A, Purbalingga. Telepon: (0281) 891040.\n"
            "5. JANGAN menjawab hal di luar konteks pemerintahan, PPID, dan Kabupaten Purbalingga."
        )

        try:
            url = f"{settings.DEEPSEEK_BASE_URL}/chat/completions"
            payload = {
                "model": settings.DEEPSEEK_MODEL,
                "messages": [
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": query}
                ],
                "max_tokens": 400,
                "temperature": 0.3
            }
            headers = {
                "Authorization": f"Bearer {api_key}",
                "Content-Type": "application/json"
            }
            res = requests.post(url, json=payload, headers=headers, timeout=12)
            if res.status_code == 200:
                data = res.json()
                return data["choices"][0]["message"]["content"]
        except Exception as e:
            print(f"[DeepSeek Error]: {e}")
        return None

    def get_template_buttons(self) -> List[str]:
        return self.knowledge.get("template_buttons", [])

    def get_org_info(self) -> Dict[str, Any]:
        return self.knowledge.get("organization", {})
