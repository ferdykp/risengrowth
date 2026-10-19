#!/usr/bin/env python3
"""Configured, permission-based HTML extraction. JSON only on stdout."""
import argparse
import hashlib
from datetime import datetime, timezone
import json
import logging
import ipaddress
import socket
import time
import xml.etree.ElementTree as ET
from email.utils import parsedate_to_datetime
import re
import sys
from pathlib import Path
from urllib.parse import urljoin, urlparse

import httpx
from bs4 import BeautifulSoup

LOG = logging.getLogger("risen_scraper")
ROOT = Path(__file__).resolve().parent


def matches_keyword(body, keyword):
    parts = keyword.strip().split()
    if not parts:
        return False
    pattern = r"(?<!\w)" + r"\s+".join(re.escape(p.casefold()) for p in parts) + r"(?!\w)"
    return re.search(pattern, body.casefold(), re.UNICODE) is not None


def source_is_valid(src):
    parsed = urlparse(src.get("url", ""))
    if (parsed.scheme != "https" or not parsed.hostname or parsed.username
            or parsed.password or parsed.port not in (None, 443)):
        return False
    try:
        addresses = socket.getaddrinfo(parsed.hostname, 443, type=socket.SOCK_STREAM)
        return bool(addresses) and all(ipaddress.ip_address(a[4][0]).is_global for a in addresses)
    except (OSError, ValueError):
        return False


def permitted_url(url, source):
    return urlparse(url).hostname == urlparse(source["url"]).hostname and source_is_valid({"url": url})


def extract(html, source, final_url, keyword):
    soup = BeautifulSoup(html, "html.parser")
    out = []
    for item in soup.select(source["item_selector"])[:150]:
        node = item.select_one(source.get("text_selector", "")) if source.get("text_selector") else item
        if node is None:
            continue
        body = re.sub(r"\s+", " ", node.get_text(" ", strip=True)).strip()
        if not body or not matches_keyword(body, keyword.casefold()):
            continue
        author_node = item.select_one(source["author_selector"]) if source.get("author_selector") else None
        link_node = item.select_one(source["link_selector"]) if source.get("link_selector") else None
        href = link_node.get("href") if link_node else None
        permalink = urljoin(final_url, href) if href else final_url
        if urlparse(permalink).scheme != "https" or not urlparse(permalink).hostname:
            continue
        uid = hashlib.sha256((source["id"] + "|" + permalink + ("|" + body if not href else "")).encode()).hexdigest()
        date_node = item.select_one(source["date_selector"]) if source.get("date_selector") else None
        published = date_node.get("datetime") if date_node else None
        try:
            published = datetime.fromisoformat(published.replace("Z", "+00:00")).isoformat() if published else None
        except (TypeError, ValueError):
            published = None
        out.append({"platform": "web", "external_post_id": uid,
                    "username": author_node.get_text(" ", strip=True)[:191] if author_node else source["id"],
                    "body": body[:20000], "permalink": permalink, "source": source["id"], "posted_at": published})
    return out


def source_timeout(source, maximum=10):
    remaining = source.get("_deadline", time.monotonic() + maximum) - time.monotonic()
    if remaining <= 0:
        raise TimeoutError("Source scan deadline exceeded")
    return min(maximum, remaining)


def scrape_http(source, keyword):
    found = []
    url = source["url"]
    visited = set()
    deadline = source.get("_deadline", time.monotonic() + 55)
    with httpx.Client(timeout=10, follow_redirects=False, headers={"User-Agent": "RisenGrowthResearch/1.0"}) as client:
        for _ in range(max(1, min(int(source.get("max_pages", 1)), 5))):
            if url in visited or time.monotonic() >= deadline:
                break
            if not permitted_url(url, source):
                raise ValueError("Page URL outside permitted public source")
            visited.add(url)
            response = client.get(url, timeout=source_timeout(source))
            response.raise_for_status()
            if response.is_redirect:
                raise RuntimeError("Redirect refused; configure final URL explicitly")
            if "text/html" not in response.headers.get("content-type", "").lower():
                raise RuntimeError("Expected HTML content-type")
            if len(response.content) > 2_000_000:
                raise RuntimeError("HTML response too large")
            found.extend(extract(response.text, source, str(response.url), keyword))
            next_node = BeautifulSoup(response.text, "html.parser").select_one(source["next_selector"]) if source.get("next_selector") else None
            if not next_node or not next_node.get("href"):
                break
            url = urljoin(url, next_node["href"])
            time.sleep(max(0.2, min(float(source.get("delay_seconds", 1)), 5)))
    return found[:max(1, min(int(source.get("max_items", 150)), 200))]


