<?php
/**
 * Wizard Step 3: Immobilien-Details.
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;
$p = $prefill;
?>
<div class="immo-wizard-step-header">
	<h2 class="immo-wizard-step-title"><?php esc_html_e( 'Immobilien-Details', 'immo-manager' ); ?></h2>
	<p class="immo-wizard-step-sub"><?php esc_html_e( 'Fläche, Zimmer, Baujahr und Energiedaten.', 'immo-manager' ); ?></p>
</div>

<div class="immo-wizard-section immo-property-only">
	<h3><?php esc_html_e( 'Fläche', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<?php
		$area_fields = array(
			'_immo_area'        => array( 'label' => __( 'Wohnfläche ca. (m²)', 'immo-manager' ),         'required' => false ),
			'_immo_usable_area' => array( 'label' => __( 'Nutzfläche ca. (m²)', 'immo-manager' ),         'required' => false ),
			'_immo_land_area'   => array( 'label' => __( 'Grundstücksfläche ca. (m²)', 'immo-manager' ), 'required' => false ),
		);
		foreach ( $area_fields as $name => $field ) :
			$val = $p[ $name ] ?? '';
			?>
			<div class="immo-field immo-field--third">
				<label><?php echo esc_html( $field['label'] ); ?><?php if ( $field['required'] ) : ?> *<?php endif; ?></label>
				<input type="number" step="0.01" min="0" name="<?php echo esc_attr( $name ); ?>" class="immo-wizard-input immo-input"
					value="<?php echo esc_attr( (string) $val ); ?>" placeholder="0.00">
			</div>
		<?php endforeach; ?>
	</div>
</div>

<div class="immo-wizard-section immo-property-only">
	<h3><?php esc_html_e( 'Räume', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<?php
		$room_fields = array(
			'_immo_rooms'     => array( 'label' => __( 'Zimmer gesamt', 'immo-manager' ) ),
			'_immo_bedrooms'  => array( 'label' => __( 'Schlafzimmer', 'immo-manager' ) ),
			'_immo_bathrooms' => array( 'label' => __( 'Badezimmer', 'immo-manager' ) ),
			'_immo_floor'     => array( 'label' => __( 'Etage (0=EG)', 'immo-manager' ) ),
			'_immo_total_floors' => array( 'label' => __( 'Stockwerke ges.', 'immo-manager' ) ),
		);
		foreach ( $room_fields as $name => $field ) :
			$val = $p[ $name ] ?? '';
			?>
			<div class="immo-field immo-field--fifth">
				<label><?php echo esc_html( $field['label'] ); ?></label>
				<input type="number" min="<?php echo '_immo_floor' === $name ? '-1' : '0'; ?>" name="<?php echo esc_attr( $name ); ?>" class="immo-wizard-input immo-input"
					value="<?php echo esc_attr( (string) $val ); ?>">
			</div>
		<?php endforeach; ?>
	</div>
</div>

<div class="immo-wizard-section immo-property-only">
	<h3><?php esc_html_e( 'Baujahr & Heizung', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<div class="immo-field immo-field--quarter">
			<label for="wiz_built"><?php esc_html_e( 'Baujahr', 'immo-manager' ); ?></label>
			<input type="number" min="1500" max="2100" id="wiz_built" name="_immo_built_year" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_built_year'] ?? '' ) ); ?>" placeholder="2005">
		</div>
		<div class="immo-field immo-field--quarter">
			<label for="wiz_renov"><?php esc_html_e( 'Sanierungsjahr', 'immo-manager' ); ?></label>
			<input type="number" min="1500" max="2100" id="wiz_renov" name="_immo_renovation_year" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_renovation_year'] ?? '' ) ); ?>" placeholder="2020">
		</div>
		<div class="immo-field immo-field--half">
			<label for="wiz_heating_select"><?php esc_html_e( 'Heizungsart', 'immo-manager' ); ?></label>
			<?php
			$heating_field = array(
				'current_value' => (string) ( $p['_immo_heating'] ?? '' ),
				'input_name'    => '_immo_heating',
				'field_id'      => 'wiz_heating',
				'input_class'   => 'immo-wizard-input immo-input',
			);
			include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/heating-field.php';
			?>
		</div>
	</div>
</div>

<div class="immo-wizard-section immo-property-only immo-energy-section">
	<h3><?php esc_html_e( 'Energieausweis', 'immo-manager' ); ?></h3>
	<p class="immo-field-hint">
		<?php esc_html_e( 'Pflichtangaben im Inserat seit 1. Juli 2026 (EAVG-Novelle): Energieeffizienzklasse, Heizwärmebedarf (HWB) und Endenergiebedarf (EEB). Der fGEE gilt nur noch für Energieausweise nach altem Recht. Ohne vollständige Angaben kann die Immobilie nicht veröffentlicht werden (Entwurf ist möglich); Strafrahmen bis 1.450 €.', 'immo-manager' ); ?>
	</p>
	<div class="immo-wizard-fields">
		<div class="immo-field immo-field--quarter">
			<label for="wiz_energy"><?php esc_html_e( 'Energieeffizienzklasse', 'immo-manager' ); ?> <span class="immo-required">*</span></label>
			<select id="wiz_energy" name="_immo_energy_class" class="immo-wizard-input immo-select-full">
				<option value="" <?php selected( $p['_immo_energy_class'] ?? '', '' ); ?>><?php esc_html_e( '— Klasse —', 'immo-manager' ); ?></option>
				<?php foreach ( \ImmoManager\MetaFields::energy_classes() as $cls ) : ?>
					<option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $p['_immo_energy_class'] ?? '', $cls ); ?>>
						<?php echo esc_html( $cls ); ?><?php echo in_array( $cls, array( 'A++', 'A+' ), true ) ? ' ' . esc_html__( '(nur Altausweis)', 'immo-manager' ) : ''; ?>
					</option>
				<?php endforeach; ?>
			</select>
			<div class="immo-field-error" data-field="_immo_energy_class" hidden></div>
		</div>
		<div class="immo-field immo-field--quarter">
			<label for="wiz_hwb"><?php esc_html_e( 'HWB (kWh/m²a)', 'immo-manager' ); ?> <span class="immo-required">*</span></label>
			<input type="number" step="0.1" min="0" id="wiz_hwb" name="_immo_energy_hwb" class="immo-wizard-input immo-input" placeholder="z. B. 68"
				value="<?php echo esc_attr( (string) ( $p['_immo_energy_hwb'] ?? '' ) ); ?>">
			<small class="immo-field-hint"><?php esc_html_e( 'Heizwärmebedarf', 'immo-manager' ); ?></small>
			<div class="immo-field-error" data-field="_immo_energy_hwb" hidden></div>
		</div>
		<div class="immo-field immo-field--quarter">
			<label for="wiz_eeb"><?php esc_html_e( 'EEB (kWh/m²a)', 'immo-manager' ); ?> <span class="immo-required">*</span></label>
			<input type="number" step="0.1" min="0" id="wiz_eeb" name="_immo_energy_eeb" class="immo-wizard-input immo-input" placeholder="z. B. 118"
				value="<?php echo esc_attr( (string) ( $p['_immo_energy_eeb'] ?? '' ) ); ?>">
			<small class="immo-field-hint"><?php esc_html_e( 'Endenergiebedarf – neu seit 2026', 'immo-manager' ); ?></small>
			<div class="immo-field-error" data-field="_immo_energy_eeb" hidden></div>
		</div>
		<div class="immo-field immo-field--quarter">
			<label for="wiz_fgee"><?php esc_html_e( 'fGEE (nur Altausweis)', 'immo-manager' ); ?></label>
			<input type="number" step="0.01" min="0" id="wiz_fgee" name="_immo_energy_fgee" class="immo-wizard-input immo-input" placeholder="z. B. 0,85"
				value="<?php echo esc_attr( (string) ( $p['_immo_energy_fgee'] ?? '' ) ); ?>">
			<small class="immo-field-hint"><?php esc_html_e( 'Ersetzt den EEB nur bei Ausweisen nach altem Recht', 'immo-manager' ); ?></small>
		</div>
	</div>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Beschreibung', 'immo-manager' ); ?></h3>
	<div class="immo-wp-editor-wrap">
		<?php
		wp_editor( (string) ( $prefill['description'] ?? '' ), 'immodescription', array(
			'textarea_name' => 'description',
			'textarea_rows' => 10,
			'media_buttons' => true,
			'editor_class'  => 'immo-wizard-input',
		) );
		?>
	</div>
</div>
