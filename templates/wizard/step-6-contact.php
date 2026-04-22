<?php
/**
 * Wizard Step 7: Kontakt, Titel & Zusammenfassung.
 * HINWEIS: Diese Datei sollte zu `step-7-contact.php` umbenannt werden, damit der Wizard sie korrekt als Schritt 7 lädt.
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;
$p = $prefill;
?>
<div class="immo-wizard-step-header">
	<h2 class="immo-wizard-step-title"><?php esc_html_e( 'Abschluss & Zusammenfassung', 'immo-manager' ); ?></h2>
	<p class="immo-wizard-step-sub"><?php esc_html_e( 'Kontaktdaten, optionaler Titel und Überblick aller Eingaben.', 'immo-manager' ); ?></p>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Anzeige-Titel (optional)', 'immo-manager' ); ?></h3>
	<input type="text" name="title" class="immo-wizard-input immo-input"
		value="<?php echo esc_attr( (string) ( $p['title'] ?? '' ) ); ?>"
		placeholder="<?php esc_attr_e( 'Leer lassen → wird automatisch aus Typ + Ort generiert', 'immo-manager' ); ?>">
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Ansprechpartner / Agent', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<div class="immo-field immo-field--full">
			<label for="wiz_contact_name"><?php esc_html_e( 'Name', 'immo-manager' ); ?></label>
			<input type="text" id="wiz_contact_name" name="_immo_contact_name" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_contact_name'] ?? '' ) ); ?>" autocomplete="name">
		</div>
		<div class="immo-field immo-field--half">
			<label for="wiz_contact_email"><?php esc_html_e( 'E-Mail', 'immo-manager' ); ?></label>
			<input type="email" id="wiz_contact_email" name="_immo_contact_email" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_contact_email'] ?? '' ) ); ?>" autocomplete="email">
		</div>
		<div class="immo-field immo-field--half">
			<label for="wiz_contact_phone"><?php esc_html_e( 'Telefon', 'immo-manager' ); ?></label>
			<input type="tel" id="wiz_contact_phone" name="_immo_contact_phone" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_contact_phone'] ?? '' ) ); ?>" autocomplete="tel">
		</div>
	</div>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Zusammenfassung', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-summary" id="immo-wizard-summary">
		<p class="immo-summary-hint"><?php esc_html_e( 'Zusammenfassung wird angezeigt, sobald du alle Schritte ausgefüllt hast.', 'immo-manager' ); ?></p>
	</div>
</div>
