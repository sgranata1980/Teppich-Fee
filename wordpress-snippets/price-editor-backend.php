<?php
/**
 * Care Lab Backend — Preiseditor
 *
 * Eigene Unterseite im "Care Lab"-Admin-Menü (wp-admin → Care Lab →
 * Preise), über die Cem/Stefano die Preise auf /leistungen-pakete/
 * selbst pflegen können, ohne Code/Git anfassen zu müssen.
 *
 * Jede Preiszeile besteht aus strukturierten Feldern statt einem
 * einzigen Freitext-Feld: Präfix (genau/ab/Aufschlag +), Betrag,
 * optionaler Bis-Betrag (für Spannen), Einheit (€/m², %, €/Monat,
 * € pro Stück, Auf Anfrage, Kostenlos) und ein Sternchen-Hinweis.
 * Der fürs Frontend/REST angezeigte Preistext ("ab 9,99 €/m²" etc.)
 * wird daraus automatisch zusammengesetzt (cl_format_price()) — man
 * tippt also nur noch die Zahl ein, die Einheit wird ausgewählt statt
 * mitgetippt. Zusätzlich zeigt das Formular live den Netto-Betrag
 * (Annahme: eingetippte Preise sind Brutto, 19 % MwSt.) neben jedem
 * Betragsfeld an.
 *
 * Die Preistabellen der Seite werden als WordPress-Optionen
 * gespeichert (cl_prices_material, cl_prices_repair, cl_prices_zusatz,
 * cl_prices_polster, plus je eine Notiz) und per Shortcode ins
 * Seiten-HTML eingeblendet:
 *
 *   [cl_preise_material]      → Tabelle "Grundreinigung nach Material"
 *   [cl_preise_reparatur]     → Tabelle "Antik-Reparatur & Teppichreparatur"
 *   [cl_preise_zusatz]        → Karten für "Einzelleistungen" (ohne Grid-Wrapper)
 *   [cl_preise_polster]       → Tabelle "Polsterreinigung"
 *   [cl_preise_pflegepakete]  → Kästen für "Pflegepakete" (inkl. .paket-grid-Wrapper)
 *
 * Neue Teppicharten/Leistungen werden einfach als neue Zeile im
 * Formular hinzugefügt (ein paar Leerzeilen stehen immer am Ende
 * bereit), Löschen per Checkbox. Keine Mengenbegrenzung.
 *
 * Seit 02.11.2026:
 * - Sichtbarkeit: Jede Zeile hat ein Feld 'aktiv' (bool). Ausgeblendete
 *   Zeilen (aktiv = false) bleiben gespeichert und im Admin-Formular
 *   sichtbar (ausgegraut), fehlen aber in allen Shortcodes, im
 *   REST-Endpoint /wp-json/care-lab/v1/preise und damit auch im
 *   Chat-Assistenten und in der Teppich-Erkennung (die rufen dieselben
 *   cl_get_prices_*()-Getter auf, die standardmäßig nur aktive Zeilen
 *   liefern). Alte Zeilen ohne 'aktiv'-Feld gelten als aktiv.
 * - Reihenfolge: ▲/▼-Buttons pro Zeile verschieben die Zeile um eine
 *   Position (serverseitig, Formular-Submit mit cl_move=tabelle:index:
 *   up|down, speichert dabei auch alle anderen Änderungen mit). Die
 *   gespeicherte Reihenfolge ist zugleich die Ausgabereihenfolge.
 */

define( 'CL_MWST_SATZ', 0.19 );

// ---------------------------------------------------------------------
// Preis-Format: strukturierte Felder → fertiger Anzeigetext.
// ---------------------------------------------------------------------

/**
 * Setzt bei einer (ggf. noch alten, nur-Freitext-) Preiszeile die
 * strukturierten Felder, falls sie fehlen — per Bestvermutung aus dem
 * bisherigen Freitext-Preis geparst. Bereits strukturierte Zeilen
 * bleiben unverändert.
 */
function cl_ensure_structured_price( $row ) {
	if ( array_key_exists( 'einheit', $row ) ) {
		return $row;
	}

	$preis = trim( (string) ( $row['preis'] ?? '' ) );
	$row['praefix']    = '';
	$row['betrag']     = '';
	$row['betrag_bis'] = '';
	$row['einheit']    = 'm2';
	$row['hinweis']    = false;

	if ( '' === $preis ) {
		return $row;
	}
	if ( false !== stripos( $preis, 'auf anfrage' ) ) {
		$row['einheit'] = 'anfrage';
		return $row;
	}
	if ( false !== stripos( $preis, 'kostenlos' ) ) {
		$row['einheit'] = 'kostenlos';
		return $row;
	}

	if ( preg_match( '/^(ab\s+)?(\+)?([\d]+(?:,[\d]+)?)(?:[–-]([\d]+(?:,[\d]+)?))?\s*(€\/m²|€\/Monat|%|€)?\s*(\*)?$/u', $preis, $m ) ) {
		$row['praefix']    = ! empty( $m[1] ) ? 'ab' : ( ! empty( $m[2] ) ? '+' : '' );
		$row['betrag']     = $m[3] ?? '';
		$row['betrag_bis'] = $m[4] ?? '';
		$unit_map          = array(
			'€/m²'    => 'm2',
			'€/Monat' => 'monat',
			'%'       => 'prozent',
			'€'       => 'stueck',
		);
		$row['einheit'] = $unit_map[ $m[5] ?? '' ] ?? 'm2';
		$row['hinweis'] = ! empty( $m[6] );
	}

	return $row;
}

/**
 * Baut aus den strukturierten Feldern den fertigen Anzeigetext,
 * z. B. "ab 9,99 €/m²", "+30–40 %", "Auf Anfrage".
 */
function cl_format_price( $row ) {
	$einheit = $row['einheit'] ?? 'm2';

	if ( 'anfrage' === $einheit ) {
		return 'auf Anfrage';
	}
	if ( 'kostenlos' === $einheit ) {
		return 'Kostenlos';
	}

	$betrag = isset( $row['betrag'] ) ? trim( (string) $row['betrag'] ) : '';
	if ( '' === $betrag ) {
		return '';
	}

	$bis    = isset( $row['betrag_bis'] ) ? trim( (string) $row['betrag_bis'] ) : '';
	$amount = $betrag . ( '' !== $bis ? '–' . $bis : '' );

	$praefix    = $row['praefix'] ?? '';
	$prefix_str = '';
	if ( 'ab' === $praefix ) {
		$prefix_str = 'ab ';
	} elseif ( '+' === $praefix ) {
		$prefix_str = '+';
	}

	$suffix_map = array(
		'm2'      => ' €/m²',
		'prozent' => ' %',
		'monat'   => ' €/Monat',
		'stueck'  => ' €',
	);
	$suffix  = $suffix_map[ $einheit ] ?? '';
	$hinweis = ! empty( $row['hinweis'] ) ? ' *' : '';

	return $prefix_str . $amount . $suffix . $hinweis;
}

