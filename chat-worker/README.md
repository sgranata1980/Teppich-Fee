# Care Lab Chat-Assistent — Deploy-Anleitung

Backend für das Chat-Widget auf teppichlabor.de. Läuft als Cloudflare
Worker (kostenloser Tarif reicht locker), nicht als WordPress-Plugin —
das Frontend (`chat-widget.html` im Design-Template) ist bereits live
und wartet nur auf die Worker-URL.

Warum Cloudflare Worker statt WordPress-PHP: kein Server-Dateizugriff
auf teppichlabor.de vorhanden (nur die WordPress-REST-API), ein Worker
lässt sich dagegen komplett über das Cloudflare-Dashboard einrichten,
copy & paste, ohne SSH oder Hosting-Zugang.

## Was du brauchst

- Einen Cloudflare-Account (kostenlos): https://dash.cloudflare.com/sign-up
- Einen Anthropic-API-Key von **console.anthropic.com** (NICHT das
  claude.ai-Abo — das ist ein separates Konto mit eigenem Guthaben,
  genau das mit den $20, die schon aufgeladen wurden).

## Schritt für Schritt

1. **Worker anlegen**
   Cloudflare-Dashboard → *Workers & Pages* → *Create* → *Create Worker*.
   Name z. B. `care-lab-chat`. Erstmal auf *Deploy* klicken (legt einen
   Platzhalter an, den wir gleich überschreiben).

2. **Code einfügen**
   Im neuen Worker auf *Edit code*. Den kompletten Inhalt von
   `worker.js` aus diesem Ordner reinkopieren (alles ersetzen), dann
   oben rechts *Deploy*.

3. **KV-Namespace für das Rate-Limit anlegen**
   Dashboard → *Workers & Pages* → *KV* → *Create a namespace*.
   Name z. B. `CARE_LAB_CHAT_RATELIMIT`. Erstellen reicht, keine
   Einträge nötig.

4. **KV mit dem Worker verbinden**
   Zurück im Worker → *Settings* → *Variables* → Abschnitt
   *KV Namespace Bindings* → *Add binding*.
   - Variable name: `RATE_LIMIT_KV` (muss exakt so heißen, steht so im Code)
   - KV namespace: den gerade erstellten Namespace auswählen
   - Speichern

5. **Anthropic-API-Key als Secret hinterlegen**
   Im selben *Settings* → *Variables* → Abschnitt *Environment
   Variables* → *Add variable*.
   - Variable name: `ANTHROPIC_API_KEY` (muss exakt so heißen)
   - Value: der Key von console.anthropic.com
   - Wichtig: Als **Secret** markieren (verschlüsselt, nicht als Klartext),
     dafür gibt es einen Schalter/Typ-Auswahl direkt beim Eintragen.
   - Speichern, dann oben rechts nochmal *Deploy* damit es aktiv wird.

6. **URL kopieren und zurückgeben**
   Nach dem Deploy zeigt Cloudflare oben eine URL wie
   `https://care-lab-chat.<dein-workers-subdomain>.workers.dev`.
   Diese URL bitte zurückgeben — damit wird `__CHAT_WORKER_URL__` im
   Chat-Widget auf der Live-Seite ersetzt und der Chat ist scharf.

## Was das Setup schon mitbringt (kein Zutun nötig)

- **Grounding**: Alle Fakten (Preise, Leistungen, Kontakt) stehen fest
  im `SYSTEM_PROMPT` am Anfang von `worker.js`, nicht geraten. Ändert
  sich ein Preis, einfach den Text dort anpassen und neu deployen —
  oder mir Bescheid geben, dann halte ich `worker.js` synchron zu
  `brand/fakten.md`.
- **Leitplanken**: Keine erfundenen Preise/Zusagen, keine
  medizinischen Aussagen bei der Hygienereinigung, ehrliches
  "weiß ich nicht" statt Raten — fest im System-Prompt verankert.
- **Rate-Limit**: 20 Anfragen pro Stunde und IP-Adresse (über das
  KV-Binding), schützt das API-Guthaben vor Missbrauch.
- **Kostenkontrolle**: kleines, günstiges Modell (Haiku), Antwortlänge
  auf 400 Tokens gedeckelt, nur die letzten 6 Nachrichten gehen pro
  Anfrage an die API.
- **Kein Key im Frontend**: Der Anthropic-Key verlässt den Worker nie,
  das Chat-Widget im Browser sieht ihn nicht.

## Testen

Sobald die URL eingebaut ist, reicht ein Blick auf teppichlabor.de:
Chat-Icon unten rechts öffnen, etwas fragen (z. B. "Was kostet eine
Handwäsche?"). Antwortet der Bot korrekt mit "ab 16,00 €/m²", läuft
alles wie vorgesehen.
