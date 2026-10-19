import unittest
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from main import extract

class ExtractTest(unittest.TestCase):
    def test_keyword_and_dedup_identity(self):
        html = '<div class="quote"><span class="text">Need a Website now</span><small class="author">Alice</small><a href="/p/1">link</a></div>'
        source = {"id": "fixture", "item_selector": ".quote", "text_selector": ".text", "author_selector": ".author", "link_selector": "a"}
        posts = extract(html, source, "https://example.org", "website")
        self.assertEqual(len(posts), 1)
        self.assertEqual(posts[0]["username"], "Alice")
        self.assertEqual(posts[0]["permalink"], "https://example.org/p/1")
        self.assertEqual(extract(html, source, "https://example.org", "pos"), [])

if __name__ == '__main__':
    unittest.main()

class BoundaryTest(unittest.TestCase):
    def test_unicode_and_phrase_boundaries(self):
        from main import matches_keyword
        self.assertFalse(matches_keyword('positive result', 'pos'))
        self.assertFalse(matches_keyword('websites', 'website'))
        self.assertTrue(matches_keyword('Need WEBSITE   help', 'website help'))
        self.assertTrue(matches_keyword('café website', 'café'))

    def test_missing_content_and_malformed_html(self):
        source = {'id': 'fixture', 'item_selector': 'article', 'text_selector': 'p', 'link_selector': 'a'}
        self.assertEqual(extract('<article><span>website</span></article>', source, 'https://example.org', 'website'), [])
        posts = extract('<article><p>Need website<a href="/one">one', source, 'https://example.org', 'website')
        self.assertEqual(len(posts), 1)

    def test_post_identity_survives_body_edit(self):
        source = {'id': 'fixture', 'item_selector': 'article', 'link_selector': 'a'}
        first = extract('<article>Need website<a href="/one">one</a></article>', source, 'https://example.org', 'website')
        edited = extract('<article>Need website today<a href="/one">one</a></article>', source, 'https://example.org', 'website')
        self.assertEqual(first[0]['external_post_id'], edited[0]['external_post_id'])