/**
 * Stellt sicher, dass eine Zeile strukturiert ist, UND lässt den
 * Anzeigetext frisch aus den strukturierten Feldern berechnen
 * (statt einem evtl. veralteten gespeicherten preis-Wert zu trauen).
 */
function cl_hydrate_price_row( $row ) {
	$row          = cl_ensure_structured_price( $row );
	$row['preis'] = cl_format_price( $row );
	$row['aktiv'] = cl_row_is_active( $row );
	return $row;
}

/**
 * Ist die Zeile im Frontend sichtbar? Zeilen ohne 'aktiv'-Feld (alle
 * vor dem 02.11.2026 gespeicherten) gelten als aktiv, damit bestehende
 * Preise nicht plötzlich verschwinden.
 */
function cl_row_is_active( $row ) {
	return ! is_array( $row ) || ! array_key_exists( 'aktiv', $row ) || ! empty( $row['aktiv'] );
}

/**
 * Filtert ausgeblendete Zeilen heraus (außer fürs Admin-Formular).
 */
function cl_filter_active_rows( $rows, $include_inactive = false ) {
	if ( $include_inactive ) {
		return array_values( $rows );
	}
	return array_values( array_filter( $rows, 'cl_row_is_active' ) );
}

// ---------------------------------------------------------------------
// Default-Werte (Stand 28.10.2026). Greifen nur, solange noch keine
// eigene Änderung gespeichert wurde.
// ---------------------------------------------------------------------

function cl_prices_material_default() {
	return array(
		array( 'art' => 'Maschinenteppiche, Acryl- & Polyesterteppiche (inkl. Shaggy)', 'verfahren' => 'Maschinenwäsche', 'praefix' => '', 'betrag' => '7,99', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'art' => 'Shaggy Wolle', 'verfahren' => 'Maschinenwäsche', 'praefix' => '', 'betrag' => '9,99', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'art' => 'Orientalische Maschinenteppiche', 'verfahren' => 'Maschinenwäsche', 'praefix' => 'ab', 'betrag' => '9,99', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'art' => 'Handgeknüpfte Teppiche', 'verfahren' => 'Handwäsche', 'praefix' => 'ab', 'betrag' => '24,99', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'art' => 'Wollteppiche', 'verfahren' => 'Handwäsche', 'praefix' => 'ab', 'betrag' => '24,99', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'art' => 'Viskoseteppiche', 'verfahren' => 'Handwäsche', 'praefix' => '', 'betrag' => '', 'betrag_bis' => '', 'einheit' => 'anfrage', 'hinweis' => false ),
		array( 'art' => 'Seidenteppiche', 'verfahren' => 'Handwäsche / Materialprüfung', 'praefix' => 'ab', 'betrag' => '39,99', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'art' => 'Stark verschmutzte oder strukturell empfindliche Teppiche', 'verfahren' => 'Spezialwäsche nach Prüfung', 'praefix' => '', 'betrag' => '', 'betrag_bis' => '', 'einheit' => 'anfrage', 'hinweis' => false ),
	);
}

function cl_prices_repair_default() {
	return array(
		array( 'leistung' => 'Fransen erneuern', 'praefix' => '', 'betrag' => '', 'betrag_bis' => '', 'einheit' => 'anfrage', 'hinweis' => false ),
		array( 'leistung' => 'Mottenschäden reparieren', 'praefix' => '', 'betrag' => '', 'betrag_bis' => '', 'einheit' => 'anfrage', 'hinweis' => false ),
		array( 'leistung' => 'Brandschäden reparieren', 'praefix' => '', 'betrag' => '', 'betrag_bis' => '', 'einheit' => 'anfrage', 'hinweis' => false ),
		array( 'leistung' => 'Verfärbungen behandeln', 'praefix' => '', 'betrag' => '', 'betrag_bis' => '', 'einheit' => 'anfrage', 'hinweis' => false ),
		array( 'leistung' => 'Teppich kürzen / anpassen', 'praefix' => '', 'betrag' => '', 'betrag_bis' => '', 'einheit' => 'anfrage', 'hinweis' => false ),
	);
}

function cl_prices_zusatz_default() {
	return array(
		array( 'titel' => 'Fleckenschutz & Imprägnierung', 'beschreibung' => 'Unsichtbare Schutzschicht auf PFAS-freier Dendrimer-Basis, erleichtert spätere Pflege.', 'praefix' => '', 'betrag' => '15,00', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'titel' => 'Wollpflege, Rückfettung & Weichspüler', 'beschreibung' => 'Für geeignete Naturfaserteppiche.', 'praefix' => '', 'betrag' => '2,99', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'titel' => 'Mottenschutz', 'beschreibung' => 'Vorbeugend, besonders vor Einlagerung.', 'praefix' => '', 'betrag' => '3,99', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'titel' => 'Geruchsneutralisierung', 'beschreibung' => 'Tiergeruch, Rauch, allgemeiner Mief.', 'praefix' => '', 'betrag' => '8,90', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'titel' => 'Haustier-Sonderbehandlung', 'beschreibung' => 'Intensive Vorbehandlung und Spülung.', 'praefix' => '', 'betrag' => '2,90', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false ),
		array( 'titel' => 'Kleinkinderpaket', 'beschreibung' => 'Zusatzleistungen für Haushalte mit kleinen Kindern. Details individuell nach Bedarf.', 'praefix' => '', 'betrag' => '', 'betrag_bis' => '', 'einheit' => 'anfrage', 'hinweis' => false ),
		array( 'titel' => 'Intensive Fleckenbehandlung', 'beschreibung' => 'Rotwein, Kaffee, Fett, Altflecken.', 'praefix' => '', 'betrag' => '6,90', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => true ),
		array( 'titel' => 'Stark verschmutzte Teppiche', 'beschreibung' => 'Hoher Sand-/Staubeintrag, mehrere Waschgänge.', 'praefix' => '+', 'betrag' => '30', 'betrag_bis' => '', 'einheit' => 'prozent', 'hinweis' => true ),
		array( 'titel' => 'Express-Service', 'beschreibung' => 'Priorisierte Trocknung, verbindlicher Termin.', 'praefix' => '+', 'betrag' => '30', 'betrag_bis' => '40', 'einheit' => 'prozent', 'hinweis' => false ),
		array( 'titel' => 'Teppicheinlagerung', 'beschreibung' => 'Gereinigt, verpackt, dokumentiert.', 'praefix' => 'ab', 'betrag' => '15', 'betrag_bis' => '30', 'einheit' => 'monat', 'hinweis' => false ),
		array( 'titel' => 'Abholung & Lieferung', 'beschreibung' => 'Aktuell kostenlos, ggf. künftig ab einem Mindestbestellwert.', 'praefix' => '', 'betrag' => '', 'betrag_bis' => '', 'einheit' => 'kostenlos', 'hinweis' => false ),
	);
}

