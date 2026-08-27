import os
import json
import re
import requests
from typing import Dict, Any, List, Optional
from difflib import SequenceMatcher

class RAGEngine:
    """
    Engine pencarian semantik dan Response Formatter Baku untuk PPID Kabupaten Purbalingga.
    """
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

    def handle_special_intents(self, query: str) -> Optional[Dict[str, Any]]:
        """
        Deteksi percakapan umum seperti Salam, Sapaan, Terima Kasih, dll.
        """
        q = self._clean_text(query)
        words = q.split()
        
        # 1. Salam / Sapaan
        greetings = ["halo", "hallo", "hai", "hi", "selamat pagi", "selamat siang", "selamat sore", "selamat malam", "assalamualaikum", "assalamu alaikum", "pagi", "siang", "sore", "malam", "ping", "p", "tes", "test"]
        if q in greetings or any(w in ["halo", "hallo", "hai", "assalamualaikum"] for w in words):
            return {
                "answer": (
                    "Selamat Datang di **PPIDbot - Layanan Informasi Publik Kabupaten Purbalingga**! 👋🏛️\n\n"
                    "Saya asisten virtual yang siap membantu Anda mendapatkan informasi seputar:\n"
                    "• 📋 **Prosedur & Syarat Permohonan Informasi Publik**\n"
                    "• ⏱️ **Jangka Waktu Layanan & Biaya (Gratis Rp 0)**\n"
                    "• 🏢 **Daftar 18 Dinas / OPD Pemerintah Kabupaten Purbalingga**\n"
                    "• ⚖️ **Tata Cara Pengajuan Keberatan Informasi Publik**\n"
                    "• 📍 **Alamat & Kontak Desk Layanan PPID Dinkominfo**\n\n"
                    "Silakan ketik pertanyaan Anda, atau pilih salah satu menu di **⚡ Pertanyaan Template**."
                ),
                "links": [
                    {"title": "Formulir Permohonan Informasi", "url": "http://ppid.purbalinggakab.go.id/permohonan-informasi/"},
                    {"title": "Portal Resmi Pemkab Purbalingga", "url": "https://purbalinggakab.go.id"}
                ],
                "confidence": 1.0,
                "matched_question": "Sapaan / Greetings",
                "source": "template_intent"
            }

        # 2. Ucapan Terima Kasih
        gratitude = ["terima kasih", "makasih", "matur nuwun", "suwun", "thanks", "thank you", "tq", "mksh", "ok makasih", "oke makasih"]
        if q in gratitude or any(w in ["makasih", "suwun", "thanks"] for w in words):
            return {
                "answer": (
                    "Sama-sama! Senang bisa membantu Anda. 🙏😊\n\n"
                    "Jika masih ada dokumen atau informasi publik lain yang Anda butuhkan mengenai Pemerintah Kabupaten Purbalingga, silakan tanyakan kembali kapan saja.\n\n"
                    "💡 *Anda juga dapat menghubungi petugas melalui tombol **Konsultasi Publik** di sebelah kanan bawah.*"
                ),
                "links": [],
                "confidence": 1.0,
                "matched_question": "Terima Kasih",
                "source": "template_intent"
            }

        return None

    def search_faq(self, query: str) -> Optional[Dict[str, Any]]:
        # 1. Cek Intent Khusus (Salam/Terima Kasih)
        special = self.handle_special_intents(query)
        if special:
            return special

        cleaned_query = self._clean_text(query)
        query_words = set(cleaned_query.split())
        
        best_match = None
        highest_score = 0.0

        for faq in self.knowledge.get("faqs", []):
            score = 0.0
            is_curated = faq.get("is_curated", False)
            
            # Cek Keyword Match
            keywords = faq.get("keywords", [])
            for kw in keywords:
                cleaned_kw = self._clean_text(kw)
                kw_words = set(cleaned_kw.split())
                if cleaned_kw in cleaned_query or cleaned_query in cleaned_kw:
                    score += 1.8 if is_curated else 0.5
                overlap = len(query_words.intersection(kw_words))
                if overlap > 0:
                    score += (overlap / len(kw_words)) * (1.2 if is_curated else 0.3)

            # Cek Kemiripan Kalimat
            q_similarity = self._calculate_similarity(cleaned_query, self._clean_text(faq.get("question", "")))
            if is_curated:
                q_similarity *= 1.4
            score = max(score, q_similarity)

            if score > highest_score:
                highest_score = score
                best_match = faq

        # Threshold pencarian
        if highest_score >= 0.45 and best_match:
            is_curated = best_match.get("is_curated", False)
            raw_answer = best_match["answer"]
            links = best_match.get("links", [])
            
            # Format baku berdasarkan tipe data
            if is_curated:
                formatted_answer = raw_answer.strip()
                if "💡" not in formatted_answer:
                    formatted_answer += "\n\n💡 *Butuh bantuan lebih lanjut? Silakan gunakan fitur **Konsultasi Publik** di pojok kanan bawah untuk terhubung langsung dengan petugas PPID.*"
            else:
                # Format Baku untuk Artikel / Sitemap Scraped Posts
                clean_c = re.sub(r'\[vc_[^\]]+\]', '', raw_answer)
                clean_c = re.sub(r'https?://[^\s]+', '', clean_c)
                clean_c = re.sub(r'\s+', ' ', clean_c).strip()
                
                sentences = [s.strip() for s in re.split(r'(?<=[.!?])\s+', clean_c) if len(s.strip()) > 20]
                summary = " ".join(sentences[:3]) if sentences else clean_c[:220] + "..."
                
                title = best_match.get("question", "Informasi Publik")
                url = links[0]["url"] if links else "https://ppid.purbalinggakab.go.id"
                
                formatted_answer = (
                    f"Berdasarkan pangkalan data publik PPID mengenai **{title}**:\n\n"
                    f"📄 **Ringkasan**:\n{summary}\n\n"
                    f"💡 *Jika dokumen lengkap yang Anda cari belum tercantum di halaman ini, Anda berhak mengajukan **Permohonan Informasi Publik** secara gratis melalui portal PPID.*"
                )

            return {
                "answer": formatted_answer,
                "links": links,
                "confidence": round(highest_score, 2),
                "matched_question": best_match["question"],
                "source": "knowledge_base"
            }

        return None

    def format_fallback_response(self, query: str) -> Dict[str, Any]:
        """
        Format balasan baku ketika pertanyaan belum ditemukan di basis data.
        """
        fallback_text = (
            f"Mohon maaf, informasi spesifik mengenai **\"{query}\"** belum ditemukan secara otomatis dalam basis data ringkas kami. 🙇‍♂️\n\n"
            "Sebagai alternatif, Anda dapat:\n"
            "1. 📝 **Permohonan Informasi**: Ajukan permintaan dokumen resmi secara online melalui menu [Permohonan Informasi](http://ppid.purbalinggakab.go.id/permohonan-informasi/) (Layanan GRATIS Rp 0, proses 10 hari kerja).\n"
            "2. 💬 **Konsultasi Publik**: Klik tombol *Konsultasi Publik* di pojok kanan bawah untuk chat langsung dengan petugas PPID.\n"
            "3. 📞 **Desk PPID Dinkominfo**: Hubungi kami di telepon (0281) 891040 (Senin - Jumat jam kerja)."
        )
        return {
            "status": "success",
            "answer": fallback_text,
            "links": [
                {"title": "Formulir Permohonan Informasi", "url": "http://ppid.purbalinggakab.go.id/permohonan-informasi/"},
                {"title": "Portal Resmi PPID Purbalingga", "url": "https://ppid.purbalinggakab.go.id"},
                {"title": "Halaman Kontak & Lokasi", "url": "http://ppid.purbalinggakab.go.id/kontak/"}
            ],
            "source": "standard_fallback",
            "confidence": 0.0
        }

    def get_template_buttons(self) -> List[str]:
        return [
            "Bagaimana cara mengajukan permohonan informasi?",
            "Berapa biaya permohonan informasi publik?",
            "Berapa lama proses pelayanan permohonan informasi?",
            "Apa saja daftar 18 Dinas Daerah di Purbalingga?",
            "Bagaimana alur pengajuan keberatan informasi?",
            "Dimana alamat dan kontak Desk Layanan PPID?"
        ]

    def get_org_info(self) -> Dict[str, Any]:
        return self.knowledge.get("organization", {})
