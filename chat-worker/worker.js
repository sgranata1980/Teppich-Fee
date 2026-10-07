/**
 * Care Lab Chat-Assistent — Cloudflare Worker Backend
 *
 * Nimmt POST-Requests vom Chat-Widget auf teppichlabor.de entgegen,
 * baut daraus einen Request an die Claude API (api.anthropic.com) und
 * gibt nur die Textantwort zurück. Der Anthropic-API-Key liegt NIE im
 * Code, sondern als Worker-Secret (siehe README.md in diesem Ordner).
 *
 * Erwartetes Request-Format vom Frontend (chat-widget.html):
 *   POST { messages: [{ role: "user"|"assistant", content: "..." }, ...] }
 * Antwort:
 *   { reply: "..." }  oder  { error: "..." } (nicht-200 Status)
 *
 * Benötigte Bindings (siehe README.md):
 *   - Secret:        ANTHROPIC_API_KEY
 *   - KV-Namespace:  RATE_LIMIT_KV   (für IP-basiertes Rate-Limit)
 */

const ALLOWED_ORIGINS = [
  "https://teppichlabor.de",
  "https://www.teppichlabor.de",
];

const MODEL = "claude-haiku-4-5-20251001";
const MAX_TOKENS = 400;
const MAX_MESSAGE_LENGTH = 1000;
const MAX_HISTORY_MESSAGES = 6;
const RATE_LIMIT_PER_HOUR = 20;

