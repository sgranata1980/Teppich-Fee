# Care Lab Chat-Assistent — Stand & Anleitung

**Aktueller Stand (07.10.2026): Läuft bereits live, fehlt nur der API-Key.**

Backend läuft als WordPress-Code-Snippet direkt auf teppichlabor.de
(`wordpress-snippet.php` in diesem Ordner, Plugin "Code Snippets",
Snippet-ID 5, Name "Care Lab Chat Assistent"), nicht als Cloudflare
Worker. Das Frontend (Chat-Widget auf der Startseite) zeigt bereits
auf den eigenen REST-Endpoint `/wp-json/care-lab/v1/chat`, same-origin,
kein CORS nötig.

Der Endpoint antwortet aktuell mit `503 not_configured`, weil noch der
Platzhalter `PASTE_YOUR_KEY_HERE` im Code steht. Sobald der echte
Anthropic-Key eingetragen ist, läuft der Chat.

## Den API-Key eintragen (einziger fehlender Schritt)

1. wp-admin öffnen → im linken Menü **Snippets** → **All Snippets**
2. **"Care Lab Chat Assistent"** anklicken → **Edit**
3. Ganz oben im Code die Zeile suchen:
   `define( 'CL_CLAUDE_API_KEY', 'PASTE_YOUR_KEY_HERE' );`
4. `PASTE_YOUR_KEY_HERE` durch den echten Key von
   **console.anthropic.com** ersetzen (NICHT das claude.ai-Abo — das
   separate Konto mit dem $20-Guthaben), Anführungszeichen drumherum
   lassen.
5. **Update** klicken. Das Snippet ist schon aktiv, kein weiterer
   Schritt nötig.

Der Key bleibt serverseitig in der WordPress-Datenbank, taucht nie im
Frontend-Code auf und geht nie durch den Chat mit Claude.

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
