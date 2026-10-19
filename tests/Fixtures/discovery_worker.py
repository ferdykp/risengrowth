"""Real subprocess smoke test; only HTTP responses are fixtures, never live data."""
import sys
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scraper'))
import httpx
import main

original_client = httpx.Client

def fixture_response(request):
    if request.url.path.endswith('stories.json'):
        return httpx.Response(200, json=[7001, 7002, 7003])
    item_id = int(request.url.path.rsplit('/', 1)[-1].split('.')[0])
    titles = {7001: 'Looking for someone to build a website.', 7002: 'My portfolio: website development', 7003: 'Website project details'}
    return httpx.Response(200, json={'id': item_id, 'type': 'story', 'title': titles[item_id], 'by': 'fixture-customer', 'time': 1792238400})

def fixture_client(**kwargs):
    return original_client(transport=httpx.MockTransport(fixture_response), **kwargs)

if __name__ == '__main__':
    with patch.object(main.httpx, 'Client', fixture_client), patch.object(main.time, 'sleep'):
        sys.exit(main.main())
