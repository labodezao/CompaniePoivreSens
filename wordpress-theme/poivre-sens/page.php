<?php
/**
 * page.php — Template page générique
 */
get_header(); ?>
<main style="padding:120px 80px 80px;max-width:900px;margin:0 auto;min-height:60vh">
  <?php while (have_posts()): the_post(); ?>
    <h1 class="sh" style="margin-bottom:32px"><?php the_title(); ?></h1>
    <div style="font-size:.96rem;line-height:1.88;color:rgba(236,227,203,.72)">
      <?php the_content(); ?>
    </div>
  <?php endwhile; ?>
</main>
<?php get_footer();
