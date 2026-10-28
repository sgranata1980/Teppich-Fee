<?php
/**
 * Care Lab Backend — Anfragen / Aufträge
 *
 * Eigener Custom Post Type "cl_auftrag", der jede Buchungsanfrage aus
 * dem Tool auf /impressum-kontakt/ als echten Datensatz speichert
 * (statt nur eine E-Mail zu verschicken, die danach weg ist). Jeder
 * Auftrag speichert die eingegebenen Teppiche, Kontaktdaten und die
 * vom Kunden hochgeladenen "Vorher"-Fotos.
 *
 * Zusätzlich lässt sich im Auftrag selbst ein "Nachher"-Foto
 * hinzufügen (z. B. nachdem der Teppich abgeholt/gereinigt wurde) —
 * das dient der Beweismittelführung (Kunde kann nicht behaupten, der
 * Teppich war vorher in Ordnung) und baut nebenbei die Fotodatenbank
 * für ein künftiges öffentliches Vorher-Nachher-Feature auf.
 *
 * REST-Endpoint POST /wp-json/care-lab/v1/anfrage wird vom
 * Buchungs-Tool (impressum-kontakt.html) aufgerufen.
 */

define( 'CL_ANFRAGE_RATE_LIMIT', 10 ); // Anfragen pro Stunde pro IP

// ---------------------------------------------------------------------
// Custom Post Type
// ---------------------------------------------------------------------

add_action( 'init', function () {
	register_post_type( 'cl_auftrag', array(
		'label'        => 'Buchungen',
		'labels'       => array(
			'name'          => 'Buchungen',
			'singular_name' => 'Buchung',
			'all_items'     => 'Alle Buchungen',
			'edit_item'     => 'Buchung ansehen/bearbeiten',
			'view_item'     => 'Buchung ansehen',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'care-lab-backend',
		'supports'     => array( 'title' ),
		'capability_type' => 'post',
		'map_meta_cap' => true,
		'menu_icon'    => 'dashicons-clipboard',
	) );
} );

add_filter( 'post_edit_form_tag', function ( $tag ) {
	global $post;
	if ( $post && 'cl_auftrag' === $post->post_type ) {
		return str_replace( '>', ' enctype="multipart/form-data">', $tag );
	}
	return $tag;
} );

add_filter( 'manage_cl_auftrag_posts_columns', function ( $columns ) {
	$columns['cl_kundentyp'] = 'Typ';
	$columns['cl_tel']       = 'Telefon';
	$columns['cl_teppiche']  = 'Teppiche';
	return $columns;
} );

add_action( 'manage_cl_auftrag_posts_custom_column', function ( $column, $post_id ) {
	if ( 'cl_kundentyp' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'cl_kundentyp', true ) );
	}
	if ( 'cl_tel' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'cl_tel', true ) );
	}
	if ( 'cl_teppiche' === $column ) {
		$carpets = get_post_meta( $post_id, 'cl_carpets', true );
		echo is_array( $carpets ) ? count( $carpets ) : 0;
	}
}, 10, 2 );

// ---------------------------------------------------------------------
// Meta-Box: zeigt die Anfrage an, Upload für Nachher-Fotos
// ---------------------------------------------------------------------

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cl_auftrag_details', 'Buchungsdetails', 'cl_auftrag_details_box', 'cl_auftrag', 'normal', 'high' );
} );

function cl_auftrag_thumb( $attachment_id, $label ) {
	if ( ! $attachment_id ) {
		return '';
	}
	$url = wp_get_attachment_image_url( $attachment_id, 'medium' );
	if ( ! $url ) {
		return '';
	}
	return '<div style="display:inline-block; margin:0 10px 10px 0; text-align:center;">' .
		'<img src="' . esc_url( $url ) . '" style="max-width:160px; max-height:160px; display:block; border-radius:6px; border:1px solid #ddd;" />' .
		'<span style="font-size:11px; color:#666;">' . esc_html( $label ) . '</span>' .
		'</div>';
}

