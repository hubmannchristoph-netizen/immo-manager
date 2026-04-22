<?php
/**
 * Template: Immobilien-Archiv (automatisch via template_include).
 *
 * Übernimmt den Header/Footer des aktiven Themes und zeigt
 * die Plugin-Listenansicht in der Content-Area.
 *
 * Themes können dieses Template überschreiben mit:
 * {theme}/immo-manager/archive-immo_mgr_property.php
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Initialdaten für die Liste laden.
$rest      = \ImmoManager\Plugin::instance()->get_rest_api();
$request   = new \WP_REST_Request( 'GET', '/immo-manager/v1/properties' );

// URL-Parameter weitergeben (Filter, Sortierung, Seite).
$allowed_params = array(
	'status', 'mode', 'type', 'region_state', 'region_district',
	'price_min', 'price_max', 'area_min', 'area_max', 'rooms',
	'energy_class', 'features', 'orderby', 'page', 'per_page',
);
$query_params = array();
foreach ( $allowed_params as $param ) {
	if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$query_params[ $param ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
}
if ( ! isset( $query_params['status'] ) ) {
	$query_params['status'] = 'available';
}
$query_params['per_page'] = $query_params['per_page'] ?? (int) \ImmoManager\Settings::get( 'items_per_page', 12 );

$request->set_query_params( $query_params );
$response   = $rest->get_properties( $request );
$data       = $response->get_data();
$properties = $data['properties'] ?? array();
$pagination = $data['pagination'] ?? array();

$api_base = rest_url( \ImmoManager\RestApi::NAMESPACE );
$nonce    = wp_create_nonce( 'wp_rest' );
$layout   = (string) \ImmoManager\Settings::get( 'default_view', 'grid' );
?>

	<div class="immo-archive-wrapper">
		<div class="immo-archive-header">
			<h1 class="immo-archive-title">
				<?php echo esc_html( post_type_archive_title( '', false ) ?: __( 'Immobilien', 'immo-manager' ) ); ?>
			</h1>
			<?php
			$desc = get_the_archive_description();
			if ( $desc ) {
				echo '<div class="immo-archive-description">' . wp_kses_post( $desc ) . '</div>';
			}
			?>
		</div>

		<?php
		// List-Template laden (enthält Filter-Sidebar + Grid + Pagination).
		include IMMO_MANAGER_PLUGIN_DIR . 'templates/property-list.php';
		?>
	</div>

<?php get_footer(); ?>
