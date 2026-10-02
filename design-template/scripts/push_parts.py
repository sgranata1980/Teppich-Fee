"""
Spielt header.html / footer.html (mit eingebettetem site.css) als WordPress-
Template-Parts ein. Vor dem Ausfuehren: __CSS__, __LOGOMARK__, __LOGO_WORDMARK__
und alle anderen __PLATZHALTER__ in header.html/footer.html durch echte Werte
ersetzen (siehe README.md im Ordner darueber).

Nutzung:
    WP_BASE=https://deine-seite.de WP_USER=user WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx" \
    WP_THEME=twentytwentyfive python3 push_parts.py
"""
import os, requests

BASE = os.environ["WP_BASE"].rstrip("/") + "/wp-json/wp/v2"
AUTH = (os.environ["WP_USER"], os.environ["WP_APP_PASSWORD"])
THEME = os.environ.get("WP_THEME", "twentytwentyfive")
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)

def wrap_html_block(inner):
    return f"<!-- wp:html -->\n{inner}\n<!-- /wp:html -->"

def put_template_part(slug, content):
    tid = f"{THEME}//{slug}"
    url = f"{BASE}/template-parts/{tid}"
    r = requests.post(url, auth=AUTH, json={"content": content}, timeout=30)
    print(slug, r.status_code)
    if r.status_code >= 300:
        print(r.text[:1000])
    r.raise_for_status()

for slug in ["header", "footer"]:
    html = open(os.path.join(ROOT, slug + ".html")).read()
    put_template_part(slug, wrap_html_block(html))

print("done")
