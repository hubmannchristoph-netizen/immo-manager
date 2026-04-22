<?php
/**
 * Template-Part: Anfrage-Formular (Sidebar der Detailseite).
 *
 * @var array<string, mixed> $property  Property-Array.
 * @var string               $api_base  REST API Base URL.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;
$property_id = (int) ( $property['id'] ?? 0 );
?>
<div class="immo-inquiry-card">
	<h3><?php esc_html_e( 'Anfrage senden', 'immo-manager' ); ?></h3>

	<div class="immo-inquiry-success" hidden role="alert">
		<span>✅</span>
		<p><?php esc_html_e( 'Vielen Dank! Ihre Anfrage wurde gesendet. Wir melden uns in Kürze.', 'immo-manager' ); ?></p>
	</div>

	<div class="immo-inquiry-error" hidden role="alert"></div>

	<div class="immo-inquiry-form" data-property-id="<?php echo esc_attr( (string) $property_id ); ?>">
		<div class="immo-form-field">
			<label for="immo-inquiry-name"><?php esc_html_e( 'Ihr Name', 'immo-manager' ); ?> <span aria-hidden="true">*</span></label>
			<input type="text" id="immo-inquiry-name" name="inquirer_name" class="immo-input" required
				autocomplete="name" placeholder="<?php esc_attr_e( 'Max Mustermann', 'immo-manager' ); ?>">
			<span class="immo-field-error" hidden></span>
		</div>

		<div class="immo-form-field">
			<label for="immo-inquiry-email"><?php esc_html_e( 'E-Mail', 'immo-manager' ); ?> <span aria-hidden="true">*</span></label>
			<input type="email" id="immo-inquiry-email" name="inquirer_email" class="immo-input" required
				autocomplete="email" placeholder="max@example.com">
			<span class="immo-field-error" hidden></span>
		</div>

		<div class="immo-form-field">
			<label for="immo-inquiry-phone"><?php esc_html_e( 'Telefon', 'immo-manager' ); ?></label>
			<input type="tel" id="immo-inquiry-phone" name="inquirer_phone" class="immo-input"
				autocomplete="tel" placeholder="+43 664 …">
		</div>

		<div class="immo-form-field">
			<label for="immo-inquiry-message"><?php esc_html_e( 'Nachricht', 'immo-manager' ); ?></label>
			<textarea id="immo-inquiry-message" name="inquirer_message" class="immo-input" rows="4"
				placeholder="<?php esc_attr_e( 'Ich interessiere mich für diese Immobilie…', 'immo-manager' ); ?>"></textarea>
		</div>

		<div class="immo-form-field immo-form-field--checkbox">
			<label>
				<input type="checkbox" name="consent" required>
				<span>
					<?php printf(
						/* translators: %s: Link zur Datenschutzerklärung */
						esc_html__( 'Ich stimme der %s zu. *', 'immo-manager' ),
						'<a href="' . esc_url( get_privacy_policy_url() ?: '#' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Datenschutzerklärung', 'immo-manager' ) . '</a>'
					); ?>
				</span>
			</label>
			<span class="immo-field-error" hidden></span>
		</div>

		<button type="button" class="immo-btn immo-btn-primary immo-inquiry-submit">
			<?php esc_html_e( 'Anfrage absenden', 'immo-manager' ); ?>
			<span class="immo-btn-spinner" hidden>⟳</span>
		</button>

		<p class="immo-form-note"><?php esc_html_e( '* Pflichtfelder', 'immo-manager' ); ?></p>
	</div>
</div>
