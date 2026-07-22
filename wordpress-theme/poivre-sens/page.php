<?php
/**
 * page.php — Template page générique
 */
get_header(); ?>
<main class="single-evt" style="min-height:60vh">
  <?php while (have_posts()): the_post(); ?>
    <h1 class="sh" style="margin-bottom:32px"><?php the_title(); ?></h1>
    <div class="single-evt__corps">
      <?php the_content(); ?>
    </div>
  <?php endwhile; ?>
</main>
<?php get_footer();
