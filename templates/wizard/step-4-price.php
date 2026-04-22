<?php
/**
 * Wizard Step 4: Preis & Bedingungen.
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;
$p = $prefill;
?>
<div class="immo-wizard-step-header">
	<h2 class="immo-wizard-step-title"><?php esc_html_e( 'Preis & Bedingungen', 'immo-manager' ); ?></h2>
	<p class="immo-wizard-step-sub"><?php esc_html_e( 'Kaufpreis, Mietkonditionen und Status der Immobilie.', 'immo-manager' ); ?></p>
</div>

<!-- Verkauf -->
<div class="immo-wizard-section immo-price-section-sale immo-property-only">
	<h3>🏷️ <?php esc_html_e( 'Kaufpreis', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<div class="immo-field immo-field--half">
			<label for="wiz_price"><?php esc_html_e( 'Kaufpreis', 'immo-manager' ); ?> * <span class="immo-currency-hint"><?php echo esc_html( $currency ); ?></span></label>
			<input type="number" step="1" min="0" id="wiz_price" name="_immo_price" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_price'] ?? '' ) ); ?>" placeholder="350000">
			<div class="immo-field-error" data-field="_immo_price" hidden></div>
		</div>
		<div class="immo-field immo-field--half">
			<label for="wiz_commission"><?php esc_html_e( 'Provision', 'immo-manager' ); ?></label>
			<input type="text" id="wiz_commission" name="_immo_commission" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_commission'] ?? '' ) ); ?>"
				placeholder="<?php esc_attr_e( 'z. B. 3 % + 20 % USt.', 'immo-manager' ); ?>">
		</div>
	</div>
</div>

<!-- Vermietung -->
<div class="immo-wizard-section immo-price-section-rent immo-property-only">
	<h3>🔑 <?php esc_html_e( 'Mietkonditionen', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<div class="immo-field immo-field--third">
			<label for="wiz_rent"><?php esc_html_e( 'Mietpreis / Monat', 'immo-manager' ); ?> * <span class="immo-currency-hint"><?php echo esc_html( $currency ); ?></span></label>
			<input type="number" step="1" min="0" id="wiz_rent" name="_immo_rent" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_rent'] ?? '' ) ); ?>" placeholder="850">
			<div class="immo-field-error" data-field="_immo_rent" hidden></div>
		</div>
		<div class="immo-field immo-field--third">
			<label for="wiz_opcosts"><?php esc_html_e( 'Betriebskosten', 'immo-manager' ); ?></label>
			<input type="number" step="1" min="0" id="wiz_opcosts" name="_immo_operating_costs" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_operating_costs'] ?? '' ) ); ?>" placeholder="150">
		</div>
		<div class="immo-field immo-field--third">
			<label for="wiz_deposit"><?php esc_html_e( 'Kaution', 'immo-manager' ); ?></label>
			<input type="number" step="1" min="0" id="wiz_deposit" name="_immo_deposit" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_deposit'] ?? '' ) ); ?>" placeholder="2550">
		</div>
	</div>
</div>

<!-- Status (Immobilie) -->
<div class="immo-wizard-section immo-property-status-section immo-property-only">
	<h3><?php esc_html_e( 'Status der Immobilie', 'immo-manager' ); ?></h3>
	<div class="immo-status-options">
		<?php
		$statuses = array(
			'available' => array( 'icon' => '✅', 'label' => __( 'Verfügbar', 'immo-manager' ) ),
			'reserved'  => array( 'icon' => '⏳', 'label' => __( 'Reserviert', 'immo-manager' ) ),
			'sold'      => array( 'icon' => '🔴', 'label' => __( 'Verkauft', 'immo-manager' ) ),
			'rented'    => array( 'icon' => '📋', 'label' => __( 'Vermietet', 'immo-manager' ) ),
		);
		$pre_status = $prefill['_immo_status'] ?? 'available';
		foreach ( $statuses as $val => $s ) : ?>
			<label class="immo-status-option <?php echo $pre_status === $val ? 'selected' : ''; ?>">
				<input type="radio" name="_immo_status" value="<?php echo esc_attr( $val ); ?>" <?php checked( $pre_status, $val ); ?> class="immo-wizard-input">
				<span><?php echo esc_html( $s['icon'] . ' ' . $s['label'] ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
</div>

<!-- Status (Bauprojekt) -->
<div class="immo-wizard-section immo-project-status-section" style="display:none;">
	<h3><?php esc_html_e( 'Projekt-Status', 'immo-manager' ); ?></h3>
	<div class="immo-status-options">
		<?php
		$proj_statuses = array(
			'planning'  => array( 'icon' => '📝', 'label' => __( 'In Planung', 'immo-manager' ) ),
			'building'  => array( 'icon' => '🏗️', 'label' => __( 'In Bau', 'immo-manager' ) ),
			'completed' => array( 'icon' => '✅', 'label' => __( 'Fertiggestellt', 'immo-manager' ) ),
		);
		$pre_proj = $prefill['_immo_project_status'] ?? 'planning';
		foreach ( $proj_statuses as $val => $s ) : ?>
			<label class="immo-status-option <?php echo $pre_proj === $val ? 'selected' : ''; ?>">
				<input type="radio" name="_immo_project_status" value="<?php echo esc_attr( $val ); ?>" <?php checked( $pre_proj, $val ); ?> class="immo-wizard-input">
				<span><?php echo esc_html( $s['icon'] . ' ' . $s['label'] ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
	<div class="immo-wizard-fields" style="margin-top: 1.5rem;">
		<div class="immo-field immo-field--half"><label><?php esc_html_e( 'Baubeginn', 'immo-manager' ); ?></label><input type="date" name="_immo_project_start_date" class="immo-wizard-input immo-input" value="<?php echo esc_attr( (string) ( $prefill['_immo_project_start_date'] ?? '' ) ); ?>"></div>
		<div class="immo-field immo-field--half"><label><?php esc_html_e( 'Fertigstellung', 'immo-manager' ); ?></label><input type="date" name="_immo_project_completion" class="immo-wizard-input immo-input" value="<?php echo esc_attr( (string) ( $prefill['_immo_project_completion'] ?? '' ) ); ?>"></div>
	</div>
</div>

<div class="immo-wizard-section immo-property-only">
	<div class="immo-field immo-field--half">
		<label for="wiz_available_from"><?php esc_html_e( 'Verfügbar ab (optional)', 'immo-manager' ); ?></label>
		<input type="date" id="wiz_available_from" name="_immo_available_from" class="immo-wizard-input immo-input"
			value="<?php echo esc_attr( (string) ( $prefill['_immo_available_from'] ?? '' ) ); ?>">
	</div>
</div>
