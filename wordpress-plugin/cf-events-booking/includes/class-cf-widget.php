<?php
/**
 * Widget sidebar « Prochains événements CF ».
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'cf_widget_prochains_evenements',
			'Prochains événements CF',
			[
				'description'                 => 'Affiche les prochains événements CF dans la barre latérale.',
				'customize_selective_refresh' => true,
			]
		);
	}

	/* ── Formulaire admin ─────────────────────────────────────── */
	public function form( $instance ) {
		$title      = ! empty( $instance['title'] )      ? $instance['title']      : 'Prochains événements';
		$nombre     = ! empty( $instance['nombre'] )     ? (int) $instance['nombre'] : 5;
		$categorie  = ! empty( $instance['categorie'] )  ? $instance['categorie']  : '';
		$show_image = isset( $instance['show_image'] )   ? (bool) $instance['show_image'] : false;
		$show_date  = isset( $instance['show_date'] )    ? (bool) $instance['show_date']  : true;
		$show_lieu  = isset( $instance['show_lieu'] )    ? (bool) $instance['show_lieu']  : true;

		$categories = get_terms( [ 'taxonomy' => CFEB_TAX, 'hide_empty' => false ] );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Titre :</label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'nombre' ) ); ?>">Nombre d'événements (1-20) :</label>
			<input class="tiny-text" type="number"
				id="<?php echo esc_attr( $this->get_field_id( 'nombre' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'nombre' ) ); ?>"
				value="<?php echo esc_attr( $nombre ); ?>"
				min="1" max="20" step="1" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'categorie' ) ); ?>">Catégorie :</label>
			<select class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'categorie' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'categorie' ) ); ?>">
				<option value="">— Toutes —</option>
				<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $categorie, $cat->slug ); ?>><?php echo esc_html( $cat->name ); ?></option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
		</p>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_image' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_image' ) ); ?>"
				value="1" <?php checked( $show_image ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_image' ) ); ?>">Afficher la vignette</label>
		</p>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_date' ) ); ?>"
				value="1" <?php checked( $show_date ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>">Afficher la date</label>
		</p>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_lieu' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_lieu' ) ); ?>"
				value="1" <?php checked( $show_lieu ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_lieu' ) ); ?>">Afficher le lieu</label>
		</p>
		<?php
	}

	/* ── Sanitisation ─────────────────────────────────────────── */
	public function update( $new_instance, $old_instance ) {
		$instance               = [];
		$instance['title']      = sanitize_text_field( $new_instance['title'] ?? '' );
		$instance['nombre']     = min( 20, max( 1, absint( $new_instance['nombre'] ?? 5 ) ) );
		$instance['categorie']  = sanitize_key( $new_instance['categorie'] ?? '' );
		$instance['show_image'] = ! empty( $new_instance['show_image'] ) ? 1 : 0;
		$instance['show_date']  = ! empty( $new_instance['show_date'] )  ? 1 : 0;
		$instance['show_lieu']  = ! empty( $new_instance['show_lieu'] )  ? 1 : 0;
		return $instance;
	}

	/* ── Affichage frontend ───────────────────────────────────── */
	public function widget( $args, $instance ) {
		$title      = apply_filters( 'widget_title', $instance['title'] ?? 'Prochains événements' );
		$nombre     = min( 20, max( 1, (int) ( $instance['nombre'] ?? 5 ) ) );
		$categorie  = $instance['categorie'] ?? '';
		$show_image = ! empty( $instance['show_image'] );
		$show_date  = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : true;
		$show_lieu  = isset( $instance['show_lieu'] )  ? (bool) $instance['show_lieu']  : true;

		$query_args = [
			'post_type'      => CFEB_SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => $nombre,
			'orderby'        => 'meta_value',
			'meta_key'       => '_cfeb_date_debut',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => [ [
				'key'     => '_cfeb_date_debut',
				'value'   => current_time( 'mysql' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			] ],
		];

		if ( $categorie ) {
			$query_args['tax_query'] = [ [
				'taxonomy' => CFEB_TAX,
				'field'    => 'slug',
				'terms'    => $categorie,
			] ];
		}

		$q = new WP_Query( $query_args );

		if ( ! $q->have_posts() ) {
			return;
		}

		echo wp_kses_post( $args['before_widget'] );

		if ( $title ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}

		echo '<ul class="cfeb-widget-list">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$meta     = CF_CPT::get_meta( get_the_ID() );
			$date_ts  = $meta['date_debut'] ? strtotime( $meta['date_debut'] ) : 0;
			$lieu     = $meta['lieu'] ?: ( $meta['lien_visio'] ? 'En ligne' : '' );
			echo '<li class="cfeb-widget-item">';
			if ( $show_image && has_post_thumbnail() ) {
				echo '<a href="' . esc_url( get_permalink() ) . '" class="cfeb-widget-thumb">';
				the_post_thumbnail( 'thumbnail', [ 'alt' => esc_attr( get_the_title() ) ] );
				echo '</a>';
			}
			echo '<div class="cfeb-widget-content">';
			echo '<a href="' . esc_url( get_permalink() ) . '" class="cfeb-widget-title">' . esc_html( get_the_title() ) . '</a>';
			if ( $show_date && $date_ts ) {
				echo '<span class="cfeb-widget-date">📅 ' . esc_html( date_i18n( 'j F Y', $date_ts ) ) . '</span>';
			}
			if ( $show_lieu && $lieu ) {
				echo '<span class="cfeb-widget-lieu">📍 ' . esc_html( $lieu ) . '</span>';
			}
			echo '</div>';
			echo '</li>';
		}
		echo '</ul>';

		wp_reset_postdata();

		echo wp_kses_post( $args['after_widget'] );
	}

	/* ── Enregistrement ───────────────────────────────────────── */
	public static function register() {
		register_widget( 'CF_Widget' );
	}
}