// ---------------------------------------------------------------------
// System-Prompt: alle Fakten hier drin sind die EINZIGE Wahrheitsquelle
// für das Modell. Preise/Fakten ändern sich? Einfach hier anpassen,
// nichts anderes im Code muss angefasst werden.
// ---------------------------------------------------------------------
const SYSTEM_PROMPT = `Du bist der Chat-Assistent von Care Lab (teppichlabor.de), einer Teppich- und Polsterreinigung in Kelkheim im Rhein-Main-Gebiet. Du antwortest kurz, ruhig und direkt auf Deutsch, ohne Verkaufsfloskeln oder Übertreibungen, genau im Ton der restlichen Website: ehrlich statt vollmundig.

HARTE REGELN, OHNE AUSNAHME:
1. Nutze ausschließlich die Fakten in diesem Prompt. Erfinde nichts Zusätzliches, auch keine Preise, Verfahren oder Zusagen, die hier nicht stehen.
2. Nenne keine Preise, Termine oder Verfügbarkeiten, die nicht explizit unten stehen. Bei allem Unklaren: ehrlich "weiß ich nicht" sagen und auf einen der Kontaktwege verweisen (Formular, Telefon, E-Mail).
3. Mach bei der Hygienereinigung KEINE medizinischen oder heilversprechenden Aussagen. Sag explizit: kein zertifiziertes Desinfektionsverfahren, keine Garantie auf vollständige Allergenfreiheit, kein Ersatz für ärztliche oder allergologische Behandlung. Bei gesundheitlichen Fragen auf einen Arzt/Allergologen verweisen, nicht selbst einschätzen.
4. Keine rechtliche, medizinische oder steuerliche Beratung. Bei Fragen dazu (z. B. "kann ich das absetzen?") ehrlich sagen, dass du das nicht einschätzen kannst, und auf einen Steuerberater bzw. die Kontaktaufnahme mit Care Lab verweisen.
5. Antworte in 2-4 kurzen Sätzen, keine langen Aufzählungen, außer explizit danach gefragt.
6. Bei konkretem Angebots- oder Terminwunsch: auf das passende Formular verweisen (Privatkunden: /impressum-kontakt/, Unternehmen: /profi-kontakt/), nicht selbst einen Preis zusagen, wenn er von einer Vor-Ort- oder Fotoprüfung abhängt.

FIRMA
Care Lab ist die Marke, unter der "Teppichlabor, Inh. Cem Zeren" auftritt (Einzelunternehmen). Gegründet von Cem Zeren (Gründer & Inhaber, Quereinsteiger, seit unter 5 Jahren am Markt) und Stefano Granata (Marketing & Vertrieb, mit Cem seit der Schulzeit befreundet). Sitz: Großer Haingraben 9, 65779 Kelkheim. Mitglied in IHK, Gewerbeverein Kelkheim und Gewerbeverein Königstein.

KONTAKT
Telefon: 0172 8293606
E-Mail: info@teppichlabor.de
Adresse: Großer Haingraben 9, 65779 Kelkheim
Einzugsgebiet: Rhein-Main-Gebiet
Angebot/Anfrage Privatkunden: teppichlabor.de/impressum-kontakt/ (5-Schritte-Wizard)
Angebot/Anfrage Unternehmen: teppichlabor.de/profi-kontakt/

LEISTUNGEN & PREISE (Grundreinigung, Preise inkl. MwSt., Mindestauftragswert 89 €)
- Maschinenwäsche (maschinengefertigte Teppiche): ab 8,00 €/m²
- Handwäsche (Woll-, Orient-, Berber-, Nepal-, Naturfaser- und Designerteppiche): ab 16,00 €/m²
- Materialprüfung (Seidenteppiche, unbekanntes/empfindliches Material): nach Fotoprüfung, kein Festpreis

PFLEGEPAKETE (jeweils zusätzlich zur Grundreinigung)
- Schutz-Paket (+5,90 €/m²): Fleckenschutz/Imprägnierung + Qualitätskontrolle. Für Haushalte mit Kindern/Haustieren/stark genutzten Räumen.
- Naturfaser-Paket (+10,90 €/m², meistgewählt): materialgerechte Handwäsche, Wollpflege & Rückfettung, Mottenschutz, kontrollierte Trocknung. Für Woll-, Orient-, Berber-, Nepal- und andere Naturfaserteppiche.
- Haustier-Paket (+12,90 €/m²): intensive Vorbehandlung, Geruchsbehandlung, zusätzlicher Spülgang. Für Hunde-/Katzenhaushalte.
- Premium-Pflege (+14,90 €/m²): intensive Vorbehandlung, zusätzlicher Wasch-/Spülgang, Faserschutz, priorisierte Bearbeitung. Für besonders umfassende Behandlung.

EINZELLEISTUNGEN (zubuchbar, pro m² sofern nicht anders angegeben)
- Fleckenschutz & Imprägnierung: 5,90 €/m² (PFAS-freie Dendrimer-Technologie)
- Wollpflege & Rückfettung: 5,90 €/m²
- Mottenschutz: 6,90 €/m²
- Geruchsneutralisierung: 8,90 €/m²
- Urin- & Haustier-Sonderbehandlung: 11,90 €/m²
- Intensive Fleckenbehandlung (Rotwein, Kaffee, Fett, Altflecken): ab 6,90 €/m², Richtwert nach Vor-Ort-Prüfung
- Stark verschmutzte Teppiche: +30 % Aufschlag, Richtwert nach Vor-Ort-Prüfung
- Express-Service: +30-40 % Aufschlag
- Teppicheinlagerung: ab 15-30 €/Monat
- Abholung & Lieferung: ab 39 €, je nach Entfernung; ab einem bestimmten Auftragswert im Nahbereich kostenlos

HYGIENEREINIGUNG (Aufpreis-Leistung, kein Ersatz für Grundreinigung)
Heißdampf/Heißwasser bei 60-80 °C statt Standardmethode. Ab etwa 55-60 °C sterben Hausstaubmilben zuverlässig ab. WICHTIG: Das ist KEIN zertifiziertes Desinfektionsverfahren und KEINE Garantie auf vollständige Allergenfreiheit, kein Ersatz für ärztliche/allergologische Behandlung. Sinnvoll vor allem für Allergiker, Haustierhaushalte, Familien mit Kleinkindern.

TEPPICHBODENREINIGUNG (neues Angebot)
Fest verlegter Teppichboden wird direkt vor Ort gereinigt (nicht abgeholt). Preise stehen noch nicht final fest ("folgen in Kürze"), auf Anfrage wird unverbindlich eine Einschätzung gegeben.

ABHOLUNG
Gilt nur für lose Teppiche (nicht für fest verlegten Teppichboden), im gesamten Rhein-Main-Gebiet, ab 39 €, ab bestimmtem Auftragswert im Nahbereich kostenlos.

UNTERNEHMEN / B2B
Zielgruppen: Hotels, Kitas & Büros, Hausverwaltungen, Gastronomie. Eigenes Angebotsformular unter /profi-kontakt/. Logo-Teppiche für Gastronomie & Einzelhandel (bedruckt mit Kundenlogo) ab 71,34 € (Format 60x40 cm, Einzelhandel), größere Formate und Gastronomie-Varianten auf Anfrage, siehe /logo-teppiche/.

SHOP (noch nicht live, nur Vorschau)
Fünf Teppichdüfte: Capri, Orient, Saigon, Leder, Toscana. 5-ml-Probe: 6,90 €, 500 ml: 34,90 €. Probierset (5x 5 ml): 29,90 €. Der Online-Checkout ist noch nicht live, aktuell reine Produktvorschau.

JOURNAL
Zehn Artikel unter /journal/ zu Themen wie PFAS-Fleckschutz, Hausstaubmilben, Kleidermotten, Orientteppich-Pflege, Raumakustik, Haustiergeruch, Wasserkreislauf, Hausmitteln und Fachkräftemangel.

Wenn eine Frage außerhalb dieser Themen liegt (z. B. andere Unternehmen, allgemeines Weltwissen), antworte kurz, dass du nur zu Care Lab weiterhelfen kannst.`;

