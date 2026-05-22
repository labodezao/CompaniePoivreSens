<?php
/**
 * archive.php — Archive générique
 */
get_header(); ?>
<main class="sec" style="padding-top:120px;min-height:60vh">
  <h1 class="sh" style="margin-bottom:40px"><?php the_archive_title(); ?></h1>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px">
    <?php while (have_posts()): the_post(); ?>
    <article style="border:1px solid var(--bord);padding:24px;background:var(--noir2)">
      <h2 style="font-family:var(--fh);font-size:1.2rem;font-weight:400;margin-bottom:8px">
        <a href="<?php the_permalink(); ?>" style="color:var(--creme)"><?php the_title(); ?></a>
      </h2>
      <p style="font-size:.82rem;color:var(--gris)"><?php the_excerpt(); ?></p>
    </article>
    <?php endwhile; ?>
  </div>
  <div style="margin-top:40px;text-align:center">
    <?php the_posts_pagination(['prev_text'=>'←','next_text'=>'→']); ?>
  </div>
</main>
<?php get_footer();
