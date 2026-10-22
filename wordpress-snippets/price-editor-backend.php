<?php
/**
 * Care Lab Backend — Preiseditor
 *
 * Eigene Unterseite im "Care Lab"-Admin-Menü (wp-admin → Care Lab →
 * Preise), über die Cem/Stefano die Preise auf /leistungen-pakete/
 * selbst pflegen können, ohne Code/Git anfassen zu müssen.
 *
 * Die drei Preistabellen der Seite werden als WordPress-Optionen
 * gespeichert (cl_prices_material, cl_prices_repair, cl_prices_zusatz
 * plus je eine Notiz) und per Shortcode ins Seiten-HTML eingeblendet:
 *
 *   [cl_preise_material]   → Tabelle "Grundreinigung nach Material"
 *   [cl_preise_reparatur]  → Tabelle "Antik-Reparatur & Teppichreparatur"
 *   [cl_preise_zusatz]     → Karten für "Einzelleistungen" (ohne Grid-Wrapper)
 *
 * Neue Teppicharten/Leistungen werden einfach als neue Zeile im
 * Formular hinzugefügt (ein paar Leerzeilen stehen immer am Ende
 * bereit), Löschen per Checkbox. Keine Mengenbegrenzung.
 */

// ---------------------------------------------------------------------
// Default-Werte (Stand 22.10.2026, 1:1 von aslan-center.com übernommen).
// Greifen nur, solange noch keine eigene Änderung gespeichert wurde.
// ---------------------------------------------------------------------

function cl_prices_material_default() {
	return array(
		array( 'art' => 'Maschinenteppiche, Acryl- & Polyesterteppiche (inkl. Shaggy)', 'verfahren' => 'Maschinenwäsche', 'preis' => '7,99 €/m²' ),
		array( 'art' => 'Shaggy Wolle', 'verfahren' => 'Maschinenwäsche', 'preis' => '9,99 €/m²' ),
		array( 'art' => 'Orientalische Maschinenteppiche', 'verfahren' => 'Maschinenwäsche', 'preis' => 'ab 9,99 €/m²' ),
		array( 'art' => 'Handgeknüpfte Teppiche', 'verfahren' => 'Handwäsche', 'preis' => 'ab 24,99 €/m²' ),
		array( 'art' => 'Wollteppiche', 'verfahren' => 'Handwäsche', 'preis' => 'ab 24,99 €/m²' ),
		array( 'art' => 'Viskoseteppiche', 'verfahren' => 'Handwäsche', 'preis' => 'auf Anfrage' ),
		array( 'art' => 'Seidenteppiche', 'verfahren' => 'Handwäsche / Materialprüfung', 'preis' => 'ab 39,99 €/m²' ),
		array( 'art' => 'Stark verschmutzte oder strukturell empfindliche Teppiche', 'verfahren' => 'Spezialwäsche nach Prüfung', 'preis' => 'auf Anfrage' ),
	);
}

function cl_prices_repair_default() {
	return array(
		array( 'leistung' => 'Fransen erneuern', 'preis' => 'auf Anfrage' ),
		array( 'leistung' => 'Mottenschäden reparieren', 'preis' => 'auf Anfrage' ),
		array( 'leistung' => 'Brandschäden reparieren', 'preis' => 'auf Anfrage' ),
		array( 'leistung' => 'Verfärbungen behandeln', 'preis' => 'auf Anfrage' ),
		array( 'leistung' => 'Teppich kürzen / anpassen', 'preis' => 'auf Anfrage' ),
	);
}

