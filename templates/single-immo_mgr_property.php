<?php
/**
 * Template: Einzelne Immobilie (automatisch via template_include).
 *
 * Übernimmt Header/Footer des Themes und zeigt die Plugin-Detailansicht.
 *
 * Themes können überschreiben mit:
 * {theme}/immo-manager/single-immo_mgr_property.php
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Aktuellen Post ermitteln.
$post_id = get_the_ID();

if ( ! $post_id ) {
	echo '<p>' . esc_html__( 'Immobilie nicht gefunden.', 'immo-manager' ) . '</p>';
	get_footer();
	return;
}

// Property-Daten via REST-Formatter laden (konsistent mit Headless-API).
$post = get_post( $post_id );
if ( ! $post || 'publish' !== $post->post_status ) {
	echo '<p>' . esc_html__( 'Diese Immobilie ist nicht mehr verfügbar.', 'immo-manager' ) . '</p>';
	get_footer();
	return;
}

$rest     = \ImmoManager\Plugin::instance()->get_rest_api();
$property = $rest->format_property( $post, true );

// Ähnliche Immobilien.
$similar_req = new \WP_REST_Request( 'GET', "/immo-manager/v1/properties/{$post_id}/similar" );
$similar_req->set_query_params( array( 'limit' => 3 ) );
$similar_res  = $rest->get_similar( $similar_req );
$similar      = $similar_res->get_data()['properties'] ?? array();

$api_base = rest_url( \ImmoManager\RestApi::NAMESPACE );
$nonce    = wp_create_nonce( 'wp_rest' );

// SEO: Meta-Tags setzen (falls kein SEO-Plugin aktiv).
add_action( 'wp_head', static function () use ( $property ) {
	$meta = $property['meta'] ?? array();
	$img  = $property['featured_image'] ?? null;

	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $property['title'] ?? '' ) . '">' . "\n";
	if ( ! empty( $property['excerpt'] ) ) {
		echo '<meta property="og:description" content="' . esc_attr( wp_strip_all_tags( $property['excerpt'] ) ) . '">' . "\n";
		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $property['excerpt'] ) ) . '">' . "\n";
	}
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img['url_medium'] ) . '">' . "\n";
	}
}, 5 );
?>

	<div class="immo-single-wrapper">
		<?php
		// Detail-Template laden.
		include IMMO_MANAGER_PLUGIN_DIR . 'templates/property-detail.php';
		?>
	</div>

<?php get_footer(); ?>
