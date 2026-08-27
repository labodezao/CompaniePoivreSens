<?php
/**
 * Gabarit page single d'un lieu (cf_venue).
 *
 * Le thème peut surcharger ce gabarit en créant
 * wp-content/themes/[votre-theme]/single-cf_venue.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! wp_style_is( 'cfeb-frontend', 'enqueued' ) ) {
	wp_enqueue_style( 'cfeb-frontend', CFEB_URL . 'assets/css/frontend.css', [], CFEB_VERSION );
}
if ( ! wp_script_is( 'cfeb-frontend', 'enqueued' ) ) {
	wp_enqueue_script( 'cfeb-frontend', CFEB_URL . 'assets/js/frontend.js', [], CFEB_VERSION, true );
	wp_localize_script( 'cfeb-frontend', 'cfeb', [
		'ajaxurl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'cfeb_front' ),
		'tel_requis' => (int) CF_Admin::get_options()['tel_obligatoire'],
		'l10n'       => [
			'reserver'      => 'Réserver',
			'confirmer'     => 'Confirmer ma réservation →',
			'en_cours'      => 'Traitement…',
			'places_dispo'  => 'place(s) disponible(s)',
			'complet'       => 'Complet',
			'confirme'      => '✅ Réservation confirmée !',
			'email_envoye'  => 'Un email de confirmation a été envoyé à',
			'erreur'        => 'Une erreur est survenue. Veuillez réessayer.',
			'deja_reserve'  => 'Vous avez déjà réservé cet événement.',
			'liste_attente' => '⏳ Vous avez été ajouté·e à la liste d\'attente.',
			'ferme'         => 'Inscriptions fermées',
			'gratuit'       => 'Gratuit',
		],
	] );
}

get_header(); ?>

<div id="primary" class="content-area">
<main id="main" class="site-main">

<?php while ( have_posts() ) : the_post(); ?>

<?php
$venue_id    = get_the_ID();
$adresse     = get_post_meta( $venue_id, '_cfeb_adresse', true );
$ville       = get_post_meta( $venue_id, '_cfeb_ville', true );
$code_postal = get_post_meta( $venue_id, '_cfeb_code_postal', true );
$pays        = get_post_meta( $venue_id, '_cfeb_pays', true );
$telephone   = get_post_meta( $venue_id, '_cfeb_telephone', true );
$site_web    = get_post_meta( $venue_id, '_cfeb_site_web', true );
$lien_maps   = get_post_meta( $venue_id, '_cfeb_lien_maps', true );
$opts        = CF_Admin::get_options();
?>

<article <?php post_class( 'cfeb-single-venue' ); ?>>

	<header class="cfeb-event-header">
		<h1><?php the_title(); ?></h1>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<div style="margin-bottom:24px;border-radius:10px;overflow:hidden;">
			<?php the_post_thumbnail( 'full', [ 'style' => 'width:100%;max-height:420px;object-fit:cover;', 'alt' => esc_attr( get_the_title() ) ] ); ?>
		</div>
	<?php endif; ?>

	<!-- Description -->
	<?php if ( get_the_content() ) : ?>
		<div class="cfeb-event-description" style="margin-bottom:28px;">
			<?php the_content(); ?>
		</div>
	<?php endif; ?>

	<!-- Infos du lieu en grille -->
	<div class="cfeb-event-infos" style="margin-bottom:28px;">
		<?php if ( $adresse ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">🏠</span>
				<div>
					<div class="cfeb-info-label">Adresse</div>
					<div class="cfeb-info-val"><?php echo esc_html( $adresse ); ?></div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $ville || $code_postal ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">🏙️</span>
				<div>
					<div class="cfeb-info-label">Ville</div>
					<div class="cfeb-info-val">
						<?php echo esc_html( trim( $code_postal . ' ' . $ville ) ); ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $pays ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">🌍</span>
				<div>
					<div class="cfeb-info-label">Pays</div>
					<div class="cfeb-info-val"><?php echo esc_html( $pays ); ?></div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $telephone ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">📞</span>
				<div>
					<div class="cfeb-info-label">Téléphone</div>
					<div class="cfeb-info-val"><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $telephone ) ); ?>"><?php echo esc_html( $telephone ); ?></a></div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $site_web ) : ?>
			<div class="cfeb-info-item">
				<span class="cfeb-info-icon">🌐</span>
				<div>
					<div class="cfeb-info-label">Site web</div>
					<div class="cfeb-info-val"><a href="<?php echo esc_url( $site_web ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $site_web ); ?></a></div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Google Maps embed -->
	<?php if ( $lien_maps ) : ?>
		<div class="cfeb-venue-map" style="margin-bottom:32px;border-radius:10px;overflow:hidden;">
			<iframe
				src="<?php echo esc_url( $lien_maps ); ?>"
				width="100%"
				height="400"
				style="border:0;display:block;"
				allowfullscreen=""
				loading="lazy"
				referrerpolicy="no-referrer-when-downgrade"
				title="Carte — <?php echo esc_attr( get_the_title() ); ?>">
			</iframe>
		</div>
	<?php elseif ( $adresse || $ville ) :
		$adresse_complete = trim( implode( ', ', array_filter( [ $adresse, $code_postal, $ville, $pays ] ) ) );
		if ( $adresse_complete ) :
		$maps_embed = 'https://www.google.com/maps?q=' . rawurlencode( $adresse_complete ) . '&output=embed';
		?>
		<div class="cfeb-venue-map" style="margin-bottom:32px;border-radius:10px;overflow:hidden;">
			<iframe
				src="<?php echo esc_url( $maps_embed ); ?>"
				width="100%"
				height="400"
				style="border:0;display:block;"
				allowfullscreen=""
				loading="lazy"
				referrerpolicy="no-referrer-when-downgrade"
				title="Carte — <?php echo esc_attr( get_the_title() ); ?>">
			</iframe>
		</div>
	<?php endif; // $adresse_complete ?>
	<?php endif; // $adresse || $ville ?>

	<!-- Prochains événements dans ce lieu -->
	<?php
	$upcoming = new WP_Query( [
		'post_type'      => CFEB_SLUG,
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'orderby'        => 'meta_value',
		'meta_key'       => '_cfeb_date_debut',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'meta_query'     => [
			'relation' => 'AND',
			[
				'key'     => '_cfeb_venue_id',
				'value'   => $venue_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			],
			[
				'key'     => '_cfeb_date_debut',
				'value'   => current_time( 'mysql' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			],
		],
	] );
	?>

	<?php if ( $upcoming->have_posts() ) : ?>
		<section class="cfeb-venue-events">
			<h2 style="margin-bottom:20px;">📅 Prochains événements en ce lieu</h2>
			<div class="cfeb-events-list">
				<?php while ( $upcoming->have_posts() ) : $upcoming->the_post(); ?>
					<?php
					$em       = CF_CPT::get_meta( get_the_ID() );
					$e_statut = CF_CPT::compute_statut( get_the_ID() );
					$e_dispo  = CF_CPT::get_dispo( get_the_ID() );
					$e_prix   = (float) $em['prix'];
					$e_date   = $em['date_debut'] ? strtotime( $em['date_debut'] ) : 0;

					$statuts_labels = [
						'ouvert'  => '✅ Places disponibles',
						'complet' => '🔴 Complet',
						'ferme'   => '🔒 Fermé',
					];

					$data_json = htmlspecialchars( wp_json_encode( [
						'id'     => get_the_ID(),
						'titre'  => get_the_title(),
						'date'   => $e_date ? date_i18n( 'l j F Y à H\hi', $e_date ) : '',
						'lieu'   => $em['lieu'],
						'prix'      => $e_prix > 0 ? number_format( $e_prix, 2, ',', ' ' ) . ' €' : 'Gratuit',
						'prix_brut' => $e_prix,
						'dispo'  => PHP_INT_MAX === $e_dispo ? -1 : $e_dispo,
						'max'    => (int) $em['max_places'],
						'statut' => $e_statut,
						'visio'  => false,
					] ), ENT_QUOTES, 'UTF-8' );
					?>
					<div class="cfeb-event-card" style="margin-bottom:16px;padding:16px;background:#f9fafb;border-radius:8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
						<div>
							<a href="<?php echo esc_url( get_permalink() ); ?>" style="font-weight:600;font-size:16px;"><?php the_title(); ?></a>
							<?php if ( $e_date ) : ?>
								<div style="color:#6b7280;font-size:13px;margin-top:4px;">📅 <?php echo esc_html( date_i18n( 'l j F Y à H\hi', $e_date ) ); ?></div>
							<?php endif; ?>
							<div style="margin-top:6px;">
								<span style="font-size:12px;padding:2px 8px;border-radius:12px;background:#e5e7eb;color:#374151;">
									<?php echo esc_html( $statuts_labels[ $e_statut ] ?? $e_statut ); ?>
								</span>
								<?php if ( $e_prix > 0 ) : ?>
									<span style="font-size:12px;margin-left:6px;color:#6b7280;">💶 <?php echo esc_html( number_format( $e_prix, 2, ',', ' ' ) ); ?> €</span>
								<?php else : ?>
									<span style="font-size:12px;margin-left:6px;color:#6b7280;">Gratuit</span>
								<?php endif; ?>
							</div>
						</div>
						<?php if ( 'ouvert' === $e_statut || ( 'complet' === $e_statut && $opts['liste_attente'] ) ) : ?>
							<button class="cfeb-btn cfeb-btn-primary cfeb-open-modal" data-event='<?php echo $data_json; // phpcs:ignore ?>'>
								<?php echo 'complet' === $e_statut ? '⏳ Liste d\'attente' : '✅ Réserver'; ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			</div>
		</section>
	<?php endif; ?>

</article>

<?php endwhile; ?>

</main>
</div>

<?php echo CF_Frontend::modal_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<?php get_footer(); ?>
