<?php
/**
 * Care Lab Teppich-Erkennung — REST-Endpoint
 *
 * Nimmt ein Foto entgegen (Base64) und lässt Claude (Vision) grob
 * einschätzen, in welche der drei bestehenden Preiskategorien der
 * Teppich fällt (Maschinenwäsche / Handwäsche / Materialprüfung
 * nötig). Die KI liefert NUR die Kategorie + eine kurze Begründung,
 * der eigentliche Preistext kommt serverseitig aus einer festen
 * Zuordnung (siehe cl_identify_category_info) — die KI erfindet also
 * nie selbst einen Preis.
 *
 * Nutzt denselben gespeicherten API-Key wie der Chat-Assistent
 * (Snippet "Care Lab Backend — API-Key-Verwaltung" muss aktiv sein).
 */

define( 'CL_IDENTIFY_MODEL', 'claude-haiku-4-5-20251001' );
define( 'CL_IDENTIFY_MAX_TOKENS', 150 );
define( 'CL_IDENTIFY_RATE_LIMIT', 15 ); // Anfragen pro Stunde pro IP
define( 'CL_IDENTIFY_MAX_IMAGE_BYTES', 6 * 1024 * 1024 ); // 6 MB Rohbild

add_action( 'rest_api_init', function () {
	register_rest_route(
		'care-lab/v1',
		'/identify-carpet',
		array(
			'methods'             => 'POST',
			'callback'            => 'cl_handle_identify_request',
			'permission_callback' => '__return_true',
		)
	);
} );

/**
 * Feste Zuordnung Kategorie -> Label + Preis-Hinweis, bewusst NICHT
 * von der KI generiert. Werte stammen aus brand/fakten.md.
 */
function cl_identify_category_info( $kategorie ) {
	$map = array(
		'maschinenwaesche' => array(
			'label' => 'Maschinengefertigter Teppich',
			'preis' => 'Richtwert: Maschinenwäsche ab 8,00 €/m² (Mindestauftragswert 89 €).',
		),
		'handwaesche'      => array(
			'label' => 'Naturfaser- oder handgeknüpfter Teppich (Wolle, Orient, Berber, Nepal, Designerteppich)',
			'preis' => 'Richtwert: Handwäsche ab 16,00 €/m² (Mindestauftragswert 89 €).',
		),
		'pruefung'         => array(
			'label' => 'Material nicht eindeutig erkennbar oder empfindlich (z. B. Seide)',
			'preis' => 'Kein Festpreis möglich, braucht eine Materialprüfung per Foto durch unser Team.',
		),
	);

	return $map[ $kategorie ] ?? $map['pruefung'];
}

function cl_identify_rate_limited( $ip ) {
	$key   = 'cl_identify_rl_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= CL_IDENTIFY_RATE_LIMIT ) {
		return true;
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return false;
}

/**
 * Extrahiert aus der Claude-Antwort das kategorie-Feld, auch wenn
 * die Antwort in ```json ... ``` eingepackt ist oder zusätzlichen
 * Text enthält.
 */
function cl_identify_parse_category( $text ) {
	$text = trim( (string) $text );
	// Code-Fences entfernen, falls vorhanden.
	$text = preg_replace( '/^```(json)?|```$/m', '', $text );

	if ( preg_match( '/\{.*\}/s', $text, $matches ) ) {
		$data = json_decode( $matches[0], true );
		if ( is_array( $data ) && isset( $data['kategorie'] ) ) {
			$kategorie = strtolower( trim( (string) $data['kategorie'] ) );
			if ( in_array( $kategorie, array( 'maschinenwaesche', 'handwaesche', 'pruefung' ), true ) ) {
				return array( $kategorie, isset( $data['begruendung'] ) ? (string) $data['begruendung'] : '' );
			}
		}
	}

	return array( 'pruefung', '' );
}