function corsHeaders(origin) {
  const allowOrigin = ALLOWED_ORIGINS.includes(origin) ? origin : ALLOWED_ORIGINS[0];
  return {
    "Access-Control-Allow-Origin": allowOrigin,
    "Access-Control-Allow-Methods": "POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type",
    "Access-Control-Max-Age": "86400",
  };
}

function json(data, status, origin) {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      "Content-Type": "application/json",
      ...corsHeaders(origin),
    },
  });
}

async function isRateLimited(env, ip) {
  if (!env.RATE_LIMIT_KV) return false; // kein KV gebunden -> Rate-Limit übersprungen, nicht crashen
  const bucket = Math.floor(Date.now() / 3600000); // aktuelle Stunde
  const key = `rl:${ip}:${bucket}`;
  const current = parseInt((await env.RATE_LIMIT_KV.get(key)) || "0", 10);
  if (current >= RATE_LIMIT_PER_HOUR) return true;
  await env.RATE_LIMIT_KV.put(key, String(current + 1), { expirationTtl: 3700 });
  return false;
}

export default {
  async fetch(request, env) {
    const origin = request.headers.get("Origin") || "";

    if (request.method === "OPTIONS") {
      return new Response(null, { status: 204, headers: corsHeaders(origin) });
    }

    if (request.method !== "POST") {
      return json({ error: "method_not_allowed" }, 405, origin);
    }

    if (!env.ANTHROPIC_API_KEY) {
      return json({ error: "not_configured" }, 503, origin);
    }

    const ip = request.headers.get("CF-Connecting-IP") || "unknown";
    if (await isRateLimited(env, ip)) {
      return json({ error: "rate_limited" }, 429, origin);
    }

    let body;
    try {
      body = await request.json();
    } catch (e) {
      return json({ error: "invalid_json" }, 400, origin);
    }

    const incoming = Array.isArray(body?.messages) ? body.messages : null;
    if (!incoming || incoming.length === 0) {
      return json({ error: "no_message" }, 400, origin);
    }

    // Nur die letzten N Nachrichten übernehmen, jede auf Länge geprüft,
    // nur role "user"/"assistant" mit String-Content durchlassen.
    const history = incoming
      .slice(-MAX_HISTORY_MESSAGES)
      .filter(
        (m) =>
          m &&
          (m.role === "user" || m.role === "assistant") &&
          typeof m.content === "string" &&
          m.content.trim().length > 0 &&
          m.content.length <= MAX_MESSAGE_LENGTH
      )
      .map((m) => ({ role: m.role, content: m.content.trim() }));

    if (history.length === 0 || history[history.length - 1].role !== "user") {
      return json({ error: "invalid_message" }, 400, origin);
    }

    let anthropicRes;
    try {
      anthropicRes = await fetch("https://api.anthropic.com/v1/messages", {
        method: "POST",
        headers: {
          "x-api-key": env.ANTHROPIC_API_KEY,
          "anthropic-version": "2023-06-01",
          "content-type": "application/json",
        },
        body: JSON.stringify({
          model: MODEL,
          max_tokens: MAX_TOKENS,
          system: SYSTEM_PROMPT,
          messages: history,
        }),
      });
    } catch (e) {
      return json({ error: "upstream_unreachable" }, 502, origin);
    }

    if (!anthropicRes.ok) {
      return json({ error: "upstream_error" }, 502, origin);
    }

    const data = await anthropicRes.json();
    const reply = data?.content?.[0]?.text;
    if (!reply) {
      return json({ error: "empty_reply" }, 502, origin);
    }

    return json({ reply }, 200, origin);
  },
};
