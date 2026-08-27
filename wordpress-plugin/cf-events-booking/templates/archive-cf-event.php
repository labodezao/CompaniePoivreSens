<?php
/**
 * Gabarit archive des événements cf_event.
 *
 * Le thème peut surcharger en créant :
 * wp-content/themes/[votre-theme]/archive-cf_event.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); ?>

<div id="primary" class="content-area">
<main id="main" class="site-main">

	<header class="page-header" style="margin-bottom:24px;">
		<h1 class="page-title">📅 <?php post_type_archive_title(); ?></h1>
		<?php if ( is_tax( CFEB_TAX ) ) : ?>
			<p class="archive-description"><?php echo esc_html( term_description() ); ?></p>
		<?php endif; ?>
	</header>

	<div class="cfeb-events-list">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<?php echo CF_Frontend::render_event_card( get_post() ); // phpcs:ignore ?>
			<?php endwhile; ?>
		<?php else : ?>
			<p class="cfeb-no-events">Aucun événement à venir. Revenez bientôt !</p>
		<?php endif; ?>
	</div>

	<?php
	the_posts_pagination( [
		'mid_size'  => 2,
		'prev_text' => '← Précédent',
		'next_text' => 'Suivant →',
	] );
	?>

</main>
</div>

<?php echo CF_Frontend::modal_html(); // phpcs:ignore ?>

<?php get_footer(); ?>
