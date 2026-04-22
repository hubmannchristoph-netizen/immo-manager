<?php
/**
 * Elementor Widget: Bauprojekte.
 *
 * @package ImmoManager
 */

namespace ImmoManager\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ImmoManager\PostTypes;
use ImmoManager\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProjectsWidget
 */
class ProjectsWidget extends Widget_Base {

	public function get_name() {
		return 'immo-projects';
	}

	public function get_title() {
		return __( 'Bauprojekte', 'immo-manager' );
	}

	public function get_icon() {
		return 'eicon-folder';
	}

	public function get_categories() {
		return array( 'immo-manager' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_query',
			array(
				'label' => __( 'Abfrage', 'immo-manager' ),
			)
		);

		$this->add_control(
			'count',
			array(
				'label'   => __( 'Anzahl', 'immo-manager' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 3,
			)
		);

		$this->add_control(
			'status',
			array(
				'label'   => __( 'Projektstatus', 'immo-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''          => __( 'Alle', 'immo-manager' ),
					'planning'  => __( 'In Planung', 'immo-manager' ),
					'building'  => __( 'In Bau', 'immo-manager' ),
					'completed' => __( 'Fertiggestellt', 'immo-manager' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'immo-manager' ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Anzeige', 'immo-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid'   => __( 'Raster (Grid)', 'immo-manager' ),
					'list'   => __( 'Liste', 'immo-manager' ),
					'slider' => __( 'Carousel', 'immo-manager' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$args = array(
			'post_type'      => PostTypes::POST_TYPE_PROJECT,
			'posts_per_page' => (int) $settings['count'],
			'post_status'    => 'publish',
		);

		if ( ! empty( $settings['status'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_immo_project_status',
					'value' => $settings['status'],
				),
			);
		}

		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			echo '<p>' . esc_html__( 'Keine Projekte gefunden.', 'immo-manager' ) . '</p>';
			return;
		}

		echo '<div class="immo-elementor-widget immo-projects-widget">';
		echo '<div class="immo-list-' . esc_attr( $settings['layout'] ) . '">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$status  = get_post_meta( $post_id, '_immo_project_status', true );
			$image   = get_the_post_thumbnail_url( $post_id, 'medium' );
			?>
			<div class="immo-project-card">
				<?php if ( $image ) : ?>
					<div class="immo-project-image">
						<a href="<?php the_permalink(); ?>"><img src="<?php echo esc_url( $image ); ?>" alt="<?php the_title_attribute(); ?>"></a>
					</div>
				<?php endif; ?>
				<div class="immo-project-content">
					<h3 class="immo-project-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<span class="immo-project-badge status-<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( $status ); ?>
					</span>
				</div>
			</div>
			<?php
		}

		echo '</div></div>';
		wp_reset_postdata();
	}
}
