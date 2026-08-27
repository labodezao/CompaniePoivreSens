<?php
/**
 * Open Graph & Twitter Card meta tags pour les pages d'événements.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_OpenGraph {

	public static function init() {
		add_action( 'wp_head', [ __CLASS__, 'output' ], 5 );
	}

	public static function output() {
		if ( ! is_singular( CFEB_SLUG ) ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		$meta        = CF_CPT::get_meta( $post->ID );
		$title       = get_the_title( $post );
		$site_name   = get_bloginfo( 'name' );
		$url         = get_permalink( $post->ID );
		$modified    = get_the_modified_date( 'c', $post );
		$published   = get_the_date( 'c', $post );

		// Description : extrait ou contenu tronqué
		if ( $post->post_excerpt ) {
			$description = wp_strip_all_tags( $post->post_excerpt );
		} else {
			$description = wp_strip_all_tags( $post->post_content );
		}
		$description = wp_trim_words( $description, 30, '…' );
		$description = mb_substr( $description, 0, 160 );

		// Image : featured image
		$image = '';
		if ( has_post_thumbnail( $post->ID ) ) {
			$img_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'large' );
			if ( $img_src ) {
				$image = $img_src[0];
			}
		}

		echo "\n<!-- CF Événements : Open Graph -->\n";
		echo '<meta property="og:type" content="event" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		}
		echo '<meta property="article:published_time" content="' . esc_attr( $published ) . '" />' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( $modified ) . '" />' . "\n";
		if ( $meta['date_debut'] ) {
			echo '<meta property="event:start_time" content="' . esc_attr( $meta['date_debut'] ) . '" />' . "\n";
		}

		// Twitter Card
		echo "\n<!-- CF Événements : Twitter Card -->\n";
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
		if ( $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
		}
		echo "\n";
	}
}