function cl_prices_polster_default() {
	return array(
		array( 'stueck' => 'Sessel', 'praefix' => '', 'betrag' => '70,00', 'betrag_bis' => '', 'einheit' => 'stueck', 'hinweis' => false ),
		array( 'stueck' => 'Sofa 2-Sitzer', 'praefix' => '', 'betrag' => '100,00', 'betrag_bis' => '', 'einheit' => 'stueck', 'hinweis' => false ),
		array( 'stueck' => 'Sofa 3-Sitzer', 'praefix' => '', 'betrag' => '125,00', 'betrag_bis' => '', 'einheit' => 'stueck', 'hinweis' => false ),
		array( 'stueck' => 'Sofa 4-Sitzer', 'praefix' => '', 'betrag' => '160,00', 'betrag_bis' => '', 'einheit' => 'stueck', 'hinweis' => false ),
		array( 'stueck' => 'Sofa 5-Sitzer', 'praefix' => '', 'betrag' => '190,00', 'betrag_bis' => '', 'einheit' => 'stueck', 'hinweis' => false ),
		array( 'stueck' => 'Eckcouch klein', 'praefix' => '', 'betrag' => '175,00', 'betrag_bis' => '', 'einheit' => 'stueck', 'hinweis' => false ),
		array( 'stueck' => 'Eckcouch groß', 'praefix' => '', 'betrag' => '225,00', 'betrag_bis' => '', 'einheit' => 'stueck', 'hinweis' => false ),
		array( 'stueck' => 'Stuhl', 'praefix' => '', 'betrag' => '12,00', 'betrag_bis' => '', 'einheit' => 'stueck', 'hinweis' => false ),
		array( 'stueck' => 'Bürostuhl', 'praefix' => '', 'betrag' => '10,00', 'betrag_bis' => '', 'einheit' => 'stueck', 'hinweis' => false ),
	);
}

function cl_prices_pflegepakete_default() {
	return array(
		array(
			'titel'      => 'Schutz-Paket',
			'tag'        => '',
			'leistungen' => "Grundreinigung nach Materialprüfung\nFleckenschutz / Imprägnierung\nAbschließende Qualitätskontrolle",
			'fuer'       => 'Haushalte mit Kindern, Haustieren oder stark genutzten Räumen.',
			'praefix'    => '', 'betrag' => '5,90', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false,
		),
		array(
			'titel'      => 'Naturfaser-Paket',
			'tag'        => 'Beliebt',
			'leistungen' => "Materialgerechte Handwäsche\nWollpflege & Rückfettung\nMottenschutz\nKontrollierte Trocknung",
			'fuer'       => 'Woll-, Orient-, Berber-, Nepal- und andere Naturfaserteppiche.',
			'praefix'    => '', 'betrag' => '10,90', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false,
		),
		array(
			'titel'      => 'Haustier-Paket',
			'tag'        => '',
			'leistungen' => "Intensive Vorbehandlung\nGeruchsbehandlung\nZusätzlicher Spülgang\nVerlängerte Qualitätskontrolle",
			'fuer'       => 'Hunde- und Katzenhaushalte, Tiergeruch, organische Rückstände.',
			'praefix'    => '', 'betrag' => '12,90', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false,
		),
		array(
			'titel'      => 'Premium-Pflege',
			'tag'        => '',
			'leistungen' => "Intensive Vorbehandlung\nZusätzlicher Wasch- oder Spülgang\nFaserschutz, ggf. Wollpflege\nPriorisierte Bearbeitung",
			'fuer'       => 'Alles, was die vollständige Behandlung verdient, Umfang je nach Material.',
			'praefix'    => '', 'betrag' => '14,90', 'betrag_bis' => '', 'einheit' => 'm2', 'hinweis' => false,
		),
	);
}

function cl_prices_polster_note_default() {
	return 'Preise pro Stück, inkl. MwSt. Richtwerte, final bestätigt nach Prüfung von Material und Zustand vor Ort.';
}

function cl_prices_material_note_default() {
	return 'Preise inkl. MwSt. Mindestauftragswert: 89 €. Ab einem definierten Auftragswert ist die Abholung im Nahbereich kostenlos.';
}

function cl_prices_repair_note_default() {
	return 'Reparaturen erfordern immer eine Begutachtung vor Ort oder per Foto, der Preis richtet sich nach Schaden und Material.';
}

// ---------------------------------------------------------------------
// Getter (Option, Fallback auf Default, Preistext immer frisch berechnet).
// Standardmäßig NUR aktive Zeilen — so respektieren Shortcodes, REST,
// Chat-Assistent und Teppich-Erkennung den Sichtbarkeits-Schalter
// automatisch. Nur das Admin-Formular holt mit $include_inactive = true
// auch die ausgeblendeten Zeilen.
// ---------------------------------------------------------------------

function cl_get_prices_material( $include_inactive = false ) {
	$rows = get_option( 'cl_prices_material', null );
	$rows = is_array( $rows ) && ! empty( $rows ) ? $rows : cl_prices_material_default();
	return cl_filter_active_rows( array_map( 'cl_hydrate_price_row', $rows ), $include_inactive );
}

function cl_get_prices_repair( $include_inactive = false ) {
	$rows = get_option( 'cl_prices_repair', null );
	$rows = is_array( $rows ) && ! empty( $rows ) ? $rows : cl_prices_repair_default();
	return cl_filter_active_rows( array_map( 'cl_hydrate_price_row', $rows ), $include_inactive );
}

