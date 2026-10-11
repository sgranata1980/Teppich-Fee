# Care Lab Chat-Assistent — Stand & Anleitung

**Aktueller Stand (11.10.2026): Läuft live, Key wird jetzt über die
Backend-API-Key-Seite verwaltet statt fest im Code zu stehen.**

Backend läuft als WordPress-Code-Snippet direkt auf teppichlabor.de
(`wordpress-snippet.php` in diesem Ordner, Plugin "Code Snippets",
Snippet-ID 5, Name "Care Lab Chat Assistent"), nicht als Cloudflare
Worker. Das Frontend (Chat-Widget auf der Startseite) zeigt bereits
auf den eigenen REST-Endpoint `/wp-json/care-lab/v1/chat`, same-origin,
kein CORS nötig.

Der Anthropic-Key liegt nicht mehr als Konstante in dieser Datei,
sondern als WordPress-Option, gepflegt über ein zweites Snippet
("Care Lab Backend — API-Key-Verwaltung", siehe
`../wordpress-snippets/api-keys-backend.php`). Dieses Snippet muss
zusätzlich zu Snippet 5 aktiv sein, sonst bleibt der Chat auf
`503 not_configured`.

## Den API-Key eintragen

1. wp-admin öffnen → im linken Menü **Care Lab** → **API-Keys**
   (erscheint erst, wenn das Snippet "Care Lab Backend —
   API-Key-Verwaltung" aktiv ist)
2. Beim Feld **Anthropic / Claude API-Key** den echten Key von
   **console.anthropic.com** eintragen (NICHT das claude.ai-Abo — das
   separate Konto mit dem Guthaben)
3. **Speichern** klicken. Kein weiterer Schritt nötig, der Chat liest
   den Key sofort darüber.

Der Key bleibt serverseitig in der WordPress-Datenbank, erscheint in
der Einstellungsseite nur noch maskiert, taucht nie im Frontend-Code
auf und geht nie durch den Chat mit Claude.

## Testen

Auf teppichlabor.de das Chat-Icon unten rechts öffnen, fragen z. B.
"Was kostet eine Handwäsche?". Antwortet der Bot mit "ab 16,00 €/m²",
läuft alles.

## Was das Setup mitbringt

- **Grounding**: Alle Fakten (Preise, Leistungen, Kontakt,
  Hygienereinigung-Einschränkungen, PFAS-Entscheidung) stehen fest im
  `cl_chat_system_prompt()`-Block in `wordpress-snippet.php`, nicht
  geraten. Ändert sich ein Preis: Snippet in wp-admin bearbeiten, oder
  mir Bescheid geben, dann halte ich es synchron zu `brand/fakten.md`.
- **Leitplanken**: Keine erfundenen Preise/Zusagen, keine
  medizinischen Aussagen bei der Hygienereinigung, ehrliches
  "weiß ich nicht" statt Raten.
- **Rate-Limit**: 20 Anfragen/Stunde/IP über WordPress-Transients
  (`set_transient`/`get_transient`), kein zusätzlicher Dienst nötig.
- **Kostenkontrolle**: Haiku-Modell, Antwortlänge auf 400 Tokens
  gedeckelt, nur die letzten 6 Nachrichten gehen pro Anfrage raus.
- **Kein Key im Frontend**: Der Key verlässt den Server nie.

## Alternative: Cloudflare Worker (nicht mehr aktiv genutzt)

`worker.js` ist eine funktionsgleiche Variante als Cloudflare Worker,
falls WordPress irgendwann gewechselt wird oder der Traffic zu groß
für die Transient-basierte Rate-Limitierung wird. Aktuell ungenutzt,
bleibt als Referenz im Repo.
