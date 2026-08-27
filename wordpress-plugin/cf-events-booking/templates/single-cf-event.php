<?php
/**
 * Gabarit page single d'un événement cf_event.
 *
 * Le thème peut surcharger ce gabarit en créant
 * wp-content/themes/[votre-theme]/single-cf_event.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); ?>

<div id="primary" class="content-area">
<main id="main" class="site-main">

<?php while ( have_posts() ) : the_post(); ?>

<?php
$m      = CF_CPT::get_meta( get_the_ID() );
$dispo  = CF_CPT::get_dispo( get_the_ID() );
$max    = (int) $m['max_places'];
$opts   = CF_Admin::get_options();
$statut = CF_CPT::compute_statut( get_the_ID() );

$prix       = (float) $m['prix'];
$date_debut = $m['date_debut'] ? strtotime( $m['date_debut'] ) : 0;
$date_fin   = $m['date_fin']   ? strtotime( $m['date_fin'] )   : 0;

$data_json = htmlspecialchars( wp_json_encode( [
	'id'     => get_the_ID(),
	'titre'  => get_the_title(),
	'date'   => $date_debut ? date_i18n( 'l j F Y à H\hi', $date_debut ) : '',
	'lieu'   => $m['lieu'],
	'prix'      => $prix > 0 ? number_format( $prix, 2, ',', ' ' ) . ' €' : 'Gratuit',
	'prix_brut' => $prix,
	'dispo'  => PHP_INT_MAX === $dispo ? -1 : $dispo,
	'max'    => $max,
	'statut' => $statut,
	'visio'  => (bool) $m['lien_visio'],
] ), ENT_QUOTES, 'UTF-8' );

$cats = get_the_terms( get_the_ID(), CFEB_TAX );
?>

<article <?php post_class( 'cfeb-single-event' ); ?>>

	<header class="cfeb-event-header">
		<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
			<div class="cfeb-card-cats" style="margin-bottom:10px;">
				<?php foreach ( $cats as $cat ) : ?>
					<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="cfeb-cat"><?php echo esc_html( $cat->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<h1><?php the_title(); ?></h1>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<div style="margin-bottom:24px;border-radius:10px;overflow:hidden;">
			<?php the_post_thumbnail( 'large', [ 'style' => 'width:100%;max-height:380px;object-fit:cover;' ] ); ?>
		</div>
	<?php endif; ?>

	<!-- Infos pratiques en grille -->
	<div class="cfeb-event-infos">
		<?php if ( $date_debut ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">📅</span>
				<div>
					<div class="cfeb-info-label">Date</div>
					<div class="cfeb-info-val">
						<?php echo esc_html( date_i18n( 'l j F Y', $date_debut ) ); ?><br>
						<?php echo esc_html( date_i18n( 'H\hi', $date_debut ) ); ?>
						<?php if ( $date_fin ) : ?>
							– <?php echo esc_html( date_i18n( 'H\hi', $date_fin ) ); ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $m['lieu'] ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">📍</span>
				<div>
					<div class="cfeb-info-label">Lieu</div>
					<div class="cfeb-info-val"><?php echo esc_html( $m['lieu'] ); ?></div>
				</div>
			</div>
		<?php elseif ( $m['lien_visio'] ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">💻</span>
				<div>
					<div class="cfeb-info-label">Format</div>
					<div class="cfeb-info-val">En ligne (lien envoyé après inscription)</div>
				</div>
			</div>
		<?php endif; ?>

		<div class="cfeb-info-item">
			<span class="cfeb-info-icon">💶</span>
			<div>
				<div class="cfeb-info-label">Tarif</div>
				<div class="cfeb-info-val">
					<?php echo $prix > 0 ? esc_html( number_format( $prix, 2, ',', ' ' ) . ' €' ) : 'Gratuit'; ?>
				</div>
			</div>
		</div>

		<?php if ( $max > 0 ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">👥</span>
				<div>
					<div class="cfeb-info-label">Places</div>
					<div class="cfeb-info-val">
						<?php if ( 'ouvert' === $statut && $dispo > 0 ) : ?>
							<?php echo (int) $dispo; ?> / <?php echo (int) $max; ?> disponible(s)
						<?php elseif ( 'complet' === $statut ) : ?>
							🔴 Complet
						<?php else : ?>
							<?php echo (int) $max; ?> places
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $m['animateur'] ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">🧘</span>
				<div>
					<div class="cfeb-info-label">Animateur</div>
					<div class="cfeb-info-val"><?php echo esc_html( $m['animateur'] ); ?></div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $m['deadline'] ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">⏰</span>
				<div>
					<div class="cfeb-info-label">Inscription avant le</div>
					<div class="cfeb-info-val"><?php echo esc_html( date_i18n( 'j F Y', strtotime( $m['deadline'] ) ) ); ?></div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Description -->
	<div class="cfeb-event-description">
		<?php the_content(); ?>
	</div>

	<!-- Informations pratiques -->
	<?php if ( $m['infos_pratiques'] ) : ?>
		<div class="cfeb-event-infos-pratiques">
			<strong>ℹ️ Informations pratiques</strong><br>
			<?php echo esc_html( $m['infos_pratiques'] ); ?>
		</div>
	<?php endif; ?>

	<!-- Section réservation -->
	<div class="cfeb-booking-section">
		<?php if ( 'ouvert' === $statut ) : ?>
			<h3>✅ Réserver votre place</h3>
			<p>
				<?php if ( $dispo > 0 && $max > 0 ) : ?>
					Plus que <?php echo (int) $dispo; ?> place(s) disponible(s) !
				<?php elseif ( $prix > 0 ) : ?>
					<?php echo esc_html( number_format( $prix, 2, ',', ' ' ) ); ?> € par personne
				<?php else : ?>
					Cet événement est gratuit. Réservez votre place maintenant.
				<?php endif; ?>
			</p>
			<button class="cfeb-btn cfeb-btn-primary cfeb-open-modal" data-event='<?php echo $data_json; // phpcs:ignore ?>'>
				✅ Je réserve ma place →
			</button>

		<?php elseif ( 'complet' === $statut && $opts['liste_attente'] ) : ?>
			<h3>⏳ Événement complet</h3>
			<p>Inscrivez-vous sur la liste d'attente, nous vous contacterons si une place se libère.</p>
			<button class="cfeb-btn cfeb-btn-primary cfeb-open-modal" data-event='<?php echo $data_json; // phpcs:ignore ?>'>
				⏳ Liste d'attente →
			</button>

		<?php elseif ( 'complet' === $statut ) : ?>
			<h3>🔴 Événement complet</h3>
			<p>Toutes les places ont été réservées. Suivez nos réseaux pour les prochaines sessions.</p>

		<?php else : ?>
			<h3>🔒 Inscriptions fermées</h3>
			<p>Les inscriptions pour cet événement sont terminées.</p>

		<?php endif; ?>

		<?php if ( $m['email_contact'] ) : ?>
			<p style="margin-top:12px;opacity:.75;font-size:13px;">
				Questions ? <a href="mailto:<?php echo esc_attr( $m['email_contact'] ); ?>" style="color:#fff;"><?php echo esc_html( $m['email_contact'] ); ?></a>
			</p>
		<?php endif; ?>
	</div>

</article>

<?php endwhile; ?>

<?php
// Navigation précédent / suivant (basée sur _cfeb_date_debut)
$current_date = get_post_meta( get_the_ID(), '_cfeb_date_debut', true );

$prev_event = null;
$next_event = null;

if ( $current_date ) {
	$prev_q = new WP_Query( [
		'post_type'      => CFEB_SLUG,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'meta_value',
		'meta_key'       => '_cfeb_date_debut',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		'post__not_in'   => [ get_the_ID() ],
		'meta_query'     => [ [
			'key'     => '_cfeb_date_debut',
			'value'   => $current_date,
			'compare' => '<',
			'type'    => 'DATETIME',
		] ],
	] );
	if ( $prev_q->have_posts() ) {
		$prev_event = $prev_q->posts[0];
	}
	wp_reset_postdata();

	$next_q = new WP_Query( [
		'post_type'      => CFEB_SLUG,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'meta_value',
		'meta_key'       => '_cfeb_date_debut',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'post__not_in'   => [ get_the_ID() ],
		'meta_query'     => [ [
			'key'     => '_cfeb_date_debut',
			'value'   => $current_date,
			'compare' => '>',
			'type'    => 'DATETIME',
		] ],
	] );
	if ( $next_q->have_posts() ) {
		$next_event = $next_q->posts[0];
	}
	wp_reset_postdata();
}

if ( $prev_event || $next_event ) : ?>
<nav class="cfeb-event-nav" style="display:flex;justify-content:space-between;align-items:center;margin:32px 0;padding:16px;background:#f9fafb;border-radius:8px;gap:12px;flex-wrap:wrap;">
	<?php if ( $prev_event ) : ?>
		<a href="<?php echo esc_url( get_permalink( $prev_event->ID ) ); ?>" class="cfeb-nav-prev" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:#2271b1;max-width:48%;">
			<span style="font-size:20px;">←</span>
			<span style="font-size:13px;line-height:1.4;">
				<span style="display:block;color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Événement précédent</span>
				<?php echo esc_html( $prev_event->post_title ); ?>
			</span>
		</a>
	<?php else : ?>
		<span></span>
	<?php endif; ?>

	<?php if ( $next_event ) : ?>
		<a href="<?php echo esc_url( get_permalink( $next_event->ID ) ); ?>" class="cfeb-nav-next" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:#2271b1;max-width:48%;text-align:right;flex-direction:row-reverse;">
			<span style="font-size:20px;">→</span>
			<span style="font-size:13px;line-height:1.4;">
				<span style="display:block;color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Événement suivant</span>
				<?php echo esc_html( $next_event->post_title ); ?>
			</span>
		</a>
	<?php else : ?>
		<span></span>
	<?php endif; ?>
</nav>
<?php endif; ?>

</main>
</div>

<?php echo CF_Frontend::modal_html(); // phpcs:ignore ?>

<?php get_footer(); ?>