function cl_prices_zusatz_default() {
	return array(
		array( 'titel' => 'Fleckenschutz & Imprägnierung', 'beschreibung' => 'Unsichtbare Schutzschicht auf PFAS-freier Dendrimer-Basis, erleichtert spätere Pflege.', 'preis' => '15,00 €/m²' ),
		array( 'titel' => 'Wollpflege, Rückfettung & Weichspüler', 'beschreibung' => 'Für geeignete Naturfaserteppiche.', 'preis' => '2,99 €/m²' ),
		array( 'titel' => 'Mottenschutz', 'beschreibung' => 'Vorbeugend, besonders vor Einlagerung.', 'preis' => '3,99 €/m²' ),
		array( 'titel' => 'Geruchsneutralisierung', 'beschreibung' => 'Tiergeruch, Rauch, allgemeiner Mief.', 'preis' => '8,90 €/m²' ),
		array( 'titel' => 'Urin- & Haustier-Sonderbehandlung', 'beschreibung' => 'Intensive Vorbehandlung und Spülung.', 'preis' => '11,90 €/m²' ),
		array( 'titel' => 'Intensive Fleckenbehandlung', 'beschreibung' => 'Rotwein, Kaffee, Fett, Altflecken.', 'preis' => '6,90 €/m² *' ),
		array( 'titel' => 'Stark verschmutzte Teppiche', 'beschreibung' => 'Hoher Sand-/Staubeintrag, mehrere Waschgänge.', 'preis' => '+30 % *' ),
		array( 'titel' => 'Express-Service', 'beschreibung' => 'Priorisierte Trocknung, verbindlicher Termin.', 'preis' => '+30–40 %' ),
		array( 'titel' => 'Teppicheinlagerung', 'beschreibung' => 'Gereinigt, verpackt, dokumentiert.', 'preis' => 'ab 15–30 €/Monat' ),
		array( 'titel' => 'Abholung & Lieferung', 'beschreibung' => 'Fahrtkostenpauschale pro Auftrag.', 'preis' => '5,00 €' ),
	);
}

function cl_prices_material_note_default() {
	return 'Preise inkl. MwSt. Mindestauftragswert: 89 €. Ab einem definierten Auftragswert ist die Abholung im Nahbereich kostenlos.';
}

function cl_prices_repair_note_default() {
	return 'Reparaturen erfordern immer eine Begutachtung vor Ort oder per Foto, der Preis richtet sich nach Schaden und Material.';
}

// ---------------------------------------------------------------------
// Getter (Option, Fallback auf Default)
// ---------------------------------------------------------------------

function cl_get_prices_material() {
	$rows = get_option( 'cl_prices_material', null );
	return is_array( $rows ) && ! empty( $rows ) ? $rows : cl_prices_material_default();
}

function cl_get_prices_repair() {
	$rows = get_option( 'cl_prices_repair', null );
	return is_array( $rows ) && ! empty( $rows ) ? $rows : cl_prices_repair_default();
}

function cl_get_prices_zusatz() {
	$rows = get_option( 'cl_prices_zusatz', null );
	return is_array( $rows ) && ! empty( $rows ) ? $rows : cl_prices_zusatz_default();
}

function cl_get_prices_material_note() {
	$note = get_option( 'cl_prices_material_note', '' );
	return '' !== $note ? $note : cl_prices_material_note_default();
}

function cl_get_prices_repair_note() {
	$note = get_option( 'cl_prices_repair_note', '' );
	return '' !== $note ? $note : cl_prices_repair_note_default();
}

// ---------------------------------------------------------------------
// Shortcodes fürs Frontend
// ---------------------------------------------------------------------

