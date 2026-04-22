<?php
/**
 * REST API – öffentliche Leserouten für Headless-Betrieb.
 *
 * Namespace: /wp-json/immo-manager/v1/
 *
 * Phase 4 implementiert alle öffentlichen GET-Routen, die sowohl der
 * interne AJAX-Filter als auch externe Frontends benötigen.
 * Schreibende Routen (POST/PUT/DELETE) folgen in Phase 6.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestApi
 */
class RestApi {

	/**
	 * API-Namespace.
	 */
	public const NAMESPACE = 'immo-manager/v1';

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );
		add_action( 'rest_api_init', array( $this, 'add_cors_headers' ), 15 );
	}

	/**
	 * Alle öffentlichen Routen registrieren.
	 *
	 * @return void
	 */
	public function register_routes(): void {

		// Immobilien.
		register_rest_route( self::NAMESPACE, '/properties', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_properties' ),
			'permission_callback' => '__return_true',
			'args'                => $this->properties_args(),
		) );

		register_rest_route( self::NAMESPACE, '/properties/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_property' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array( 'validate_callback' => 'is_numeric' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/properties/(?P<id>\d+)/similar', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_similar' ),
			'permission_callback' => '__return_true',
		) );

		// Bauprojekte.
		register_rest_route( self::NAMESPACE, '/projects', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_projects' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/projects/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_project' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/projects/(?P<id>\d+)/units', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_project_units' ),
			'permission_callback' => '__return_true',
		) );

		// Referenzdaten.
		register_rest_route( self::NAMESPACE, '/regions', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_regions' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/regions/(?P<state>[a-z_]+)/districts', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_districts' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/features', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_features' ),
			'permission_callback' => '__return_true',
		) );

		// Öffentliche Settings (Farben, Währung, Karte).
		register_rest_route( self::NAMESPACE, '/settings/public', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_public_settings' ),
			'permission_callback' => '__return_true',
		) );

		// Anfragen einreichen (öffentlich, Rate-limited).
		register_rest_route( self::NAMESPACE, '/inquiries', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_inquiry' ),
			'permission_callback' => '__return_true',
			'args'                => $this->inquiry_args(),
		) );
	}

	// =========================================================================
	// Callbacks – Immobilien
	// =========================================================================

	/**
	 * GET /properties
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_properties( \WP_REST_Request $request ): \WP_REST_Response {
		$args  = $this->build_property_query( $request );
		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->format_property( $post );
		}

		return rest_ensure_response( array(
			'properties' => $items,
			'pagination' => array(
				'total'        => (int) $query->found_posts,
				'pages'        => (int) $query->max_num_pages,
				'current_page' => (int) ( $request->get_param( 'page' ) ?? 1 ),
				'per_page'     => (int) ( $request->get_param( 'per_page' ) ?? Settings::get( 'items_per_page', 12 ) ),
			),
		) );
	}

	/**
	 * GET /properties/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_property( \WP_REST_Request $request ) {
		$post = get_post( (int) $request->get_param( 'id' ) );
		if ( ! $post || PostTypes::POST_TYPE_PROPERTY !== $post->post_type || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'not_found', __( 'Immobilie nicht gefunden.', 'immo-manager' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->format_property( $post, true ) );
	}

	/**
	 * GET /properties/{id}/similar
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_similar( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || PostTypes::POST_TYPE_PROPERTY !== $post->post_type ) {
			return rest_ensure_response( array( 'properties' => array() ) );
		}

		$state = get_post_meta( $id, '_immo_region_state', true );
		$type  = get_post_meta( $id, '_immo_property_type', true );
		$limit = (int) ( $request->get_param( 'limit' ) ?? 3 );
		$limit = max( 1, min( 12, $limit ) );

		$q = new \WP_Query( array(
			'post_type'      => PostTypes::POST_TYPE_PROPERTY,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__not_in'   => array( $id ),
			'orderby'        => 'rand',
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => '_immo_region_state',  'value' => $state, 'compare' => '=' ),
				array( 'key' => '_immo_property_type', 'value' => $type,  'compare' => '=' ),
			),
		) );

		return rest_ensure_response( array(
			'properties' => array_map( array( $this, 'format_property' ), $q->posts ),
		) );
	}

	// =========================================================================
	// Callbacks – Projekte
	// =========================================================================

	/**
	 * GET /projects
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_projects( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = min( 50, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 12 ) ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );

		$args = array(
			'post_type'      => PostTypes::POST_TYPE_PROJECT,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$status = $request->get_param( 'status' );
		if ( $status ) {
			$statuses         = array_map( 'sanitize_key', explode( ',', $status ) );
			$args['meta_query'] = array(
				array( 'key' => '_immo_project_status', 'value' => $statuses, 'compare' => 'IN' ),
			);
		}

		$q = new \WP_Query( $args );
		return rest_ensure_response( array(
			'projects'   => array_map( array( $this, 'format_project' ), $q->posts ),
			'pagination' => array(
				'total'        => (int) $q->found_posts,
				'pages'        => (int) $q->max_num_pages,
				'current_page' => $page,
				'per_page'     => $per_page,
			),
		) );
	}

	/**
	 * GET /projects/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_project( \WP_REST_Request $request ) {
		$post = get_post( (int) $request->get_param( 'id' ) );
		if ( ! $post || PostTypes::POST_TYPE_PROJECT !== $post->post_type || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'not_found', __( 'Bauprojekt nicht gefunden.', 'immo-manager' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->format_project( $post, true ) );
	}

	/**
	 * GET /projects/{id}/units
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_project_units( \WP_REST_Request $request ): \WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$status = $request->get_param( 'status' );
		$orderby = sanitize_key( (string) ( $request->get_param( 'orderby' ) ?? 'floor' ) );

		$units    = Units::get_by_project( $id, $orderby );
		$filtered = $units;

		if ( $status && in_array( $status, Units::STATUSES, true ) ) {
			$filtered = array_values( array_filter( $units, static function ( $u ) use ( $status ) {
				return $u['status'] === $status;
			} ) );
		}

		$counts = Units::count_by_status( $id );
		$counts['total'] = array_sum( $counts );

		return rest_ensure_response( array(
			'units' => array_map( array( $this, 'format_unit' ), $filtered ),
			'stats' => $counts,
		) );
	}

	// =========================================================================
	// Callbacks – Referenzdaten & Settings
	// =========================================================================

	/**
	 * GET /regions
	 *
	 * @return \WP_REST_Response
	 */
	public function get_regions(): \WP_REST_Response {
		return rest_ensure_response( array( 'states' => Regions::get_states() ) );
	}

	/**
	 * GET /regions/{state}/districts
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_districts( \WP_REST_Request $request ) {
		$state = sanitize_key( (string) $request->get_param( 'state' ) );
		if ( ! Regions::is_valid_state( $state ) ) {
			return new \WP_Error( 'not_found', __( 'Bundesland nicht gefunden.', 'immo-manager' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'districts' => Regions::get_districts( $state ) ) );
	}

	/**
	 * GET /features
	 *
	 * @return \WP_REST_Response
	 */
	public function get_features(): \WP_REST_Response {
		return rest_ensure_response( array(
			'categories' => Features::get_categories(),
			'features'   => Features::get_all(),
		) );
	}

	/**
	 * GET /settings/public
	 *
	 * @return \WP_REST_Response
	 */
	public function get_public_settings(): \WP_REST_Response {
		return rest_ensure_response( array(
			'currency_symbol'     => Settings::get( 'currency_symbol', '€' ),
			'currency_position'   => Settings::get( 'currency_position', 'after' ),
			'thousands_separator' => Settings::get( 'thousands_separator', '.' ),
			'decimal_separator'   => Settings::get( 'decimal_separator', ',' ),
			'price_decimals'      => (int) Settings::get( 'price_decimals', 0 ),
			'primary_color'       => Settings::get( 'primary_color', '#1e88e5' ),
			'secondary_color'     => Settings::get( 'secondary_color', '#43a047' ),
			'accent_color'        => Settings::get( 'accent_color', '#ff9800' ),
			'default_view'        => Settings::get( 'default_view', 'grid' ),
			'items_per_page'      => (int) Settings::get( 'items_per_page', 12 ),
			'map'                 => array(
				'enabled'     => (bool) Settings::get( 'map_enabled', 1 ),
				'tile_url'    => Settings::get( 'map_tile_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' ),
				'attribution' => Settings::get( 'map_attribution', '' ),
				'default_lat' => (float) Settings::get( 'map_default_lat', 47.5162 ),
				'default_lng' => (float) Settings::get( 'map_default_lng', 14.5501 ),
				'default_zoom' => (int) Settings::get( 'map_default_zoom', 7 ),
			),
		) );
	}

	// =========================================================================
	// Callback – Anfragen einreichen
	// =========================================================================

	/**
	 * POST /inquiries
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_inquiry( \WP_REST_Request $request ) {
		// API-Key-Prüfung für schreibende Endpunkte.
		$api_key_hash = Settings::get( 'api_key_hash', '' );
		if ( ! empty( $api_key_hash ) ) {
			// Lokale Anfragen aus dem eigenen Frontend via Nonce erlauben.
			$nonce = $request->get_header( 'x-wp-nonce' );
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				$provided_key = $request->get_header( 'x-immo-api-key' );
				// wp_check_password ist sicher gegen Timing-Attacken.
				if ( ! $provided_key || ! wp_check_password( $provided_key, $api_key_hash ) ) {
					return new \WP_Error( 'rest_unauthorized', __( 'Ungültiger oder fehlender API-Key.', 'immo-manager' ), array( 'status' => 401 ) );
				}
			}
		}

		// Rate-Limiting.
		$limit = (int) Settings::get( 'api_rate_limit', 60 );
		if ( $limit > 0 ) {
			$ip        = $this->get_client_ip();
			$cache_key = 'immo_rl_' . md5( $ip );
			$count     = (int) get_transient( $cache_key );
			if ( $count >= $limit ) {
				return new \WP_Error( 'rate_limited', __( 'Zu viele Anfragen. Bitte warte einen Moment.', 'immo-manager' ), array( 'status' => 429 ) );
			}
			set_transient( $cache_key, $count + 1, MINUTE_IN_SECONDS );
		}

		// Validierung.
		$body = $request->get_json_params() ?: (array) $request->get_body_params();

		$errors = array();
		if ( empty( $body['property_id'] ) ) {
			$errors['property_id'] = __( 'Pflichtfeld.', 'immo-manager' );
		}
		if ( empty( $body['inquirer_email'] ) || ! is_email( $body['inquirer_email'] ) ) {
			$errors['inquirer_email'] = __( 'Gültige E-Mail-Adresse erforderlich.', 'immo-manager' );
		}
		if ( empty( $body['inquirer_name'] ) ) {
			$errors['inquirer_name'] = __( 'Pflichtfeld.', 'immo-manager' );
		}
		if ( empty( $body['consent'] ) ) {
			$errors['consent'] = __( 'Datenschutz-Einwilligung erforderlich.', 'immo-manager' );
		}

		if ( $errors ) {
			return new \WP_Error( 'validation_error', __( 'Eingabe fehlerhaft.', 'immo-manager' ), array(
				'status' => 422,
				'errors' => $errors,
			) );
		}

		// Speichern.
		$data = array(
			'property_id'      => absint( $body['property_id'] ),
			'unit_id'          => isset( $body['unit_id'] ) ? absint( $body['unit_id'] ) : null,
			'inquirer_name'    => (string) $body['inquirer_name'],
			'inquirer_email'   => (string) $body['inquirer_email'],
			'inquirer_phone'   => (string) ( $body['inquirer_phone'] ?? '' ),
			'inquirer_message' => (string) ( $body['inquirer_message'] ?? '' ),
			'status'           => 'new',
			'ip_address'       => $this->get_client_ip(),
			'user_agent'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
		);

		$id = Inquiries::create( $data );
		if ( ! $id ) {
			return new \WP_Error( 'db_error', __( 'Anfrage konnte nicht gespeichert werden.', 'immo-manager' ), array( 'status' => 500 ) );
		}

		// Admin-Benachrichtigung.
		$this->notify_admin( $id, $data );
		
		// Auto-Responder an den Interessenten.
		$this->notify_inquirer( $id, $data );

		$response = rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Vielen Dank! Ihre Anfrage wurde gesendet.', 'immo-manager' ),
		) );
		$response->set_status( 201 );
		return $response;
	}

	// =========================================================================
	// CORS
	// =========================================================================

	/**
	 * CORS-Header senden.
	 *
	 * @return void
	 */
	public function add_cors_headers(): void {
		add_filter( 'rest_pre_serve_request', array( $this, 'send_cors_headers' ), 10, 4 );
	}

	/**
	 * CORS-Header Filter-Callback.
	 *
	 * @param bool             $served  Wurde die Anfrage schon beantwortet?
	 * @param \WP_HTTP_Response $result  Response.
	 * @param \WP_REST_Request  $request Request.
	 * @param \WP_REST_Server   $server  Server.
	 *
	 * @return bool
	 */
	public function send_cors_headers( bool $served, $result, $request, $server ): bool {
		// Nur für unsere eigenen Routen.
		$route = $request->get_route();
		if ( 0 !== strpos( $route, '/' . self::NAMESPACE ) ) {
			return $served;
		}

		$allowed_raw = Settings::get( 'cors_allowed_origins', '*' );
		$origin      = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

		if ( '*' === trim( $allowed_raw ) ) {
			header( 'Access-Control-Allow-Origin: *' );
		} elseif ( $origin ) {
			$allowed_list = array_filter( array_map( 'trim', explode( "\n", $allowed_raw ) ) );
			if ( in_array( $origin, $allowed_list, true ) ) {
				header( 'Access-Control-Allow-Origin: ' . $origin );
				header( 'Vary: Origin' );
			}
		}

		header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, X-Immo-API-Key, X-WP-Nonce' );
		header( 'Access-Control-Max-Age: 86400' );

		if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			status_header( 204 );
			exit;
		}

		return $served;
	}

	// =========================================================================
	// Query-Builder
	// =========================================================================

	/**
	 * WP_Query-Args aus REST-Request aufbauen.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return array<string, mixed>
	 */
	public function build_property_query( \WP_REST_Request $request ): array {
		$per_page = min( 50, max( 1, (int) ( $request->get_param( 'per_page' ) ?? Settings::get( 'items_per_page', 12 ) ) ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );

		$args = array(
			'post_type'      => PostTypes::POST_TYPE_PROPERTY,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_query'     => array( 'relation' => 'AND' ),
		);

		// Sortierung.
		$orderby = sanitize_key( (string) ( $request->get_param( 'orderby' ) ?? 'newest' ) );
		switch ( $orderby ) {
			case 'price_asc':
				$args['meta_key'] = '_immo_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';
				break;
			case 'price_desc':
				$args['meta_key'] = '_immo_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'area_desc':
				$args['meta_key'] = '_immo_area';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			default: // newest.
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
		}

		// Filter: Status.
		$status = $request->get_param( 'status' );
		if ( $status ) {
			$statuses = array_filter( array_map( 'sanitize_key', explode( ',', $status ) ) );
			$valid    = array_intersect( $statuses, array( 'available', 'reserved', 'sold', 'rented' ) );
			if ( $valid ) {
				$args['meta_query'][] = array( 'key' => '_immo_status', 'value' => array_values( $valid ), 'compare' => 'IN' );
			}
		}

		// Filter: Modus (sale/rent/both).
		$mode = sanitize_key( (string) ( $request->get_param( 'mode' ) ?? '' ) );
		if ( $mode && in_array( $mode, array( 'sale', 'rent', 'both' ), true ) ) {
			$args['meta_query'][] = array( 'key' => '_immo_mode', 'value' => $mode, 'compare' => '=' );
		}

		// Filter: Immobilientyp.
		$type = $request->get_param( 'type' );
		if ( $type ) {
			$types = array_filter( array_map( 'sanitize_text_field', explode( ',', $type ) ) );
			if ( count( $types ) === 1 ) {
				$args['meta_query'][] = array( 'key' => '_immo_property_type', 'value' => reset( $types ), 'compare' => 'LIKE' );
			} elseif ( count( $types ) > 1 ) {
				$args['meta_query'][] = array( 'key' => '_immo_property_type', 'value' => $types, 'compare' => 'IN' );
			}
		}

		// Filter: Region.
		$state = sanitize_key( (string) ( $request->get_param( 'region_state' ) ?? '' ) );
		if ( $state && Regions::is_valid_state( $state ) ) {
			$args['meta_query'][] = array( 'key' => '_immo_region_state', 'value' => $state, 'compare' => '=' );
		}
		$district = sanitize_key( (string) ( $request->get_param( 'region_district' ) ?? '' ) );
		if ( $district ) {
			$args['meta_query'][] = array( 'key' => '_immo_region_district', 'value' => $district, 'compare' => '=' );
		}

		// Filter: Preisbereich.
		$price_min = $request->get_param( 'price_min' );
		$price_max = $request->get_param( 'price_max' );
		if ( $price_min !== null || $price_max !== null ) {
			$price_q = array( 'key' => '_immo_price', 'type' => 'NUMERIC' );
			if ( $price_min !== null ) { $price_q['value']   = (float) $price_min; $price_q['compare'] = '>='; }
			if ( $price_max !== null ) {
				if ( $price_min !== null ) {
					// Beides gesetzt → BETWEEN.
					$price_q['value']   = array( (float) $price_min, (float) $price_max );
					$price_q['compare'] = 'BETWEEN';
				} else {
					$price_q['value']   = (float) $price_max;
					$price_q['compare'] = '<=';
				}
			}
			$args['meta_query'][] = $price_q;
		}

		// Filter: Fläche.
		$area_min = $request->get_param( 'area_min' );
		$area_max = $request->get_param( 'area_max' );
		if ( $area_min !== null || $area_max !== null ) {
			$area_q = array( 'key' => '_immo_area', 'type' => 'NUMERIC' );
			if ( $area_min !== null && $area_max !== null ) {
				$area_q['value']   = array( (float) $area_min, (float) $area_max );
				$area_q['compare'] = 'BETWEEN';
			} elseif ( $area_min !== null ) {
				$area_q['value'] = (float) $area_min; $area_q['compare'] = '>=';
			} else {
				$area_q['value'] = (float) $area_max; $area_q['compare'] = '<=';
			}
			$args['meta_query'][] = $area_q;
		}

		// Filter: Zimmer.
		$rooms = $request->get_param( 'rooms' );
		if ( $rooms ) {
			$room_values = array_filter( array_map( 'absint', explode( ',', $rooms ) ) );
			if ( $room_values ) {
				$args['meta_query'][] = array( 'key' => '_immo_rooms', 'value' => array_values( $room_values ), 'compare' => 'IN', 'type' => 'NUMERIC' );
			}
		}

		// Filter: Energieklasse.
		$energy = $request->get_param( 'energy_class' );
		if ( $energy ) {
			$classes = array_filter( array_map( 'sanitize_text_field', explode( ',', $energy ) ) );
			if ( $classes ) {
				$args['meta_query'][] = array( 'key' => '_immo_energy_class', 'value' => array_values( $classes ), 'compare' => 'IN' );
			}
		}

		// Filter: Projekt-ID.
		$project_id = $request->get_param( 'project_id' );
		if ( $project_id ) {
			$args['meta_query'][] = array( 'key' => '_immo_project_id', 'value' => absint( $project_id ), 'compare' => '=', 'type' => 'NUMERIC' );
		}

		// Leere meta_query entfernen.
		if ( count( $args['meta_query'] ) === 1 ) {
			unset( $args['meta_query'] );
		}

		return $args;
	}

	// =========================================================================
	// Formatter – Property
	// =========================================================================

	/**
	 * WP_Post in API-Array konvertieren.
	 *
	 * PERFORMANCE: Alle Meta-Felder werden in EINEM get_post_meta()-Aufruf
	 * geladen (statt 38 Einzel-Calls). Das spart massiv DB-Queries bei
	 * Listen-Ansichten.
	 *
	 * @param \WP_Post $post Post.
	 * @param bool     $full Vollständige Daten (inkl. Galerie, Beschreibung)?
	 *
	 * @return array<string, mixed>
	 */
	public function format_property( \WP_Post $post, bool $full = false ): array {
		$id = $post->ID;

		// ALLE Meta-Felder auf einmal laden (1 Query statt 38!).
		$all_meta = get_post_meta( $id );
		$m        = function ( string $key, $default = '' ) use ( $all_meta ) {
			if ( ! isset( $all_meta[ $key ][0] ) ) {
				return $default;
			}
			$val = maybe_unserialize( $all_meta[ $key ][0] );
			return null === $val || '' === $val ? $default : $val;
		};

		// Preise.
		$price = (float) $m( '_immo_price', 0 );
		$rent  = (float) $m( '_immo_rent', 0 );

		// Features anreichern.
		$raw_features    = $m( '_immo_features', array() );
		$features        = is_array( $raw_features ) ? $raw_features : array();
		$features_detail = array();
		foreach ( $features as $fkey ) {
			$features_detail[] = array(
				'key'   => $fkey,
				'label' => Features::get_label( $fkey ),
				'icon'  => Features::get_icon( $fkey ),
			);
		}

		$documents = array();
		foreach ( (array) $m( '_immo_documents', array() ) as $doc_id ) {
			if ( $doc = $this->format_document( (int) $doc_id ) ) {
				$documents[] = $doc;
			}
		}

		$state_key    = (string) $m( '_immo_region_state', '' );
		$district_key = (string) $m( '_immo_region_district', '' );

		$project_id = (int) $m( '_immo_project_id', 0 );
		if ( ! $project_id ) {
			global $wpdb;
			$table = Database::units_table();
			$found_pid = $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM {$table} WHERE property_id = %d LIMIT 1", $id ) );
			if ( $found_pid ) {
				$project_id = (int) $found_pid;
			}
		}

		$project_data = null;
		if ( $project_id > 0 ) {
			$proj_post = get_post( $project_id );
			if ( $proj_post && 'publish' === $proj_post->post_status ) {
				$proj_meta   = get_post_meta( $project_id );
				$proj_img_id = get_post_thumbnail_id( $project_id );
				$proj_counts = Units::count_by_status( $project_id );
				
				$project_data = array(
					'id'              => $project_id,
					'title'           => get_the_title( $proj_post ),
					'permalink'       => get_permalink( $proj_post ),
					'image'           => $proj_img_id ? wp_get_attachment_image_url( $proj_img_id, 'medium_large' ) : '',
					'status'          => $proj_meta['_immo_project_status'][0] ?? '',
					'completion'      => $proj_meta['_immo_project_completion'][0] ?? '',
					'description'     => $proj_post->post_content,
					'total_units'     => array_sum( $proj_counts ),
					'available_units' => $proj_counts['available'] ?? 0,
				);
			}
		}

		$result = array(
			'id'             => $id,
			'title'          => get_the_title( $post ),
			'slug'           => $post->post_name,
			'permalink'      => get_permalink( $post ),
			'excerpt'        => wp_trim_words( get_the_excerpt( $post ), 25 ),
			'featured_image' => $this->format_image( get_post_thumbnail_id( $id ) ),
			'meta'           => array(
				'mode'                  => (string) $m( '_immo_mode', 'sale' ),
				'status'                => (string) $m( '_immo_status', 'available' ),
				'property_type'         => (string) $m( '_immo_property_type', '' ),
				'address'               => (string) $m( '_immo_address', '' ),
				'postal_code'           => (string) $m( '_immo_postal_code', '' ),
				'city'                  => (string) $m( '_immo_city', '' ),
				'region_state'          => $state_key,
				'region_state_label'    => Regions::get_state_label( $state_key ),
				'region_district'       => $district_key,
				'region_district_label' => Regions::get_district_label( $state_key, $district_key ),
				'lat'                   => (float) $m( '_immo_lat', 0 ),
				'lng'                   => (float) $m( '_immo_lng', 0 ),
				'area'                  => (float) $m( '_immo_area', 0 ),
				'usable_area'           => (float) $m( '_immo_usable_area', 0 ),
				'land_area'             => (float) $m( '_immo_land_area', 0 ),
				'rooms'                 => (int)   $m( '_immo_rooms', 0 ),
				'bedrooms'              => (int)   $m( '_immo_bedrooms', 0 ),
				'bathrooms'             => (int)   $m( '_immo_bathrooms', 0 ),
				'floor'                 => (int)   $m( '_immo_floor', 0 ),
				'total_floors'          => (int)   $m( '_immo_total_floors', 0 ),
				'built_year'            => (int)   $m( '_immo_built_year', 0 ),
				'renovation_year'       => (int)   $m( '_immo_renovation_year', 0 ),
				'energy_class'          => (string) $m( '_immo_energy_class', '' ),
				'energy_hwb'            => (float) $m( '_immo_energy_hwb', 0 ),
				'heating'               => (string) $m( '_immo_heating', '' ),
				'price'                 => $price,
				'price_formatted'       => $price > 0 ? $this->format_price( $price ) : null,
				'rent'                  => $rent,
				'rent_formatted'        => $rent > 0 ? $this->format_price( $rent ) : null,
				'operating_costs'       => (float) $m( '_immo_operating_costs', 0 ),
				'deposit'               => (float) $m( '_immo_deposit', 0 ),
				'commission'            => (string) $m( '_immo_commission', '' ),
				'available_from'        => (string) $m( '_immo_available_from', '' ),
				'features'              => $features,
				'features_detail'       => $features_detail,
				'custom_features'       => (string) $m( '_immo_custom_features', '' ),
				'documents'             => $documents,
				'contact_name'          => (string) $m( '_immo_contact_name', '' ),
				'contact_email'         => (string) $m( '_immo_contact_email', '' ),
				'contact_phone'         => (string) $m( '_immo_contact_phone', '' ),
				'contact_image'         => $this->format_image( (int) $m( '_immo_contact_image_id', 0 ) ),
				'project_id'            => $project_id,
				'project'               => $project_data,
			),
			'created_at'  => get_the_date( 'c', $post ),
			'modified_at' => get_the_modified_date( 'c', $post ),
		);

		if ( $full ) {
			$result['description'] = apply_filters( 'the_content', $post->post_content );
			$result['gallery']     = $this->format_gallery( $id );
		}

		return $result;
	}

	/**
	 * WP_Post (Projekt) in API-Array konvertieren – optimiert mit 1 Meta-Call.
	 *
	 * @param \WP_Post $post Post.
	 * @param bool     $full Vollständige Daten?
	 *
	 * @return array<string, mixed>
	 */
	public function format_project( \WP_Post $post, bool $full = false ): array {
		$id       = $post->ID;
		$all_meta = get_post_meta( $id );
		$m        = function ( string $key, $default = '' ) use ( $all_meta ) {
			if ( ! isset( $all_meta[ $key ][0] ) ) {
				return $default;
			}
			$val = maybe_unserialize( $all_meta[ $key ][0] );
			return null === $val || '' === $val ? $default : $val;
		};

		$documents = array();
		foreach ( (array) $m( '_immo_documents', array() ) as $doc_id ) {
			if ( $doc = $this->format_document( (int) $doc_id ) ) {
				$documents[] = $doc;
			}
		}

		$counts    = Units::count_by_status( $id );
		$state_key = (string) $m( '_immo_region_state', '' );

		$result = array(
			'id'             => $id,
			'title'          => get_the_title( $post ),
			'slug'           => $post->post_name,
			'permalink'      => get_permalink( $post ),
			'excerpt'        => wp_trim_words( get_the_excerpt( $post ), 25 ),
			'featured_image' => $this->format_image( get_post_thumbnail_id( $id ) ),
			'meta'           => array(
				'project_status'     => (string) $m( '_immo_project_status', '' ),
				'project_start_date' => (string) $m( '_immo_project_start_date', '' ),
				'project_completion' => (string) $m( '_immo_project_completion', '' ),
				'address'            => (string) $m( '_immo_address', '' ),
				'postal_code'        => (string) $m( '_immo_postal_code', '' ),
				'city'               => (string) $m( '_immo_city', '' ),
				'region_state'       => $state_key,
				'region_state_label' => Regions::get_state_label( $state_key ),
				'lat'                => (float)  $m( '_immo_lat', 0 ),
				'lng'                => (float)  $m( '_immo_lng', 0 ),
				'contact_name'       => (string) $m( '_immo_contact_name', '' ),
				'contact_email'      => (string) $m( '_immo_contact_email', '' ),
				'contact_phone'      => (string) $m( '_immo_contact_phone', '' ),
				'contact_image'      => $this->format_image( (int) $m( '_immo_contact_image_id', 0 ) ),
				'documents'          => $documents,
			),
			'unit_stats'  => array_merge( $counts, array( 'total' => array_sum( $counts ) ) ),
			'created_at'  => get_the_date( 'c', $post ),
			'modified_at' => get_the_modified_date( 'c', $post ),
		);

		if ( $full ) {
			$result['description'] = apply_filters( 'the_content', $post->post_content );
			$result['gallery']     = $this->format_gallery( $id );
		}

		return $result;
	}

	/**
	 * Unit-Array für API formatieren.
	 *
	 * @param array<string, mixed> $unit Raw Unit-Row.
	 *
	 * @return array<string, mixed>
	 */
	public function format_unit( array $unit ): array {
		$price = (float) $unit['price'];
		$rent  = (float) $unit['rent'];

		$status_labels = array(
			'available' => __( 'Verfügbar', 'immo-manager' ),
			'reserved'  => __( 'Reserviert', 'immo-manager' ),
			'sold'      => __( 'Verkauft', 'immo-manager' ),
			'rented'    => __( 'Vermietet', 'immo-manager' ),
		);

		$features        = is_array( $unit['features'] ) ? $unit['features'] : array();
		$features_detail = array();
		foreach ( $features as $fkey ) {
			$features_detail[] = array(
				'key'   => $fkey,
				'label' => Features::get_label( $fkey ),
				'icon'  => Features::get_icon( $fkey ),
			);
		}

		$property_data = null;
		if ( ! empty( $unit['property_id'] ) ) {
			$prop_post = get_post( $unit['property_id'] );
			if ( $prop_post && 'publish' === $prop_post->post_status ) {
				$prop_meta  = get_post_meta( $prop_post->ID );
				$img_id     = get_post_thumbnail_id( $prop_post->ID );
				$prop_price = (float) ( $prop_meta['_immo_price'][0] ?? 0 );
				$prop_rent  = (float) ( $prop_meta['_immo_rent'][0] ?? 0 );
				
				$property_data = array(
					'title'     => get_the_title( $prop_post ),
					'permalink' => get_permalink( $prop_post ),
					'image'     => $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '',
					'price'     => $prop_price > 0 ? $this->format_price( $prop_price ) : '',
					'rent'      => $prop_rent > 0 ? $this->format_price( $prop_rent ) : '',
					'area'      => (float) ( $prop_meta['_immo_area'][0] ?? 0 ),
					'rooms'     => (int) ( $prop_meta['_immo_rooms'][0] ?? 0 ),
					'type'      => (string) ( $prop_meta['_immo_property_type'][0] ?? '' ),
					'floor'     => isset( $prop_meta['_immo_floor'][0] ) ? (int) $prop_meta['_immo_floor'][0] : null,
					'built_year' => (int) ( $prop_meta['_immo_built_year'][0] ?? 0 ),
					'energy_class' => (string) ( $prop_meta['_immo_energy_class'][0] ?? '' ),
				);
			}
		}

		return array(
			'id'              => (int) $unit['id'],
			'project_id'      => (int) $unit['project_id'],
			'property'        => $property_data,
			'unit_number'     => $unit['unit_number'],
			'floor'           => (int) $unit['floor'],
			'area'            => (float) $unit['area'],
			'usable_area'     => (float) $unit['usable_area'],
			'rooms'           => (int) $unit['rooms'],
			'bedrooms'        => (int) $unit['bedrooms'],
			'bathrooms'       => (int) $unit['bathrooms'],
			'price'           => $price,
			'price_formatted' => $price > 0 ? $this->format_price( $price ) : null,
			'rent'            => $rent,
			'rent_formatted'  => $rent > 0 ? $this->format_price( $rent ) : null,
			'status'          => $unit['status'],
			'status_label'    => $status_labels[ $unit['status'] ] ?? $unit['status'],
			'features'        => $features,
			'features_detail' => $features_detail,
			'description'     => $unit['description'],
			'floor_plan'      => $unit['floor_plan_image_id'] ? $this->format_image( (int) $unit['floor_plan_image_id'] ) : null,
			'contact_email'   => $unit['contact_email'],
			'contact_phone'   => $unit['contact_phone'],
		);
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Preis formatieren.
	 *
	 * @param float $amount Betrag.
	 *
	 * @return string
	 */
	public function format_price( float $amount ): string {
		$symbol   = Settings::get( 'currency_symbol', '€' );
		$position = Settings::get( 'currency_position', 'after' );
		$decimals = (int) Settings::get( 'price_decimals', 0 );
		$thou     = Settings::get( 'thousands_separator', '.' );
		$dec      = Settings::get( 'decimal_separator', ',' );
		$formatted = number_format( $amount, $decimals, $dec, $thou );
		return 'before' === $position ? $symbol . ' ' . $formatted : $formatted . ' ' . $symbol;
	}

	/**
	 * Bild-Array für API aufbauen.
	 *
	 * @param int|false $attachment_id Attachment-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function format_image( $attachment_id ): ?array {
		if ( ! $attachment_id ) {
			return null;
		}
		$full      = wp_get_attachment_image_url( $attachment_id, 'full' );
		$medium    = wp_get_attachment_image_url( $attachment_id, 'medium_large' );
		$thumbnail = wp_get_attachment_image_url( $attachment_id, 'medium' );
		$alt       = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		return array(
			'url'           => $full ?: '',
			'url_medium'    => $medium ?: $full ?: '',
			'url_thumbnail' => $thumbnail ?: $medium ?: $full ?: '',
			'alt'           => (string) $alt,
		);
	}

	/**
	 * Dokument-Array für API aufbauen.
	 *
	 * @param int|false $attachment_id Attachment-ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function format_document( $attachment_id ): ?array {
		if ( ! $attachment_id ) return null;
		return array(
			'id'    => (int) $attachment_id,
			'url'   => wp_get_attachment_url( $attachment_id ) ?: '',
			'title' => get_the_title( $attachment_id ),
		);
	}

	/**
	 * Galerie-Array für eine Property aufbauen.
	 *
	 * @param int $post_id Post-ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function format_gallery( int $post_id ): array {
		$ids = get_post_meta( $post_id, '_immo_gallery', true );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return array();
		}
		$gallery = array();
		foreach ( $ids as $id ) {
			$img = $this->format_image( (int) $id );
			if ( $img ) {
				$gallery[] = $img;
			}
		}
		return $gallery;
	}

	/**
	 * REST-Args für Properties.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function properties_args(): array {
		return array(
			'page'            => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'per_page'        => array( 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 50 ),
			'orderby'         => array( 'type' => 'string', 'default' => 'newest', 'enum' => array( 'newest', 'price_asc', 'price_desc', 'area_desc' ) ),
			'status'          => array( 'type' => 'string' ),
			'mode'            => array( 'type' => 'string' ),
			'type'            => array( 'type' => 'string' ),
			'region_state'    => array( 'type' => 'string' ),
			'region_district' => array( 'type' => 'string' ),
			'price_min'       => array( 'type' => 'number' ),
			'price_max'       => array( 'type' => 'number' ),
			'area_min'        => array( 'type' => 'number' ),
			'area_max'        => array( 'type' => 'number' ),
			'rooms'           => array( 'type' => 'string' ),
			'energy_class'    => array( 'type' => 'string' ),
			'features'        => array( 'type' => 'string' ),
			'project_id'      => array( 'type' => 'integer' ),
		);
	}

	/**
	 * REST-Args für Inquiries.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function inquiry_args(): array {
		return array(
			'property_id'      => array( 'type' => 'integer', 'required' => true ),
			'unit_id'          => array( 'type' => array( 'integer', 'null' ) ),
			'inquirer_name'    => array( 'type' => 'string',  'required' => true ),
			'inquirer_email'   => array( 'type' => 'string',  'required' => true, 'format' => 'email' ),
			'inquirer_phone'   => array( 'type' => 'string' ),
			'inquirer_message' => array( 'type' => 'string' ),
			'consent'          => array( 'type' => 'boolean', 'required' => true ),
		);
	}

	/**
	 * Client-IP ermitteln (mit Proxy-Support).
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = trim( explode( ',', wp_unslash( $_SERVER[ $key ] ) )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	/**
	 * Admin-Benachrichtigung bei neuer Anfrage senden.
	 *
	 * @param int                  $inquiry_id Inquiry-ID.
	 * @param array<string, mixed> $data       Anfrage-Daten.
	 *
	 * @return void
	 */
	private function notify_admin( int $inquiry_id, array $data ): void {
		if ( ! Settings::get( 'admin_notifications', 1 ) ) {
			return;
		}
		$subject = sprintf( __( '[Immo Manager] Neue Anfrage #%d', 'immo-manager' ), $inquiry_id );

		$property_title = get_the_title( $data['property_id'] ?? 0 );
		$unit_info      = '';

		// Makler-E-Mail aus der Immobilie/dem Projekt auslesen
		$agent_email = get_post_meta( $data['property_id'] ?? 0, '_immo_contact_email', true );

		if ( ! empty( $data['unit_id'] ) ) {
			$unit = Units::get( (int) $data['unit_id'] );
			if ( $unit ) {
				$unit_info = sprintf( __( "Wohneinheit: %s (Etage %s)", 'immo-manager' ), $unit['unit_number'], $unit['floor'] );
				if ( ! empty( $unit['contact_email'] ) && is_email( $unit['contact_email'] ) ) {
					$agent_email = $unit['contact_email'];
				}
			}
		}

		// Wenn Agenten-E-Mail existiert, diese nutzen. Sonst Fallback auf globale Settings.
		$to = ( ! empty( $agent_email ) && is_email( $agent_email ) ) ? $agent_email : ( Settings::get( 'contact_email' ) ?: get_option( 'admin_email' ) );

		$date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) );

		$logo_id       = (int) Settings::get( 'email_logo_id', 0 );
		$logo_url      = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		$primary_color = Settings::get( 'primary_color', '#0073aa' );
		$bg_color      = '#f3f4f6';

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html( $subject ); ?></title>
		</head>
		<body style="background-color: <?php echo esc_attr( $bg_color ); ?>; margin: 0; padding: 30px 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111827; line-height: 1.6;">
			<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;">
				<?php if ( $logo_url ) : ?>
					<div style="padding: 25px 20px; text-align: left; border-bottom: 1px solid #f3f4f6; background: #ffffff;">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" style="max-height: 50px; max-width: 100%; height: auto; display: inline-block;">
					</div>
				<?php endif; ?>
				
				<div style="padding: 30px;">
					<h2 style="color: <?php echo esc_attr( $primary_color ); ?>; margin-top: 0; margin-bottom: 20px; font-size: 20px;"><?php esc_html_e( 'Neue Immobilien-Anfrage', 'immo-manager' ); ?></h2>
					
					<p style="margin: 0 0 20px;"><?php printf( esc_html__( 'Am %s wurde eine neue Anfrage eingereicht:', 'immo-manager' ), esc_html( $date ) ); ?></p>
					
					<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 15px;">
						<tr><th style="text-align: left; padding: 12px 10px 12px 0; border-bottom: 1px solid #f3f4f6; width: 35%; color: #6b7280;"><?php esc_html_e( 'Immobilie', 'immo-manager' ); ?></th><td style="padding: 12px 0 12px 10px; border-bottom: 1px solid #f3f4f6; font-weight: bold;"><a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . ( $data['property_id'] ?? 0 ) ) ); ?>" style="color: <?php echo esc_attr( $primary_color ); ?>; text-decoration: none;"><?php echo esc_html( $property_title ); ?> (#<?php echo (int) ( $data['property_id'] ?? 0 ); ?>)</a><?php echo $unit_info ? '<br><span style="font-size: 13px; color: #6b7280; font-weight: normal;">' . esc_html( $unit_info ) . '</span>' : ''; ?></td></tr>
						<tr><th style="text-align: left; padding: 12px 10px 12px 0; border-bottom: 1px solid #f3f4f6; color: #6b7280;"><?php esc_html_e( 'Name', 'immo-manager' ); ?></th><td style="padding: 12px 0 12px 10px; border-bottom: 1px solid #f3f4f6;"><?php echo esc_html( $data['inquirer_name'] ?? '' ); ?></td></tr>
						<tr><th style="text-align: left; padding: 12px 10px 12px 0; border-bottom: 1px solid #f3f4f6; color: #6b7280;"><?php esc_html_e( 'E-Mail', 'immo-manager' ); ?></th><td style="padding: 12px 0 12px 10px; border-bottom: 1px solid #f3f4f6;"><a href="mailto:<?php echo esc_attr( $data['inquirer_email'] ?? '' ); ?>" style="color: <?php echo esc_attr( $primary_color ); ?>; text-decoration: none;"><?php echo esc_html( $data['inquirer_email'] ?? '' ); ?></a></td></tr>
						<tr><th style="text-align: left; padding: 12px 10px 12px 0; border-bottom: 1px solid #f3f4f6; color: #6b7280;"><?php esc_html_e( 'Telefon', 'immo-manager' ); ?></th><td style="padding: 12px 0 12px 10px; border-bottom: 1px solid #f3f4f6;"><a href="tel:<?php echo esc_attr( $data['inquirer_phone'] ?? '' ); ?>" style="color: <?php echo esc_attr( $primary_color ); ?>; text-decoration: none;"><?php echo esc_html( $data['inquirer_phone'] ?? '–' ); ?></a></td></tr>
					</table>
					
					<h3 style="font-size: 16px; margin: 0 0 10px; color: #374151;"><?php esc_html_e( 'Nachricht:', 'immo-manager' ); ?></h3>
					<div style="background: #f9fafb; padding: 15px; border-radius: 6px; border: 1px solid #e5e7eb; font-size: 15px; white-space: pre-wrap; margin-bottom: 30px;"><?php echo esc_html( $data['inquirer_message'] ?? '–' ); ?></div>
					
					<div style="text-align: center;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=immo-inquiries' ) ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $primary_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px;"><?php esc_html_e( 'Alle Anfragen ansehen', 'immo-manager' ); ?></a>
					</div>
				</div>
				
				<div style="background: #f9fafb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb;">
					<?php printf( esc_html__( 'Diese E-Mail wurde automatisch vom %s gesendet.', 'immo-manager' ), '<a href="' . esc_url( site_url() ) . '" style="color: inherit; text-decoration: none;">' . esc_html( get_bloginfo( 'name' ) ) . '</a>' ); ?>
				</div>
			</div>
		</body>
		</html>
		<?php
		$body = ob_get_clean();

		// Filter setzen, um die E-Mail als HTML zu senden
		$set_html_content_type = function () {
			return 'text/html';
		};
		add_filter( 'wp_mail_content_type', $set_html_content_type );

		// Domain für die noreply-Adresse ermitteln
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( strpos( (string) $domain, 'www.' ) === 0 ) {
			$domain = substr( (string) $domain, 4 );
		}
		$noreply_email = 'noreply@' . $domain;
		$sender_name   = Settings::get( 'sender_name' ) ?: get_bloginfo( 'name' );

		$headers = array(
			'From: ' . $sender_name . ' <' . $noreply_email . '>',
			'Reply-To: ' . ( $data['inquirer_name'] ?? '' ) . ' <' . ( $data['inquirer_email'] ?? '' ) . '>',
		);

		wp_mail( $to, $subject, $body, $headers );

		// Filter wieder entfernen, damit andere E-Mails von WP nicht beeinträchtigt werden
		remove_filter( 'wp_mail_content_type', $set_html_content_type );
	}

	/**
	 * Auto-Responder E-Mail an den Interessenten senden.
	 *
	 * @param int                  $inquiry_id Inquiry-ID.
	 * @param array<string, mixed> $data       Anfrage-Daten.
	 *
	 * @return void
	 */
	private function notify_inquirer( int $inquiry_id, array $data ): void {
		$to = $data['inquirer_email'] ?? '';
		if ( empty( $to ) || ! is_email( $to ) ) {
			return;
		}

		$property_id    = $data['property_id'] ?? 0;
		$property_title = get_the_title( $property_id );
		$property_link  = get_permalink( $property_id );
		$unit_info      = '';

		if ( ! empty( $data['unit_id'] ) ) {
			$unit = Units::get( (int) $data['unit_id'] );
			if ( $unit ) {
				$unit_info = sprintf( __( "Wohneinheit: %s (Etage %s)", 'immo-manager' ), $unit['unit_number'], $unit['floor'] );
			}
		}

		$subject = sprintf( __( 'Ihre Anfrage zu %s', 'immo-manager' ), $property_title );

		$logo_id       = (int) Settings::get( 'email_logo_id', 0 );
		$logo_url      = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		$primary_color = Settings::get( 'primary_color', '#0073aa' );
		$bg_color      = '#f3f4f6';

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html( $subject ); ?></title>
		</head>
		<body style="background-color: <?php echo esc_attr( $bg_color ); ?>; margin: 0; padding: 30px 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111827; line-height: 1.6;">
			<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;">
				<?php if ( $logo_url ) : ?>
					<div style="padding: 25px 20px; text-align: left; border-bottom: 1px solid #f3f4f6; background: #ffffff;">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" style="max-height: 50px; max-width: 100%; height: auto; display: inline-block;">
					</div>
				<?php endif; ?>
				
				<div style="padding: 30px;">
					<h2 style="color: <?php echo esc_attr( $primary_color ); ?>; margin-top: 0; margin-bottom: 20px; font-size: 20px;"><?php esc_html_e( 'Vielen Dank für Ihre Anfrage!', 'immo-manager' ); ?></h2>
					
					<p style="margin: 0 0 20px;"><?php printf( esc_html__( 'Hallo %s,', 'immo-manager' ), esc_html( $data['inquirer_name'] ?? '' ) ); ?><br><br>
					<?php esc_html_e( 'wir haben Ihre Anfrage dankend erhalten und werden uns in Kürze bei Ihnen melden. Nachfolgend finden Sie eine Zusammenfassung Ihrer übermittelten Daten:', 'immo-manager' ); ?></p>
					
					<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 15px;">
						<tr><th style="text-align: left; padding: 12px 10px 12px 0; border-bottom: 1px solid #f3f4f6; width: 35%; color: #6b7280;"><?php esc_html_e( 'Immobilie', 'immo-manager' ); ?></th><td style="padding: 12px 0 12px 10px; border-bottom: 1px solid #f3f4f6; font-weight: bold;"><a href="<?php echo esc_url( $property_link ); ?>" style="color: <?php echo esc_attr( $primary_color ); ?>; text-decoration: none;"><?php echo esc_html( $property_title ); ?></a><?php echo $unit_info ? '<br><span style="font-size: 13px; color: #6b7280; font-weight: normal;">' . esc_html( $unit_info ) . '</span>' : ''; ?></td></tr>
						<tr><th style="text-align: left; padding: 12px 10px 12px 0; border-bottom: 1px solid #f3f4f6; color: #6b7280;"><?php esc_html_e( 'Name', 'immo-manager' ); ?></th><td style="padding: 12px 0 12px 10px; border-bottom: 1px solid #f3f4f6;"><?php echo esc_html( $data['inquirer_name'] ?? '' ); ?></td></tr>
						<tr><th style="text-align: left; padding: 12px 10px 12px 0; border-bottom: 1px solid #f3f4f6; color: #6b7280;"><?php esc_html_e( 'E-Mail', 'immo-manager' ); ?></th><td style="padding: 12px 0 12px 10px; border-bottom: 1px solid #f3f4f6;"><?php echo esc_html( $data['inquirer_email'] ?? '' ); ?></td></tr>
						<tr><th style="text-align: left; padding: 12px 10px 12px 0; border-bottom: 1px solid #f3f4f6; color: #6b7280;"><?php esc_html_e( 'Telefon', 'immo-manager' ); ?></th><td style="padding: 12px 0 12px 10px; border-bottom: 1px solid #f3f4f6;"><?php echo esc_html( $data['inquirer_phone'] ?? '–' ); ?></td></tr>
					</table>
					
					<?php if ( ! empty( $data['inquirer_message'] ) ) : ?>
					<h3 style="font-size: 16px; margin: 0 0 10px; color: #374151;"><?php esc_html_e( 'Ihre Nachricht:', 'immo-manager' ); ?></h3>
					<div style="background: #f9fafb; padding: 15px; border-radius: 6px; border: 1px solid #e5e7eb; font-size: 15px; white-space: pre-wrap; margin-bottom: 30px;"><?php echo esc_html( $data['inquirer_message'] ); ?></div>
					<?php endif; ?>
					
					<div style="text-align: center;">
						<a href="<?php echo esc_url( $property_link ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $primary_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px;"><?php esc_html_e( 'Zum Exposé', 'immo-manager' ); ?></a>
					</div>
				</div>
				
				<div style="background: #f9fafb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb;">
					<?php printf( esc_html__( 'Diese E-Mail wurde automatisch von %s gesendet.', 'immo-manager' ), '<a href="' . esc_url( site_url() ) . '" style="color: inherit; text-decoration: none;">' . esc_html( get_bloginfo( 'name' ) ) . '</a>' ); ?>
				</div>
			</div>
		</body>
		</html>
		<?php
		$body = ob_get_clean();

		// Filter setzen, um die E-Mail als HTML zu senden
		$set_html_content_type = function () {
			return 'text/html';
		};
		add_filter( 'wp_mail_content_type', $set_html_content_type );

		$sender_name = Settings::get( 'sender_name' ) ?: get_bloginfo( 'name' );
		$sender_email = Settings::get( 'contact_email' ) ?: get_option( 'admin_email' );

		// Reply-To auf die E-Mail Adresse des Maklers setzen
		$agent_email = get_post_meta( $property_id, '_immo_contact_email', true );
		if ( ! empty( $data['unit_id'] ) ) {
			$unit = Units::get( (int) $data['unit_id'] );
			if ( $unit && ! empty( $unit['contact_email'] ) && is_email( $unit['contact_email'] ) ) {
				$agent_email = $unit['contact_email'];
			}
		}

		$headers = array(
			'From: ' . $sender_name . ' <' . $sender_email . '>',
		);
		
		if ( ! empty( $agent_email ) && is_email( $agent_email ) ) {
			$headers[] = 'Reply-To: ' . $agent_email;
		}

		wp_mail( $to, $subject, $body, $headers );

		remove_filter( 'wp_mail_content_type', $set_html_content_type );
	}
}