function cl_auftrag_details_box( $post ) {
	$kundentyp     = get_post_meta( $post->ID, 'cl_kundentyp', true );
	$name          = get_post_meta( $post->ID, 'cl_name', true );
	$tel           = get_post_meta( $post->ID, 'cl_tel', true );
	$email         = get_post_meta( $post->ID, 'cl_email', true );
	$msg           = get_post_meta( $post->ID, 'cl_msg', true );
	$carpets       = get_post_meta( $post->ID, 'cl_carpets', true );
	$kleinkind     = get_post_meta( $post->ID, 'cl_kleinkindpaket', true );
	$haustier      = get_post_meta( $post->ID, 'cl_haustierpaket', true );
	$subtotal      = get_post_meta( $post->ID, 'cl_subtotal_text', true );
	$subtotal_netto = get_post_meta( $post->ID, 'cl_subtotal_netto_text', true );
	$vorher_fotos  = get_post_meta( $post->ID, 'cl_vorher_fotos', true );
	$nachher_fotos = get_post_meta( $post->ID, 'cl_nachher_fotos', true );

	if ( ! is_array( $carpets ) ) {
		$carpets = array();
	}
	if ( ! is_array( $vorher_fotos ) ) {
		$vorher_fotos = array();
	}
	if ( ! is_array( $nachher_fotos ) ) {
		$nachher_fotos = array();
	}

	wp_nonce_field( 'cl_auftrag_save', 'cl_auftrag_nonce' );
	?>
	<table class="form-table">
		<tr><th>Kundentyp</th><td><?php echo esc_html( $kundentyp ); ?></td></tr>
		<tr><th>Name</th><td><?php echo esc_html( $name ); ?></td></tr>
		<tr><th>Telefon</th><td><?php echo esc_html( $tel ); ?></td></tr>
		<tr><th>E-Mail</th><td><?php echo esc_html( $email ); ?></td></tr>
		<tr><th>Nachricht</th><td><?php echo esc_html( $msg ); ?></td></tr>
		<tr><th>Kleinkinderpaket</th><td><?php echo $kleinkind ? 'Ja' : 'Nein'; ?></td></tr>
		<tr><th>Haustierpaket</th><td><?php echo $haustier ? 'Ja' : 'Nein'; ?></td></tr>
		<tr><th>Zwischensumme brutto (Richtwert)</th><td><?php echo esc_html( $subtotal ); ?></td></tr>
		<tr><th>davon Netto</th><td><?php echo esc_html( $subtotal_netto ); ?></td></tr>
	</table>

	<h3>Teppiche</h3>
	<?php
	$paket_labels = array( 'kleinkindpaket' => 'Kleinkinderpaket', 'haustierpaket' => 'Haustierpaket' );
	foreach ( $carpets as $i => $c ) :
		$carpet_pakete = is_array( $c['pakete'] ?? null ) ? $c['pakete'] : array();
		$paket_names   = array_map( function ( $p ) use ( $paket_labels ) { return $paket_labels[ $p ] ?? $p; }, $carpet_pakete );
		?>
		<div style="border:1px solid #ddd; border-radius:6px; padding:12px 16px; margin-bottom:12px;">
			<b>Teppich <?php echo (int) $i + 1; ?>:</b>
			<?php echo esc_html( $c['art'] ?? '' ); ?>
			— <?php echo esc_html( $c['breite_cm'] ?? '?' ); ?> × <?php echo esc_html( $c['laenge_cm'] ?? '?' ); ?> cm
			<?php if ( $paket_names ) : ?>
				— <?php echo esc_html( implode( ', ', $paket_names ) ); ?>
			<?php endif; ?>
			<div style="margin-top:8px;">
				<?php echo cl_auftrag_thumb( $vorher_fotos[ $i ] ?? 0, 'Vorher' ); ?>
				<?php echo cl_auftrag_thumb( $nachher_fotos[ $i ] ?? 0, 'Nachher' ); ?>
			</div>
		</div>
	<?php endforeach; ?>

	<h3>Nachher-Fotos hinzufügen</h3>
	<p class="description">Pro Teppich ein Foto nach der Reinigung/Rückgabe. Reihenfolge entspricht der Teppich-Nummerierung oben. Beim Speichern des Beitrags werden sie übernommen.</p>
	<?php foreach ( $carpets as $i => $c ) : ?>
		<p>
			<label>Teppich <?php echo (int) $i + 1; ?> — Nachher-Foto:
				<input type="file" name="cl_nachher_foto_<?php echo (int) $i; ?>" accept="image/jpeg,image/png,image/webp" />
			</label>
		</p>
	<?php endforeach; ?>
	<?php
}

