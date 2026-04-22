<?php
/**
 * Template-Part: Filter-Sidebar (Off-Canvas).
 *
 * Erwartete Variablen:
 * @var array<string, string> $states   Bundesländer Key => Label.
 * @var string                $currency Währungs-Symbol.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;
$states = $states ?? \ImmoManager\Regions::get_states();
?>
<aside class="immo-filter-sidebar" id="immo-filter-sidebar" aria-label="<?php esc_attr_e( 'Filter', 'immo-manager' ); ?>" aria-hidden="true">
	<div class="immo-filter-header">
		<h3 class="immo-filter-title"><?php esc_html_e( 'Filter', 'immo-manager' ); ?></h3>
		<button class="immo-filter-close" aria-label="<?php esc_attr_e( 'Filter schließen', 'immo-manager' ); ?>">✕</button>
	</div>

	<div class="immo-filter-content">

		<!-- Immobilientyp -->
		<div class="immo-filter-group">
			<h4 class="immo-filter-group-title"><?php esc_html_e( 'Immobilientyp', 'immo-manager' ); ?></h4>
			<div class="immo-filter-options">
				<?php
				$types = array(
					'wohnung'         => '🏘️ ' . __( 'Wohnung', 'immo-manager' ),
					'einfamilienhaus' => '🏠 ' . __( 'Einfamilienhaus', 'immo-manager' ),
					'mehrfamilienhaus' => '🏢 ' . __( 'Mehrfamilienhaus', 'immo-manager' ),
					'grundstück'      => '🏗️ ' . __( 'Grundstück', 'immo-manager' ),
					'gewerbe'         => '🏪 ' . __( 'Gewerbe', 'immo-manager' ),
				);
				foreach ( $types as $val => $label ) : ?>
					<label class="immo-filter-option">
						<input type="checkbox" class="immo-filter-input" name="type" value="<?php echo esc_attr( $val ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Modus -->
		<div class="immo-filter-group">
			<h4 class="immo-filter-group-title"><?php esc_html_e( 'Angebot', 'immo-manager' ); ?></h4>
			<div class="immo-filter-options immo-filter-radio">
				<label class="immo-filter-option">
					<input type="radio" class="immo-filter-input" name="mode" value="" checked>
					<span><?php esc_html_e( 'Alle', 'immo-manager' ); ?></span>
				</label>
				<label class="immo-filter-option">
					<input type="radio" class="immo-filter-input" name="mode" value="sale">
					<span><?php esc_html_e( 'Kaufen', 'immo-manager' ); ?></span>
				</label>
				<label class="immo-filter-option">
					<input type="radio" class="immo-filter-input" name="mode" value="rent">
					<span><?php esc_html_e( 'Mieten', 'immo-manager' ); ?></span>
				</label>
			</div>
		</div>

		<!-- Preisbereich -->
		<div class="immo-filter-group">
			<h4 class="immo-filter-group-title"><?php esc_html_e( 'Preis', 'immo-manager' ); ?></h4>
			<div class="immo-range-inputs">
				<input type="number" class="immo-filter-input immo-range-min" name="price_min" min="0" step="10000"
					placeholder="<?php esc_attr_e( 'Von', 'immo-manager' ); ?>" aria-label="<?php esc_attr_e( 'Mindestpreis', 'immo-manager' ); ?>">
				<span class="immo-range-sep">–</span>
				<input type="number" class="immo-filter-input immo-range-max" name="price_max" min="0" step="10000"
					placeholder="<?php esc_attr_e( 'Bis', 'immo-manager' ); ?>" aria-label="<?php esc_attr_e( 'Höchstpreis', 'immo-manager' ); ?>">
				<span class="immo-range-unit"><?php echo esc_html( $currency ?? '€' ); ?></span>
			</div>
		</div>

		<!-- Fläche -->
		<div class="immo-filter-group">
			<h4 class="immo-filter-group-title"><?php esc_html_e( 'Fläche (m²)', 'immo-manager' ); ?></h4>
			<div class="immo-range-inputs">
				<input type="number" class="immo-filter-input immo-range-min" name="area_min" min="0" step="10"
					placeholder="<?php esc_attr_e( 'Von', 'immo-manager' ); ?>">
				<span class="immo-range-sep">–</span>
				<input type="number" class="immo-filter-input immo-range-max" name="area_max" min="0" step="10"
					placeholder="<?php esc_attr_e( 'Bis', 'immo-manager' ); ?>">
				<span class="immo-range-unit">m²</span>
			</div>
		</div>

		<!-- Zimmer -->
		<div class="immo-filter-group">
			<h4 class="immo-filter-group-title"><?php esc_html_e( 'Zimmer', 'immo-manager' ); ?></h4>
			<div class="immo-filter-options immo-filter-buttons">
				<?php foreach ( array( '1', '2', '3', '4', '5+' ) as $r ) : ?>
					<label class="immo-filter-option immo-filter-btn-option">
						<input type="checkbox" class="immo-filter-input" name="rooms" value="<?php echo esc_attr( str_replace( '+', '', $r ) ); ?>">
						<span><?php echo esc_html( $r ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Region -->
		<div class="immo-filter-group">
			<h4 class="immo-filter-group-title"><?php esc_html_e( 'Region', 'immo-manager' ); ?></h4>
			<select class="immo-filter-input immo-region-state" name="region_state"
				aria-label="<?php esc_attr_e( 'Bundesland', 'immo-manager' ); ?>">
				<option value=""><?php esc_html_e( '— Bundesland —', 'immo-manager' ); ?></option>
				<?php foreach ( $states as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select class="immo-filter-input immo-region-district" name="region_district" disabled
				aria-label="<?php esc_attr_e( 'Bezirk', 'immo-manager' ); ?>">
				<option value=""><?php esc_html_e( '— Bezirk —', 'immo-manager' ); ?></option>
			</select>
		</div>

		<!-- Status -->
		<div class="immo-filter-group">
			<h4 class="immo-filter-group-title"><?php esc_html_e( 'Status', 'immo-manager' ); ?></h4>
			<div class="immo-filter-options">
				<label class="immo-filter-option">
					<input type="checkbox" class="immo-filter-input" name="status" value="available" checked>
					<span>✅ <?php esc_html_e( 'Verfügbar', 'immo-manager' ); ?></span>
				</label>
				<label class="immo-filter-option">
					<input type="checkbox" class="immo-filter-input" name="status" value="reserved">
					<span>⏳ <?php esc_html_e( 'Reserviert', 'immo-manager' ); ?></span>
				</label>
				<label class="immo-filter-option">
					<input type="checkbox" class="immo-filter-input" name="status" value="sold">
					<span>🔴 <?php esc_html_e( 'Verkauft/Vermietet', 'immo-manager' ); ?></span>
				</label>
			</div>
		</div>

		<!-- Energieklasse -->
		<div class="immo-filter-group">
			<h4 class="immo-filter-group-title"><?php esc_html_e( 'Energieklasse', 'immo-manager' ); ?></h4>
			<div class="immo-filter-options immo-filter-buttons">
				<?php foreach ( array( 'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' ) as $cls ) : ?>
					<label class="immo-filter-option immo-filter-btn-option">
						<input type="checkbox" class="immo-filter-input" name="energy_class" value="<?php echo esc_attr( $cls ); ?>">
						<span><?php echo esc_html( $cls ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Ausstattung (Top-Features) -->
		<div class="immo-filter-group">
			<h4 class="immo-filter-group-title"><?php esc_html_e( 'Ausstattung', 'immo-manager' ); ?></h4>
			<div class="immo-filter-options">
				<?php
				$top_features = array( 'pool', 'garden', 'garage', 'balcony', 'elevator', 'floor_heating', 'solar_panels', 'wheelchair_accessible', 'air_conditioning', 'fitted_kitchen' );
				$all_features = \ImmoManager\Features::get_all();
				foreach ( $top_features as $fkey ) :
					if ( ! isset( $all_features[ $fkey ] ) ) { continue; }
					$f = $all_features[ $fkey ];
					?>
					<label class="immo-filter-option">
						<input type="checkbox" class="immo-filter-input" name="features" value="<?php echo esc_attr( $fkey ); ?>">
						<span><?php echo esc_html( $f['icon'] . ' ' . $f['label'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Filter-Aktionen -->
		<div class="immo-filter-actions">
			<button class="immo-btn immo-btn-secondary immo-filter-reset">
				<?php esc_html_e( 'Zurücksetzen', 'immo-manager' ); ?>
			</button>
		</div>
	</div><!-- .immo-filter-content -->
</aside>
