<?php
/**
 * Wizard Step 1: Immobilientyp und Angebotsmodus.
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;
$pre_type = $prefill['_immo_property_type'] ?? '';
$pre_mode = $prefill['_immo_mode'] ?? 'sale';
$types = array(
	'Wohnung'          => array( 'icon' => '🏘️', 'label' => __( 'Wohnung', 'immo-manager' ) ),
	'Einfamilienhaus'  => array( 'icon' => '🏠', 'label' => __( 'Einfamilienhaus', 'immo-manager' ) ),
	'Mehrfamilienhaus' => array( 'icon' => '🏢', 'label' => __( 'Mehrfamilienhaus', 'immo-manager' ) ),
	'Grundstück'       => array( 'icon' => '🏗️', 'label' => __( 'Grundstück', 'immo-manager' ) ),
	'Gewerbe'          => array( 'icon' => '🏪', 'label' => __( 'Gewerbe', 'immo-manager' ) ),
	'Ferienimmobilie'  => array( 'icon' => '🌴', 'label' => __( 'Ferienimmobilie', 'immo-manager' ) ),
);
?>
<div class="immo-wizard-step-header">
	<h2 class="immo-wizard-step-title"><?php esc_html_e( 'Was möchtest du anbieten?', 'immo-manager' ); ?></h2>
	<p class="immo-wizard-step-sub"><?php esc_html_e( 'Wähle den Immobilientyp und ob du verkaufen oder vermieten möchtest.', 'immo-manager' ); ?></p>
</div>

<div class="immo-wizard-section immo-property-type-section">
	<h3><?php esc_html_e( 'Immobilientyp', 'immo-manager' ); ?></h3>
	<div class="immo-type-grid">
		<?php foreach ( $types as $value => $t ) : ?>
			<label class="immo-type-tile <?php echo $pre_type === $value ? 'selected' : ''; ?>">
				<input type="radio" name="_immo_property_type" value="<?php echo esc_attr( $value ); ?>" <?php checked( $pre_type, $value ); ?> class="immo-wizard-input" required>
				<span class="immo-type-icon"><?php echo esc_html( $t['icon'] ); ?></span>
				<span class="immo-type-label"><?php echo esc_html( $t['label'] ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
	<div class="immo-field-error" data-field="_immo_property_type" hidden></div>
</div>

<div class="immo-wizard-section immo-mode-section">
	<h3><?php esc_html_e( 'Angebotsmodus', 'immo-manager' ); ?></h3>
	<div class="immo-mode-options">
		<?php
		$modes = array(
			'sale' => array( 'icon' => '🏷️', 'label' => __( 'Verkauf', 'immo-manager' ) ),
			'rent' => array( 'icon' => '🔑', 'label' => __( 'Vermietung', 'immo-manager' ) ),
			'both' => array( 'icon' => '🔄', 'label' => __( 'Verkauf & Vermietung', 'immo-manager' ) ),
		);
		foreach ( $modes as $value => $m ) : ?>
			<label class="immo-mode-option <?php echo $pre_mode === $value ? 'selected' : ''; ?>">
				<input type="radio" name="_immo_mode" value="<?php echo esc_attr( $value ); ?>" <?php checked( $pre_mode, $value ); ?> class="immo-wizard-input" required>
				<span class="immo-mode-icon"><?php echo esc_html( $m['icon'] ); ?></span>
				<span class="immo-mode-label"><?php echo esc_html( $m['label'] ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
</div>
