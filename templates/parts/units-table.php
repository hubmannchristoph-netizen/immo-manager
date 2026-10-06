<?php
/**
 * Template-Part: Wohneinheiten-Tabelle (kompakt).
 *
 * Verwendet von [immo_units] und dem Elementor-Widget „Wohneinheiten eines Projekts".
 * Die Einzelseite des Bauprojekts nutzt ihre eigene, erweiterte Tabelle mit
 * Quick-Info-Lightbox (templates/single-immo_mgr_project.php).
 *
 * Erwartete Variable:
 * @var array<int, array<string, mixed>> $units Formatierte Units aus RestApi::format_unit().
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

$unit_status_class = array(
	'available' => 'status-available',
	'reserved'  => 'status-reserved',
	'sold'      => 'status-sold',
	'rented'    => 'status-sold',
);
?>
<div class="immo-units-table-wrap">
	<table class="immo-units-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Nr.', 'immo-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Etage', 'immo-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Fläche', 'immo-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Zi.', 'immo-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Preis', 'immo-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'immo-manager' ); ?></th>
				<th scope="col" class="immo-units-table-action"><span class="screen-reader-text"><?php esc_html_e( 'Aktion', 'immo-manager' ); ?></span></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $units as $unit ) :
			$status        = (string) ( $unit['status'] ?? 'available' );
			$status_class  = $unit_status_class[ $status ] ?? '';
			$floor_val     = (int) ( $unit['floor'] ?? 0 );
			$floor_display = 0 === $floor_val ? __( 'EG', 'immo-manager' ) : $floor_val . '.';
			$price_display = '';
			if ( (float) $unit['price'] > 0 ) {
				$price_display = (string) $unit['price_formatted'];
			} elseif ( (float) $unit['rent'] > 0 ) {
				$price_display = $unit['rent_formatted'] . ' / ' . __( 'Monat', 'immo-manager' );
			}
			$property = $unit['property'] ?? null;
			?>
			<tr class="immo-units-row" data-status="<?php echo esc_attr( $status ); ?>">
				<td class="immo-units-cell-number"><strong><?php echo esc_html( $unit['unit_number'] ); ?></strong></td>
				<td><?php echo esc_html( $floor_display ); ?></td>
				<td><?php echo (float) $unit['area'] > 0 ? esc_html( number_format_i18n( (float) $unit['area'], 0 ) . ' m²' ) : '—'; ?></td>
				<td><?php echo (int) $unit['rooms'] > 0 ? esc_html( (string) (int) $unit['rooms'] ) : '—'; ?></td>
				<td class="immo-units-cell-price">
					<?php echo $price_display ? esc_html( $price_display ) : '—'; ?>
					<?php
					if ( ! empty( $property['commission_free'] ) && (float) $unit['price'] > 0 ) {
						\ImmoManager\Templates::commission_free_badge( array( 'commission_free' => true, 'mode' => 'sale' ), 'icon' );
					}
					?>
				</td>
				<td><span class="immo-unit-status-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $unit['status_label'] ?? $status ); ?></span></td>
				<td class="immo-units-table-action">
					<?php if ( ! empty( $property['permalink'] ) ) : ?>
						<a href="<?php echo esc_url( $property['permalink'] ); ?>" class="immo-btn immo-btn-secondary immo-btn-sm"><?php esc_html_e( 'Details', 'immo-manager' ); ?></a>
					<?php elseif ( ! empty( $unit['floor_plan']['url'] ) ) : ?>
						<a href="<?php echo esc_url( $unit['floor_plan']['url'] ); ?>" class="immo-btn immo-btn-secondary immo-btn-sm" target="_blank" rel="noopener"><?php esc_html_e( 'Grundriss', 'immo-manager' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
