<?php
/**
 * Wizard Step 2: Standort & Projekt.
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;
$pre_state    = $prefill['_immo_region_state']    ?? '';
$pre_district = $prefill['_immo_region_district'] ?? '';
$pre_project  = $prefill['_immo_project_id']      ?? 0;
?>
<div class="immo-wizard-step-header">
	<h2 class="immo-wizard-step-title"><?php esc_html_e( 'Wo befindet sich die Immobilie?', 'immo-manager' ); ?></h2>
	<p class="immo-wizard-step-sub"><?php esc_html_e( 'Adresse, Region und Bauprojekt-Zuordnung.', 'immo-manager' ); ?></p>
</div>

<!-- Projekt-Auswahl (sichtbar wenn "Teil eines Projekts") -->
<div class="immo-wizard-section immo-project-section" hidden>
	<h3><?php esc_html_e( 'Bauprojekt', 'immo-manager' ); ?></h3>
	<select name="_immo_project_id" class="immo-wizard-input immo-select-full">
		<option value="0"><?php esc_html_e( '— Projekt wählen —', 'immo-manager' ); ?></option>
		<?php foreach ( $projects as $project ) : ?>
			<option value="<?php echo esc_attr( (string) $project->ID ); ?>" <?php selected( (int) $pre_project, $project->ID ); ?>>
				<?php echo esc_html( $project->post_title ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Adresse', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<div class="immo-field immo-field--full">
			<label for="wiz_address"><?php esc_html_e( 'Straße & Hausnummer', 'immo-manager' ); ?></label>
			<input type="text" id="wiz_address" name="_immo_address" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $prefill['_immo_address'] ?? '' ) ); ?>"
				placeholder="<?php esc_attr_e( 'Hauptstraße 1', 'immo-manager' ); ?>" autocomplete="street-address">
		</div>
		<div class="immo-field immo-field--small">
			<label for="wiz_postal"><?php esc_html_e( 'PLZ', 'immo-manager' ); ?></label>
			<input type="text" id="wiz_postal" name="_immo_postal_code" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $prefill['_immo_postal_code'] ?? '' ) ); ?>"
				placeholder="1010" autocomplete="postal-code" maxlength="10">
		</div>
		<div class="immo-field immo-field--large">
			<label for="wiz_city"><?php esc_html_e( 'Ort', 'immo-manager' ); ?> *</label>
			<input type="text" id="wiz_city" name="_immo_city" class="immo-wizard-input immo-input" required
				value="<?php echo esc_attr( (string) ( $prefill['_immo_city'] ?? '' ) ); ?>"
				placeholder="Wien" autocomplete="address-level2">
			<div class="immo-field-error" data-field="_immo_city" hidden></div>
		</div>
	</div>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Region (Österreich)', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<div class="immo-field immo-field--half">
			<label for="wiz_state"><?php esc_html_e( 'Bundesland', 'immo-manager' ); ?></label>
			<select id="wiz_state" name="_immo_region_state" class="immo-wizard-input immo-select-full immo-region-state">
				<option value=""><?php esc_html_e( '— Bundesland —', 'immo-manager' ); ?></option>
				<?php foreach ( $states as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $pre_state, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="immo-field immo-field--half">
			<label for="wiz_district"><?php esc_html_e( 'Bezirk', 'immo-manager' ); ?></label>
			<select id="wiz_district" name="_immo_region_district" class="immo-wizard-input immo-select-full immo-region-district"
				data-current="<?php echo esc_attr( $pre_district ); ?>" <?php echo $pre_state ? '' : 'disabled'; ?>>
				<option value=""><?php esc_html_e( '— Bezirk —', 'immo-manager' ); ?></option>
				<?php if ( $pre_state ) :
					foreach ( \ImmoManager\Regions::get_districts( $pre_state ) as $dkey => $dlabel ) : ?>
						<option value="<?php echo esc_attr( $dkey ); ?>" <?php selected( $pre_district, $dkey ); ?>>
							<?php echo esc_html( $dlabel ); ?>
						</option>
					<?php endforeach;
				endif; ?>
			</select>
		</div>
	</div>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Geo-Koordinaten (optional, für Karte)', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<div class="immo-field immo-field--half">
			<label for="wiz_lat"><?php esc_html_e( 'Latitude', 'immo-manager' ); ?></label>
			<input type="number" step="0.000001" min="-90" max="90" id="wiz_lat" name="_immo_lat" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $prefill['_immo_lat'] ?? '' ) ); ?>" placeholder="48.2083">
		</div>
		<div class="immo-field immo-field--half">
			<label for="wiz_lng"><?php esc_html_e( 'Longitude', 'immo-manager' ); ?></label>
			<input type="number" step="0.000001" min="-180" max="180" id="wiz_lng" name="_immo_lng" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $prefill['_immo_lng'] ?? '' ) ); ?>" placeholder="16.3731">
		</div>
	</div>
</div>
