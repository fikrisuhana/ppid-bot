# 🏛️ Arsitektur Sistem PPID Bot Purbalingga

Dokumen ini menjelaskan arsitektur menyeluruh, strategi mitigasi token LLM, dan integrasi dual-mode antara Chatbot AI Otomatis dan Live Agent (Helo Min / JivoChat).

---

## 1. Topologi Komponen

```
+-------------------------------------------------------------------+
|                        PENGUNJUNG WEB                             |
+---------------------------------+---------------------------------+
                                  |
                                  v
+---------------------------------+---------------------------------+
|              FRONTEND WIDGET (HTML / CSS / JS)                    |
| - Floating Button "Tanya PPIDbot" (Pojok Kiri Bawah)             |
| - Menu Accordion "Pertanyaan Template"                           |
| - Tombol Eskalasi: "Chat Petugas (Live Agent / WA)"               |
+---------------------------------+---------------------------------+
                                  |
                                  | (AJAX Request / X-WP-Nonce)
                                  v
+---------------------------------+---------------------------------+
|                   WORDPRESS REST API BRIDGE                       |
|   Endpoint: /wp-json/chatbot/v1/ask                               |
| - Sanitasi Input & Nonce Verification                             |
| - Proxy Forwarding ke Backend AI Internal                         |
+---------------------------------+---------------------------------+
                                  |
                                  | (HTTP JSON Post)
                                  v
+---------------------------------+---------------------------------+
|            BACKEND AI SERVICE (PYTHON FASTAPI)                    |
|                                                                   |
| [Layer 1: In-Memory Semantic Cache]                               |
|   -> Cek apakah pertanyaan sudah ada di cache 24 jam               |
|                                                                   |
| [Layer 2: Hybrid RAG Search (FAQ & SOP PPID Purbalingga)]         |
|   -> Pencocokan keyword + Sequence similarity score                |
|   -> Kembalikan jawaban resmi + Tautan Download Formulir/SOP      |
|                                                                   |
| [Layer 3: Optional LLM Synthesis (DeepSeek / Gemini / Ollama)]    |
|   -> Hanya dipanggil jika skor relevansi butuh penalaran khusus   |
|   -> Prompt dikunci (guardrail) khusus topik PPID Purbalingga     |
+-------------------------------------------------------------------+
```

---

## 2. Strategi Mitigasi Overuse Token & Penghematan Biaya

1. **Pertanyaan Template (Zero-Token Cost)**:
   - Pertanyaan seperti *"Bagaimana cara permohonan informasi?"* atau *"Berapa biayanya?"* dijawab langsung oleh database internal tanpa memanggil LLM berbayar.
2. **In-Memory Caching**:
   - Setiap pertanyaan yang berhasil dijawab disimpan dalam cache selama 24 jam. Jika 100 pengunjung menanyakan hal yang sama, LLM hanya dipanggil 1 kali.
3. **Guardrails Prompt**:
   - Backend membatasi panjang input maksimal (250 karakter) untuk mencegah prompt injection atau scraping.
