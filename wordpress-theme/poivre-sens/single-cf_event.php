<?php
/**
 * single-cf_event.php — Fiche événement (plugin CF)
 *
 * Le plugin fournit ses propres gabarits, mais laisse le thème
 * les remplacer. On réutilise donc celui du site, qui lit les
 * champs via ps_evt_champ() quelle qu'en soit la source.
 */
defined('ABSPATH') || exit;
require get_template_directory() . '/single-evenement.php';
