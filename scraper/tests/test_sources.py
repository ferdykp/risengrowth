import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
import httpx
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import main


class SourceTest(unittest.TestCase):
    def test_rss_extracts_unicode_date_and_stable_identity(self):
        xml = '<rss><channel><item><guid>one</guid><title>Need a website for café</title><link>https://example.org/one</link><author>Alice</author><pubDate>Sat, 17 Oct 2026 12:00:00 GMT</pubDate></item></channel></rss>'
        posts = main.extract_rss(xml, {'id': 'feed'}, 'website')
        self.assertEqual(len(posts), 1)
        self.assertEqual(posts[0]['username'], 'Alice')
        self.assertEqual(posts[0]['posted_at'], '2026-10-17T12:00:00+00:00')
        self.assertIn('café', posts[0]['body'])

    def test_rss_malformed_xml_is_failure(self):
        with self.assertRaises(main.ET.ParseError):
            main.extract_rss('<rss><item>', {'id': 'feed'}, 'website')

    @patch('main.socket.getaddrinfo', return_value=[(2, 1, 6, '', ('10.0.0.1', 443))])
    def test_private_and_loopback_source_addresses_are_rejected(self, resolver):
        for url in ['https://internal.example', 'https://127.0.0.1', 'https://[::1]', 'http://example.org', 'https://user:pass@example.org', 'https://example.org:444']:
            self.assertFalse(main.source_is_valid({'url': url}))

    def test_source_timeout_and_http_errors_are_retained_while_other_sources_continue(self):
        source = {'id': 'failed', 'enabled': True, 'mode': 'http', 'url': 'https://example.org'}
        successful = {'id': 'working', 'enabled': True, 'mode': 'rss', 'url': 'https://example.org/feed'}
        for error in [httpx.ReadTimeout('timeout'), httpx.HTTPStatusError('denied', request=httpx.Request('GET', source['url']), response=httpx.Response(403))]:
            with self.subTest(error=type(error).__name__), tempfile.TemporaryDirectory() as directory:
                config = Path(directory) / 'sources.json'
                config.write_text(json.dumps({'sources': [source, successful]}))
                result_post = {'external_post_id': 'one', 'body': 'Need website', 'permalink': 'https://example.org/one', 'source': 'working'}
                with patch('main.source_is_valid', return_value=True), patch.dict(main.SOURCE_ADAPTERS, {'http': lambda *_: (_ for _ in ()).throw(error), 'rss': lambda *_: [result_post]}):
                    result = main.run('website', config)
                self.assertEqual(result['posts'], [result_post])
                self.assertEqual(result['errors'][0]['source'], 'failed')
                self.assertEqual(result['sources_configured'], 2)

    def test_html_pagination_stays_on_configured_source_and_deduplicates_pages(self):
        real_client = httpx.Client
        def handler(request):
            return httpx.Response(200, headers={'content-type': 'text/html'}, text='<article>Need website<a href="/post">post</a></article><a class="next" href="/page2">next</a>')
        source = {'id': 'html', 'url': 'https://example.org', 'item_selector': 'article', 'link_selector': 'a', 'next_selector': '.next', 'max_pages': 5}
        with patch('main.permitted_url', return_value=True), patch('main.time.sleep'), patch('main.httpx.Client', side_effect=lambda **kwargs: real_client(transport=httpx.MockTransport(handler), **kwargs)):
            posts = main.scrape_http(source, 'website')
        self.assertEqual(len(posts), 2)
        self.assertEqual(posts[0]['external_post_id'], posts[1]['external_post_id'])


class BoundaryContractTest(unittest.TestCase):
    def test_invalid_source_ports_and_payloads_are_rejected(self):
        for url in ["https://example.org:invalid", "https://[invalid", None]:
            self.assertFalse(main.source_is_valid({"url": url}))
        with tempfile.TemporaryDirectory() as directory:
            config = Path(directory) / "invalid.json"
            for payload in [[], {"sources": "invalid"}, {"sources": [None]}]:
                config.write_text(json.dumps(payload))
                with self.assertRaises(ValueError):
                    main.run("website", config)

    def test_bounded_fetch_refuses_redirect_and_oversized_response(self):
        for response in [httpx.Response(302, headers={"location": "https://127.0.0.1"}),
                         httpx.Response(200, content=b"x" * 2_000_001)]:
            with httpx.Client(transport=httpx.MockTransport(lambda _: response)) as client:
                with self.assertRaises(ValueError):
                    main.fetch_bounded(client, "https://example.org", 2)

    def test_empty_config_is_explicit_and_does_not_fabricate_posts(self):
        with tempfile.TemporaryDirectory() as directory:
            config = Path(directory) / "empty.json"
            config.write_text(json.dumps({"sources": []}))
            self.assertEqual(main.run("website", config), {"posts": [], "errors": [], "sources_configured": 0})
