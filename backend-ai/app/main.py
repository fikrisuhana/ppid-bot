from fastapi import FastAPI, Request, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import datetime
import os

from app.config import settings
from app.cache import cache_instance
from app.rag_engine import RAGEngine

app = FastAPI(title=settings.APP_NAME, debug=settings.DEBUG_MODE)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

data_file = os.path.join(os.path.dirname(os.path.dirname(__file__)), "data", "ppid_knowledge.json")
rag_engine = RAGEngine(data_path=data_file)

class AskRequest(BaseModel):
    question: str
    env: str = "ppid"

@app.get("/")
def root():
    return {
        "status": "online",
        "service": settings.APP_NAME,
        "llm_provider": settings.LLM_PROVIDER,
        "deepseek_configured": bool(settings.DEEPSEEK_API_KEY)
    }

@app.get("/test")
def test_endpoint():
    return {
        "status": "success",
        "message": "PPID Bot Purbalingga Backend is working properly",
        "timestamp": str(datetime.datetime.now())
    }

@app.get("/templates")
def get_templates():
    return {
        "templates": rag_engine.get_template_buttons()
    }

@app.post("/ask")
def ask_chatbot(req: AskRequest):
    question = req.question.strip()
    if not question:
        raise HTTPException(status_code=400, detail="Pertanyaan tidak boleh kosong")

    # 1. Cek In-Memory Cache (0 Token Cost)
    cached_res = cache_instance.get(question)
    if cached_res:
        cached_res["source"] = "cache"
        return cached_res

    # 2. Cek RAG Database Lokal & Format Baku PPID Purbalingga (0 Token Cost)
    match = rag_engine.search_faq(question)
    if match:
        response_data = {
            "status": "success",
            "answer": match["answer"],
            "links": match["links"],
            "source": match.get("source", "knowledge_base"),
            "confidence": match["confidence"]
        }
        cache_instance.set(question, response_data)
        return response_data

    # 3. Format Balasan Baku Standar PPID (Fallback Terstruktur)
    fallback_data = rag_engine.format_fallback_response(question)
    return fallback_data