def extract_rss(xml, source, keyword):
    root = ET.fromstring(xml)
    entries = root.findall("./channel/item") + root.findall("{http://www.w3.org/2005/Atom}entry")
    posts = []
    for entry in entries[:200]:
        def value(name):
            return entry.findtext(name) or entry.findtext("{http://www.w3.org/2005/Atom}" + name) or ""
        body = BeautifulSoup(value("title") + " " + value("description") + " " + value("summary") + " " + value("content"), "html.parser").get_text(" ", strip=True)
        link = value("link")
        if not link:
            node = entry.find("{http://www.w3.org/2005/Atom}link")
            link = node.get("href", "") if node is not None else ""
        if not matches_keyword(body, keyword) or urlparse(link).scheme != "https" or not urlparse(link).hostname:
            continue
        published = value("pubDate") or value("published") or value("updated")
        try:
            published = (datetime.fromisoformat(published.replace("Z", "+00:00")).isoformat() if re.match(r"^\d{4}-", published) else parsedate_to_datetime(published).isoformat()) if published else None
        except (ValueError, TypeError):
            published = None
        identity = value("guid") or value("id") or link
        posts.append({"platform": "web", "external_post_id": hashlib.sha256((source["id"] + "|" + identity).encode()).hexdigest(),
                      "body": body[:20000], "permalink": link, "username": value("author")[:191] or None,
                      "posted_at": published, "source": source["id"]})
    return posts[:max(1, min(int(source.get("max_items", 100)), 200))]


def scrape_rss(source, keyword):
    with httpx.Client(timeout=10, follow_redirects=False, headers={"User-Agent": "RisenGrowthResearch/1.0"}) as client:
        response = client.get(source["url"], timeout=source_timeout(source))
        response.raise_for_status()
        if response.is_redirect or len(response.content) > 2_000_000:
            raise ValueError("Feed redirect or oversized response refused")
        return extract_rss(response.text, source, keyword)


def scrape_browser(source, keyword):
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        try:
            page = browser.new_page()
            page.route("**/*", lambda route: route.continue_() if permitted_url(route.request.url, source) else route.abort())
            page.goto(source["url"], wait_until="domcontentloaded", timeout=int(source_timeout(source, 25) * 1000))
            page.locator(source["item_selector"]).first.wait_for(timeout=int(source_timeout(source, 10) * 1000))
            # Only configured public sources: do not bypass authentication, CAPTCHA or blocks.
            if urlparse(page.url).scheme != "https" or urlparse(page.url).hostname != urlparse(source["url"]).hostname:
                raise RuntimeError("Cross-domain navigation refused")
            return extract(page.content(), source, page.url, keyword)
        finally:
            browser.close()



def extract_hn_item(item, keyword, source_id="hackernews"):
    """Normalize official HN API item data; do not treat every mention as a lead."""
    if not isinstance(item, dict) or item.get("deleted") or item.get("dead"):
        return None
    if item.get("type") not in ("story", "job") or not isinstance(item.get("id"), int):
        return None
    title = BeautifulSoup(str(item.get("title") or ""), "html.parser").get_text(" ", strip=True)
    text = BeautifulSoup(str(item.get("text") or ""), "html.parser").get_text(" ", strip=True)
    body = re.sub(r"\s+", " ", f"{title} {text}").strip()
    if not body or not matches_keyword(body, keyword.casefold()):
        return None
    uid = str(item["id"])
    return {
        "platform": "web", "external_post_id": f"hn-{uid}",
        "username": str(item.get("by") or "unknown")[:191],
        "body": body[:20000],
        "permalink": f"https://news.ycombinator.com/item?id={uid}",
        "source": source_id,
        "posted_at": datetime.fromtimestamp(item["time"], timezone.utc).isoformat() if type(item.get("time")) is int else None,
    }


