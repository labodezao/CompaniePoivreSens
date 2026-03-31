/**
 * customizer-preview.js
 * Met à jour les éléments de la page d'accueil en temps réel
 * pendant l'édition dans le Customizer WordPress.
 */
(function ($, api) {
    'use strict';

    /* ── Utilitaire : binder texte simple ───────────────────────── */
    function live(settingId, selector, attr) {
        api('ps_' + settingId, function (value) {
            value.bind(function (newval) {
                if (attr === 'html') {
                    $(selector).html(newval);
                } else if (attr === 'href') {
                    $(selector).attr('href', newval);
                } else {
                    $(selector).text(newval);
                }
            });
        });
    }

    /* ── Hero ───────────────────────────────────────────────────── */
    live('hero_surtitle',     '.hero__sup',   'text');
    live('hero_cta_label',    '.hero__cta',   'text');
    live('hero_quote',        '.hero__q',     'text');
    live('hero_intro',        '.hero__intro', 'text');

    // Disciplines — une par ligne → <br>
    api('ps_hero_disciplines', function (value) {
        value.bind(function (newval) {
            var lines = newval.split('\n').filter(Boolean);
            var html  = lines.join('<br>');
            $('.hero__disc').html(
                '<strong>' + $('.hero__disc strong').text() + '</strong>' + html
            );
        });
    });

    /* ── Manifeste ──────────────────────────────────────────────── */
    live('manifeste_p1', '.mf-tx p:nth-child(1)', 'html');
    live('manifeste_p2', '.mf-tx p:nth-child(2)', 'html');
    live('manifeste_p3', '.mf-tx p:nth-child(3)', 'html');

    api('ps_manifeste_titre', function (value) {
        value.bind(function (newval) {
            var em1 = api('ps_manifeste_titre_em1')();
            var em2 = api('ps_manifeste_titre_em2')();
            var parts = newval.split(em1);
            if (parts.length > 1) {
                var p2 = parts[1].split(em2);
                $('.mf-t').html(
                    parts[0] +
                    '<em>' + em1 + '</em>' +
                    (p2[0] || '') +
                    (p2[1] !== undefined ? '<em>' + em2 + '</em>' + p2[1] : '')
                );
            } else {
                $('.mf-t').text(newval);
            }
        });
    });

    /* ── Artiste Ambre ──────────────────────────────────────────── */
    live('ambre_nom',      '.bio:nth-child(1) .bio__nom',  'text');
    live('ambre_role',     '.bio:nth-child(1) .bio__rol',  'text');
    live('ambre_initiale', '.bio:nth-child(1) .bio__mn',   'text');
    live('ambre_bio1',     '.bio:nth-child(1) .bio__tx:nth-child(1)', 'text');
    live('ambre_bio2',     '.bio:nth-child(1) .bio__tx:nth-child(2)', 'text');

    api('ps_ambre_tags', function (value) {
        value.bind(function (newval) {
            var tags = newval.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
            var html = tags.map(function (t) {
                return '<span class="bio__tg">' + t + '</span>';
            }).join('');
            $('.bio:nth-child(1) .bio__tgs').html(html);
        });
    });

    /* ── Artiste Ewen ───────────────────────────────────────────── */
    live('ewen_nom',      '.bio:nth-child(2) .bio__nom',  'text');
    live('ewen_role',     '.bio:nth-child(2) .bio__rol',  'text');
    live('ewen_initiale', '.bio:nth-child(2) .bio__mn',   'text');
    live('ewen_bio1',     '.bio:nth-child(2) .bio__tx:nth-child(1)', 'text');
    live('ewen_bio2',     '.bio:nth-child(2) .bio__tx:nth-child(2)', 'text');

    api('ps_ewen_tags', function (value) {
        value.bind(function (newval) {
            var tags = newval.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
            var html = tags.map(function (t) {
                return '<span class="bio__tg">' + t + '</span>';
            }).join('');
            $('.bio:nth-child(2) .bio__tgs').html(html);
        });
    });

    /* ── Esthétique — citation ──────────────────────────────────── */
    live('esthet_cite_ligne1',  '.gcite',         'text');
    live('esthet_cite_source',  '.gcite__src',    'text');

    api('ps_esthet_cite_ligne2', function (value) {
        value.bind(function () { ps_rebuild_cite(); });
    });
    api('ps_esthet_cite_em', function (value) {
        value.bind(function () { ps_rebuild_cite(); });
    });
    api('ps_esthet_cite_ligne1', function (value) {
        value.bind(function () { ps_rebuild_cite(); });
    });

    function ps_rebuild_cite() {
        var l1 = api('ps_esthet_cite_ligne1')();
        var l2 = api('ps_esthet_cite_ligne2')();
        var em = api('ps_esthet_cite_em')();
        $('.gcite').html(
            l1 + '<br>' + l2 + '<br><em>' + em + '</em>.'
        );
    }

    /* ── Contact ────────────────────────────────────────────────── */
    live('contact_nom',          '.co-col:nth-child(1) .co-row:nth-child(1) .co-v', 'text');
    live('contact_statut',       '.co-col:nth-child(1) .co-row:nth-child(2) .co-v', 'text');
    live('contact_direction',    '.co-col:nth-child(1) .co-row:nth-child(3) .co-v', 'text');
    live('contact_disciplines',  '.co-col:nth-child(1) .co-row:nth-child(4) .co-v', 'text');
    live('contact_note_reseaux', '.co-col:nth-child(2) p:last-child',               'text');

    api('ps_contact_email', function (value) {
        value.bind(function (v) {
            var $el = $('.co-row a[href^="mailto:contact"]');
            $el.attr('href', 'mailto:' + v).text(v);
        });
    });
    api('ps_contact_site', function (value) {
        value.bind(function (v) {
            var $el = $('.co-row a[href*="poivresens"]').not('[href^="mailto:"]');
            $el.attr('href', 'https://' + v).text(v);
        });
    });
    api('ps_contact_email_ambre', function (value) {
        value.bind(function (v) {
            var $el = $('.co-row a[href^="mailto:ambre"]');
            $el.attr('href', 'mailto:' + v).text(v);
        });
    });
    api('ps_contact_email_ewen', function (value) {
        value.bind(function (v) {
            var $el = $('.co-row a[href^="mailto:ewen"]');
            $el.attr('href', 'mailto:' + v).text(v);
        });
    });

    /* ── Footer ─────────────────────────────────────────────────── */
    api('ps_footer_line1', function (value) {
        value.bind(function () { ps_rebuild_footer(); });
    });
    api('ps_footer_line2', function (value) {
        value.bind(function () { ps_rebuild_footer(); });
    });
    function ps_rebuild_footer() {
        var l1 = api('ps_footer_line1')();
        var l2 = api('ps_footer_line2')();
        var yr = new Date().getFullYear();
        $('.footer__mt').html(
            l1 + '<br>' + l2 + '<br>© ' + yr + ' Poivre & Sens · Tous droits réservés'
        );
    }

}(jQuery, wp.customize));
