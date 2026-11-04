import json
import os
import sys
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scrapling.engines.toolbelt.custom import Response  # noqa: E402

import threads_scrapling as ts  # noqa: E402


def post(pk, code="C0de_1", username="buyer.one", text="Need a website for my shop", **extra):
    return {"pk": pk, "code": code, "user": {"username": username}, "caption": {"text": text},
            "taken_at": 1793000000, "like_count": 4,
            "text_post_app_info": {"direct_reply_count": 2, "repost_count": 1}, **extra}


def page(data=None, status=200, html="", url="https://www.threads.com/search?q=website", headers=None):
    script = f'<script type="application/json">{json.dumps(data)}</script>' if data is not None else ""
    return Response(url=url, content=f"<html><body>{script}{html}</body></html>".encode(), status=status,
                    reason="", cookies={}, headers=headers or {}, request_headers={})


class ParseTest(unittest.TestCase):
    def test_extracts_and_normalizes_nested_posts(self):
        data = {"require": [{"edges": [{"node": {"thread_items": [{"post": post("111")}]}}]}]}
        posts = ts.extract(page(data), "website")
        self.assertEqual(posts, [{
            "platform": "threads", "external_post_id": "111", "username": "buyer.one",
            "body": "Need a website for my shop", "permalink": "https://www.threads.com/@buyer.one/post/C0de_1",
            "source": "scrapling", "posted_at": "2026-10-26T07:33:20+00:00", "keyword": "website",
            "metrics": {"likes": 4, "replies": 2, "reposts": 1},
        }])

    def test_duplicate_posts_in_page_are_kept_once(self):
        posts = ts.extract(page([post("111"), post("111"), post("222", code="X2")]), "website")
        self.assertEqual([p["external_post_id"] for p in posts], ["111", "222"])

    def test_posts_missing_required_fields_are_skipped(self):
        broken = [post("abc"), post("1", code=None), post("2", username="bad name"), post("3", text=""),
                  {"pk": "4", "code": "Y", "user": {"username": "u"}, "caption": None}]
        posts = ts.extract(page(broken + [post("5", taken_at=None, like_count=None, text_post_app_info=None)]), "w")
        self.assertEqual(len(posts), 1)
        self.assertIsNone(posts[0]["posted_at"])
        self.assertEqual(posts[0]["metrics"], {})

    def test_invalid_json_and_no_posts_return_empty(self):
        self.assertEqual(ts.extract(page(html='<script type="application/json">{oops</script>'), "w"), [])
        self.assertEqual(ts.extract(page({"data": {}}), "w"), [])


class SearchTest(unittest.TestCase):
    def search_with(self, response=None, error=None):
        with mock.patch.object(ts, "fetch", side_effect=error, return_value=response):
            return ts.search("website")

    def test_empty_results_are_not_an_error(self):
        self.assertEqual(self.search_with(page({"data": {"results": []}})), [])

    def test_login_wall_is_reported(self):
        for response in (page(html='<form><input type="password"></form>'),
                         page(html="<p>Log in or sign up to see more</p>"),
                         page(status=302, headers={"Location": "https://www.threads.com/login"})):
            with self.assertRaisesRegex(ts.ScrapeError, "LOGIN_WALL"):
                self.search_with(response)

    def test_http_failures_are_reported(self):
        for status in (403, 429, 500):
            with self.assertRaisesRegex(ts.ScrapeError, f"HTTP_{status}"):
                self.search_with(page(status=status))

    def test_network_failure_is_reported(self):
        with self.assertRaisesRegex(ts.ScrapeError, "NETWORK_ERROR: TimeoutError"):
            self.search_with(error=TimeoutError())


class RunTest(unittest.TestCase):
    def test_partial_results_deduplicate_across_keywords(self):
        results = {"website": [ts.normalize(post("1"), "website")],
                   "aplikasi": [ts.normalize(post("1"), "aplikasi"), ts.normalize(post("2", code="Z"), "aplikasi")]}

        def searcher(keyword, mode, timeout):
            if keyword == "blocked":
                raise ts.ScrapeError("HTTP_429")
            if keyword == "broken":
                raise KeyError("x")
            return results[keyword]

        with mock.patch.object(ts.time, "sleep"):
            out = ts.run(["website", "blocked", "aplikasi", "broken"], searcher=searcher)
        self.assertEqual([p["external_post_id"] for p in out["posts"]], ["1", "2"])
        self.assertEqual(out["posts"][0]["keyword"], "website")
        self.assertEqual(out["errors"], [{"source": "scrapling", "error": "blocked: HTTP_429"},
                                         {"source": "scrapling", "error": "broken: UNEXPECTED_KeyError"}])
        self.assertEqual(out["sources_configured"], 4)


@unittest.skipUnless(os.environ.get("RISENGROWTH_LIVE_THREADS") == "1", "Set RISENGROWTH_LIVE_THREADS=1 to hit live Threads")
class LiveThreadsTest(unittest.TestCase):
    def test_live_search_returns_posts_or_a_reported_restriction(self):
        out = ts.run([os.environ.get("RISENGROWTH_LIVE_KEYWORD", "website")])
        print(json.dumps(out, ensure_ascii=False, indent=2)[:4000])
        self.assertTrue(out["posts"] or out["errors"])


if __name__ == "__main__":
    unittest.main()
