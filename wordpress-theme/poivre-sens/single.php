<?php
/**
 * single.php — Article blog générique
 */
get_header(); ?>
<main style="padding:120px 80px 80px;max-width:800px;margin:0 auto">
  <?php while (have_posts()): the_post(); ?>
    <a href="<?php echo esc_url(get_post_type_archive_link('post')); ?>" class="single-evt__back"><?php _e('← Retour', 'poivre-sens'); ?></a>
    <h1 class="sh" style="margin-bottom:16px"><?php the_title(); ?></h1>
    <p style="font-size:.72rem;color:var(--gris);letter-spacing:.12em;margin-bottom:40px">
      <?php echo get_the_date(); ?>
    </p>
    <?php if (has_post_thumbnail()): ?>
    <figure style="margin:0 0 40px">
      <?php the_post_thumbnail('large', ['style'=>'width:100%;height:auto']); ?>
    </figure>
    <?php endif; ?>
    <div class="single-evt__corps"><?php the_content(); ?></div>
  <?php endwhile; ?>
</main>
<?php get_footer();
