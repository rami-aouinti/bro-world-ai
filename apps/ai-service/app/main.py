from typing import Literal

import httpx
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")
    ai_provider: Literal["mock", "ollama"] = "mock"
    ollama_base_url: str = "http://localhost:11434"
    ollama_model: str = "llama3.2:3b"


settings = Settings()
app = FastAPI(title="Bro World AI Service", version="0.1.0")


class GenerateRequest(BaseModel):
    topic: str = Field(min_length=3, max_length=300)
    channel: Literal["instagram", "facebook", "tiktok", "linkedin"]
    audience: str = Field(min_length=2, max_length=200)
    tone: str = Field(default="friendly and personal", max_length=120)
    context: list[str] = Field(default_factory=list)


class GenerateResponse(BaseModel):
    text: str
    provider: str
    simulated: bool


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok", "service": "ai-service"}


@app.post("/api/v1/generate", response_model=GenerateResponse)
async def generate(payload: GenerateRequest) -> GenerateResponse:
    prompt = (
        "Write a factual social media draft for a fictional optician. "
        f"Topic: {payload.topic}. Channel: {payload.channel}. "
        f"Audience: {payload.audience}. Tone: {payload.tone}. "
        f"Use only this supplied context: {' | '.join(payload.context)}. "
        "Do not invent product claims, prices, medical advice or statistics. "
        "If context is insufficient, say so."
    )

    if settings.ai_provider == "mock":
        return GenerateResponse(
            text=(
                f"[SIMULATED TEST OUTPUT] {payload.topic}. "
                "Gutes Sehen beginnt mit einer Beratung, die zu Ihrem Alltag passt. "
                "Vereinbaren Sie einen unverbindlichen Beratungstermin: "
                "https://example.com/beratung"
            ),
            provider="deterministic-mock",
            simulated=True,
        )

    try:
        async with httpx.AsyncClient(timeout=90) as client:
            response = await client.post(
                f"{settings.ollama_base_url.rstrip('/')}/api/generate",
                json={"model": settings.ollama_model, "prompt": prompt, "stream": False},
            )
            response.raise_for_status()
            result = response.json()
    except (httpx.HTTPError, ValueError) as exc:
        raise HTTPException(status_code=502, detail="LLM provider unavailable") from exc

    return GenerateResponse(
        text=result.get("response", "").strip(),
        provider=f"ollama:{settings.ollama_model}",
        simulated=False,
    )
