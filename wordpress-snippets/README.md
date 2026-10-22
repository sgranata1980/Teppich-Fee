# Aktive Code Snippets auf teppichlabor.de

Alle hier als Backup abgelegten Snippets laufen live über das Plugin
**Code Snippets** (wp-admin → Snippets → All Snippets). Reihenfolge
entspricht der Snippet-ID auf der Live-Seite.

| ID | Name in WordPress | Datei hier | Zweck |
|----|---|---|---|
| 5 | Care Lab Chat Assistent | `../chat-worker/wordpress-snippet.php` | REST-Endpoint `/wp-json/care-lab/v1/chat` für das Chat-Widget, siehe `chat-worker/README.md` |
| 6 | Care Lab Zweit-Domain (care-lab.app) | `domain-care-lab-app.php` | Verhindert den WordPress-Redirect von care-lab.app auf teppichlabor.de |
| 7 | Care Lab Login-Design (Apple-Look) | `login-design.php` | Stylt wp-login.php: weiß, blaue Buttons, eigenes Logo statt WordPress-Logo |
| 8 | Care Lab SEO-Basics (Meta-Description + LocalBusiness-Schema) | `seo-basics.php` | Meta-Description pro Seite + LocalBusiness-JSON-LD, ohne SEO-Plugin |
| 9 | Care Lab Backend — API-Key-Verwaltung | `api-keys-backend.php` | Eigenes Admin-Menü "Care Lab" → "API-Keys": speichert Anthropic/OpenAI-Keys als WP-Optionen statt im Code. Muss aktiv sein, sonst bleibt der Chat-Assistent (Snippet 5) auf `503 not_configured`. Seit 22.10.2026 zusätzlich Link zur neuen Preise-Seite im Dashboard |
| 10 | Care Lab Teppich-Erkennung | `../chat-worker/carpet-identify-snippet.php` | REST-Endpoint `/wp-json/care-lab/v1/identify-carpet`: nimmt ein Teppich-Foto entgegen, lässt Claude (Vision) die Material-Kategorie einschätzen, Preistext kommt fest aus PHP (nie von der KI erfunden). Für die "Jetzt Teppich bestellen"-Box auf der Startseite |
| 17 | Care Lab Backend — Preiseditor | `price-editor-backend.php` | Admin-Menü "Care Lab" → "Preise": Teppichreinigungs-Preise (Grundreinigung nach Material, Reparatur, Einzelleistungen) als WP-Optionen statt im Seiten-HTML. Seite `/leistungen-pakete/` zieht sie per Shortcode (`[cl_preise_material]`, `[cl_preise_reparatur]`, `[cl_preise_zusatz]`) — Preisänderungen im Backend sind sofort live, kein Code/Git nötig |

## Warum als Backup im Repo

Code Snippets speichert den Code in der WordPress-Datenbank, nicht im
Git-Repo. Diese Kopien sind reine Referenz/Backup, falls mal ein
Snippet in wp-admin aus Versehen verändert oder gelöscht wird — dann
einfach den Inhalt der passenden Datei hier zurück ins Snippet kopieren
(wp-admin → Snippets → betroffenes Snippet → Edit → Code ersetzen →
Update).

## Deployment per REST-API (seit 12.10.2026 bekannt)

Entgegen der ursprünglichen Annahme lässt sich Code Snippets doch per
REST-API verwalten: `/wp-json/code-snippets/v1/snippets` (GET/POST),
`/wp-json/code-snippets/v1/snippets/{id}` (GET/PUT/DELETE), Schema unter
`/wp-json/code-snippets/v1/snippets/schema`. Das `code`-Feld enthält den
PHP-Code OHNE öffnendes `<?php` (das beim Senden abschneiden). Snippets 9
und 10 wurden so direkt live angelegt, ohne manuellen Copy-Paste-Schritt
in wp-admin. Für neue Snippets ab jetzt bevorzugt so vorgehen, nicht mehr
den User um manuelles Einfügen bitten.

## Ändert sich live etwas an einem Snippet

Bitte kurz Bescheid geben oder selbst hier im Repo nachziehen, damit
Backup und Live-Stand nicht auseinanderlaufen.
