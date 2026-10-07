/**
 * Care Lab — Zweit-Domain care-lab.app ohne Redirect
 *
 * teppichlabor.de und care-lab.app zeigen serverseitig schon auf
 * denselben Webspace-Ordner. WordPress selbst hat aber eine feste
 * "Home"/"Site"-URL in der Datenbank und leitet deshalb jeden Besuch
 * ueber eine andere Domain automatisch auf teppichlabor.de um
 * (redirect_canonical). Dieser Snippet macht Home/Site-URL dynamisch:
 * je nachdem, welche der beiden Domains aufgerufen wurde, bleibt genau
 * diese in der Adresszeile.
 */

function cl_dynamic_home_url( $value ) {
	$allowed_hosts = array(
		'teppichlabor.de',
		'www.teppichlabor.de',
		'care-lab.app',
		'www.care-lab.app',
	);
	$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( $_SERVER['HTTP_HOST'] ) : '';
	if ( in_array( $host, $allowed_hosts, true ) ) {
		return 'https://' . $host;
	}
	return false; // Fallback: echten Wert aus der Datenbank nehmen.
}
add_filter( 'pre_option_home', 'cl_dynamic_home_url' );
add_filter( 'pre_option_siteurl', 'cl_dynamic_home_url' );
