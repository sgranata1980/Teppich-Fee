<?php
/**
 * Care Lab — 301-Weiterleitungen fuer zusammengelegte/umbenannte Seiten
 *
 * WordPress legt fuer Seiten (anders als fuer Beitraege) keine automatische
 * Weiterleitung an, wenn eine Seite umbenannt, zusammengelegt oder auf
 * Entwurf gesetzt wird, alte Links liefen sonst ins 404.
 * Hier zentral gepflegt: alter Pfad (ohne Slashes) => neues Ziel
 * (host-relativ, home_url() haengt die gerade aufgerufene Domain an,
 * siehe Snippet "Care Lab Zweit-Domain").
 *
 * Laeuft auf template_redirect mit Prioritaet 1, also vor
 * redirect_canonical, und greift auch, wenn die alte Seite nicht mehr
 * veroeffentlicht ist (404).
 */

function cl_redirect_map() {
	return array(
		// 02.11.2026: FAQ in die Fleckenlexikon-Seite integriert (Seite 155 auf Entwurf).
		'faq' => '/fleckenlexikon/#haeufige-fragen',
	);
}

add_action( 'template_redirect', function () {
	if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	$path = strtolower( trim( (string) $path, '/' ) );
	if ( '' === $path ) {
		return;
	}
	$map = cl_redirect_map();
	if ( isset( $map[ $path ] ) ) {
		wp_redirect( home_url( $map[ $path ] ), 301, 'Care Lab' );
		exit;
	}
}, 1 );
