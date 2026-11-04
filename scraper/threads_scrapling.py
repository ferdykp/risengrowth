#!/usr/bin/env python3
"""Keyword search of public Threads pages using Scrapling. JSON only on stdout.

Output contract matches main.py: {"posts": [...], "errors": [...], "sources_configured": n}.
Threads often shows a login wall or blocks automated clients. Those cases are reported
as errors; they are never bypassed. No credentials, cookies or private APIs are used.
"""
import argparse
import json
import logging
import random
import re
import sys
import time
from datetime import datetime, timezone
from urllib.parse import quote

LOG = logging.getLogger("risen_scrapling")
SEARCH_URL = "https://www.threads.com/search?q={}&serp_type=recent"
USERNAME = re.compile(r"\A[a-zA-Z0-9._]{1,191}\Z")
CODE = re.compile(r"\A[a-zA-Z0-9_-]{1,64}\Z")
LOGIN_TEXT = re.compile(r"log in (or sign up )?(to|with) ", re.IGNORECASE)


class ScrapeError(Exception):
    """Expected failure reported per keyword, e.g. LOGIN_WALL or HTTP_429."""


def fetch(url, mode, timeout, scrolls=0):
    """HTTP fetch by default; browser rendering only when explicitly configured.

    Returns (page, extra_json_texts). In browser mode the page is scrolled `scrolls` times and the
    JSON bodies the page itself loads while scrolling are returned, since later posts arrive that way.
    """
    if mode != "browser":
        from scrapling.fetchers import Fetcher
        return Fetcher.get(url, timeout=timeout, follow_redirects=False, retries=1), []
    from scrapling.fetchers import StealthyFetcher
    responses, texts = [], []

    def setup(page):
        page.on("response", lambda r: responses.append(r)
                if "json" in (r.headers.get("content-type") or "") or r.request.method == "POST" else None)

    def scroll(page):
        for _ in range(scrolls):
            page.mouse.wheel(0, 4000)
            page.wait_for_timeout(random.randint(1500, 3000))
        for response in responses:
            try:
                texts.append(response.text())
            except Exception:  # body gone or not text
                continue

    page = StealthyFetcher.fetch(url, headless=True, network_idle=True, timeout=int(timeout * 1000),
                                 solve_cloudflare=False, block_webrtc=True, page_setup=setup, page_action=scroll)
    return page, texts


def parse_json_texts(text):
    """Yield JSON values from a response body: plain JSON, a guarded prefix like `for (;;);`, or JSON lines."""
    start = min((i for i in (text.find("{"), text.find("[")) if i >= 0), default=-1)
    if start < 0:
        return
    try:
        yield json.loads(text[start:])
        return
    except ValueError:
        pass
    for line in text[start:].splitlines():
        try:
            yield json.loads(line)
        except ValueError:
            continue


def iter_post_objects(node):
    """Yield every dict shaped like a Threads post anywhere in the embedded page JSON."""
    if isinstance(node, dict):
        if "pk" in node and "code" in node and isinstance(node.get("user"), dict):
            yield node
        for value in node.values():
            yield from iter_post_objects(value)
    elif isinstance(node, list):
        for value in node:
            yield from iter_post_objects(value)


def normalize(raw, keyword):
    """Map one embedded Threads post to the shared worker schema; None when required fields are missing."""
    pk = str(raw.get("pk") or "")
    code = raw.get("code")
    username = raw["user"].get("username")
    caption = raw.get("caption")
    text = caption.get("text") if isinstance(caption, dict) else None
    text = text.strip() if isinstance(text, str) else ""
    if (not pk.isdigit() or len(pk) > 64 or not isinstance(code, str) or not CODE.match(code)
            or not isinstance(username, str) or not USERNAME.match(username) or not text):
        return None
    taken_at = raw.get("taken_at")
    info = raw.get("text_post_app_info") if isinstance(raw.get("text_post_app_info"), dict) else {}
    metrics = {"likes": raw.get("like_count"), "replies": info.get("direct_reply_count"),
               "reposts": info.get("repost_count"), "quotes": info.get("quote_count")}
    return {
        "platform": "threads", "external_post_id": pk, "username": username, "body": text[:20000],
        "permalink": f"https://www.threads.com/@{username}/post/{code}", "source": "scrapling",
        "posted_at": datetime.fromtimestamp(taken_at, timezone.utc).isoformat() if type(taken_at) is int else None,
        "keyword": keyword, "metrics": {k: v for k, v in metrics.items() if type(v) is int},
    }


