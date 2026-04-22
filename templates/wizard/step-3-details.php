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
			'_immo_area'        => array( 'label' => __( 'Gesamtfläche (m²)', 'immo-manager' ), 'required' => false ),
			'_immo_usable_area' => array( 'label' => __( 'Wohnfläche (m²)', 'immo-manager' ),   'required' => false ),
			'_immo_land_area'   => array( 'label' => __( 'Grundstück (m²)', 'immo-manager' ),    'required' => false ),
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
	<h3><?php esc_html_e( 'Baujahr & Energie', 'immo-manager' ); ?></h3>
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
		<div class="immo-field immo-field--quarter">
			<label for="wiz_energy"><?php esc_html_e( 'Energieklasse', 'immo-manager' ); ?></label>
			<select id="wiz_energy" name="_immo_energy_class" class="immo-wizard-input immo-select-full">
				<?php foreach ( array( '', 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' ) as $cls ) : ?>
					<option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $p['_immo_energy_class'] ?? '', $cls ); ?>>
						<?php echo $cls ? esc_html( $cls ) : esc_html__( '— Klasse —', 'immo-manager' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="immo-field immo-field--quarter">
			<label for="wiz_hwb"><?php esc_html_e( 'HWB (kWh/m²a)', 'immo-manager' ); ?></label>
			<input type="number" step="0.1" min="0" id="wiz_hwb" name="_immo_energy_hwb" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_energy_hwb'] ?? '' ) ); ?>">
		</div>
		<div class="immo-field immo-field--half">
			<label for="wiz_heating"><?php esc_html_e( 'Heizungsart', 'immo-manager' ); ?></label>
			<input type="text" id="wiz_heating" name="_immo_heating" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_heating'] ?? '' ) ); ?>"
				placeholder="<?php esc_attr_e( 'z. B. Fernwärme, Gasheizung', 'immo-manager' ); ?>">
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
