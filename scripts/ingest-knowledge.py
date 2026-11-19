#!/usr/bin/env python3
"""Load the committed synthetic knowledge documents into the local AI service."""
import json
import os
from pathlib import Path
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[1]
DOCUMENTS = ROOT / "data" / "synthetic" / "knowledge-documents.json"
BASE_URL = os.environ.get("AI_SERVICE_URL", "http://localhost:8001").rstrip("/")

def main() -> None:
    documents = json.loads(DOCUMENTS.read_text(encoding="utf-8"))
    for document in documents:
        payload = json.dumps(document).encode("utf-8")
        request = Request(f"{BASE_URL}/api/v1/knowledge", data=payload,
                          headers={"Content-Type": "application/json", "Accept": "application/json"},
                          method="POST")
        with urlopen(request, timeout=120) as response:
            result = json.loads(response.read().decode("utf-8"))
        print(f"{result['sourceId']}: stored {result['chunksStored']} chunks")

if __name__ == "__main__":
    main()
