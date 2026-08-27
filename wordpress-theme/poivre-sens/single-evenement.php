<?php
/**
 * single-evenement.php — Fiche événement détaillée
 */
get_header(); ?>

<?php while (have_posts()): the_post();
    $id          = get_the_ID();
    $date        = ps_evt_champ($id, 'date');
    $heure       = ps_evt_champ($id, 'heure');
    $heure_fin   = ps_evt_champ($id, 'heure_fin');
    $lieu        = ps_evt_champ($id, 'lieu');
    $adresse     = ps_evt_champ($id, 'adresse');
    $ville       = ps_evt_champ($id, 'ville');
    $type        = ps_evt_champ($id, 'type_label');
    $prix        = ps_evt_champ($id, 'prix');
    $billetterie = ps_evt_champ($id, 'billetterie');
    $complet     = ps_evt_champ($id, 'complet');

    // Champs propres au plugin de réservation : absents en dehors de lui.
    $animateur      = ps_evt_champ($id, 'animateur');
    $lien_visio     = ps_evt_champ($id, 'lien_visio');
    $statut_event   = ps_evt_champ($id, 'statut_event') ?: 'publie';
    $places_restantes = ps_evt_places_restantes($id);
?>
<article class="single-evt">

    <a href="<?= esc_url(get_post_type_archive_link(ps_evt_cpt())) ?>" class="single-evt__back">
        <?php _e('Tous les événements', 'poivre-sens'); ?>
    </a>

    <?php if ($statut_event === 'annule'): ?>
    <p class="single-evt__banniere single-evt__banniere--annule">
        <?php _e('⚠ Cet événement est annulé.', 'poivre-sens'); ?>
    </p>
    <?php elseif ($statut_event === 'reporte'): ?>
    <p class="single-evt__banniere single-evt__banniere--reporte">
        <?php _e('⏳ Cet événement est reporté — une nouvelle date sera annoncée.', 'poivre-sens'); ?>
    </p>
    <?php endif; ?>

    <div class="single-evt__meta">
        <?php if ($type): ?>
        <span class="single-evt__type"><?= esc_html($type) ?></span>
        <?php endif; ?>
        <?php if ($complet): ?>
        <span class="single-evt__type" style="background:rgba(158,55,16,.15);color:var(--rouge);border-color:rgba(158,55,16,.3);margin-left:8px">
            <?php _e('Complet', 'poivre-sens'); ?>
        </span>
        <?php endif; ?>

        <h1 class="single-evt__titre"><?php the_title(); ?></h1>
    </div>

    <?php if ($date || $heure || $lieu || $prix): ?>
    <div class="single-evt__infos">
        <?php if ($date): ?>
        <div>
            <div class="single-evt__info-k"><?php _e('Date', 'poivre-sens'); ?></div>
            <div class="single-evt__info-v"><?= esc_html(ps_format_date($date, 'l j F Y')) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($heure): ?>
        <div>
            <div class="single-evt__info-k"><?php _e('Horaire', 'poivre-sens'); ?></div>
            <div class="single-evt__info-v">
                <?= esc_html($heure) ?><?= $heure_fin ? ' – ' . esc_html($heure_fin) : '' ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($lieu || $ville): ?>
        <div>
            <div class="single-evt__info-k"><?php _e('Lieu', 'poivre-sens'); ?></div>
            <div class="single-evt__info-v">
                <?= esc_html($lieu) ?><?= ($lieu && $ville) ? ', ' : '' ?><?= esc_html($ville) ?>
                <?php if ($adresse): ?>
                <br><span style="font-size:.82rem;color:var(--gris)"><?= esc_html($adresse) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($prix): ?>
        <div>
            <div class="single-evt__info-k"><?php _e('Tarif', 'poivre-sens'); ?></div>
            <div class="single-evt__info-v"><?= esc_html($prix) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($animateur): ?>
        <div>
            <div class="single-evt__info-k"><?php _e('Avec', 'poivre-sens'); ?></div>
            <div class="single-evt__info-v"><?= esc_html($animateur) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($lien_visio): ?>
        <div>
            <div class="single-evt__info-k"><?php _e('En ligne', 'poivre-sens'); ?></div>
            <div class="single-evt__info-v"><a href="<?= esc_url($lien_visio) ?>" target="_blank" rel="noopener"><?php _e('Rejoindre la visio', 'poivre-sens'); ?></a></div>
        </div>
        <?php endif; ?>
        <?php if ($places_restantes !== null && !$complet): ?>
        <div>
            <div class="single-evt__info-k"><?php _e('Places', 'poivre-sens'); ?></div>
            <div class="single-evt__info-v">
                <?= esc_html(sprintf(
                      /* translators: %d: number of remaining spots */
                      _n('%d place restante', '%d places restantes', $places_restantes, 'poivre-sens'),
                      $places_restantes
                    )) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (has_post_thumbnail()): ?>
    <figure style="margin:0 0 48px">
        <?php the_post_thumbnail('evt-thumbnail', ['class' => 'single-evt__img', 'alt' => get_the_title()]); ?>
    </figure>
    <?php endif; ?>

    <div class="single-evt__corps">
        <?php the_content(); ?>
    </div>

    <?php if ($statut_event === 'annule' || $statut_event === 'reporte'): ?>
    <?php // Bandeau déjà affiché en tête de page : pas de bouton de réservation. ?>
    <?php elseif ($billetterie && !$complet): ?>
    <a href="<?= esc_url($billetterie) ?>" class="single-evt__billetterie" target="_blank" rel="noopener">
        <?php _e('Réserver ma place', 'poivre-sens'); ?> →
    </a>
    <?php elseif ($complet): ?>
    <p style="margin-top:48px;font-size:.82rem;color:var(--rouge);letter-spacing:.1em;text-transform:uppercase">
        <?php _e('Cet événement est complet.', 'poivre-sens'); ?>
    </p>
    <?php endif; ?>

</article>

<?php endwhile; ?>
<?php get_footer();
