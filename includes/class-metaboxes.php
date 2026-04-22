<?php
/**
 * Metaboxes für Immobilien und Bauprojekte.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Metaboxes
 *
 * Registriert und rendert alle Metaboxes im Post-Editor und kümmert sich
 * um das sichere Speichern der Meta-Werte.
 */
class Metaboxes {

	/**
	 * Nonce-Action für Property-Save.
	 */
	private const PROPERTY_NONCE = 'immo_property_metabox_save';

	/**
	 * Nonce-Action für Project-Save.
	 */
	private const PROJECT_NONCE = 'immo_project_metabox_save';

	/**
	 * Konstruktor – Hooks registrieren.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post_' . PostTypes::POST_TYPE_PROPERTY, array( $this, 'save_property' ), 10, 2 );
		add_action( 'save_post_' . PostTypes::POST_TYPE_PROJECT, array( $this, 'save_project' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'cleanup_on_project_delete' ) );
	}

	/**
	 * Metaboxes registrieren.
	 *
	 * @return void
	 */
	public function register(): void {
		// Property-Metaboxes.
		add_meta_box( 'immo_property_details',  __( 'Immobilie – Details', 'immo-manager' ),       array( $this, 'render_property_details' ),  PostTypes::POST_TYPE_PROPERTY, 'normal', 'high' );
		add_meta_box( 'immo_property_location', __( 'Standort', 'immo-manager' ),                  array( $this, 'render_property_location' ), PostTypes::POST_TYPE_PROPERTY, 'normal', 'high' );
		add_meta_box( 'immo_property_gallery',  __( 'Bildergalerie', 'immo-manager' ),             array( $this, 'render_gallery' ),           PostTypes::POST_TYPE_PROPERTY, 'normal', 'high' );
		add_meta_box( 'immo_property_price',    __( 'Preis & Bedingungen', 'immo-manager' ),       array( $this, 'render_property_price' ),    PostTypes::POST_TYPE_PROPERTY, 'normal', 'default' );
		add_meta_box( 'immo_property_energy',   __( 'Energie', 'immo-manager' ),                   array( $this, 'render_property_energy' ),   PostTypes::POST_TYPE_PROPERTY, 'normal', 'default' );
		add_meta_box( 'immo_property_features', __( 'Ausstattung', 'immo-manager' ),               array( $this, 'render_features' ),          PostTypes::POST_TYPE_PROPERTY, 'normal', 'default' );
		add_meta_box( 'immo_property_display',  __( 'Darstellung & Layout', 'immo-manager' ),      array( $this, 'render_property_display' ),   PostTypes::POST_TYPE_PROPERTY, 'side',   'default' );
		add_meta_box( 'immo_property_contact',  __( 'Kontakt / Agent', 'immo-manager' ),           array( $this, 'render_contact' ),           PostTypes::POST_TYPE_PROPERTY, 'side',   'default' );

		// Project-Metaboxes.
		add_meta_box( 'immo_project_details',   __( 'Projekt – Daten', 'immo-manager' ),           array( $this, 'render_project_details' ),   PostTypes::POST_TYPE_PROJECT,  'normal', 'high' );
		add_meta_box( 'immo_project_location',  __( 'Standort', 'immo-manager' ),                  array( $this, 'render_property_location' ), PostTypes::POST_TYPE_PROJECT,  'normal', 'high' );
		add_meta_box( 'immo_project_gallery',   __( 'Bildergalerie', 'immo-manager' ),             array( $this, 'render_gallery' ),           PostTypes::POST_TYPE_PROJECT,  'normal', 'high' );
		add_meta_box( 'immo_project_features',  __( 'Gemeinschafts-Ausstattung', 'immo-manager' ), array( $this, 'render_features' ),          PostTypes::POST_TYPE_PROJECT,  'normal', 'default' );
		add_meta_box( 'immo_project_units',     __( 'Wohneinheiten', 'immo-manager' ),             array( $this, 'render_project_units' ),     PostTypes::POST_TYPE_PROJECT,  'normal', 'default' );
		add_meta_box( 'immo_project_contact',   __( 'Kontakt / Agent', 'immo-manager' ),           array( $this, 'render_contact' ),           PostTypes::POST_TYPE_PROJECT,  'side',   'default' );
	}

	// =========================================================================
	// Property Metaboxes
	// =========================================================================

