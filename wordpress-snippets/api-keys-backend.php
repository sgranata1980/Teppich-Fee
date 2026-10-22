<?php
/**
 * Care Lab Backend — API-Key-Verwaltung
 *
 * Eigene Admin-Seite (wp-admin → Care Lab → API-Keys), um API-Keys
 * (Anthropic/Claude, OpenAI) als WordPress-Optionen zu speichern,
 * statt sie hartcodiert in anderen Snippets stehen zu haben. Keys
 * werden nie im Klartext wieder angezeigt, nur maskiert.
 *
 * Der Chat-Assistent (Snippet "Care Lab Chat Assistent") liest den
 * Claude-Key über cl_get_api_key( 'claude' ) statt über die alte
 * Konstante CL_CLAUDE_API_KEY.
 */

add_action( 'init', function () {
	$args = array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'show_in_rest'      => true,
		'default'           => '',
	);
	register_setting( 'cl_api_keys_group', 'cl_api_key_claude', $args );
	register_setting( 'cl_api_keys_group', 'cl_api_key_openai', $args );
} );

add_action( 'admin_menu', function () {
	add_menu_page(
		'Care Lab Backend',
		'Care Lab',
		'manage_options',
		'care-lab-backend',
		'cl_backend_dashboard_page',
		'dashicons-admin-generic',
		3
	);
	add_submenu_page(
		'care-lab-backend',
		'API-Keys',
		'API-Keys',
		'manage_options',
		'care-lab-api-keys',
		'cl_api_keys_page'
	);
} );

function cl_backend_dashboard_page() {
	?>
	<div class="wrap">
		<h1>Care Lab Backend</h1>
		<p>Zentrale Stelle für Backend-Werkzeuge, die nicht ins normale WordPress-Menü passen.</p>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=care-lab-api-keys' ) ); ?>">API-Keys verwalten</a> (Claude/Anthropic, OpenAI)</li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=care-lab-preise' ) ); ?>">Preise verwalten</a> (Grundreinigung, Reparatur, Einzelleistungen — erscheint live auf /leistungen-pakete/)</li>
		</ul>
	</div>
	<?php
}

/**
 * Liefert den gespeicherten API-Key für einen Dienst.
 *
 * @param string $service 'claude' oder 'openai'.
 * @return string Leerstring, wenn kein Key hinterlegt ist.
 */
function cl_get_api_key( $service ) {
	$option_key = 'cl_api_key_' . $service;
	$value      = get_option( $option_key, '' );

	// Fallback auf die alte hartcodierte Konstante, falls noch gesetzt
	// und in der Datenbank noch kein Key eingetragen wurde.
	if ( '' === $value && 'claude' === $service && defined( 'CL_CLAUDE_API_KEY' ) && 'PASTE_YOUR_KEY_HERE' !== CL_CLAUDE_API_KEY ) {
		return CL_CLAUDE_API_KEY;
	}

	return $value;
}

function cl_mask_key( $key ) {
	$len = strlen( $key );
	if ( $len <= 8 ) {
		return str_repeat( '•', max( $len, 4 ) );
	}
	return substr( $key, 0, 4 ) . str_repeat( '•', $len - 8 ) . substr( $key, -4 );
}

function cl_api_keys_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$services = array(
		'claude' => array(
			'label'       => 'Anthropic / Claude API-Key',
			'placeholder' => 'sk-ant-...',
			'hint'        => 'Wird vom Chat-Assistenten auf der Website genutzt. Key von console.anthropic.com (nicht das claude.ai-Abo).',
		),
		'openai' => array(
			'label'       => 'OpenAI API-Key',
			'placeholder' => 'sk-...',
			'hint'        => 'Aktuell ungenutzt, für zukünftige Erweiterungen hinterlegt.',
		),
	);

	if ( isset( $_POST['cl_api_keys_nonce'] ) && wp_verify_nonce( $_POST['cl_api_keys_nonce'], 'cl_api_keys_save' ) ) {
		foreach ( $services as $service => $meta ) {
			$field_name = 'cl_api_key_' . $service;
			$clear_name = 'cl_clear_' . $service;

			if ( isset( $_POST[ $clear_name ] ) ) {
				delete_option( $field_name );
				continue;
			}

			if ( isset( $_POST[ $field_name ] ) && '' !== trim( (string) $_POST[ $field_name ] ) ) {
				update_option( $field_name, sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) );
			}
		}
		echo '<div class="notice notice-success is-dismissible"><p>Gespeichert.</p></div>';
	}

	?>
	<div class="wrap">
		<h1>API-Keys</h1>
		<p>Keys werden serverseitig in der WordPress-Datenbank gespeichert, erscheinen nie im Frontend-Code und werden hier nur maskiert angezeigt. Leer lassen, um einen bestehenden Key unverändert zu behalten.</p>
		<form method="post">
			<?php wp_nonce_field( 'cl_api_keys_save', 'cl_api_keys_nonce' ); ?>
			<table class="form-table">
				<?php foreach ( $services as $service => $meta ) :
					$current     = get_option( 'cl_api_key_' . $service, '' );
					$field_name  = 'cl_api_key_' . $service;
					$clear_name  = 'cl_clear_' . $service;
					$placeholder = $current ? ( cl_mask_key( $current ) . ' (gespeichert)' ) : $meta['placeholder'];
					?>
					<tr>
						<th><label for="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_html( $meta['label'] ); ?></label></th>
						<td>
							<input
								type="password"
								name="<?php echo esc_attr( $field_name ); ?>"
								id="<?php echo esc_attr( $field_name ); ?>"
								class="regular-text"
								placeholder="<?php echo esc_attr( $placeholder ); ?>"
								autocomplete="off"
							/>
							<?php if ( $current ) : ?>
								<label style="margin-left:10px;">
									<input type="checkbox" name="<?php echo esc_attr( $clear_name ); ?>" value="1" /> Key löschen
								</label>
							<?php endif; ?>
							<p class="description"><?php echo esc_html( $meta['hint'] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( 'Speichern' ); ?>
		</form>
	</div>
	<?php
}
