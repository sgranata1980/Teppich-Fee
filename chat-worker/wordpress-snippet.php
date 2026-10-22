<?php
/**
 * Care Lab Chat-Assistent — REST-Endpoint
 *
 * Der Anthropic-API-Key wird NICHT mehr hier im Code eingetragen,
 * sondern unter wp-admin → Care Lab → API-Keys gespeichert (Snippet
 * "Care Lab Backend — API-Key-Verwaltung" muss aktiv sein). Dieses
 * Snippet liest den Key zur Laufzeit über cl_get_api_key( 'claude' ).
 */

define( 'CL_CHAT_MODEL', 'claude-haiku-4-5-20251001' );
define( 'CL_CHAT_MAX_TOKENS', 400 );
define( 'CL_CHAT_MAX_MSG_LEN', 1000 );
define( 'CL_CHAT_MAX_HISTORY', 6 );
define( 'CL_CHAT_RATE_LIMIT', 20 ); // Anfragen pro Stunde pro IP

add_action( 'rest_api_init', function () {
	register_rest_route(
		'care-lab/v1',
		'/chat',
		array(
			'methods'             => 'POST',
			'callback'            => 'cl_handle_chat_request',
			'permission_callback' => '__return_true',
		)
	);
} );

/**
 * Baut den Preis-Block für den Chat-Prompt live aus denselben
 * WordPress-Optionen, die auch der Preiseditor (wp-admin → Care Lab →
 * Preise) und die Seite /leistungen-pakete/ nutzen. So gibt der
 * Chatbot nie veraltete, hier hartcodierte Preise weiter.
 */
function cl_chat_prices_block() {
	if ( ! function_exists( 'cl_get_prices_material' ) || ! function_exists( 'cl_get_prices_zusatz' ) || ! function_exists( 'cl_get_prices_repair' ) ) {
		return "LEISTUNGEN & PREISE: aktuell nicht automatisch abrufbar. Verweise auf /leistungen-pakete/, nenne selbst keine Preise.";
	}

	$lines   = array();
	$lines[] = 'LEISTUNGEN & PREISE (Grundreinigung nach Material, Preise inkl. MwSt., Mindestauftragswert 89 €)';
	foreach ( cl_get_prices_material() as $row ) {
		$lines[] = '- ' . $row['art'] . ' (' . $row['verfahren'] . '): ' . $row['preis'];
	}
	$lines[] = '';
	$lines[] = 'EINZELLEISTUNGEN (zubuchbar)';
	foreach ( cl_get_prices_zusatz() as $row ) {
		$lines[] = '- ' . $row['titel'] . ': ' . $row['preis'] . ( ! empty( $row['beschreibung'] ) ? ' (' . $row['beschreibung'] . ')' : '' );
	}
	$lines[] = '';
	$lines[] = 'ANTIK-REPARATUR & TEPPICHREPARATUR';
	foreach ( cl_get_prices_repair() as $row ) {
		$lines[] = '- ' . $row['leistung'] . ': ' . $row['preis'];
	}
	$lines[] = '';
	$lines[] = 'ZUSATZPAKETE IM BUCHUNGS-TOOL (/impressum-kontakt/)';
	$lines[] = '- Kleinkinderpaket: Details werden noch mit dem Kunden abgestimmt, kein fester Preis/Umfang bekannt — nenne hierzu nichts Konkretes.';
	$lines[] = '- Haustierpaket: Enzymreiniger gegen Tierhaare/Tiergeruch, Preis wie "Urin- & Haustier-Sonderbehandlung" oben.';

	return implode( "\n", $lines );
}

