# 🛠️ Panduan Instalasi & Menjalankan PPID Bot Purbalingga

## 1. Menjalankan Backend AI (Python)

### Persyaratan:
- Python 3.9+

### Langkah-langkah:
```bash
# 1. Masuk ke direktori backend
cd backend-ai

# 2. Buat virtual environment (opsional tapi disarankan)
python -m venv venv
venv\Scripts\activate   # Untuk Windows
# source venv/bin/activate # Untuk Linux/Mac

# 3. Install dependencies
pip install -r requirements.txt

# 4. Jalankan server FastAPI
uvicorn app.main:app --host 0.0.0.0 --port 5000 --reload
```

Server akan aktif di: `http://localhost:5000`
- Dokumentasi Swagger API: `http://localhost:5000/docs`
- Endpoint Test: `http://localhost:5000/test`
- Endpoint Tanya: `POST http://localhost:5000/ask`

---

## 2. Instalasi Plugin di WordPress

1. Copy folder `wordpress-plugin/ppid-bot-purbalingga` ke direktori `wp-content/plugins/` di server WordPress Anda.
2. Buka dashboard WordPress -> **Plugins (Plugin Terpasang)**.
3. Klik **Aktifkan (Activate)** pada plugin **PPID Bot Purbalingga**.
4. Buka menu **Pengaturan -> PPID Bot Settings**:
   - Masukkan URL Backend AI (contoh: `http://127.0.0.1:5000/ask` atau IP server internal).
   - Masukkan Nomor WhatsApp Helpdesk Petugas (contoh: `6281234567890`).
5. Simpan pengaturan. Widget chatbot otomatis muncul di pojok kiri bawah website.

---

## 3. Uji Coba Standalone Frontend

Buka file `frontend-widget/index.html` langsung di browser Anda untuk melihat demo tampilan dan menguji interaksi chatbot.
