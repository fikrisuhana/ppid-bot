import os
from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    APP_NAME: str = "PPID Bot Purbalingga AI Engine"
    HOST: str = "0.0.0.0"
    PORT: int = 5000
    DEBUG_MODE: bool = True
    
    # LLM Provider: "deepseek", "openai", "gemini", "ollama", "none"
    LLM_PROVIDER: str = os.getenv("LLM_PROVIDER", "deepseek")
    DEEPSEEK_API_KEY: str = os.getenv("DEEPSEEK_API_KEY", "")
    DEEPSEEK_BASE_URL: str = os.getenv("DEEPSEEK_BASE_URL", "https://api.deepseek.com")
    DEEPSEEK_MODEL: str = os.getenv("DEEPSEEK_MODEL", "deepseek-chat")
    
    # Cache and Match Thresholds
    SIMILARITY_THRESHOLD: float = 0.55
    CACHE_EXPIRATION_SECONDS: int = 86400 # 24 Hours
    
    class Config:
        env_file = ".env"

settings = Settings()
