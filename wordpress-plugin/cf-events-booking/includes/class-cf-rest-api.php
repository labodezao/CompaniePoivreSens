<?php
/**
 * REST API publique — namespace cfeb/v1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_RestApi {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		$ns = 'cfeb/v1';

		register_rest_route( $ns, '/events', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_events' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'per_page'   => [ 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100 ],
				'page'       => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
				'categorie'  => [ 'type' => 'string',  'default' => '' ],
				'tag'        => [ 'type' => 'string',  'default' => '' ],
				'featured'   => [ 'type' => 'string',  'default' => '' ],
				'passe'      => [ 'type' => 'boolean', 'default' => false ],
				'date_debut' => [ 'type' => 'string',  'default' => '' ],
				'date_fin'   => [ 'type' => 'string',  'default' => '' ],
			],
		] );

		register_rest_route( $ns, '/events/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_event' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [ 'type' => 'integer', 'required' => true ],
			],
		] );

		register_rest_route( $ns, '/venues', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_venues' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
				'page'     => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
			],
		] );

		register_rest_route( $ns, '/venues/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_venue' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [ 'type' => 'integer', 'required' => true ],
			],
		] );
	}

	/* ── Liste événements ─────────────────────────────────────── */
	public static function get_events( WP_REST_Request $request ) {
		$per_page   = (int) $request->get_param( 'per_page' );
		$page       = (int) $request->get_param( 'page' );
		$categorie  = sanitize_text_field( $request->get_param( 'categorie' ) );
		$tag        = sanitize_text_field( $request->get_param( 'tag' ) );
		$featured   = $request->get_param( 'featured' );
		$passe      = (bool) $request->get_param( 'passe' );
		$date_debut = sanitize_text_field( $request->get_param( 'date_debut' ) );
		$date_fin   = sanitize_text_field( $request->get_param( 'date_fin' ) );

		$now = current_time( 'mysql' );

		$query_args = [
			'post_type'      => CFEB_SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'meta_value',
			'meta_key'       => '_cfeb_date_debut',
			'order'          => $passe ? 'DESC' : 'ASC',
		];

		if ( $date_debut && $date_fin ) {
			$query_args['meta_query'] = [ [
				'key'     => '_cfeb_date_debut',
				'value'   => [ $date_debut, $date_fin ],
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			] ];
		} elseif ( $passe ) {
			$query_args['meta_query'] = [ [
				'key'     => '_cfeb_date_debut',
				'value'   => $now,
				'compare' => '<',
				'type'    => 'DATETIME',
			] ];
		} else {
			$query_args['meta_query'] = [ [
				'key'     => '_cfeb_date_debut',
				'value'   => $now,
				'compare' => '>=',
				'type'    => 'DATETIME',
			] ];
		}

		$tax_query = [];
		if ( $categorie ) {
			$tax_query[] = [
				'taxonomy' => CFEB_TAX,
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_key', explode( ',', $categorie ) ),
			];
		}
		if ( $tag ) {
			$tax_query[] = [
				'taxonomy' => 'cf_event_tag',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_key', explode( ',', $tag ) ),
			];
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( $tax_query ) {
			$query_args['tax_query'] = $tax_query;
		}

		if ( '1' === (string) $featured ) {
			$query_args['meta_query'][] = [ 'key' => '_cfeb_featured', 'value' => '1' ];
			$query_args['meta_query']['relation'] = 'AND';
		}

		$q     = new WP_Query( $query_args );
		$posts = $q->posts;

		$data = [];
		foreach ( $posts as $post ) {
			$data[] = self::format_event( $post );
		}

		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'X-WP-Total',      $q->found_posts );
		$response->header( 'X-WP-TotalPages', $q->max_num_pages );
		return $response;
	}

	/* ── Événement unique ─────────────────────────────────────── */
	public static function get_event( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || 'publish' !== $post->post_status || CFEB_SLUG !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Événement introuvable.', [ 'status' => 404 ] );
		}
		return new WP_REST_Response( self::format_event( $post ), 200 );
	}

	/* ── Liste lieux ─────────────────────────────────────────── */
	public static function get_venues( WP_REST_Request $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );

		$q = new WP_Query( [
			'post_type'      => 'cf_venue',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$data = [];
		foreach ( $q->posts as $post ) {
			$data[] = self::format_venue( $post );
		}

		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'X-WP-Total',      $q->found_posts );
		$response->header( 'X-WP-TotalPages', $q->max_num_pages );
		return $response;
	}

	/* ── Lieu unique ─────────────────────────────────────────── */
	public static function get_venue( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || 'publish' !== $post->post_status || 'cf_venue' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Lieu introuvable.', [ 'status' => 404 ] );
		}
		return new WP_REST_Response( self::format_venue( $post ), 200 );
	}

	/* ── Formater un événement ────────────────────────────────── */
	private static function format_event( WP_Post $post ) {
		$meta   = CF_CPT::get_meta( $post->ID );
		$dispo  = CF_CPT::get_dispo( $post->ID );
		$statut = CF_CPT::compute_statut( $post->ID );

		// Catégories
		$cats = [];
		$terms_cat = get_the_terms( $post->ID, CFEB_TAX );
		if ( $terms_cat && ! is_wp_error( $terms_cat ) ) {
			foreach ( $terms_cat as $t ) {
				$cats[] = [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ];
			}
		}

		// Tags
		$tags = [];
		$terms_tag = get_the_terms( $post->ID, 'cf_event_tag' );
		if ( $terms_tag && ! is_wp_error( $terms_tag ) ) {
			foreach ( $terms_tag as $t ) {
				$tags[] = [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ];
			}
		}

		// Lieu (venue)
		$venue = null;
		$venue_id = (int) ( $meta['venue_id'] ?? 0 );
		if ( $venue_id ) {
			$v = get_post( $venue_id );
			if ( $v && 'cf_venue' === $v->post_type ) {
				$venue = self::format_venue( $v );
			}
		}

		// Thumbnail
		$thumbnail = '';
		if ( has_post_thumbnail( $post->ID ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'medium' );
			if ( $src ) {
				$thumbnail = $src[0];
			}
		}

		return [
			'id'                   => $post->ID,
			'title'                => $post->post_title,
			'slug'                 => $post->post_name,
			'url'                  => get_permalink( $post->ID ),
			'status'               => $post->post_status,
			'start_date'           => $meta['date_debut'],
			'end_date'             => $meta['date_fin'],
			'all_day'              => (bool) $meta['all_day'],
			'lieu'                 => $meta['lieu'],
			'animateur'            => $meta['animateur'],
			'prix'                 => (float) $meta['prix'],
			'max_places'           => (int) $meta['max_places'],
			'places_disponibles'   => PHP_INT_MAX === $dispo ? -1 : (int) $dispo,
			'statut_reservations'  => $statut,
			'statut_event'         => $meta['statut_event'],
			'featured'             => (bool) $meta['featured'],
			'lien_visio'           => $meta['lien_visio'],
			'event_url'            => $meta['event_url'],
			'categories'           => $cats,
			'tags'                 => $tags,
			'venue'                => $venue,
			'thumbnail_url'        => $thumbnail,
		];
	}

	/* ── Formater un lieu ─────────────────────────────────────── */
	private static function format_venue( WP_Post $post ) {
		$thumbnail = '';
		if ( has_post_thumbnail( $post->ID ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'medium' );
			if ( $src ) {
				$thumbnail = $src[0];
			}
		}

		return [
			'id'           => $post->ID,
			'title'        => $post->post_title,
			'slug'         => $post->post_name,
			'url'          => get_permalink( $post->ID ),
			'description'  => wp_strip_all_tags( $post->post_content ),
			'adresse'      => get_post_meta( $post->ID, '_cfeb_adresse', true ),
			'ville'        => get_post_meta( $post->ID, '_cfeb_ville', true ),
			'code_postal'  => get_post_meta( $post->ID, '_cfeb_code_postal', true ),
			'pays'         => get_post_meta( $post->ID, '_cfeb_pays', true ),
			'telephone'    => get_post_meta( $post->ID, '_cfeb_telephone', true ),
			'site_web'     => get_post_meta( $post->ID, '_cfeb_site_web', true ),
			'lien_maps'    => get_post_meta( $post->ID, '_cfeb_lien_maps', true ),
			'thumbnail_url'=> $thumbnail,
		];
	}
}