function cl_chat_system_prompt() {
	$preise_block = cl_chat_prices_block();

	return <<<PROMPT
Du bist der Chat-Assistent von Care Lab (teppichlabor.de), einer Teppich- und Polsterreinigung in Kelkheim im Rhein-Main-Gebiet. Du antwortest kurz, ruhig und direkt auf Deutsch, ohne Verkaufsfloskeln oder Übertreibungen, genau im Ton der restlichen Website: ehrlich statt vollmundig.

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
Angebot/Anfrage Privatkunden: teppichlabor.de/impressum-kontakt/ (Buchungs-Tool)
Angebot/Anfrage Unternehmen: teppichlabor.de/profi-kontakt/

{$preise_block}

PFLEGEPAKETE auf /leistungen-pakete/ (jeweils zusätzlich zur Grundreinigung, unabhängig von den Zusatzpaketen im Buchungs-Tool)
- Schutz-Paket (+5,90 €/m²): Fleckenschutz/Imprägnierung + Qualitätskontrolle. Für Haushalte mit Kindern/Haustieren/stark genutzten Räumen.
- Naturfaser-Paket (+10,90 €/m², meistgewählt): materialgerechte Handwäsche, Wollpflege & Rückfettung, Mottenschutz, kontrollierte Trocknung. Für Woll-, Orient-, Berber-, Nepal- und andere Naturfaserteppiche.
- Haustier-Paket (+12,90 €/m²): intensive Vorbehandlung, Geruchsbehandlung, zusätzlicher Spülgang. Für Hunde-/Katzenhaushalte.
- Premium-Pflege (+14,90 €/m²): intensive Vorbehandlung, zusätzlicher Wasch-/Spülgang, Faserschutz, priorisierte Bearbeitung. Für besonders umfassende Behandlung.

HYGIENEREINIGUNG (Aufpreis-Leistung, kein Ersatz für Grundreinigung)
Heißdampf/Heißwasser bei 60-80 °C statt Standardmethode. Ab etwa 55-60 °C sterben Hausstaubmilben zuverlässig ab. WICHTIG: Das ist KEIN zertifiziertes Desinfektionsverfahren und KEINE Garantie auf vollständige Allergenfreiheit, kein Ersatz für ärztliche/allergologische Behandlung. Sinnvoll vor allem für Allergiker, Haustierhaushalte, Familien mit Kleinkindern.

TEPPICHBODENREINIGUNG (neues Angebot)
Fest verlegter Teppichboden wird direkt vor Ort gereinigt (nicht abgeholt). Preise stehen noch nicht final fest ("folgen in Kürze"), auf Anfrage wird unverbindlich eine Einschätzung gegeben.

ABHOLUNG
Gilt nur für lose Teppiche (nicht für fest verlegten Teppichboden), im gesamten Rhein-Main-Gebiet. Wir holen grundsätzlich beim Kunden ab, kein Vorbeibringen. Preis: siehe "Abholung & Lieferung" oben unter Einzelleistungen (Fahrtkostenpauschale pro Auftrag).

UNTERNEHMEN / B2B
Zielgruppen: Hotels, Kitas & Büros, Hausverwaltungen, Gastronomie. Eigenes Angebotsformular unter /profi-kontakt/. Logo-Teppiche für Gastronomie & Einzelhandel (bedruckt mit Kundenlogo) ab 71,34 € (Format 60x40 cm, Einzelhandel), größere Formate und Gastronomie-Varianten auf Anfrage, siehe /logo-teppiche/.

SHOP (noch nicht live, nur Vorschau)
Fünf Teppichdüfte: Capri, Orient, Saigon, Leder, Toscana. 5-ml-Probe: 6,90 €, 500 ml: 34,90 €. Probierset (5x 5 ml): 29,90 €. Der Online-Checkout ist noch nicht live, aktuell reine Produktvorschau.

JOURNAL
Zehn Artikel unter /journal/ zu Themen wie PFAS-Fleckschutz, Hausstaubmilben, Kleidermotten, Orientteppich-Pflege, Raumakustik, Haustiergeruch, Wasserkreislauf, Hausmitteln und Fachkräftemangel.

Wenn eine Frage außerhalb dieser Themen liegt (z. B. andere Unternehmen, allgemeines Weltwissen), antworte kurz, dass du nur zu Care Lab weiterhelfen kannst.
PROMPT;
}

function cl_chat_rate_limited( $ip ) {
	$key     = 'cl_chat_rl_' . md5( $ip );
	$count   = (int) get_transient( $key );
	if ( $count >= CL_CHAT_RATE_LIMIT ) {
		return true;
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return false;
}

function cl_handle_chat_request( WP_REST_Request $request ) {
	$claude_key = function_exists( 'cl_get_api_key' ) ? cl_get_api_key( 'claude' ) : '';
	if ( ! $claude_key ) {
		return new WP_Error( 'not_configured', 'Chat ist noch nicht eingerichtet.', array( 'status' => 503 ) );
	}

	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	if ( cl_chat_rate_limited( $ip ) ) {
		return new WP_Error( 'rate_limited', 'Zu viele Anfragen. Bitte in einer Stunde erneut versuchen.', array( 'status' => 429 ) );
	}

	$body     = $request->get_json_params();
	$incoming = isset( $body['messages'] ) && is_array( $body['messages'] ) ? $body['messages'] : array();

	if ( empty( $incoming ) ) {
		return new WP_Error( 'no_message', 'Ungültige Nachricht.', array( 'status' => 400 ) );
	}

	$incoming = array_slice( $incoming, -CL_CHAT_MAX_HISTORY );
	$history  = array();
	foreach ( $incoming as $m ) {
		if ( ! isset( $m['role'], $m['content'] ) ) {
			continue;
		}
		if ( ! in_array( $m['role'], array( 'user', 'assistant' ), true ) ) {
			continue;
		}
		$content = trim( (string) $m['content'] );
		if ( '' === $content || mb_strlen( $content ) > CL_CHAT_MAX_MSG_LEN ) {
			continue;
		}
		$history[] = array( 'role' => $m['role'], 'content' => $content );
	}

	if ( empty( $history ) || 'user' !== end( $history )['role'] ) {
		return new WP_Error( 'invalid_message', 'Ungültige Nachricht.', array( 'status' => 400 ) );
	}

	$response = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 25,
			'headers' => array(
				'x-api-key'         => $claude_key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'model'      => CL_CHAT_MODEL,
					'max_tokens' => CL_CHAT_MAX_TOKENS,
					'system'     => cl_chat_system_prompt(),
					'messages'   => $history,
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'upstream_unreachable', 'Chat gerade nicht erreichbar.', array( 'status' => 502 ) );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || empty( $data['content'][0]['text'] ) ) {
		return new WP_Error( 'upstream_error', 'Chat gerade nicht erreichbar.', array( 'status' => 502 ) );
	}

	return array( 'reply' => $data['content'][0]['text'] );
}