function cl_get_prices_zusatz( $include_inactive = false ) {
	$rows = get_option( 'cl_prices_zusatz', null );
	if ( is_array( $rows ) && ! empty( $rows ) ) {
		$has_kleinkind = false;
		foreach ( $rows as $row ) {
			if ( false !== stripos( $row['titel'] ?? '', 'kleinkind' ) ) {
				$has_kleinkind = true;
				break;
			}
		}
		// Einmalige Selbstreparatur: Kleinkinderpaket fehlte bislang als
		// Zeile bei den Einzelleistungen (es gab nur die Wizard-Karte ohne
		// Preis). Hier einmalig nachtragen und dauerhaft speichern, damit
		// es ab sofort wie jede andere Zeile im Preiseditor pflegbar ist.
		if ( ! $has_kleinkind ) {
			foreach ( cl_prices_zusatz_default() as $default_row ) {
				if ( false !== stripos( $default_row['titel'] ?? '', 'kleinkind' ) ) {
					$rows[] = $default_row;
					break;
				}
			}
			update_option( 'cl_prices_zusatz', $rows );
		}
	} else {
		$rows = cl_prices_zusatz_default();
	}
	return cl_filter_active_rows( array_map( 'cl_hydrate_price_row', $rows ), $include_inactive );
}

function cl_get_prices_polster( $include_inactive = false ) {
	$rows = get_option( 'cl_prices_polster', null );
	$rows = is_array( $rows ) && ! empty( $rows ) ? $rows : cl_prices_polster_default();
	return cl_filter_active_rows( array_map( 'cl_hydrate_price_row', $rows ), $include_inactive );
}

function cl_get_prices_pflegepakete( $include_inactive = false ) {
	$rows = get_option( 'cl_prices_pflegepakete', null );
	$rows = is_array( $rows ) && ! empty( $rows ) ? $rows : cl_prices_pflegepakete_default();
	return cl_filter_active_rows( array_map( function ( $row ) {
		$row          = cl_hydrate_price_row( $row );
		$row['preis'] = 'Grundreinigung + ' . $row['preis'];
		return $row;
	}, $rows ), $include_inactive );
}

function cl_get_prices_polster_note() {
	$note = get_option( 'cl_prices_polster_note', '' );
	return '' !== $note ? $note : cl_prices_polster_note_default();
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
// REST-Endpoint, damit das Buchungs-Tool (impressum-kontakt.html) die
// Preise live abfragen kann, statt sie im JS hart zu verdrahten.
// Liefert weiterhin nur art/verfahren/preis etc. (+preis als fertigen
// Text) — die internen Struktur-Felder (betrag/einheit/…) bleiben
// Admin-intern und werden hier nicht mit rausgegeben.
// ---------------------------------------------------------------------

function cl_public_rows( $rows, $keep_keys ) {
	return array_values( array_map( function ( $row ) use ( $keep_keys ) {
		$out = array();
		foreach ( $keep_keys as $key ) {
			$out[ $key ] = $row[ $key ] ?? '';
		}
		$out['preis'] = $row['preis'] ?? '';
		return $out;
	}, $rows ) );
}

add_action( 'rest_api_init', function () {
	register_rest_route(
		'care-lab/v1',
		'/preise',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				return array(
					'material'      => cl_public_rows( cl_get_prices_material(), array( 'art', 'verfahren' ) ),
					'zusatz'        => cl_public_rows( cl_get_prices_zusatz(), array( 'titel', 'beschreibung' ) ),
					'polster'       => cl_public_rows( cl_get_prices_polster(), array( 'stueck' ) ),
					'pflegepakete'  => cl_public_rows( cl_get_prices_pflegepakete(), array( 'titel', 'tag', 'leistungen', 'fuer' ) ),
				);
			},
			'permission_callback' => '__return_true',
		)
	);
} );

// ---------------------------------------------------------------------
// Shortcodes fürs Frontend
// ---------------------------------------------------------------------