def extract(page, keyword, extra_texts=()):
    """Parse posts from the JSON Threads embeds in the HTML, plus JSON loaded while scrolling."""
    posts = {}
    for blob in [*page.css('script[type="application/json"]::text').getall(), *extra_texts]:
        for data in parse_json_texts(blob):
            for raw in iter_post_objects(data):
                post = normalize(raw, keyword)
                if post:
                    posts.setdefault(post["external_post_id"], post)
    return list(posts.values())


def is_login_wall(page):
    return ("/login" in str(page.url) or bool(page.css('input[type="password"]'))
            or bool(LOGIN_TEXT.search(" ".join(page.css("body ::text").getall()))))


def search(keyword, mode="http", timeout=20, scrolls=0):
    try:
        page, extra_texts = fetch(SEARCH_URL.format(quote(keyword)), mode, timeout, scrolls)
    except Exception as exc:  # curl / browser transport errors
        raise ScrapeError("NETWORK_ERROR: " + type(exc).__name__) from exc
    if 300 <= page.status < 400:
        location = str({k.lower(): v for k, v in dict(page.headers).items()}.get("location", ""))
        raise ScrapeError("LOGIN_WALL" if "login" in location.lower() else f"HTTP_{page.status}_REDIRECT")
    if page.status >= 400:
        raise ScrapeError(f"HTTP_{page.status}")
    posts = extract(page, keyword, extra_texts)
    if not posts and is_login_wall(page):
        raise ScrapeError("LOGIN_WALL")
    return posts


def run(keywords, mode="http", timeout=20, max_posts=20, delay=15.0, searcher=search, scrolls=0):
    """Search each keyword, keep partial results when one fails, deduplicate by Threads post ID.

    Waits delay..1.5*delay seconds (random jitter) between keywords to lower the chance of a login wall.
    """
    posts, errors = {}, []
    for index, keyword in enumerate(keywords):
        if index:
            time.sleep(delay + random.uniform(0, delay / 2))
        try:
            for post in searcher(keyword, mode, timeout, scrolls)[:max_posts]:
                posts.setdefault(post["external_post_id"], post)
        except ScrapeError as exc:
            errors.append({"source": "scrapling", "error": f"{keyword[:60]}: {exc}"[:200]})
        except Exception as exc:
            LOG.warning("Unexpected Scrapling failure for %r: %s", keyword, type(exc).__name__)
            errors.append({"source": "scrapling", "error": f"{keyword[:60]}: UNEXPECTED_{type(exc).__name__}"[:200]})
    return {"posts": list(posts.values())[:200], "errors": errors, "sources_configured": len(keywords)}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--keyword", action="append", required=True, help="Repeat for several keywords")
    parser.add_argument("--mode", choices=["http", "browser"], default="http")
    parser.add_argument("--timeout", type=float, default=20)
    parser.add_argument("--max-posts", type=int, default=20)
    parser.add_argument("--scrolls", type=int, default=0, help="Browser mode only: times to scroll for more posts (0-15)")
    parser.add_argument("--delay", type=float, default=15.0, help="Seconds between keywords, plus up to 50%% jitter")
    args = parser.parse_args()
    keywords = [k.strip() for k in args.keyword]
    if any(not k or len(k) > 191 for k in keywords):
        parser.error("keyword must be 1..191 characters")
    result = run(keywords, args.mode, max(5.0, min(args.timeout, 60.0)),
                 max(1, min(args.max_posts, 100)), max(1.0, min(args.delay, 120.0)),
                 scrolls=max(0, min(args.scrolls, 15)))
    print(json.dumps(result, ensure_ascii=False))
    return 0 if not result["errors"] else 2


if __name__ == "__main__":
    logging.basicConfig(level=logging.WARNING, stream=sys.stderr)
    logging.getLogger("scrapling").setLevel(logging.WARNING)
    sys.exit(main())
