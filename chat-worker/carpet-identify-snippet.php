<?php
/**
 * Care Lab Teppich-Erkennung — REST-Endpoint
 *
 * Nimmt ein Foto entgegen (Base64) und lässt Claude (Vision) einschätzen,
 * welcher der im Preiseditor hinterlegten Teppicharten (wp-admin → Care
 * Lab → Preise, Tabelle "Grundreinigung nach Material") am ehesten
 * passt. Die KI bekommt die EXAKTE, aktuell gültige Liste als Prompt
 * mitgegeben und muss eine davon per Nummer wählen (oder 0, wenn sie
 * unsicher ist) — sie erfindet also nie eine Teppichart oder einen
 * Preis, die es nicht gibt. Wird vom Buchungs-Tool (impressum-kontakt.html)
 * aufgerufen: Foto in den Dropzonen hochladen → Teppichart-Dropdown wird
 * automatisch vorausgefüllt, falls noch leer, als reiner Richtwert.
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
 * Extrahiert aus der Claude-Antwort die gewählte Nummer + Begründung,
 * auch wenn die Antwort in ```json ... ``` eingepackt ist oder
 * zusätzlichen Text enthält.
 */
function cl_identify_parse_result( $text ) {
	$text = trim( (string) $text );
	$text = preg_replace( '/^```(json)?|```$/m', '', $text );

	if ( preg_match( '/\{.*\}/s', $text, $matches ) ) {
		$data = json_decode( $matches[0], true );
		if ( is_array( $data ) ) {
			$nummer      = isset( $data['nummer'] ) ? (int) $data['nummer'] : 0;
			$begruendung = isset( $data['begruendung'] ) ? (string) $data['begruendung'] : '';
			return array( $nummer, $begruendung );
		}
	}

	return array( 0, '' );
}

function cl_handle_identify_request( WP_REST_Request $request ) {
	$claude_key = function_exists( 'cl_get_api_key' ) ? cl_get_api_key( 'claude' ) : '';
	if ( ! $claude_key ) {
		return new WP_Error( 'not_configured', 'Teppich-Erkennung ist noch nicht eingerichtet.', array( 'status' => 503 ) );
	}
	if ( ! function_exists( 'cl_get_prices_material' ) ) {
		return new WP_Error( 'not_configured', 'Preisliste gerade nicht verfügbar.', array( 'status' => 503 ) );
	}

	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	if ( cl_identify_rate_limited( $ip ) ) {
		return new WP_Error( 'rate_limited', 'Zu viele Anfragen. Bitte in einer Stunde erneut versuchen.', array( 'status' => 429 ) );
	}

	$body      = $request->get_json_params();
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

	$material_list = array();
	foreach ( cl_get_prices_material() as $row ) {
		if ( ! empty( $row['art'] ) ) {
			$material_list[] = $row['art'];
		}
	}
	if ( empty( $material_list ) ) {
		return new WP_Error( 'not_configured', 'Keine Teppicharten konfiguriert.', array( 'status' => 503 ) );
	}

	$list_text = '';
	foreach ( $material_list as $i => $art ) {
		$list_text .= ( $i + 1 ) . '. ' . $art . "\n";
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
									'text' => "Du siehst das Foto eines Teppichs. Ordne ihn GENAU EINER dieser Teppicharten zu (exakt wie in der Liste, nicht umformulieren):\n" . $list_text .
										"Wenn keine der Optionen wirklich passt oder du unsicher bist, wähle 0.\n" .
										"Antworte AUSSCHLIESSLICH mit kompaktem JSON, kein Fließtext davor oder danach: " .
										'{"nummer":<Zahl aus der Liste oder 0>,"begruendung":"<ein kurzer Satz auf Deutsch, woran du das erkennst>"}',
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

	list( $nummer, $begruendung ) = cl_identify_parse_result( $data['content'][0]['text'] );
	$art = ( $nummer >= 1 && $nummer <= count( $material_list ) ) ? $material_list[ $nummer - 1 ] : '';

	return array(
		'art'         => $art,
		'begruendung' => $begruendung,
	);
}