add_shortcode( 'cl_preise_material', function () {
	$rows = cl_get_prices_material();
	ob_start();
	?>
	<div class="table-scroll price-card">
	<table>
		<thead><tr><th scope="col">Teppichart</th><th scope="col">Verfahren</th><th scope="col">Preis</th></tr></thead>
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
	<div class="table-scroll price-card">
	<table>
		<thead><tr><th scope="col">Leistung</th><th scope="col">Preis</th></tr></thead>
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

add_shortcode( 'cl_preise_polster', function () {
	$rows = cl_get_prices_polster();
	ob_start();
	?>
	<div class="table-scroll price-card">
	<table>
		<thead><tr><th scope="col">Stück</th><th scope="col">Preis</th></tr></thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
			<tr>
				<td><?php echo esc_html( $row['stueck'] ?? '' ); ?></td>
				<td class="price"><?php echo esc_html( $row['preis'] ?? '' ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p class="basetable-note"><?php echo esc_html( cl_get_prices_polster_note() ); ?></p>
	<?php
	return ob_get_clean();
} );

add_shortcode( 'cl_preise_pflegepakete', function () {
	$rows = cl_get_prices_pflegepakete();
	ob_start();
	?>
	<div class="paket-grid">
		<?php foreach ( $rows as $row ) : ?>
			<?php
			// Textarme Karte (Apple-Card-Stil): nur die ersten 2 Leistungen
			// sichtbar, der Rest steckt in einem <details>-Ausklapper (kein
			// Datenverlust, nur progressive disclosure). Der Preis wird in
			// Betrag (gross) + "zur Grundreinigung" (klein) aufgeteilt; der
			// REST-Endpoint liefert weiterhin den kompletten Text.
			$leistungen = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $row['leistungen'] ?? '' ) ) ), 'strlen' ) );
			$sichtbar   = array_slice( $leistungen, 0, 2 );
			$weitere    = array_slice( $leistungen, 2 );
			$preis      = (string) ( $row['preis'] ?? '' );
			$basis      = '';
			if ( 0 === strpos( $preis, 'Grundreinigung + ' ) ) {
				$preis = trim( substr( $preis, strlen( 'Grundreinigung + ' ) ) );
				$basis = 'zur Grundreinigung';
				if ( '' !== $preis && preg_match( '/^(ab )?\d/', $preis ) ) {
					$preis = '+ ' . $preis;
				}
			}
			?>
			<article class="paket">
				<?php if ( ! empty( $row['tag'] ) ) : ?>
					<span class="tag"><?php echo esc_html( $row['tag'] ); ?></span>
				<?php endif; ?>
				<h3><?php echo esc_html( $row['titel'] ?? '' ); ?></h3>
				<?php if ( '' !== $preis ) : ?>
					<p class="price">
						<span class="price-amount"><?php echo esc_html( $preis ); ?></span>
						<?php if ( '' !== $basis ) : ?>
							<span class="price-base"><?php echo esc_html( $basis ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<?php if ( $sichtbar ) : ?>
					<ul class="paket-highlights">
						<?php foreach ( $sichtbar as $leistung ) : ?>
							<li><?php echo esc_html( $leistung ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $weitere ) : ?>
					<details class="paket-more">
						<summary>
							<span class="more-open"><?php echo esc_html( '+ ' . count( $weitere ) . ( 1 === count( $weitere ) ? ' weitere Leistung' : ' weitere Leistungen' ) ); ?></span>
							<span class="more-close">Weniger anzeigen</span>
						</summary>
						<ul class="paket-highlights">
							<?php foreach ( $weitere as $leistung ) : ?>
								<li><?php echo esc_html( $leistung ); ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
				<?php if ( ! empty( $row['fuer'] ) ) : ?>
					<p class="fit"><b>Für:</b> <?php echo esc_html( $row['fuer'] ); ?></p>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
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
 * $text_fields sind die reinen Text-Felder der Zeile (z. B. art,
 * verfahren); die Preis-Struktur-Felder (praefix/betrag/betrag_bis/
 * einheit/hinweis) werden immer zusätzlich eingesammelt, der fertige
 * Anzeigetext wird daraus berechnet und als 'preis' mitgespeichert.
 *
 * Sichtbarkeit: Pro Zeile wird ein Hidden-Feld aktiv=0 VOR der
 * Checkbox aktiv=1 gerendert (gleicher Name, PHP nimmt den letzten
 * Wert) — so kommt pro Zeile immer ein eindeutiger Wert an. Fehlt das
 * Feld ganz (z. B. veraltetes Formular), gilt die Zeile als aktiv.
 *
 * Reihenfolge: $move (aus cl_parse_move_request()) verschiebt die Zeile
 * mit dem gegebenen Formular-Index um eine Position unter den
 * verbleibenden (nicht gelöschten, nicht leeren) Zeilen.
 *
 * @param string     $post_key    Name des POST-Arrays (z. B. 'material').
 * @param array      $text_fields Reine Text-Feldnamen pro Zeile.
 * @param array|null $move        array( 'table', 'index', 'dir' ) oder null.
 */
function cl_collect_price_rows( $post_key, $text_fields, $move = null ) {
	if ( ! isset( $_POST[ $post_key ] ) || ! is_array( $_POST[ $post_key ] ) ) {
		return array();
	}

	$valid_praefix  = array( '', 'ab', '+' );
	$valid_einheit  = array( 'm2', 'prozent', 'monat', 'stueck', 'anfrage', 'kostenlos' );

	$clean = array();
	foreach ( wp_unslash( $_POST[ $post_key ] ) as $idx => $row ) {
		if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
			continue;
		}

		$entry       = array();
		$has_content = false;
		foreach ( $text_fields as $field ) {
			$raw             = isset( $row[ $field ] ) ? $row[ $field ] : '';
			$value           = 'leistungen' === $field ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			$entry[ $field ] = $value;
			if ( '' !== $value ) {
				$has_content = true;
			}
		}

		$entry['praefix']    = in_array( $row['praefix'] ?? '', $valid_praefix, true ) ? ( $row['praefix'] ?? '' ) : '';
		$entry['betrag']     = isset( $row['betrag'] ) ? sanitize_text_field( $row['betrag'] ) : '';
		$entry['betrag_bis'] = isset( $row['betrag_bis'] ) ? sanitize_text_field( $row['betrag_bis'] ) : '';
		$entry['einheit']    = in_array( $row['einheit'] ?? '', $valid_einheit, true ) ? $row['einheit'] : 'm2';
		$entry['hinweis']    = ! empty( $row['hinweis'] );
		$entry['aktiv']      = isset( $row['aktiv'] ) ? ( '1' === (string) $row['aktiv'] ) : true;
		$entry['preis']      = cl_format_price( $entry );

		if ( '' !== $entry['betrag'] || in_array( $entry['einheit'], array( 'anfrage', 'kostenlos' ), true ) ) {
			$has_content = true;
		}

		if ( $has_content ) {
			$clean[ (int) $idx ] = $entry;
		}
	}

	if ( is_array( $move ) && $move['table'] === $post_key && isset( $clean[ $move['index'] ] ) ) {
		$keys   = array_keys( $clean );
		$pos    = array_search( $move['index'], $keys, true );
		$target = 'up' === $move['dir'] ? $pos - 1 : $pos + 1;
		$clean  = array_values( $clean );
		if ( false !== $pos && $target >= 0 && $target < count( $clean ) ) {
			$tmp              = $clean[ $pos ];
			$clean[ $pos ]    = $clean[ $target ];
			$clean[ $target ] = $tmp;
		}
	}

	return array_values( $clean );
}

/**
 * Liest den Sortier-Button aus (name="cl_move", value="tabelle:index:up|down").
 * Gibt null zurück, wenn kein (gültiger) Sortier-Button geklickt wurde.
 */
function cl_parse_move_request() {
	if ( empty( $_POST['cl_move'] ) || ! is_string( $_POST['cl_move'] ) ) {
		return null;
	}
	$raw = sanitize_text_field( wp_unslash( $_POST['cl_move'] ) );
	if ( ! preg_match( '/^(material|repair|zusatz|polster|pflegepakete):(\d+):(up|down)$/', $raw, $m ) ) {
		return null;
	}
	return array(
		'table' => $m[1],
		'index' => (int) $m[2],
		'dir'   => $m[3],
	);
}

/**
 * Rendert die beiden Steuer-Zellen einer Zeile: ▲/▼ (Reihenfolge) und
 * die Sichtbar-Checkbox. Leere Zusatzzeilen ($i >= $count) bekommen
 * keine Pfeile; erste/letzte echte Zeile bekommt den jeweils sinnlosen
 * Pfeil deaktiviert.
 *
 * @param string $key   Tabellen-Schlüssel (material, repair, …).
 * @param int    $i     Zeilen-Index im Formular.
 * @param int    $count Anzahl echter (gespeicherter) Zeilen.
 * @param array  $row   Zeilendaten.
 * @param string $which 'move' oder 'aktiv'.
 */