add_shortcode( 'cl_preise_material', function () {
	$rows = cl_get_prices_material();
	ob_start();
	?>
	<div class="table-scroll">
	<table>
		<thead><tr><th>Teppichart</th><th>Verfahren</th><th>Preis</th></tr></thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
			<tr>
				<td><?php echo esc_html( $row['art'] ?? '' ); ?></td>
				<td><?php echo esc_html( $row['verfahren'] ?? '' ); ?></td>
				<td class="price"><?php echo esc_html( $row['preis'] ?? '' ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p class="basetable-swipe-hint">← Nach rechts wischen für Preis →</p>
	<p class="basetable-note"><?php echo esc_html( cl_get_prices_material_note() ); ?></p>
	<?php
	return ob_get_clean();
} );

add_shortcode( 'cl_preise_reparatur', function () {
	$rows = cl_get_prices_repair();
	ob_start();
	?>
	<div class="table-scroll">
	<table>
		<thead><tr><th>Leistung</th><th>Preis</th></tr></thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
			<tr>
				<td><?php echo esc_html( $row['leistung'] ?? '' ); ?></td>
				<td class="price"><?php echo esc_html( $row['preis'] ?? '' ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p class="basetable-note"><?php echo esc_html( cl_get_prices_repair_note() ); ?></p>
	<?php
	return ob_get_clean();
} );

add_shortcode( 'cl_preise_zusatz', function () {
	$rows = cl_get_prices_zusatz();
	ob_start();
	foreach ( $rows as $row ) :
		?>
		<div class="zusatz-item">
			<div>
				<h4><?php echo esc_html( $row['titel'] ?? '' ); ?></h4>
				<p><?php echo wp_kses_post( $row['beschreibung'] ?? '' ); ?></p>
			</div>
			<span class="p"><?php echo esc_html( $row['preis'] ?? '' ); ?></span>
		</div>
		<?php
	endforeach;
	return ob_get_clean();
} );

// ---------------------------------------------------------------------
// Admin-Menü
// ---------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_submenu_page(
		'care-lab-backend',
		'Preise',
		'Preise',
		'manage_options',
		'care-lab-preise',
		'cl_preise_page'
	);
} );

/**
 * Liest ein Zeilen-Array aus $_POST, verwirft leere/gelöschte Zeilen.
 *
 * @param string $post_key Name des POST-Arrays (z. B. 'material').
 * @param array  $fields   Feldnamen pro Zeile, die gespeichert werden.
 */
function cl_collect_price_rows( $post_key, $fields ) {
	if ( ! isset( $_POST[ $post_key ] ) || ! is_array( $_POST[ $post_key ] ) ) {
		return array();
	}

	$clean = array();
	foreach ( wp_unslash( $_POST[ $post_key ] ) as $row ) {
		if ( ! empty( $row['delete'] ) ) {
			continue;
		}
		$entry      = array();
		$has_content = false;
		foreach ( $fields as $field ) {
			$value           = isset( $row[ $field ] ) ? sanitize_text_field( $row[ $field ] ) : '';
			$entry[ $field ] = $value;
			if ( '' !== $value ) {
				$has_content = true;
			}
		}
		if ( $has_content ) {
			$clean[] = $entry;
		}
	}
	return $clean;
}

