import pytest
from fastapi.testclient import TestClient

from app.main import app, settings, _split_chunks


@pytest.fixture(autouse=True)
def use_deterministic_provider(monkeypatch):
    monkeypatch.setattr(settings, 'ai_provider', 'mock')


client = TestClient(app)


def test_health_reports_mock_provider():
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json()["provider"] == "mock"


def test_generate_returns_deterministic_mock():
    response = client.post(
        "/api/v1/generate",
        json={"topic": "summer sunglasses", "channel": "instagram", "audience": "adults", "tone": "friendly"},
    )
    assert response.status_code == 200
    assert response.json()["simulated"] is True
    assert response.json()["provider"] == "deterministic-mock"


def test_ideas_returns_exactly_five_synthetic_ideas():
    response = client.post(
        "/api/v1/ideas",
        json={"campaign": "Summer campaign", "audience": "Adults", "channels": ["instagram", "facebook"]},
    )
    assert response.status_code == 200
    assert len(response.json()["ideas"]) == 5
    assert response.json()["simulated"] is True


def test_brief_returns_requested_channel_variants_and_human_review_warning():
    response = client.post(
        "/api/v1/brief",
        json={
            "title": "Summer eye protection",
            "topic": "Sunglasses for summer",
            "audience": "Adults aged 25-45",
            "tone": "friendly",
            "channels": ["instagram", "facebook", "linkedin"],
            "scheduledAt": "2026-12-01T10:00:00+01:00",
            "mediaReference": "media://sample/summer",
            "useKnowledgeBase": False,
        },
    )
    assert response.status_code == 200
    body = response.json()
    assert set(body["channelVariants"]) == {"instagram", "facebook", "linkedin"}
    assert body["ai"]["simulated"] is True
    assert body["ai"]["provider"] == "deterministic-mock"
    assert body["effortMinutes"]["total"] == 90
    assert any("human review" in item.lower() for item in body["assumptions"])


def test_brief_rejects_missing_media_reference():
    response = client.post(
        "/api/v1/brief",
        json={
            "title": "Summer",
            "topic": "Sunglasses for summer",
            "audience": "Adults",
            "channels": ["instagram"],
            "scheduledAt": "2026-12-01T10:00:00+01:00",
            "mediaReference": "",
        },
    )
    assert response.status_code == 422


def test_knowledge_chunking_has_overlap_and_preserves_content():
    text = "A" * 1000 + " " + "B" * 1000
    chunks = _split_chunks(text, size=900, overlap=120)
    assert len(chunks) >= 2
    assert all(chunks)
    assert "".join(chunks).count("A") >= 1000
    assert "".join(chunks).count("B") >= 1000


def test_rag_endpoint_explains_missing_database_configuration(monkeypatch):
    monkeypatch.setattr(settings, "database_url", None)
    response = client.post(
        "/api/v1/knowledge",
        json={"sourceId": "brand-guide-001", "title": "Brand guide", "text": "This is a sufficiently long synthetic brand guideline text."},
    )
    assert response.status_code == 503
    assert "DATABASE_URL" in response.json()["detail"]