function cl_row_control_cell( $key, $i, $count, $row, $which ) {
	ob_start();
	if ( 'move' === $which ) {
		echo '<td class="cl-col-move">';
		if ( $i < $count ) {
			$anchor = '#cl-tabelle-' . $key;
			printf(
				'<button type="submit" class="button button-small cl-move" name="cl_move" value="%1$s" formaction="%2$s" title="Eine Position nach oben"%3$s>▲</button> ',
				esc_attr( $key . ':' . $i . ':up' ),
				esc_attr( $anchor ),
				0 === $i ? ' disabled' : ''
			);
			printf(
				'<button type="submit" class="button button-small cl-move" name="cl_move" value="%1$s" formaction="%2$s" title="Eine Position nach unten"%3$s>▼</button>',
				esc_attr( $key . ':' . $i . ':down' ),
				esc_attr( $anchor ),
				( $count - 1 ) === $i ? ' disabled' : ''
			);
		}
		echo '</td>';
	} else {
		$name = $key . '[' . $i . '][aktiv]';
		echo '<td class="cl-col-aktiv">';
		printf( '<input type="hidden" name="%s" value="0">', esc_attr( $name ) );
		printf(
			'<label title="Haken raus = auf der Website ausgeblendet, bleibt aber gespeichert"><input type="checkbox" class="cl-aktiv" name="%s" value="1" %s> sichtbar</label>',
			esc_attr( $name ),
			checked( cl_row_is_active( $row ), true, false )
		);
		echo '</td>';
	}
	return ob_get_clean();
}

/**
 * Rendert Präfix/Betrag/Bis/Einheit/Hinweis-Felder plus Live-Netto-
 * Anzeige für eine einzelne Preiszeile.
 */
function cl_price_fields_html( $name_prefix, $row ) {
	$row = cl_ensure_structured_price( $row );
	ob_start();
	?>
	<div class="cl-price-group" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; min-width:360px;">
		<select name="<?php echo esc_attr( $name_prefix ); ?>[praefix]">
			<option value="" <?php selected( $row['praefix'], '' ); ?>>genau</option>
			<option value="ab" <?php selected( $row['praefix'], 'ab' ); ?>>ab</option>
			<option value="+" <?php selected( $row['praefix'], '+' ); ?>>Aufschlag +</option>
		</select>
		<input type="text" class="cl-betrag" name="<?php echo esc_attr( $name_prefix ); ?>[betrag]" value="<?php echo esc_attr( $row['betrag'] ); ?>" placeholder="Betrag" style="width:80px;">
		<span style="color:#888;">–</span>
		<input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[betrag_bis]" value="<?php echo esc_attr( $row['betrag_bis'] ); ?>" placeholder="bis (optional)" style="width:100px;">
		<select name="<?php echo esc_attr( $name_prefix ); ?>[einheit]">
			<option value="m2" <?php selected( $row['einheit'], 'm2' ); ?>>€/m²</option>
			<option value="prozent" <?php selected( $row['einheit'], 'prozent' ); ?>>%</option>
			<option value="monat" <?php selected( $row['einheit'], 'monat' ); ?>>€/Monat</option>
			<option value="stueck" <?php selected( $row['einheit'], 'stueck' ); ?>>€ (pro Stück)</option>
			<option value="anfrage" <?php selected( $row['einheit'], 'anfrage' ); ?>>Auf Anfrage</option>
			<option value="kostenlos" <?php selected( $row['einheit'], 'kostenlos' ); ?>>Kostenlos</option>
		</select>
		<label style="font-size:11px; color:#666; white-space:nowrap;">
			<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[hinweis]" value="1" <?php checked( ! empty( $row['hinweis'] ) ); ?>> *
		</label>
		<span class="cl-netto" style="font-size:11px; color:#888; white-space:nowrap;"></span>
	</div>
	<?php
	return ob_get_clean();
}