add_action( 'save_post_cl_auftrag', function ( $post_id ) {
	if ( ! isset( $_POST['cl_auftrag_nonce'] ) || ! wp_verify_nonce( $_POST['cl_auftrag_nonce'], 'cl_auftrag_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$carpets = get_post_meta( $post_id, 'cl_carpets', true );
	if ( ! is_array( $carpets ) ) {
		return;
	}

	$nachher_fotos = get_post_meta( $post_id, 'cl_nachher_fotos', true );
	if ( ! is_array( $nachher_fotos ) ) {
		$nachher_fotos = array();
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	foreach ( $carpets as $i => $c ) {
		$field = 'cl_nachher_foto_' . $i;
		if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) {
			continue;
		}
		$attachment_id = media_handle_upload( $field, $post_id );
		if ( ! is_wp_error( $attachment_id ) ) {
			$nachher_fotos[ $i ] = $attachment_id;
		}
	}

	update_post_meta( $post_id, 'cl_nachher_fotos', $nachher_fotos );
} );

// ---------------------------------------------------------------------
// REST-Endpoint für die Buchungs-Anfrage vom Frontend
// ---------------------------------------------------------------------

add_action( 'rest_api_init', function () {
	register_rest_route(
		'care-lab/v1',
		'/anfrage',
		array(
			'methods'             => 'POST',
			'callback'            => 'cl_handle_anfrage_request',
			'permission_callback' => '__return_true',
		)
	);
} );

function cl_anfrage_rate_limited( $ip ) {
	$key   = 'cl_anfrage_rl_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= CL_ANFRAGE_RATE_LIMIT ) {
		return true;
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return false;
}

function cl_handle_anfrage_request( WP_REST_Request $request ) {
	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	if ( cl_anfrage_rate_limited( $ip ) ) {
		return new WP_Error( 'rate_limited', 'Zu viele Anfragen. Bitte in einer Stunde erneut versuchen.', array( 'status' => 429 ) );
	}

	// Honeypot: ein für Menschen unsichtbares Feld. Füllt ein Bot es aus,
	// tun wir so, als sei alles gutgegangen, speichern aber nichts und
	// verschicken keine E-Mail — hält Spam-Bots ab, ohne sie zu warnen.
	$honeypot = (string) $request->get_param( 'website' );
	if ( '' !== trim( $honeypot ) ) {
		return array( 'status' => 'ok', 'id' => 0 );
	}

	$kundentyp = sanitize_text_field( (string) $request->get_param( 'kundentyp' ) );
	$vorname   = sanitize_text_field( (string) $request->get_param( 'vorname' ) );
	$nachname  = sanitize_text_field( (string) $request->get_param( 'nachname' ) );
	$name      = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$tel       = sanitize_text_field( (string) $request->get_param( 'tel' ) );
	$email     = sanitize_email( (string) $request->get_param( 'email' ) );
	$msg       = sanitize_textarea_field( (string) $request->get_param( 'msg' ) );
	$subtotal  = sanitize_text_field( (string) $request->get_param( 'subtotal_text' ) );
	$subtotal_netto = sanitize_text_field( (string) $request->get_param( 'subtotal_netto_text' ) );
	$ausgleich = '1' === (string) $request->get_param( 'mindestausgleich' );

	$carpets_raw = json_decode( (string) $request->get_param( 'carpets_json' ), true );
	if ( ! is_array( $carpets_raw ) || empty( $carpets_raw ) ) {
		return new WP_Error( 'invalid_carpets', 'Bitte mindestens einen Teppich angeben.', array( 'status' => 400 ) );
	}

	if ( '' === $name || '' === $tel || ! is_email( $email ) ) {
		return new WP_Error( 'invalid_contact', 'Bitte Name, Telefon und eine gültige E-Mail-Adresse angeben.', array( 'status' => 400 ) );
	}

	$known_pakete = array( 'kleinkindpaket', 'haustierpaket' );
	$carpets      = array();
	$kleinkind    = false;
	$haustier     = false;
	foreach ( $carpets_raw as $c ) {
		$pakete_raw = is_array( $c['pakete'] ?? null ) ? $c['pakete'] : array();
		$pakete     = array_values( array_intersect( $known_pakete, array_map( 'sanitize_text_field', $pakete_raw ) ) );
		if ( in_array( 'kleinkindpaket', $pakete, true ) ) {
			$kleinkind = true;
		}
		if ( in_array( 'haustierpaket', $pakete, true ) ) {
			$haustier = true;
		}
		$carpets[] = array(
			'art'       => sanitize_text_field( (string) ( $c['art'] ?? '' ) ),
			'breite_cm' => sanitize_text_field( (string) ( $c['breite_cm'] ?? '' ) ),
			'laenge_cm' => sanitize_text_field( (string) ( $c['laenge_cm'] ?? '' ) ),
			'pakete'    => $pakete,
		);
	}

	$post_id = wp_insert_post( array(
		'post_type'   => 'cl_auftrag',
		'post_title'  => $name . ' — ' . wp_date( 'd.m.Y H:i' ),
		'post_status' => 'publish',
	) );

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'save_failed', 'Anfrage konnte nicht gespeichert werden. Bitte nutzen Sie stattdessen Telefon oder E-Mail.', array( 'status' => 500 ) );
	}

	update_post_meta( $post_id, 'cl_kundentyp', $kundentyp );
	update_post_meta( $post_id, 'cl_vorname', $vorname );
	update_post_meta( $post_id, 'cl_nachname', $nachname );
	update_post_meta( $post_id, 'cl_name', $name );
	update_post_meta( $post_id, 'cl_tel', $tel );
	update_post_meta( $post_id, 'cl_email', $email );
	update_post_meta( $post_id, 'cl_msg', $msg );
	update_post_meta( $post_id, 'cl_kleinkindpaket', $kleinkind );
	update_post_meta( $post_id, 'cl_haustierpaket', $haustier );
	update_post_meta( $post_id, 'cl_mindestausgleich', $ausgleich );
	update_post_meta( $post_id, 'cl_subtotal_text', $subtotal );
	update_post_meta( $post_id, 'cl_subtotal_netto_text', $subtotal_netto );
	update_post_meta( $post_id, 'cl_carpets', $carpets );

	// Vorher-Fotos (eines pro Teppich, per Index zugeordnet) als Anhang speichern.
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$files        = $request->get_file_params();
	$vorher_fotos = array();
	foreach ( $carpets as $i => $c ) {
		$field = 'carpet_photo_' . $i;
		if ( empty( $files[ $field ] ) || empty( $files[ $field ]['name'] ) ) {
			continue;
		}
		$_FILES[ $field ] = $files[ $field ];
		$attachment_id    = media_handle_upload( $field, $post_id );
		if ( ! is_wp_error( $attachment_id ) ) {
			$vorher_fotos[ $i ] = $attachment_id;
		}
	}
	update_post_meta( $post_id, 'cl_vorher_fotos', $vorher_fotos );

	// Benachrichtigung per E-Mail, wie bisher über das Kontaktformular.
	$lines = array(
		'Neue Buchungsanfrage (' . $kundentyp . ')',
		'',
		'Name: ' . $name,
		'Telefon: ' . $tel,
		'E-Mail: ' . $email,
	);
	$paket_labels = array( 'kleinkindpaket' => 'Kleinkinderpaket', 'haustierpaket' => 'Haustierpaket' );
	foreach ( $carpets as $i => $c ) {
		$paket_names = array_map( function ( $p ) use ( $paket_labels ) { return $paket_labels[ $p ] ?? $p; }, $c['pakete'] );
		$lines[]     = '';
		$lines[]     = 'Teppich ' . ( $i + 1 ) . ': ' . $c['art'] . ' — ' . $c['breite_cm'] . ' × ' . $c['laenge_cm'] . ' cm' . ( $paket_names ? ' (' . implode( ', ', $paket_names ) . ')' : '' );
	}
	$lines[] = '';
	$lines[] = 'Zwischensumme brutto (Richtwert, inkl. 19% MwSt.): ' . $subtotal . ( $ausgleich ? ' (inkl. Mindestauftrag-Ausgleich)' : '' );
	if ( $subtotal_netto ) {
		$lines[] = 'davon Netto: ' . $subtotal_netto;
	}
	if ( $msg ) {
		$lines[] = '';
		$lines[] = 'Nachricht: ' . $msg;
	}
	$lines[] = '';
	$lines[] = 'Im Backend ansehen: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' );

	wp_mail( 'zerenmobil@googlemail.com', 'Neue Buchungsanfrage: ' . $name, implode( "\n", $lines ) );

	// Bestätigungsmail an den Absender selbst, damit er weiß, dass seine
	// Anfrage angekommen ist (bis es ein echtes Buchungssystem gibt, ist
	// das hier noch eine unverbindliche Anfrage, kein verbindlicher Auftrag).
	$customer_lines = array(
		'Hallo ' . $vorname . ',',
		'',
		'danke für deine Anfrage bei Care Lab. Sie ist bei uns eingegangen, wir melden uns in der Regel innerhalb eines Werktags mit einer verbindlichen Bestätigung bei dir.',
		'',
		'Deine Angaben:',
	);
	foreach ( $carpets as $i => $c ) {
		$paket_names        = array_map( function ( $p ) use ( $paket_labels ) { return $paket_labels[ $p ] ?? $p; }, $c['pakete'] );
		$customer_lines[]   = 'Teppich ' . ( $i + 1 ) . ': ' . $c['art'] . ' — ' . $c['breite_cm'] . ' × ' . $c['laenge_cm'] . ' cm' . ( $paket_names ? ' (' . implode( ', ', $paket_names ) . ')' : '' );
	}
	$customer_lines[] = '';
	$customer_lines[] = 'Zwischensumme brutto (Richtwert, unter Vorbehalt, inkl. 19% MwSt.): ' . $subtotal;
	if ( $subtotal_netto ) {
		$customer_lines[] = 'davon Netto: ' . $subtotal_netto;
	}
	$customer_lines[] = '';
	$customer_lines[] = 'Der Betrag ist ein Richtwert auf Basis deiner Angaben, final bestätigt nach Prüfung vor Ort. Abholung ist aktuell kostenlos.';
	$customer_lines[] = '';
	$customer_lines[] = 'Fragen? Ruf uns an: 0172 8293606, oder antworte einfach auf diese E-Mail.';
	$customer_lines[] = '';
	$customer_lines[] = 'Viele Grüße';
	$customer_lines[] = 'Care Lab';

	if ( is_email( $email ) ) {
		wp_mail( $email, 'Deine Anfrage bei Care Lab ist eingegangen', implode( "\n", $customer_lines ) );
	}

	return array( 'status' => 'ok', 'id' => $post_id );
}
