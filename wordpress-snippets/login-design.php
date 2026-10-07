/**
 * Care Lab — Login-Seite im Apple-Look (weiß, blaue Buttons, eigenes Logo)
 */

add_action( 'login_enqueue_scripts', function () {
	?>
	<style>
		body.login {
			background: #FFFFFF;
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Arial, sans-serif;
		}
		body.login #login {
			width: 360px;
			padding-top: 8vh;
		}
		#login h1.wp-login-logo a,
		.login h1 a,
		.login h1.wp-login-logo a {
			background-image: url('https://teppichlabor.de/wp-content/uploads/2026/09/logo-wordmark-transparent.png') !important;
			background-size: contain !important;
			background-position: center !important;
			width: 200px;
			height: 60px;
			margin: 0 auto 24px;
		}
		.login form {
			background: #FFFFFF;
			border: 1px solid #E5E5E5;
			border-radius: 14px;
			box-shadow: 0 20px 48px rgba(0,0,0,.06);
			padding: 32px 28px;
		}
		.login label {
			color: #171A20;
			font-size: 13px;
			font-weight: 600;
			letter-spacing: .01em;
		}
		.login form .input,
		.login input[type="text"],
		.login input[type="password"] {
			border-radius: 8px;
			border: 1px solid #CCCCCC;
			padding: 10px 12px;
			font-size: 15px;
			box-shadow: none;
			transition: border-color .15s ease;
		}
		.login form .input:focus,
		.login input[type="text"]:focus,
		.login input[type="password"]:focus {
			border-color: #3E6AE1;
			box-shadow: 0 0 0 3px rgba(62,106,225,.12);
		}
		.login .button-primary {
			background: #3E6AE1;
			border: none;
			border-radius: 8px;
			box-shadow: none;
			text-shadow: none;
			font-weight: 600;
			font-size: 14px;
			padding: 10px 20px;
			height: auto;
			line-height: 1.4;
			transition: background .15s ease;
		}
		.login .button-primary:hover,
		.login .button-primary:focus {
			background: #3559BD;
			box-shadow: none;
		}
		.login #nav a,
		.login #backtoblog a {
			color: #5C5E62;
			font-size: 13px;
			text-decoration: none;
		}
		.login #nav a:hover,
		.login #backtoblog a:hover {
			color: #171A20;
		}
		.login .message,
		.login #login_error {
			border-radius: 8px;
			border-left: none;
			box-shadow: none;
			background: #F5F5F3;
		}
		.login #login_error {
			background: #FDEAEA;
		}
		#login_error, .login .message, .login .success {
			border-radius: 8px;
		}
	</style>
	<?php
} );

add_filter( 'login_headerurl', function () {
	return home_url( '/' );
} );

add_filter( 'login_headertext', function () {
	return 'Care Lab';
} );
