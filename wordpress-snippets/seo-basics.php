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
		'journal'                                          => 'Das Journal von Care Lab: Artikel über Teppichpflege, Hygienereinigung, den Ablauf der Abholung und Teppichreinigung für Unternehmen.',
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
		'hygienereinigung-mehr-als-sauberkeit'    => 'Warum ein sauberer Teppich nicht automatisch hygienisch ist, und was Heißdampf bei 60-80 °C in der Faser bewirkt. Mit ehrlichen Grenzen.',
		'teppichreinigung-fuer-unternehmen'       => 'Teppichreinigung für Hotels, Kitas, Büros, Gastronomie und Hausverwaltungen: Begehung, Einzelauftrag oder Jahresvertrag, feste Ansprechperson.',
		'teppiche-kita-reinigung'                 => 'Teppiche in Kitas reinigen lassen: worauf es bei Spülung, Trocknung, Hygienereinigung und Terminen im Kita-Alltag ankommt.',
		'teppichboden-buero-pflege'               => 'Teppichboden im Büro: warum Laufwege grau werden, wann eine Grundreinigung sinnvoll ist, und wie sie im laufenden Betrieb klappt.',
		'teppich-abholservice-ablauf'             => 'So funktioniert der Abholservice von Care Lab im Rhein-Main-Gebiet: Anfrage, Terminvorschlag, Prüfung, Reinigung und Rückgabe.',
		'perserteppich-pflege'                    => 'Perserteppiche richtig pflegen: warum Handwäsche, was die Fransen verraten, Pflege zuhause und Reparatur bei älteren Stücken.',
		'teppiche-gastronomie-reinigung'          => 'Teppiche in Restaurant und Café: Fettflecken, PFAS-freier Fleckschutz und Reinigung, ohne den Betrieb zu stören.',
		'teppichreinigung-zuhause-privatkunden'   => 'Teppichreinigung für Privatkunden: Preise, Verfahren, Zusatzpakete, Abholung und steuerliche Absetzbarkeit, ehrlich beantwortet.',
		'teppichduft-geruch-wirkung'              => 'Warum Duftspray Gerüche im Teppich nur überdeckt, was wirklich wirkt, und wann Teppichduft nach der Reinigung Sinn ergibt.',
		'maschinenpark-teppichreinigung-technik'  => 'Wie eine industrielle Reinigungsstraße Teppiche reinigt: Staubentfernung, Waschanlage, Schleuder, Trocknung und Finish erklärt.',
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
