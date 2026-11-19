from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_health_endpoint():
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json()["status"] == "ok"


def test_generation_is_explicitly_marked_as_simulated_by_default():
    response = client.post(
        "/api/v1/generate",
        json={
            "topic": "Sonnenbrillen im Alltag",
            "channel": "instagram",
            "audience": "Erwachsene",
        },
    )
    assert response.status_code == 200
    assert response.json()["simulated"] is True
    assert response.json()["provider"] == "deterministic-mock"