function cl_preise_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['cl_preise_nonce'] ) && wp_verify_nonce( $_POST['cl_preise_nonce'], 'cl_preise_save' ) ) {
		update_option( 'cl_prices_material', cl_collect_price_rows( 'material', array( 'art', 'verfahren', 'preis' ) ) );
		update_option( 'cl_prices_repair', cl_collect_price_rows( 'repair', array( 'leistung', 'preis' ) ) );
		update_option( 'cl_prices_zusatz', cl_collect_price_rows( 'zusatz', array( 'titel', 'beschreibung', 'preis' ) ) );
		update_option( 'cl_prices_material_note', sanitize_text_field( wp_unslash( $_POST['material_note'] ?? '' ) ) );
		update_option( 'cl_prices_repair_note', sanitize_text_field( wp_unslash( $_POST['repair_note'] ?? '' ) ) );
		echo '<div class="notice notice-success is-dismissible"><p>Preise gespeichert. Änderungen sind sofort auf der Seite „Leistungen & Preise“ sichtbar.</p></div>';
	}

	$material = cl_get_prices_material();
	$repair   = cl_get_prices_repair();
	$zusatz   = cl_get_prices_zusatz();

	// Ein paar Leerzeilen am Ende zum Hinzufügen neuer Teppicharten/Leistungen.
	for ( $i = 0; $i < 3; $i++ ) {
		$material[] = array( 'art' => '', 'verfahren' => '', 'preis' => '' );
		$repair[]   = array( 'leistung' => '', 'preis' => '' );
		$zusatz[]   = array( 'titel' => '', 'beschreibung' => '', 'preis' => '' );
	}
	?>
	<div class="wrap">
		<h1>Preise</h1>
		<p>Diese Preise erscheinen live auf <a href="<?php echo esc_url( home_url( '/leistungen-pakete/' ) ); ?>" target="_blank">/leistungen-pakete/</a>. Neue Zeile: einfach eine der leeren Zeilen unten ausfüllen. Zeile löschen: Haken bei „Löschen“ setzen und speichern. Leere Zeilen werden beim Speichern automatisch verworfen.</p>
		<form method="post">
			<?php wp_nonce_field( 'cl_preise_save', 'cl_preise_nonce' ); ?>

			<h2>Grundreinigung nach Material</h2>
			<table class="widefat striped" style="max-width:1100px;">
				<thead><tr><th>Teppichart</th><th>Verfahren</th><th>Preis</th><th>Löschen</th></tr></thead>
				<tbody>
					<?php foreach ( $material as $i => $row ) : ?>
					<tr>
						<td><input type="text" name="material[<?php echo $i; ?>][art]" value="<?php echo esc_attr( $row['art'] ); ?>" class="regular-text" style="width:100%;"></td>
						<td><input type="text" name="material[<?php echo $i; ?>][verfahren]" value="<?php echo esc_attr( $row['verfahren'] ); ?>" style="width:100%;"></td>
						<td><input type="text" name="material[<?php echo $i; ?>][preis]" value="<?php echo esc_attr( $row['preis'] ); ?>" style="width:100%;"></td>
						<td><input type="checkbox" name="material[<?php echo $i; ?>][delete]" value="1"></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<label>Hinweistext unter der Tabelle:<br>
				<input type="text" name="material_note" value="<?php echo esc_attr( cl_get_prices_material_note() ); ?>" style="width:100%;max-width:700px;"></label>
			</p>

			<h2 style="margin-top:36px;">Antik-Reparatur &amp; Teppichreparatur</h2>
			<table class="widefat striped" style="max-width:900px;">
				<thead><tr><th>Leistung</th><th>Preis</th><th>Löschen</th></tr></thead>
				<tbody>
					<?php foreach ( $repair as $i => $row ) : ?>
					<tr>
						<td><input type="text" name="repair[<?php echo $i; ?>][leistung]" value="<?php echo esc_attr( $row['leistung'] ); ?>" style="width:100%;"></td>
						<td><input type="text" name="repair[<?php echo $i; ?>][preis]" value="<?php echo esc_attr( $row['preis'] ); ?>" style="width:100%;"></td>
						<td><input type="checkbox" name="repair[<?php echo $i; ?>][delete]" value="1"></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<label>Hinweistext unter der Tabelle:<br>
				<input type="text" name="repair_note" value="<?php echo esc_attr( cl_get_prices_repair_note() ); ?>" style="width:100%;max-width:700px;"></label>
			</p>

			<h2 style="margin-top:36px;">Einzelleistungen</h2>
			<table class="widefat striped" style="max-width:1200px;">
				<thead><tr><th>Titel</th><th>Beschreibung</th><th>Preis</th><th>Löschen</th></tr></thead>
				<tbody>
					<?php foreach ( $zusatz as $i => $row ) : ?>
					<tr>
						<td><input type="text" name="zusatz[<?php echo $i; ?>][titel]" value="<?php echo esc_attr( $row['titel'] ); ?>" style="width:100%;"></td>
						<td><input type="text" name="zusatz[<?php echo $i; ?>][beschreibung]" value="<?php echo esc_attr( $row['beschreibung'] ); ?>" style="width:100%;"></td>
						<td><input type="text" name="zusatz[<?php echo $i; ?>][preis]" value="<?php echo esc_attr( $row['preis'] ); ?>" style="width:100%;"></td>
						<td><input type="checkbox" name="zusatz[<?php echo $i; ?>][delete]" value="1"></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( 'Preise speichern' ); ?>
		</form>
	</div>
	<?php
}