def scrape_hackernews(source, keyword):
    """Search a bounded slice of current Ask HN/new stories via HN's official free API.

    This is a recent-items filter, not full historical keyword search.
    """
    base = "https://hacker-news.firebaseio.com/v0"
    feeds = source.get("feeds", ["askstories", "newstories"])
    allowed = {"askstories", "newstories", "jobstories", "showstories"}
    if not isinstance(feeds, list) or not feeds or any(f not in allowed for f in feeds):
        raise ValueError("Invalid HN feeds")
    max_items = max(1, min(int(source.get("max_items", 60)), 120))
    with httpx.Client(timeout=15, follow_redirects=False, headers={"User-Agent": "RisenGrowthResearch/1.0"}) as client:
        ids = []
        for feed in feeds:
            response = client.get(f"{base}/{feed}.json", timeout=source_timeout(source, 15))
            response.raise_for_status()
            data = response.json()
            if not isinstance(data, list):
                raise ValueError("HN feed must be an ID list")
            ids.extend(x for x in data[:max_items] if type(x) is int)
        ids = list(dict.fromkeys(ids))[:max_items]
        found = []
        deadline = source.get("_deadline", time.monotonic() + 55)
        for item_id in ids:
            if time.monotonic() >= deadline:
                raise TimeoutError("Source scan deadline exceeded")
            response = client.get(f"{base}/item/{item_id}.json", timeout=source_timeout(source, 15))
            response.raise_for_status()
            item = extract_hn_item(response.json(), keyword, source["id"])
            time.sleep(max(0.05, min(float(source.get("delay_seconds", 0.1)), 2)))
            if item:
                found.append(item)
        return found

# Each adapter accepts an admin-controlled source and a keyword, returning normalized posts.
SOURCE_ADAPTERS = {"http": scrape_http, "browser": scrape_browser, "rss": scrape_rss, "hackernews": scrape_hackernews}


def run(keyword, config_path):
    configuration = json.loads(Path(config_path).read_text(encoding="utf-8"))
    all_posts, errors = [], []
    sources = [s for s in configuration.get("sources", []) if s.get("enabled", False)]
    for configured_source in sources:
        source = dict(configured_source, _deadline=time.monotonic() + min(55, 65 / len(sources)))
        try:
            mode = source.get("mode", "http")
            if mode not in SOURCE_ADAPTERS:
                raise ValueError("Unsupported source mode")
            if mode != "hackernews" and not source_is_valid(source):
                raise ValueError("Invalid or non-public HTTPS source configuration")
            all_posts.extend(SOURCE_ADAPTERS[mode](source, keyword))
        except Exception as e:
            LOG.warning("Source %s failed: %s", source.get("id"), type(e).__name__)
            errors.append({"source": source.get("id", "unknown"), "error": type(e).__name__ + ": source request or extraction failed"})
    unique = {p["external_post_id"]: p for p in all_posts}
    return {"posts": list(unique.values())[:200], "errors": errors, "sources_configured": len(sources)}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--keyword", required=True)
    parser.add_argument("--config", default=str(ROOT / "sources.json"))
    args = parser.parse_args()
    if not args.keyword.strip() or len(args.keyword) > 191:
        parser.error("keyword must be 1..191 characters")
    result = run(args.keyword.strip(), args.config)
    print(json.dumps(result, ensure_ascii=False))
    return 0 if not result["errors"] else 2


if __name__ == "__main__":
    logging.basicConfig(level=logging.WARNING, stream=sys.stderr)
    sys.exit(main())