function cl_handle_identify_request( WP_REST_Request $request ) {
	$body      = $request->get_json_params();
	$kategorie = isset( $body['kategorie'] ) ? strtolower( trim( (string) $body['kategorie'] ) ) : '';

	// Manuelle Auswahl ohne Foto: braucht weder API-Key noch KI-Aufruf,
	// die Kategorie steht ja schon fest.
	if ( '' !== $kategorie ) {
		if ( ! in_array( $kategorie, array( 'maschinenwaesche', 'handwaesche', 'pruefung' ), true ) ) {
			return new WP_Error( 'invalid_kategorie', 'Ungültige Teppichart.', array( 'status' => 400 ) );
		}
		$info = cl_identify_category_info( $kategorie );
		return array(
			'kategorie'       => $kategorie,
			'kategorie_label' => $info['label'],
			'preis_hinweis'   => $info['preis'],
			'begruendung'     => '',
		);
	}

	$claude_key = function_exists( 'cl_get_api_key' ) ? cl_get_api_key( 'claude' ) : '';
	if ( ! $claude_key ) {
		return new WP_Error( 'not_configured', 'Teppich-Erkennung ist noch nicht eingerichtet.', array( 'status' => 503 ) );
	}

	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	if ( cl_identify_rate_limited( $ip ) ) {
		return new WP_Error( 'rate_limited', 'Zu viele Anfragen. Bitte in einer Stunde erneut versuchen.', array( 'status' => 429 ) );
	}

	$image_b64 = isset( $body['image_base64'] ) ? (string) $body['image_base64'] : '';
	$mime_type = isset( $body['mime_type'] ) ? (string) $body['mime_type'] : '';
	$allowed   = array( 'image/jpeg', 'image/png', 'image/webp' );

	if ( '' === $image_b64 || ! in_array( $mime_type, $allowed, true ) ) {
		return new WP_Error( 'invalid_image', 'Bitte ein Foto als JPEG, PNG oder WebP hochladen.', array( 'status' => 400 ) );
	}

	// Grobe Größenprüfung über die Base64-Länge (Base64 ist ca. 4/3 der Rohgröße).
	if ( strlen( $image_b64 ) > CL_IDENTIFY_MAX_IMAGE_BYTES * 4 / 3 ) {
		return new WP_Error( 'image_too_large', 'Das Foto ist zu groß. Bitte ein kleineres Foto hochladen (max. 6 MB).', array( 'status' => 400 ) );
	}

	$response = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 30,
			'headers' => array(
				'x-api-key'         => $claude_key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'model'      => CL_IDENTIFY_MODEL,
					'max_tokens' => CL_IDENTIFY_MAX_TOKENS,
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => array(
								array(
									'type'   => 'image',
									'source' => array(
										'type'       => 'base64',
										'media_type' => $mime_type,
										'data'       => $image_b64,
									),
								),
								array(
									'type' => 'text',
									'text' => "Du siehst das Foto eines Teppichs. Ordne ihn EINER der drei Kategorien zu:\n" .
										"- \"maschinenwaesche\": maschinell gefertigter Teppich (z. B. glatte, gleichmäßige Webstruktur, typischer Kurzflor-/Webteppich aus dem Möbelhaus)\n" .
										"- \"handwaesche\": Woll-, Orient-, Berber-, Nepal-, Naturfaser- oder erkennbar handgeknüpfter/hochwertiger Designerteppich\n" .
										"- \"pruefung\": Material ist auf dem Foto nicht sicher erkennbar, wirkt sehr empfindlich, oder könnte Seide sein\n" .
										"Antworte AUSSCHLIESSLICH mit kompaktem JSON, kein Fließtext davor oder danach: " .
										'{"kategorie":"maschinenwaesche|handwaesche|pruefung","begruendung":"<ein kurzer Satz auf Deutsch, warum>"}' .
										"\nWenn du dir nicht sicher bist, wähle \"pruefung\".",
								),
							),
						),
					),
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'upstream_unreachable', 'Gerade nicht erreichbar, bitte später erneut versuchen.', array( 'status' => 502 ) );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || empty( $data['content'][0]['text'] ) ) {
		return new WP_Error( 'upstream_error', 'Gerade nicht erreichbar, bitte später erneut versuchen.', array( 'status' => 502 ) );
	}

	list( $kategorie, $begruendung ) = cl_identify_parse_category( $data['content'][0]['text'] );
	$info                            = cl_identify_category_info( $kategorie );

	return array(
		'kategorie'       => $kategorie,
		'kategorie_label' => $info['label'],
		'preis_hinweis'   => $info['preis'],
		'begruendung'     => $begruendung,
	);
}
