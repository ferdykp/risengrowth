import sys
import unittest
from pathlib import Path
from unittest.mock import patch, MagicMock
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from main import extract_hn_item, scrape_hackernews


class HackerNewsTest(unittest.TestCase):
    def test_matching_story(self):
        item = {"id": 123, "type": "story", "title": "Looking for website help", "text": "<p>Need a developer</p>", "by": "alice"}
        result = extract_hn_item(item, "website")
        self.assertEqual(result["external_post_id"], "hn-123")
        self.assertEqual(result["permalink"], "https://news.ycombinator.com/item?id=123")
        self.assertEqual(result["username"], "alice")
        self.assertNotIn("<p>", result["body"])

    def test_irrelevant_and_deleted(self):
        self.assertIsNone(extract_hn_item({"id": 1, "type": "story", "title": "Weather"}, "website"))
        self.assertIsNone(extract_hn_item({"id": 2, "type": "story", "title": "Website", "deleted": True}, "website"))

    @patch("main.httpx.Client")
    def test_fetch_and_dedupe_ids(self, mocked_client):
        client = mocked_client.return_value.__enter__.return_value
        def fake_get(url, **kwargs):
            response = MagicMock()
            if url.endswith("askstories.json") or url.endswith("newstories.json"):
                response.json.return_value = [123, 123]
            else:
                response.json.return_value = {"id": 123, "type": "story", "title": "Need website developer", "by": "alice"}
            return response
        client.get.side_effect = fake_get
        posts = scrape_hackernews({"id": "hn", "feeds": ["askstories", "newstories"], "max_items": 20}, "website")
        self.assertEqual(len(posts), 1)
        self.assertEqual(client.get.call_count, 3)

if __name__ == "__main__":
    unittest.main()
