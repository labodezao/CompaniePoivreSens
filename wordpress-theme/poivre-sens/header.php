<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="<?php echo esc_attr(get_theme_mod('color_scheme', 'nuit')); ?>">
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<nav class="nav" id="nav" role="navigation" aria-label="Navigation principale">
  <a href="<?php echo esc_url(home_url('/')); ?>" class="nav__logo">
    Poivre <b>&amp;</b> Sens
  </a>

  <?php if (is_front_page()): ?>
  <ul class="nav__list" id="nav-list" role="list">
    <li><a href="#galerie"      onclick="closeMenu()">Galerie</a></li>
    <li><a href="#projet"       onclick="closeMenu()">Projet</a></li>
    <li><a href="#artistes"     onclick="closeMenu()">Artistes</a></li>
    <li><a href="#activites"    onclick="closeMenu()">Activités</a></li>
    <li><a href="#evenements"   onclick="closeMenu()">Événements</a></li>
    <li><a href="#esthetique"   onclick="closeMenu()">Esthétique</a></li>
    <li><a href="#newsletter"   onclick="closeMenu()">Newsletter</a></li>
    <li><a href="#contact"      onclick="closeMenu()">Contact</a></li>
  </ul>
  <?php else: ?>
  <ul class="nav__list" id="nav-list" role="list">
    <li><a href="<?php echo esc_url(home_url('/')); ?>">Accueil</a></li>
    <li><a href="<?php echo esc_url(home_url('/evenements/')); ?>" <?php if (is_post_type_archive('evenement') || is_singular('evenement')) echo 'class="current-menu-item"'; ?>>Événements</a></li>
    <li><a href="<?php echo esc_url(home_url('/#contact')); ?>">Contact</a></li>
  </ul>
  <?php endif; ?>

  <button class="nav__burger" id="nav-burger"
          aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="nav-list"
          onclick="toggleMenu()">
    <span></span><span></span><span></span>
  </button>
</nav>
