/**
 * Care Lab — SEO-Basics: Meta-Descriptions + LocalBusiness-Schema
 *
 * Kein SEO-Plugin (Yoast/RankMath brauchen eigenen GUI-Assistenten,
 * genau wie MailPoet), stattdessen direkt per wp_head ausgegeben.
 * Neue Seite? Einfach unten in $cl_meta_descriptions ergänzen.
 */

function cl_meta_descriptions() {
	return array(
		'startseite'                                      => 'Teppich- und Polsterreinigung für Privatkunden und Unternehmen in Rhein-Main. Abholung, Grundreinigung, Hygienereinigung, transparente Preise.',
		'leistungen-pakete'                                => 'Preise für Teppichreinigung in Kelkheim: Maschinenwäsche ab 8 €/m², Handwäsche ab 16 €/m², Pflegepakete und Einzelleistungen im Überblick.',
		'unternehmen'                                      => 'Teppich- und Polsterreinigung für Hotels, Kitas, Büros, Hausverwaltungen und Gastronomie im Rhein-Main-Gebiet, ein fester Ansprechpartner.',
		'hygienereinigung'                                 => 'Hygienereinigung mit Heißdampf bei 60-80 °C, reduziert Hausstaubmilben in der Teppichfaser. Sinnvoll für Allergiker, Haustierhaushalte und Familien.',
		'wer-wir-sind'                                     => 'Care Lab aus Kelkheim: gegründet von Cem Zeren und Stefano Granata. Die Geschichte hinter der Teppich- und Polsterreinigung im Rhein-Main-Gebiet.',
		'impressum-kontakt'                                => 'Teppichreinigung anfragen: in 5 Schritten zum unverbindlichen Angebot für Privatkunden in Kelkheim und Rhein-Main.',
		'shop'                                             => 'Teppichdüfte von Care Lab: fünf Duftnoten für den gereinigten Teppich, erhältlich als Probe oder 500-ml-Flasche.',
		'app'                                               => 'Care Lab entwickelt eine App zur Einschätzung des Verschmutzungsgrads von Teppichen per Foto. Aktueller Entwicklungsstand.',
		'innovation'                                       => 'Woran Care Lab technisch arbeitet: KI-gestützte Reinigung, Materialforschung und neue Pflegeprodukte für Teppiche.',
		'journal'                                          => 'Fleckenlexikon und Materialwissen von Care Lab: praktische Artikel über Teppichreinigung, Hygiene und was sich in der Branche tut.',
		'abholung'                                         => 'Teppich abholen lassen statt selbst transportieren: Care Lab holt und liefert im gesamten Rhein-Main-Gebiet, ab 39 €.',
		'impressum'                                        => 'Impressum von Care Lab (Teppichlabor, Inh. Cem Zeren), Kelkheim. Anbieterkennzeichnung gemäß § 5 TMG.',
		'datenschutz'                                      => 'Datenschutzerklärung von Care Lab: wie personenbezogene Daten bei Anfragen und beim Website-Besuch verarbeitet werden.',
		'faq'                                              => 'Häufige Fragen zur Teppichreinigung bei Care Lab: Ablauf, Preise, Material, Hygienereinigung und Einzugsgebiet.',
		'logo-teppiche'                                    => 'Teppiche mit eigenem Logo für Gastronomie und Einzelhandel, bedruckt und schalldämpfend. Ab 71,34 € für das Einstiegsformat.',
		'teppichbodenreinigung'                            => 'Grundreinigung für fest verlegten Teppichboden, direkt vor Ort in Büro oder Wohnung. Neues Angebot von Care Lab.',
		'profi-kontakt'                                    => 'Angebot für Gewerbekunden anfragen: Hotels, Kitas, Büros, Hausverwaltungen und Gastronomie im Rhein-Main-Gebiet.',
		'maschinenpark'                                    => 'Der Maschinenpark von Care Lab: CE-zertifizierte Industriemaschinen von Staubentfernung bis Finish, keine Haushaltsgeräte.',
		'partner-textilreinigung'                          => 'Partnerprogramm für Textilreinigungen: Care Lab übernimmt die Teppichreinigung für eure Kunden, im Hintergrund.',
		'newsletter'                                       => 'Newsletter von Care Lab: neue Düfte, Leistungen und Werkstatt-Updates, ohne festes Intervall.',
		'pfas-fleckschutz-2026'                            => 'Was die EU-PFAS-Regulierung ab 2026 für Fleckschutz auf Teppich und Polster bedeutet, und welchen Wirkstoff Care Lab einsetzt.',
		'hausstaubmilben-temperatur-hygienereinigung'      => 'Ab welcher Temperatur Hausstaubmilben im Teppich absterben, und warum Duftspray dagegen kaum hilft.',
		'kleidermotten-teppich-erkennen'                   => 'Woran man einen Kleidermottenbefall im Teppich erkennt, welche Bedingungen ihn begünstigen, und was wirklich vorbeugt.',
		'orientteppich-handwaesche-pflege'                 => 'Warum Orientteppiche Handwäsche brauchen und Kunstfaserteppiche die Waschanlage vertragen, einfach erklärt.',
		'teppich-raumakustik-buero'                        => 'Was der Schallabsorptionsgrad von Teppich bedeutet, und wo er in Büro und Gastronomie wirklich den Unterschied macht.',
		'haustiergeruch-enzymreiniger-teppich'             => 'Warum Enzymreiniger Haustiergeruch im Teppich wirksamer entfernen als Duftspray, einfach erklärt.',
		'wasserkreislauf-textilreinigung-nachhaltigkeit'   => 'Wie sich der Wasserverbrauch der Textilreinigungsbranche in 20 Jahren verändert hat, und wohin er sich bewegt.',
		'hausmittel-fleckenentfernung-grenzen'             => 'Was Hausmittel wie Natron und Essig gegen Teppichflecken wirklich leisten, und wann sich professionelle Reinigung lohnt.',
		'ki-kamera-teppichreinigung-zukunft'               => 'Wie KI und Kameratechnik die Teppichreinigung verändern könnten, und woran Care Lab selbst gerade arbeitet.',
		'fachkraeftemangel-textilreiniger-handwerk'        => 'Warum Quereinsteiger im Textilreiniger-Handwerk wichtiger werden, am Beispiel von Care Lab.',
	);
}

add_action( 'wp_head', function () {
	if ( ! is_page() ) {
		return;
	}
	global $post;
	if ( ! $post ) {
		return;
	}
	$descriptions = cl_meta_descriptions();
	$slug         = $post->post_name;
	if ( isset( $descriptions[ $slug ] ) ) {
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $descriptions[ $slug ] ) );
	}
}, 1 );

add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'LocalBusiness',
		'name'       => 'Care Lab',
		'legalName'  => 'Teppichlabor, Inh. Cem Zeren',
		'url'        => home_url( '/' ),
		'telephone'  => '+491728293606',
		'email'      => 'info@teppichlabor.de',
		'address'    => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => 'Großer Haingraben 9',
			'postalCode'      => '65779',
			'addressLocality' => 'Kelkheim',
			'addressCountry'  => 'DE',
		),
		'areaServed' => 'Rhein-Main-Gebiet',
		'description' => 'Teppich- und Polsterreinigung für Privatkunden und Unternehmen in Rhein-Main.',
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
} );