	/**
	 * Metabox: Property-Details.
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_property_details( \WP_Post $post ): void {
		wp_nonce_field( self::PROPERTY_NONCE, 'immo_property_nonce' );
		$meta = $this->get_meta( $post->ID, MetaFields::property_fields() );
		?>
		<table class="form-table immo-form">
			<tr>
				<th><label for="_immo_mode"><?php esc_html_e( 'Modus', 'immo-manager' ); ?></label></th>
				<td>
					<select id="_immo_mode" name="immo_meta[_immo_mode]">
						<option value="sale" <?php selected( $meta['_immo_mode'], 'sale' ); ?>><?php esc_html_e( 'Verkauf', 'immo-manager' ); ?></option>
						<option value="rent" <?php selected( $meta['_immo_mode'], 'rent' ); ?>><?php esc_html_e( 'Vermietung', 'immo-manager' ); ?></option>
						<option value="both" <?php selected( $meta['_immo_mode'], 'both' ); ?>><?php esc_html_e( 'Verkauf & Vermietung', 'immo-manager' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="_immo_status"><?php esc_html_e( 'Status', 'immo-manager' ); ?></label></th>
				<td>
					<select id="_immo_status" name="immo_meta[_immo_status]">
						<option value="available" <?php selected( $meta['_immo_status'], 'available' ); ?>><?php esc_html_e( 'Verfügbar', 'immo-manager' ); ?></option>
						<option value="reserved"  <?php selected( $meta['_immo_status'], 'reserved' ); ?>><?php esc_html_e( 'Reserviert', 'immo-manager' ); ?></option>
						<option value="sold"      <?php selected( $meta['_immo_status'], 'sold' ); ?>><?php esc_html_e( 'Verkauft', 'immo-manager' ); ?></option>
						<option value="rented"    <?php selected( $meta['_immo_status'], 'rented' ); ?>><?php esc_html_e( 'Vermietet', 'immo-manager' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="_immo_property_type"><?php esc_html_e( 'Immobilientyp', 'immo-manager' ); ?></label></th>
				<td>
					<input type="text" id="_immo_property_type" name="immo_meta[_immo_property_type]" value="<?php echo esc_attr( (string) $meta['_immo_property_type'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'z. B. Wohnung, Einfamilienhaus', 'immo-manager' ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="_immo_area"><?php esc_html_e( 'Gesamtfläche (m²)', 'immo-manager' ); ?></label></th>
				<td><input type="number" step="0.01" min="0" id="_immo_area" name="immo_meta[_immo_area]" value="<?php echo esc_attr( (string) $meta['_immo_area'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label for="_immo_usable_area"><?php esc_html_e( 'Wohnfläche (m²)', 'immo-manager' ); ?></label></th>
				<td><input type="number" step="0.01" min="0" id="_immo_usable_area" name="immo_meta[_immo_usable_area]" value="<?php echo esc_attr( (string) $meta['_immo_usable_area'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label for="_immo_land_area"><?php esc_html_e( 'Grundstücksfläche (m²)', 'immo-manager' ); ?></label></th>
				<td><input type="number" step="0.01" min="0" id="_immo_land_area" name="immo_meta[_immo_land_area]" value="<?php echo esc_attr( (string) $meta['_immo_land_area'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label for="_immo_rooms"><?php esc_html_e( 'Zimmer', 'immo-manager' ); ?></label></th>
				<td>
					<input type="number" min="0" id="_immo_rooms"     name="immo_meta[_immo_rooms]"     value="<?php echo esc_attr( (string) $meta['_immo_rooms'] ); ?>"     class="small-text" />
					<label style="margin-left:16px;"><?php esc_html_e( 'davon Schlafzimmer:', 'immo-manager' ); ?>
						<input type="number" min="0" name="immo_meta[_immo_bedrooms]"  value="<?php echo esc_attr( (string) $meta['_immo_bedrooms'] ); ?>"  class="small-text" />
					</label>
					<label style="margin-left:16px;"><?php esc_html_e( 'Badezimmer:', 'immo-manager' ); ?>
						<input type="number" min="0" name="immo_meta[_immo_bathrooms]" value="<?php echo esc_attr( (string) $meta['_immo_bathrooms'] ); ?>" class="small-text" />
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="_immo_floor"><?php esc_html_e( 'Etage / Stockwerke', 'immo-manager' ); ?></label></th>
				<td>
					<input type="number" id="_immo_floor" name="immo_meta[_immo_floor]" value="<?php echo esc_attr( (string) $meta['_immo_floor'] ); ?>" class="small-text" />
					<?php esc_html_e( 'von', 'immo-manager' ); ?>
					<input type="number" min="0" name="immo_meta[_immo_total_floors]" value="<?php echo esc_attr( (string) $meta['_immo_total_floors'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( '-1 = Kellergeschoss, 0 = Erdgeschoss.', 'immo-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="_immo_built_year"><?php esc_html_e( 'Baujahr / Sanierung', 'immo-manager' ); ?></label></th>
				<td>
					<input type="number" min="1500" max="2100" id="_immo_built_year" name="immo_meta[_immo_built_year]" value="<?php echo esc_attr( (string) $meta['_immo_built_year'] ); ?>" class="small-text" placeholder="<?php esc_attr_e( 'Baujahr', 'immo-manager' ); ?>" />
					<input type="number" min="1500" max="2100" name="immo_meta[_immo_renovation_year]" value="<?php echo esc_attr( (string) $meta['_immo_renovation_year'] ); ?>" class="small-text" placeholder="<?php esc_attr_e( 'Sanierung', 'immo-manager' ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="_immo_project_id"><?php esc_html_e( 'Teil eines Bauprojekts?', 'immo-manager' ); ?></label></th>
				<td>
					<?php $this->render_project_select( (int) $meta['_immo_project_id'] ); ?>
					<p class="description"><?php esc_html_e( 'Optional: Ordne diese Immobilie einem Bauprojekt zu.', 'immo-manager' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Metabox: Standort (wird für Property UND Project genutzt).
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_property_location( \WP_Post $post ): void {
		$meta   = $this->get_meta( $post->ID, MetaFields::property_fields() );
		$states = Regions::get_states();
		?>
		<table class="form-table immo-form">
			<tr>
				<th><label for="_immo_address"><?php esc_html_e( 'Adresse', 'immo-manager' ); ?></label></th>
				<td><input type="text" id="_immo_address" name="immo_meta[_immo_address]" value="<?php echo esc_attr( (string) $meta['_immo_address'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="_immo_postal_code"><?php esc_html_e( 'PLZ / Ort', 'immo-manager' ); ?></label></th>
				<td>
					<input type="text" id="_immo_postal_code" name="immo_meta[_immo_postal_code]" value="<?php echo esc_attr( (string) $meta['_immo_postal_code'] ); ?>" class="small-text" placeholder="<?php esc_attr_e( 'PLZ', 'immo-manager' ); ?>" />
					<input type="text" name="immo_meta[_immo_city]" value="<?php echo esc_attr( (string) $meta['_immo_city'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Ort', 'immo-manager' ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="_immo_region_state"><?php esc_html_e( 'Bundesland / Bezirk', 'immo-manager' ); ?></label></th>
				<td>
					<select id="_immo_region_state" name="immo_meta[_immo_region_state]" class="immo-region-state">
						<option value=""><?php esc_html_e( '— Bundesland —', 'immo-manager' ); ?></option>
						<?php foreach ( $states as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $meta['_immo_region_state'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<select id="_immo_region_district" name="immo_meta[_immo_region_district]" class="immo-region-district" data-current="<?php echo esc_attr( (string) $meta['_immo_region_district'] ); ?>">
						<option value=""><?php esc_html_e( '— Bezirk —', 'immo-manager' ); ?></option>
						<?php
						$districts = $meta['_immo_region_state'] ? Regions::get_districts( (string) $meta['_immo_region_state'] ) : array();
						foreach ( $districts as $d_key => $d_label ) :
							?>
							<option value="<?php echo esc_attr( $d_key ); ?>" <?php selected( $meta['_immo_region_district'], $d_key ); ?>><?php echo esc_html( $d_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="_immo_lat"><?php esc_html_e( 'Koordinaten (Lat, Lng)', 'immo-manager' ); ?></label></th>
				<td>
					<input type="number" step="0.000001" min="-90" max="90"   id="_immo_lat" name="immo_meta[_immo_lat]" value="<?php echo esc_attr( (string) $meta['_immo_lat'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Latitude', 'immo-manager' ); ?>" />
					<input type="number" step="0.000001" min="-180" max="180"                name="immo_meta[_immo_lng]" value="<?php echo esc_attr( (string) $meta['_immo_lng'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Longitude', 'immo-manager' ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional – für die Kartendarstellung (OpenStreetMap).', 'immo-manager' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Metabox: Preis.
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_property_price( \WP_Post $post ): void {
		$meta = $this->get_meta( $post->ID, MetaFields::property_fields() );
		?>
		<table class="form-table immo-form">
			<tr>
				<th><label for="_immo_price"><?php esc_html_e( 'Kaufpreis', 'immo-manager' ); ?></label></th>
				<td><input type="number" step="1" min="0" id="_immo_price" name="immo_meta[_immo_price]" value="<?php echo esc_attr( (string) $meta['_immo_price'] ); ?>" class="regular-text" /> <?php echo esc_html( Settings::get( 'currency_symbol', '€' ) ); ?></td>
			</tr>
			<tr>
				<th><label for="_immo_rent"><?php esc_html_e( 'Mietpreis (Monat)', 'immo-manager' ); ?></label></th>
				<td><input type="number" step="1" min="0" id="_immo_rent" name="immo_meta[_immo_rent]" value="<?php echo esc_attr( (string) $meta['_immo_rent'] ); ?>" class="regular-text" /> <?php echo esc_html( Settings::get( 'currency_symbol', '€' ) ); ?></td>
			</tr>
			<tr>
				<th><label for="_immo_operating_costs"><?php esc_html_e( 'Betriebskosten (Monat)', 'immo-manager' ); ?></label></th>
				<td><input type="number" step="1" min="0" id="_immo_operating_costs" name="immo_meta[_immo_operating_costs]" value="<?php echo esc_attr( (string) $meta['_immo_operating_costs'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="_immo_deposit"><?php esc_html_e( 'Kaution', 'immo-manager' ); ?></label></th>
				<td><input type="number" step="1" min="0" id="_immo_deposit" name="immo_meta[_immo_deposit]" value="<?php echo esc_attr( (string) $meta['_immo_deposit'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="_immo_commission"><?php esc_html_e( 'Provision', 'immo-manager' ); ?></label></th>
				<td><input type="text" id="_immo_commission" name="immo_meta[_immo_commission]" value="<?php echo esc_attr( (string) $meta['_immo_commission'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'z. B. 3 % + 20 % USt.', 'immo-manager' ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="_immo_available_from"><?php esc_html_e( 'Verfügbar ab', 'immo-manager' ); ?></label></th>
				<td><input type="date" id="_immo_available_from" name="immo_meta[_immo_available_from]" value="<?php echo esc_attr( (string) $meta['_immo_available_from'] ); ?>" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Metabox: Energie.
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_property_energy( \WP_Post $post ): void {
		$meta    = $this->get_meta( $post->ID, MetaFields::property_fields() );
		$classes = array( '', 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' );
		?>
		<table class="form-table immo-form">
			<tr>
				<th><label for="_immo_energy_class"><?php esc_html_e( 'Energieklasse', 'immo-manager' ); ?></label></th>
				<td>
					<select id="_immo_energy_class" name="immo_meta[_immo_energy_class]">
						<?php foreach ( $classes as $cls ) : ?>
							<option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $meta['_immo_energy_class'], $cls ); ?>>
								<?php echo $cls ? esc_html( $cls ) : esc_html__( '— nicht angegeben —', 'immo-manager' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="_immo_energy_hwb"><?php esc_html_e( 'HWB (kWh/m²·a)', 'immo-manager' ); ?></label></th>
				<td><input type="number" step="0.1" min="0" id="_immo_energy_hwb" name="immo_meta[_immo_energy_hwb]" value="<?php echo esc_attr( (string) $meta['_immo_energy_hwb'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label for="_immo_energy_fgee"><?php esc_html_e( 'fGEE', 'immo-manager' ); ?></label></th>
				<td><input type="number" step="0.01" min="0" id="_immo_energy_fgee" name="immo_meta[_immo_energy_fgee]" value="<?php echo esc_attr( (string) $meta['_immo_energy_fgee'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th><label for="_immo_heating"><?php esc_html_e( 'Heizungsart', 'immo-manager' ); ?></label></th>
				<td><input type="text" id="_immo_heating" name="immo_meta[_immo_heating]" value="<?php echo esc_attr( (string) $meta['_immo_heating'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'z. B. Gasetagenheizung, Fernwärme', 'immo-manager' ); ?>" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Metabox: Features (Property UND Project).
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_features( \WP_Post $post ): void {
		$meta    = $this->get_meta( $post->ID, PostTypes::POST_TYPE_PROJECT === $post->post_type ? MetaFields::project_fields() : MetaFields::property_fields() );
		$active  = is_array( $meta['_immo_features'] ) ? $meta['_immo_features'] : array();
		$grouped = Features::get_grouped();
		$cats    = Features::get_categories();
		?>
		<div class="immo-features">
			<?php foreach ( $grouped as $cat_key => $items ) : ?>
				<?php if ( empty( $items ) ) { continue; } ?>
				<fieldset class="immo-feature-group">
					<legend><?php echo esc_html( $cats[ $cat_key ] ?? $cat_key ); ?></legend>
					<div class="immo-feature-grid">
						<?php foreach ( $items as $key => $feature ) : ?>
							<label class="immo-feature">
								<input type="checkbox" name="immo_meta[_immo_features][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $active, true ) ); ?> />
								<span class="icon" aria-hidden="true"><?php echo esc_html( $feature['icon'] ); ?></span>
								<span class="label"><?php echo esc_html( $feature['label'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endforeach; ?>
		</div>

		<p>
			<label for="_immo_custom_features"><strong><?php esc_html_e( 'Weitere Ausstattungen (Freitext):', 'immo-manager' ); ?></strong></label><br>
			<textarea id="_immo_custom_features" name="immo_meta[_immo_custom_features]" rows="2" class="large-text"><?php
				$custom = PostTypes::POST_TYPE_PROJECT === $post->post_type ? '' : (string) ( $meta['_immo_custom_features'] ?? '' );
				echo esc_textarea( $custom );
			?></textarea>
		</p>
		<?php
	}

	/**
	 * Metabox: Darstellung & Layout (Overrides).
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_property_display( \WP_Post $post ): void {
		$meta = $this->get_meta( $post->ID, MetaFields::property_fields() );
		?>
		<p>
			<label for="_immo_layout_type"><strong><?php esc_html_e( 'Layout-Typ', 'immo-manager' ); ?></strong></label><br>
			<select id="_immo_layout_type" name="immo_meta[_immo_layout_type]" style="width:100%;">
				<option value="" <?php selected( $meta['_immo_layout_type'], '' ); ?>><?php esc_html_e( '— Globaler Standard —', 'immo-manager' ); ?></option>
				<option value="standard" <?php selected( $meta['_immo_layout_type'], 'standard' ); ?>><?php esc_html_e( 'Standard', 'immo-manager' ); ?></option>
				<option value="compact"  <?php selected( $meta['_immo_layout_type'], 'compact' ); ?>><?php esc_html_e( 'Kompakt', 'immo-manager' ); ?></option>
			</select>
		</p>
		<p>
			<label for="_immo_gallery_type"><strong><?php esc_html_e( 'Galerie-Typ', 'immo-manager' ); ?></strong></label><br>
			<select id="_immo_gallery_type" name="immo_meta[_immo_gallery_type]" style="width:100%;">
				<option value="" <?php selected( $meta['_immo_gallery_type'], '' ); ?>><?php esc_html_e( '— Globaler Standard —', 'immo-manager' ); ?></option>
				<option value="slider" <?php selected( $meta['_immo_gallery_type'], 'slider' ); ?>><?php esc_html_e( 'Slider', 'immo-manager' ); ?></option>
				<option value="grid"   <?php selected( $meta['_immo_gallery_type'], 'grid' ); ?>><?php esc_html_e( 'Raster (Grid)', 'immo-manager' ); ?></option>
			</select>
		</p>
		<p>
			<label for="_immo_hero_type"><strong><?php esc_html_e( 'Hauptbild-Breite', 'immo-manager' ); ?></strong></label><br>
			<select id="_immo_hero_type" name="immo_meta[_immo_hero_type]" style="width:100%;">
				<option value="" <?php selected( $meta['_immo_hero_type'], '' ); ?>><?php esc_html_e( '— Globaler Standard —', 'immo-manager' ); ?></option>
				<option value="full"      <?php selected( $meta['_immo_hero_type'], 'full' ); ?>><?php esc_html_e( 'Volle Breite', 'immo-manager' ); ?></option>
				<option value="contained" <?php selected( $meta['_immo_hero_type'], 'contained' ); ?>><?php esc_html_e( 'Eingefasst', 'immo-manager' ); ?></option>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( 'Diese Einstellungen überschreiben die globalen Design-Vorgaben für diese Immobilie.', 'immo-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Metabox: Kontakt (Property UND Project).
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_contact( \WP_Post $post ): void {
		$fields = PostTypes::POST_TYPE_PROJECT === $post->post_type ? MetaFields::project_fields() : MetaFields::property_fields();
		$meta   = $this->get_meta( $post->ID, $fields );

		// Fallback auf Standard-Einstellungen, wenn Makler-Daten noch leer sind.
		if ( empty( $meta['_immo_contact_name'] ) && empty( $meta['_immo_contact_email'] ) ) {
			$meta['_immo_contact_name']     = Settings::get( 'agent_name', '' );
			$meta['_immo_contact_email']    = Settings::get( 'agent_email', '' );
			$meta['_immo_contact_phone']    = Settings::get( 'agent_phone', '' );
			$meta['_immo_contact_image_id'] = (int) Settings::get( 'agent_image_id', 0 );
		}
		?>
		<p>
			<label for="_immo_contact_name"><strong><?php esc_html_e( 'Name', 'immo-manager' ); ?></strong></label>
			<input type="text" id="_immo_contact_name" name="immo_meta[_immo_contact_name]" value="<?php echo esc_attr( (string) $meta['_immo_contact_name'] ); ?>" class="widefat" />
		</p>
		<p>
			<label for="_immo_contact_email"><strong><?php esc_html_e( 'E-Mail', 'immo-manager' ); ?></strong></label>
			<input type="email" id="_immo_contact_email" name="immo_meta[_immo_contact_email]" value="<?php echo esc_attr( (string) $meta['_immo_contact_email'] ); ?>" class="widefat" />
		</p>
		<p>
			<label for="_immo_contact_phone"><strong><?php esc_html_e( 'Telefon', 'immo-manager' ); ?></strong></label>
			<input type="text" id="_immo_contact_phone" name="immo_meta[_immo_contact_phone]" value="<?php echo esc_attr( (string) $meta['_immo_contact_phone'] ); ?>" class="widefat" />
		</p>
		<p>
			<label for="_immo_contact_image_id"><strong><?php esc_html_e( 'Makler Foto', 'immo-manager' ); ?></strong></label><br>
			<?php
			$img_id = (int) $meta['_immo_contact_image_id'];
			$url    = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
			?>
			<span class="immo-contact-image-preview-wrapper" style="display:block; margin-bottom:10px;">
				<img src="<?php echo esc_url( $url ); ?>" style="max-width:100px; height:auto; border-radius:4px; <?php echo $url ? '' : 'display:none;'; ?>" class="immo-image-preview" />
			</span>
			<input type="hidden" id="_immo_contact_image_id" name="immo_meta[_immo_contact_image_id]" value="<?php echo esc_attr( $img_id ); ?>" />
			<button type="button" class="button immo-contact-upload-btn"><?php esc_html_e( 'Bild auswählen', 'immo-manager' ); ?></button>
			<button type="button" class="button immo-contact-remove-btn" <?php echo $img_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Entfernen', 'immo-manager' ); ?></button>
		</p>
		<script>
		jQuery(document).ready(function($){
			var contactFrame;
			$('.immo-contact-upload-btn').on('click', function(e) {
				e.preventDefault();
				var btn = $(this);
				if ( contactFrame ) { contactFrame.open(); return; }
				contactFrame = wp.media({
					title: '<?php esc_js( __( 'Makler Foto auswählen', 'immo-manager' ) ); ?>',
					button: { text: '<?php esc_js( __( 'Verwenden', 'immo-manager' ) ); ?>' },
					multiple: false
				});
				contactFrame.on('select', function() {
					var attachment = contactFrame.state().get('selection').first().toJSON();
					$('#_immo_contact_image_id').val(attachment.id);
					btn.siblings('.immo-contact-image-preview-wrapper').find('.immo-image-preview').attr('src', attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url).show();
					btn.siblings('.immo-contact-remove-btn').show();
				});
				contactFrame.open();
			});
			$('.immo-contact-remove-btn').on('click', function(e) {
				e.preventDefault();
				$('#_immo_contact_image_id').val('0');
				$(this).siblings('.immo-contact-image-preview-wrapper').find('.immo-image-preview').attr('src', '').hide();
				$(this).hide();
			});
		});
		</script>
		<?php
	}

	// =========================================================================
	// Project-spezifische Metaboxes
	// =========================================================================

	/**
	 * Metabox: Project-Details.
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_project_details( \WP_Post $post ): void {
		wp_nonce_field( self::PROJECT_NONCE, 'immo_project_nonce' );
		$meta = $this->get_meta( $post->ID, MetaFields::project_fields() );
		?>
		<table class="form-table immo-form">
			<tr>
				<th><label for="_immo_project_status"><?php esc_html_e( 'Projektstatus', 'immo-manager' ); ?></label></th>
				<td>
					<select id="_immo_project_status" name="immo_meta[_immo_project_status]">
						<option value="planning"  <?php selected( $meta['_immo_project_status'], 'planning' ); ?>><?php esc_html_e( 'In Planung', 'immo-manager' ); ?></option>
						<option value="building"  <?php selected( $meta['_immo_project_status'], 'building' ); ?>><?php esc_html_e( 'In Bau', 'immo-manager' ); ?></option>
						<option value="completed" <?php selected( $meta['_immo_project_status'], 'completed' ); ?>><?php esc_html_e( 'Fertiggestellt', 'immo-manager' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="_immo_project_start_date"><?php esc_html_e( 'Baubeginn / Fertigstellung', 'immo-manager' ); ?></label></th>
				<td>
					<input type="date" id="_immo_project_start_date" name="immo_meta[_immo_project_start_date]" value="<?php echo esc_attr( (string) $meta['_immo_project_start_date'] ); ?>" />
					<input type="date"                              name="immo_meta[_immo_project_completion]"  value="<?php echo esc_attr( (string) $meta['_immo_project_completion'] ); ?>" />
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Metabox: Wohneinheiten.
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_project_units( \WP_Post $post ): void {
		$units    = Units::get_by_project( $post->ID );
		$template = IMMO_MANAGER_PLUGIN_DIR . 'templates/admin/metabox-project-units.php';
		if ( is_readable( $template ) ) {
			include $template;
		}
	}

	/**
	 * Metabox: Bildergalerie (Property UND Project).
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return void
	 */
	public function render_gallery( \WP_Post $post ): void {
		$gallery = get_post_meta( $post->ID, '_immo_gallery', true );
		$gallery = is_array( $gallery ) ? $gallery : array();
		wp_nonce_field( 'immo_gallery_nonce', 'immo_gallery_nonce' );
		?>
		<div id="immo-gallery-container">
			<div class="immo-upload-drop-zone" id="immo-gallery-drop-zone">
				<span class="immo-upload-icon">📸</span>
				<p><?php esc_html_e( 'Bilder hierher ziehen oder klicken zum Auswählen', 'immo-manager' ); ?></p>
				<input type="file" id="immo-gallery-file-input" multiple accept="image/*" style="display:none">
				<div style="display:flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 10px;">
					<button type="button" class="button immo-upload-btn"><?php esc_html_e( 'Bilder hochladen', 'immo-manager' ); ?></button>
					<button type="button" id="immo-gallery-add" class="button"><?php esc_html_e( 'Aus Mediathek wählen', 'immo-manager' ); ?></button>
				</div>
			</div>
			<div id="immo-gallery-images">
				<?php foreach ( $gallery as $image_id ) : ?>
					<?php if ( wp_attachment_is_image( $image_id ) ) : ?>
						<div class="immo-gallery-item" data-id="<?php echo esc_attr( $image_id ); ?>">
							<?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?>
							<button type="button" class="immo-gallery-remove" title="<?php esc_attr_e( 'Entfernen', 'immo-manager' ); ?>">×</button>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<input type="hidden" id="immo-gallery-ids" name="immo_gallery_ids" value="<?php echo esc_attr( implode( ',', $gallery ) ); ?>" />
		</div>
		<script>
		jQuery(document).ready(function($) {
			var frame;
			$('#immo-gallery-add').on('click', function(e) {
				e.preventDefault();
				if (frame) {
					frame.open();
					return;
				}
				frame = wp.media({
					title: '<?php esc_js( __( 'Bilder auswählen', 'immo-manager' ) ); ?>',
					button: { text: '<?php esc_js( __( 'Hinzufügen', 'immo-manager' ) ); ?>' },
					multiple: true,
					library: { type: 'image' }
				});
				frame.on('select', function() {
					var attachments = frame.state().get('selection').toJSON();
					var ids = [];
					attachments.forEach(function(attachment) {
						if ($('#immo-gallery-images .immo-gallery-item[data-id="' + attachment.id + '"]').length === 0) {
							$('#immo-gallery-images').append(
								'<div class="immo-gallery-item" data-id="' + attachment.id + '">' +
									'<img src="' + attachment.sizes.thumbnail.url + '" alt="' + attachment.alt + '">' +
									'<button type="button" class="immo-gallery-remove" title="<?php esc_attr_e( 'Entfernen', 'immo-manager' ); ?>">×</button>' +
								'</div>'
							);
						}
						ids.push(attachment.id);
					});
					updateGalleryIds();
				});
				frame.open();
			});
			$(document).on('click', '.immo-gallery-remove', function() {
				$(this).closest('.immo-gallery-item').remove();
				updateGalleryIds();
			});
			function updateGalleryIds() {
				var ids = [];
				$('#immo-gallery-images .immo-gallery-item').each(function() {
					ids.push($(this).data('id'));
				});
				$('#immo-gallery-ids').val(ids.join(','));
			}
			if ($.fn.sortable) {
				$('#immo-gallery-images').sortable({
					items: '.immo-gallery-item',
					cursor: 'move',
					update: function() {
						updateGalleryIds();
					}
				});
			}

			// Drag & Drop Upload Logic
			var dropZone = document.getElementById('immo-gallery-drop-zone');
			var fileInput = document.getElementById('immo-gallery-file-input');
			if (dropZone && fileInput) {
				dropZone.addEventListener('click', function (e) {
					if (e.target.id === 'immo-gallery-add') return;
					fileInput.click();
				});
				var uBtn = dropZone.querySelector('.immo-upload-btn');
				if (uBtn) uBtn.addEventListener('click', function(e) { e.stopPropagation(); fileInput.click(); });
				
				dropZone.addEventListener('dragover', function (e) { e.preventDefault(); dropZone.classList.add('drag-over'); });
				dropZone.addEventListener('dragleave', function () { dropZone.classList.remove('drag-over'); });
				dropZone.addEventListener('drop', function (e) {
					e.preventDefault(); dropZone.classList.remove('drag-over');
					uploadFiles(e.dataTransfer.files);
				});
				fileInput.addEventListener('change', function () { uploadFiles(fileInput.files); });

				function uploadFiles(files) {
					var fileArray = Array.from(files).filter(function(file) { return file.type.startsWith('image/'); });
					function processNext() {
						if (fileArray.length === 0) return;
						var file = fileArray.shift();

						var placeholder = $('<div class="immo-gallery-item immo-preview-uploading"><div class="immo-spinner"></div></div>');
						$('#immo-gallery-images').append(placeholder);

						var fd = new FormData();
						fd.append('action', 'upload-attachment');
						fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'media-form' ) ); ?>');
						fd.append('post_id', '<?php echo esc_js( $post->ID ); ?>');
						fd.append('async-upload', file, file.name);

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: fd,
							processData: false,
							contentType: false,
							success: function(data) {
								if (data.success && data.data && data.data.id) {
									var id = data.data.id;
									placeholder.attr('data-id', id);
									placeholder.removeClass('immo-preview-uploading');
									placeholder.html('<img src="' + (data.data.url || '') + '" alt=""><button type="button" class="immo-gallery-remove" title="Entfernen">×</button>');
									updateGalleryIds();
								} else {
									placeholder.remove();
								}
							},
							error: function() { placeholder.remove(); },
							complete: function() { processNext(); }
						});
					}
					processNext();
				}
			}
		});
		</script>
		<style>
		#immo-gallery-container { margin-top: 10px; }
		.immo-upload-drop-zone { border: 2px dashed #ccc; border-radius: 8px; padding: 2rem 1rem; text-align: center; cursor: pointer; transition: all 0.2s ease; background: #fafafa; margin-bottom: 1rem; }
		.immo-upload-drop-zone:hover, .immo-upload-drop-zone.drag-over { border-color: #0073aa; background: rgba(0,115,170,0.05); }
		.immo-upload-icon { font-size: 2.5em; display: block; margin-bottom: 0.5rem; }
		#immo-gallery-images { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
		.immo-gallery-item { position: relative; display: inline-block; cursor: move; width: 80px; height: 80px; border: 1px solid #ddd; background: #fff; display: flex; align-items: center; justify-content: center; }
		.immo-gallery-item img { width: 100%; height: 100%; object-fit: cover; }
		.immo-gallery-remove { position: absolute; top: -5px; right: -5px; background: #f00; color: #fff; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 14px; line-height: 1; z-index: 10; padding: 0;}
		.immo-preview-uploading .immo-spinner { width: 24px; height: 24px; border: 3px solid #ccc; border-top-color: #0073aa; border-radius: 50%; animation: immo-spin 1s linear infinite; }
		@keyframes immo-spin { to { transform: rotate(360deg); } }
		</style>
		<?php
	}

	// =========================================================================
	// Save Handlers
	// =========================================================================

	/**
	 * Property speichern.
	 *
	 * @param int      $post_id Post-ID.
	 * @param \WP_Post $post    Post.
	 *
	 * @return void
	 */
	public function save_property( int $post_id, \WP_Post $post ): void {
		if ( ! $this->can_save( $post_id, $post, self::PROPERTY_NONCE, 'immo_property_nonce' ) ) {
			return;
		}

		$raw = isset( $_POST['immo_meta'] ) && is_array( $_POST['immo_meta'] ) ? wp_unslash( $_POST['immo_meta'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$san = MetaFields::sanitize_property_payload( $raw );

		foreach ( MetaFields::property_fields() as $key => $def ) {
			if ( array_key_exists( $key, $san ) ) {
				update_post_meta( $post_id, $key, $san[ $key ] );
			} elseif ( '_immo_features' === $key ) {
				// Checkboxen unchecked → leeres Array.
				update_post_meta( $post_id, $key, array() );
			}
		}

		// Galerie speichern.
		if ( isset( $_POST['immo_gallery_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['immo_gallery_nonce'] ) ), 'immo_gallery_nonce' ) ) {
			$gallery_ids = isset( $_POST['immo_gallery_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['immo_gallery_ids'] ) ) : '';
			$gallery     = $gallery_ids ? array_map( 'intval', explode( ',', $gallery_ids ) ) : array();
			update_post_meta( $post_id, '_immo_gallery', $gallery );
		}
	}

	/**
	 * Project speichern (inkl. Units via AJAX separat).
	 *
	 * @param int      $post_id Post-ID.
	 * @param \WP_Post $post    Post.
	 *
	 * @return void
	 */
	public function save_project( int $post_id, \WP_Post $post ): void {
		if ( ! $this->can_save( $post_id, $post, self::PROJECT_NONCE, 'immo_project_nonce' ) ) {
			return;
		}

		$raw = isset( $_POST['immo_meta'] ) && is_array( $_POST['immo_meta'] ) ? wp_unslash( $_POST['immo_meta'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$san = MetaFields::sanitize_project_payload( $raw );

		foreach ( MetaFields::project_fields() as $key => $def ) {
			if ( array_key_exists( $key, $san ) ) {
				update_post_meta( $post_id, $key, $san[ $key ] );
			} elseif ( '_immo_features' === $key ) {
				update_post_meta( $post_id, $key, array() );
			}
		}

		// Galerie speichern.
		if ( isset( $_POST['immo_gallery_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['immo_gallery_nonce'] ) ), 'immo_gallery_nonce' ) ) {
			$gallery_ids = isset( $_POST['immo_gallery_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['immo_gallery_ids'] ) ) : '';
			$gallery     = $gallery_ids ? array_map( 'intval', explode( ',', $gallery_ids ) ) : array();
			update_post_meta( $post_id, '_immo_gallery', $gallery );
		}
	}

	/**
	 * Units eines gelöschten Projekts aufräumen.
	 *
	 * @param int $post_id Gelöschter Post.
	 *
	 * @return void
	 */
	public function cleanup_on_project_delete( int $post_id ): void {
		if ( get_post_type( $post_id ) === PostTypes::POST_TYPE_PROJECT ) {
			Units::delete_by_project( $post_id );
		}
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Prüft, ob gespeichert werden darf.
	 *
	 * @param int      $post_id      Post-ID.
	 * @param \WP_Post $post         Post.
	 * @param string   $nonce_action Nonce-Action.
	 * @param string   $nonce_name   Nonce-Feldname.
	 *
	 * @return bool
	 */
	private function can_save( int $post_id, \WP_Post $post, string $nonce_action, string $nonce_name ): bool {
		// Autosave/Revision überspringen.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		// Nonce prüfen – Feld muss vorhanden und gültig sein.
		if ( ! isset( $_POST[ $nonce_name ] ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) );
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Meta-Werte mit Defaults aus Definition holen.
	 *
	 * @param int                  $post_id Post-ID.
	 * @param array<string, array> $fields  Field-Definitionen.
	 *
	 * @return array<string, mixed>
	 */
	private function get_meta( int $post_id, array $fields ): array {
		$out = array();
		foreach ( $fields as $key => $def ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' === $value || null === $value || ( is_array( $value ) && empty( $value ) && 'array' !== ( $def['type'] ?? '' ) ) ) {
				$value = $def['default'] ?? '';
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Dropdown mit allen Bauprojekten rendern.
	 *
	 * @param int $selected Aktuelle Auswahl.
	 *
	 * @return void
	 */
	private function render_project_select( int $selected ): void {
		$projects = get_posts(
			array(
				'post_type'      => PostTypes::POST_TYPE_PROJECT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<select name="immo_meta[_immo_project_id]">
			<option value="0"><?php esc_html_e( '— Kein Projekt —', 'immo-manager' ); ?></option>
			<?php foreach ( $projects as $project ) : ?>
				<option value="<?php echo esc_attr( (string) $project->ID ); ?>" <?php selected( $selected, $project->ID ); ?>>
					<?php echo esc_html( $project->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}
