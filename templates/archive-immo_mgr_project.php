<?php
/**
 * Template: Bauprojekte-Archiv.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

get_header();

$rest    = \ImmoManager\Plugin::instance()->get_rest_api();
$request = new \WP_REST_Request( 'GET', '/immo-manager/v1/projects' );
$request->set_query_params( array( 'per_page' => 12 ) );
$data     = $rest->get_projects( $request )->get_data();
$projects = $data['projects'] ?? array();
$currency = \ImmoManager\Settings::get( 'currency_symbol', '€' );
?>

	<div class="immo-archive-wrapper">
		<h1 class="immo-archive-title">
			<?php echo esc_html( post_type_archive_title( '', false ) ?: __( 'Bauprojekte', 'immo-manager' ) ); ?>
		</h1>

		<?php if ( empty( $projects ) ) : ?>
			<p class="immo-no-results"><?php esc_html_e( 'Keine Bauprojekte gefunden.', 'immo-manager' ); ?></p>
		<?php else : ?>
			<div class="immo-projects-grid immo-widget-grid immo-widget-cols-3" role="list">
				<?php foreach ( $projects as $project ) :
					include IMMO_MANAGER_PLUGIN_DIR . 'templates/parts/project-card.php';
				endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

<?php get_footer(); ?>
