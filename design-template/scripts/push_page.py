"""
Spielt eine einzelne Seite aus pages/<slug>.html auf eine WordPress-Seite (Update)
oder legt sie neu an.

Nutzung (Update, Seite existiert schon):
    WP_BASE=... WP_USER=... WP_APP_PASSWORD="..." python3 push_page.py <slug> <page_id>

Nutzung (neu anlegen):
    WP_BASE=... WP_USER=... WP_APP_PASSWORD="..." python3 push_page.py <slug> --create "Titel" <ziel-slug>
"""
import os, sys, requests

BASE = os.environ["WP_BASE"].rstrip("/") + "/wp-json/wp/v2"
AUTH = (os.environ["WP_USER"], os.environ["WP_APP_PASSWORD"])
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)

def wrap_html_block(inner):
    return f"<!-- wp:html -->\n{inner}\n<!-- /wp:html -->"

slug = sys.argv[1]
html = open(os.path.join(ROOT, "pages", slug + ".html")).read()
content = wrap_html_block(html)

if len(sys.argv) > 2 and sys.argv[2] == "--create":
    title = sys.argv[3]
    target_slug = sys.argv[4]
    payload = {"title": title, "slug": target_slug, "content": content, "status": "publish", "template": "page-no-title"}
    r = requests.post(f"{BASE}/pages", auth=AUTH, json=payload, timeout=30)
    print("create", r.status_code)
    r.raise_for_status()
    print(r.json()["id"], r.json()["link"])
else:
    page_id = sys.argv[2]
    r = requests.post(f"{BASE}/pages/{page_id}", auth=AUTH, json={"content": content}, timeout=30)
    print("update", r.status_code)
    r.raise_for_status()
    print(r.json()["link"])