function cl_preise_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['cl_preise_nonce'] ) && wp_verify_nonce( $_POST['cl_preise_nonce'], 'cl_preise_save' ) ) {
		$move = cl_parse_move_request();
		update_option( 'cl_prices_material', cl_collect_price_rows( 'material', array( 'art', 'verfahren' ), $move ) );
		update_option( 'cl_prices_repair', cl_collect_price_rows( 'repair', array( 'leistung' ), $move ) );
		update_option( 'cl_prices_zusatz', cl_collect_price_rows( 'zusatz', array( 'titel', 'beschreibung' ), $move ) );
		update_option( 'cl_prices_polster', cl_collect_price_rows( 'polster', array( 'stueck' ), $move ) );
		update_option( 'cl_prices_pflegepakete', cl_collect_price_rows( 'pflegepakete', array( 'titel', 'tag', 'leistungen', 'fuer' ), $move ) );
		update_option( 'cl_prices_material_note', sanitize_text_field( wp_unslash( $_POST['material_note'] ?? '' ) ) );
		update_option( 'cl_prices_repair_note', sanitize_text_field( wp_unslash( $_POST['repair_note'] ?? '' ) ) );
		update_option( 'cl_prices_polster_note', sanitize_text_field( wp_unslash( $_POST['polster_note'] ?? '' ) ) );
		if ( $move ) {
			echo '<div class="notice notice-success is-dismissible"><p>Reihenfolge geändert und alle Eingaben gespeichert.</p></div>';
		}
		echo '<div class="notice notice-success is-dismissible"><p>Preise gespeichert. Änderungen sind sofort auf der Seite „Leistungen & Preise" und im Buchungs-Tool sichtbar.</p></div>';
	}

	// Admin sieht ALLE Zeilen, auch ausgeblendete.
	$material     = cl_get_prices_material( true );
	$repair       = cl_get_prices_repair( true );
	$zusatz       = cl_get_prices_zusatz( true );
	$polster      = cl_get_prices_polster( true );
	$pflegepakete = cl_get_prices_pflegepakete( true );

	// Anzahl echter Zeilen (für ▲/▼; die Leerzeilen unten bekommen keine Pfeile).
	$n_material     = count( $material );
	$n_repair       = count( $repair );
	$n_zusatz       = count( $zusatz );
	$n_polster      = count( $polster );
	$n_pflegepakete = count( $pflegepakete );

	// Ein paar Leerzeilen am Ende zum Hinzufügen neuer Teppicharten/Leistungen.
	for ( $i = 0; $i < 3; $i++ ) {
		$material[]     = array( 'art' => '', 'verfahren' => '' );
		$repair[]       = array( 'leistung' => '' );
		$zusatz[]       = array( 'titel' => '', 'beschreibung' => '' );
		$polster[]      = array( 'stueck' => '' );
		$pflegepakete[] = array( 'titel' => '', 'tag' => '', 'leistungen' => '', 'fuer' => '' );
	}
	?>
	<div class="wrap">
		<h1>Preise</h1>
		<p>Diese Preise erscheinen live auf <a href="<?php echo esc_url( home_url( '/leistungen-pakete/' ) ); ?>" target="_blank">/leistungen-pakete/</a> und im Buchungs-Tool. Betrag eintippen, Einheit auswählen — der fertige Preistext wird automatisch zusammengesetzt. Eingaben sind Brutto-Preise (inkl. MwSt.), daneben wird zur Kontrolle der Netto-Betrag (19 % MwSt.) angezeigt. Neue Zeile: einfach eine der leeren Zeilen unten ausfüllen. Leere Zeilen werden beim Speichern automatisch verworfen.</p>

		<div class="cl-legende">
			<strong>So funktionieren die Felder</strong> (gilt für alle Tabellen auf dieser Seite)
			<dl>
				<dt>▲ / ▼</dt>
				<dd>Zeile eine Position nach oben/unten schieben. Die Reihenfolge hier ist auch die Reihenfolge auf der Website. Ein Klick speichert dabei alle anderen Eingaben gleich mit.</dd>
				<dt>genau</dt>
				<dd>Fester Preis, z. B. „9,90 €/m²".</dd>
				<dt>ab</dt>
				<dd>Startpreis, der Endpreis kann je nach Teppich höher liegen, z. B. „ab 24,99 €/m²".</dd>
				<dt>Aufschlag +</dt>
				<dd>Zuschlag/Aufpreis zu einem Grundpreis, z. B. „+10,90 €/m²" oder „+30 %".</dd>
				<dt>Betrag – bis</dt>
				<dd>„bis" nur ausfüllen, wenn es eine Spanne ist, z. B. „15–30 €/Monat". Sonst leer lassen.</dd>
				<dt>Kästchen *</dt>
				<dd>Hängt ein Sternchen an den Preis. Es verweist auf den Hinweistext unter der Tabelle (z. B. „Richtwert, wird nach Begutachtung final bestätigt").</dd>
				<dt>sichtbar</dt>
				<dd>Haken drin = Preis wird auf der Website, im Buchungs-Tool und im Chat-Assistenten angezeigt. Haken raus = ausgeblendet, aber nicht gelöscht (Zeile erscheint hier ausgegraut und lässt sich jederzeit wieder einschalten).</dd>
				<dt>Löschen</dt>
				<dd>Entfernt die Zeile beim Speichern endgültig.</dd>
			</dl>
		</div>

		<form method="post">
			<?php wp_nonce_field( 'cl_preise_save', 'cl_preise_nonce' ); ?>
			<?php // Unsichtbarer Standard-Button: Enter in einem Textfeld soll normal speichern, nicht den ersten ▲-Button auslösen. ?>
			<button type="submit" name="cl_save_default" value="1" tabindex="-1" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;">Speichern</button>

			<h2 id="cl-tabelle-material">Grundreinigung nach Material</h2>
			<table class="widefat striped cl-preise-table" style="max-width:1400px;">
				<thead><tr><th class="cl-col-move">Reihenfolge</th><th>Teppichart</th><th>Verfahren</th><th style="min-width:380px;">Preis</th><th>Sichtbar</th><th>Löschen</th></tr></thead>
				<tbody>
					<?php foreach ( $material as $i => $row ) : ?>
					<tr class="<?php echo cl_row_is_active( $row ) ? '' : 'cl-row-inactive'; ?>">
						<?php echo cl_row_control_cell( 'material', $i, $n_material, $row, 'move' ); ?>
						<td><input type="text" name="material[<?php echo $i; ?>][art]" value="<?php echo esc_attr( $row['art'] ); ?>" class="regular-text" style="width:100%;"></td>
						<td><input type="text" name="material[<?php echo $i; ?>][verfahren]" value="<?php echo esc_attr( $row['verfahren'] ); ?>" style="width:100%;"></td>
						<td><?php echo cl_price_fields_html( "material[$i]", $row ); ?></td>
						<?php echo cl_row_control_cell( 'material', $i, $n_material, $row, 'aktiv' ); ?>
						<td><input type="checkbox" name="material[<?php echo $i; ?>][delete]" value="1"></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<label>Hinweistext unter der Tabelle:<br>
				<input type="text" name="material_note" value="<?php echo esc_attr( cl_get_prices_material_note() ); ?>" style="width:100%;max-width:700px;"></label>
			</p>

			<h2 id="cl-tabelle-repair" style="margin-top:36px;">Antik-Reparatur &amp; Teppichreparatur</h2>
			<table class="widefat striped cl-preise-table" style="max-width:1200px;">
				<thead><tr><th class="cl-col-move">Reihenfolge</th><th>Leistung</th><th style="min-width:380px;">Preis</th><th>Sichtbar</th><th>Löschen</th></tr></thead>
				<tbody>
					<?php foreach ( $repair as $i => $row ) : ?>
					<tr class="<?php echo cl_row_is_active( $row ) ? '' : 'cl-row-inactive'; ?>">
						<?php echo cl_row_control_cell( 'repair', $i, $n_repair, $row, 'move' ); ?>
						<td><input type="text" name="repair[<?php echo $i; ?>][leistung]" value="<?php echo esc_attr( $row['leistung'] ); ?>" style="width:100%;"></td>
						<td><?php echo cl_price_fields_html( "repair[$i]", $row ); ?></td>
						<?php echo cl_row_control_cell( 'repair', $i, $n_repair, $row, 'aktiv' ); ?>
						<td><input type="checkbox" name="repair[<?php echo $i; ?>][delete]" value="1"></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<label>Hinweistext unter der Tabelle:<br>
				<input type="text" name="repair_note" value="<?php echo esc_attr( cl_get_prices_repair_note() ); ?>" style="width:100%;max-width:700px;"></label>
			</p>

			<h2 id="cl-tabelle-zusatz" style="margin-top:36px;">Einzelleistungen</h2>
			<table class="widefat striped cl-preise-table" style="max-width:1500px;">
				<thead><tr><th class="cl-col-move">Reihenfolge</th><th>Titel</th><th>Beschreibung</th><th style="min-width:380px;">Preis</th><th>Sichtbar</th><th>Löschen</th></tr></thead>
				<tbody>
					<?php foreach ( $zusatz as $i => $row ) : ?>
					<tr class="<?php echo cl_row_is_active( $row ) ? '' : 'cl-row-inactive'; ?>">
						<?php echo cl_row_control_cell( 'zusatz', $i, $n_zusatz, $row, 'move' ); ?>
						<td><input type="text" name="zusatz[<?php echo $i; ?>][titel]" value="<?php echo esc_attr( $row['titel'] ); ?>" style="width:100%;"></td>
						<td><input type="text" name="zusatz[<?php echo $i; ?>][beschreibung]" value="<?php echo esc_attr( $row['beschreibung'] ); ?>" style="width:100%;"></td>
						<td><?php echo cl_price_fields_html( "zusatz[$i]", $row ); ?></td>
						<?php echo cl_row_control_cell( 'zusatz', $i, $n_zusatz, $row, 'aktiv' ); ?>
						<td><input type="checkbox" name="zusatz[<?php echo $i; ?>][delete]" value="1"></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2 id="cl-tabelle-polster" style="margin-top:36px;">Polsterreinigung</h2>
			<table class="widefat striped cl-preise-table" style="max-width:1200px;">
				<thead><tr><th class="cl-col-move">Reihenfolge</th><th>Stück</th><th style="min-width:380px;">Preis</th><th>Sichtbar</th><th>Löschen</th></tr></thead>
				<tbody>
					<?php foreach ( $polster as $i => $row ) : ?>
					<tr class="<?php echo cl_row_is_active( $row ) ? '' : 'cl-row-inactive'; ?>">
						<?php echo cl_row_control_cell( 'polster', $i, $n_polster, $row, 'move' ); ?>
						<td><input type="text" name="polster[<?php echo $i; ?>][stueck]" value="<?php echo esc_attr( $row['stueck'] ); ?>" style="width:100%;"></td>
						<td><?php echo cl_price_fields_html( "polster[$i]", $row ); ?></td>
						<?php echo cl_row_control_cell( 'polster', $i, $n_polster, $row, 'aktiv' ); ?>
						<td><input type="checkbox" name="polster[<?php echo $i; ?>][delete]" value="1"></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<label>Hinweistext unter der Tabelle:<br>
				<input type="text" name="polster_note" value="<?php echo esc_attr( cl_get_prices_polster_note() ); ?>" style="width:100%;max-width:700px;"></label>
			</p>

			<h2 id="cl-tabelle-pflegepakete" style="margin-top:36px;">Pflegepakete</h2>
			<p class="description">Erscheinen als Kästen auf /leistungen-pakete/ (unter "Pflegepakete"). Betrag/Einheit wie überall, der Preis wird automatisch mit "Grundreinigung + " davor angezeigt. "Leistungen" ist eine Liste, eine Zeile pro Punkt.</p>
			<table class="widefat striped cl-preise-table" style="max-width:1500px;">
				<thead><tr><th class="cl-col-move">Reihenfolge</th><th>Titel</th><th>Badge (optional, z. B. "Beliebt")</th><th>Leistungen (eine pro Zeile)</th><th>Für (Zielgruppe)</th><th style="min-width:380px;">Aufpreis</th><th>Sichtbar</th><th>Löschen</th></tr></thead>
				<tbody>
					<?php foreach ( $pflegepakete as $i => $row ) : ?>
					<tr class="<?php echo cl_row_is_active( $row ) ? '' : 'cl-row-inactive'; ?>">
						<?php echo cl_row_control_cell( 'pflegepakete', $i, $n_pflegepakete, $row, 'move' ); ?>
						<td><input type="text" name="pflegepakete[<?php echo $i; ?>][titel]" value="<?php echo esc_attr( $row['titel'] ); ?>" style="width:100%;"></td>
						<td><input type="text" name="pflegepakete[<?php echo $i; ?>][tag]" value="<?php echo esc_attr( $row['tag'] ?? '' ); ?>" style="width:100%;"></td>
						<td><textarea name="pflegepakete[<?php echo $i; ?>][leistungen]" rows="4" style="width:100%;"><?php echo esc_textarea( $row['leistungen'] ?? '' ); ?></textarea></td>
						<td><input type="text" name="pflegepakete[<?php echo $i; ?>][fuer]" value="<?php echo esc_attr( $row['fuer'] ?? '' ); ?>" style="width:100%;"></td>
						<td><?php echo cl_price_fields_html( "pflegepakete[$i]", $row ); ?></td>
						<?php echo cl_row_control_cell( 'pflegepakete', $i, $n_pflegepakete, $row, 'aktiv' ); ?>
						<td><input type="checkbox" name="pflegepakete[<?php echo $i; ?>][delete]" value="1"></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( 'Preise speichern' ); ?>
		</form>
	</div>
	<style>
		.cl-legende { background:#f6f7f7; border:1px solid #dcdcde; border-radius:4px; padding:10px 14px; margin:12px 0 20px; max-width:1000px; font-size:12px; color:#50575e; }
		.cl-legende dl { display:grid; grid-template-columns:max-content 1fr; gap:3px 14px; margin:8px 0 0; }
		.cl-legende dt { font-weight:600; color:#1d2327; }
		.cl-legende dd { margin:0; }
		.cl-preise-table .cl-col-move { width:72px; white-space:nowrap; }
		.cl-preise-table .cl-move { min-height:24px; line-height:1; padding:0 6px; }
		.cl-preise-table .cl-col-aktiv { white-space:nowrap; }
		.cl-preise-table tr.cl-row-inactive td { opacity:.45; }
		.cl-preise-table tr.cl-row-inactive td.cl-col-aktiv,
		.cl-preise-table tr.cl-row-inactive td.cl-col-move { opacity:1; }
		.cl-preise-table tr.cl-row-inactive td.cl-col-aktiv label::after { content:" (ausgeblendet)"; color:#b32d2e; }
	</style>
	<script>
	(function(){
		document.querySelectorAll('.cl-preise-table .cl-aktiv').forEach(function(cb){
			cb.addEventListener('change', function(){
				var tr = cb.closest('tr');
				if (tr) tr.classList.toggle('cl-row-inactive', !cb.checked);
			});
		});
		function recalc(el){
			var group = el.closest('.cl-price-group');
			if (!group) return;
			var nettoEl = group.querySelector('.cl-netto');
			if (!nettoEl) return;
			var raw = (el.value || '').replace(/\./g, '').replace(',', '.');
			var brutto = parseFloat(raw);
			if (!raw || isNaN(brutto)) { nettoEl.textContent = ''; return; }
			var netto = brutto / <?php echo 1 + CL_MWST_SATZ; ?>;
			nettoEl.textContent = 'netto ' + netto.toLocaleString('de-DE', { minimumFractionDigits:2, maximumFractionDigits:2 }) + ' €';
		}
		document.querySelectorAll('.cl-price-group .cl-betrag').forEach(function(el){
			recalc(el);
			el.addEventListener('input', function(){ recalc(el); });
		});
	})();
	</script>
	<?php
}
