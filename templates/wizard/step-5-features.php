<?php
/**
 * Wizard Step 5: Ausstattung & Bilder.
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;
$pre_features = $prefill['_immo_features'] ?? array();
if ( ! is_array( $pre_features ) ) { $pre_features = array(); }
?>
<div class="immo-wizard-step-header">
	<h2 class="immo-wizard-step-title"><?php esc_html_e( 'Ausstattung & Bilder', 'immo-manager' ); ?></h2>
	<p class="immo-wizard-step-sub"><?php esc_html_e( 'Wähle die Ausstattungsmerkmale und lade Fotos hoch.', 'immo-manager' ); ?></p>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Ausstattungsmerkmale', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-features">
		<?php foreach ( $features as $cat_key => $items ) :
			if ( empty( $items ) ) { continue; } ?>
			<fieldset class="immo-wizard-feature-group">
				<legend><?php echo esc_html( $categories[ $cat_key ] ?? $cat_key ); ?></legend>
				<div class="immo-wizard-feature-grid">
					<?php foreach ( $items as $key => $feature ) : ?>
						<label class="immo-wizard-feature-item <?php echo in_array( $key, $pre_features, true ) ? 'checked' : ''; ?>">
							<input type="checkbox" name="_immo_features[]" value="<?php echo esc_attr( $key ); ?>"
								class="immo-wizard-input" <?php checked( in_array( $key, $pre_features, true ) ); ?>>
							<span class="immo-feature-icon" aria-hidden="true"><?php echo esc_html( $feature['icon'] ); ?></span>
							<span class="immo-feature-label"><?php echo esc_html( $feature['label'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>
	</div>
	<div class="immo-wizard-section">
		<label for="wiz_custom_features"><strong><?php esc_html_e( 'Weitere Ausstattungen (Freitext)', 'immo-manager' ); ?></strong></label>
		<textarea id="wiz_custom_features" name="_immo_custom_features" class="immo-wizard-input immo-input" rows="2"
			placeholder="<?php esc_attr_e( 'z. B. Whirlpool, Weinkeller, Solarthermie…', 'immo-manager' ); ?>"><?php echo esc_textarea( (string) ( $prefill['_immo_custom_features'] ?? '' ) ); ?></textarea>
	</div>
</div>
