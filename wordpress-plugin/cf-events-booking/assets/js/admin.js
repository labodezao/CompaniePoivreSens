/* ============================================================
   CF Événements — Admin JS
   ============================================================ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    var l10n = (typeof cfebAdmin !== 'undefined' && cfebAdmin.l10n) ? cfebAdmin.l10n : {};

    /* ── Changement de statut inline ──────────────────────── */
    document.querySelectorAll('.cfeb-statut-select').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var id     = sel.dataset.id;
        var statut = sel.value;
        if (!id || !statut) return;

        var motif = '';
        if (statut === 'annule') {
          motif = prompt(
            (l10n.motif_annulation || 'Motif d\'annulation (optionnel) :'),
            ''
          );
          if (motif === null) {
            // annulé par l'utilisateur — revenir à l'ancienne valeur
            sel.value = sel.dataset.prev || sel.value;
            return;
          }
        }

        sel.dataset.prev = statut;
        var fd = new FormData();
        fd.append('action', 'cfeb_statut_update');
        fd.append('nonce',  cfebAdmin.nonce);
        fd.append('id',     id);
        fd.append('statut', statut);
        fd.append('motif',  motif || '');

        sel.disabled = true;
        fetch(cfebAdmin.ajaxurl, {
          method:  'POST',
          body:    fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            sel.disabled = false;
            var row = document.getElementById('cfeb-row-' + id);
            if (row) {
              row.style.transition = 'background .5s';
              row.style.background = res.success ? '#f0fdf4' : '#fef2f2';
              setTimeout(function () { row.style.background = ''; }, 1200);
            }
          })
          .catch(function () { sel.disabled = false; });
      });
      sel.dataset.prev = sel.value;
    });

    /* ── Toggle paiement ──────────────────────────────────── */
    document.querySelectorAll('.cfeb-paye-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id      = btn.dataset.id;
        var current = parseInt(btn.dataset.paye, 10);
        var nouveau = current ? 0 : 1;

        var modes = ['', 'Virement', 'Ch\xe8que', 'Esp\xe8ces', 'CB en ligne', 'CB sur place', 'Autre'];
        var mode  = '';
        if (nouveau) {
          var choix = prompt((l10n.mode_paiement || 'Mode de paiement :\n0 - Non renseign\xe9\n1 - Virement\n2 - Ch\xe8que\n3 - Esp\xe8ces\n4 - CB en ligne\n5 - CB sur place\n6 - Autre'), '0');
          if (choix === null) return;
          mode = modes[parseInt(choix, 10)] || '';
        }

        var fd = new FormData();
        fd.append('action',        'cfeb_payment_update');
        fd.append('nonce',         cfebAdmin.nonce);
        fd.append('id',            id);
        fd.append('paye',          nouveau);
        fd.append('mode_paiement', mode);

        btn.disabled = true;
        fetch(cfebAdmin.ajaxurl, {
          method:  'POST',
          body:    fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            btn.disabled = false;
            if (res.success) {
              btn.dataset.paye = nouveau;
              btn.textContent  = nouveau ? '✅' : '☐';
              btn.title        = mode || '';
              if (nouveau) btn.classList.add('cfeb-paye-oui');
              else         btn.classList.remove('cfeb-paye-oui');
              var row = document.getElementById('cfeb-row-' + id);
              if (row) {
                row.style.transition = 'background .3s';
                row.style.background = '#eff6ff';
                setTimeout(function () { row.style.background = ''; }, 900);
              }
            }
          })
          .catch(function () { btn.disabled = false; });
      });
    });

    /* ── Case à cocher « tout sélectionner » ──────────────── */
    var cbAll = document.getElementById('cfeb-cb-all');
    if (cbAll) {
      cbAll.addEventListener('change', function () {
        document.querySelectorAll('.cfeb-cb').forEach(function (cb) {
          cb.checked = cbAll.checked;
        });
      });
    }

    /* ── Actions en masse ─────────────────────────────────── */
    var bulkForm = document.getElementById('cfeb-bulk-form');
    if (bulkForm) {
      bulkForm.addEventListener('submit', function (e) {
        var actionTop    = document.getElementById('cfeb-bulk-action');
        var actionBottom = bulkForm.querySelector('[name="cfeb_bulk_action2"]');
        var action = '';
        if (actionTop && actionTop.value)    action = actionTop.value;
        if (!action && actionBottom && actionBottom.value) action = actionBottom.value;

        if (!action) {
          e.preventDefault();
          alert(l10n.select_action || 'Veuillez choisir une action.');
          return;
        }

        var checked = bulkForm.querySelectorAll('.cfeb-cb:checked');
        if (!checked.length) {
          e.preventDefault();
          alert(l10n.select_items || 'Veuillez sélectionner au moins une réservation.');
          return;
        }

        if (action === 'delete') {
          if (!confirm(l10n.confirm_bulk_delete || 'Supprimer les réservations sélectionnées ?')) {
            e.preventDefault();
          }
        }
      });
    }

    /* ── Confirmation suppression individuelle ────────────── */
    document.querySelectorAll('.cfeb-delete[data-confirm]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        var msg = l10n[link.dataset.confirm] || 'Supprimer cette réservation ?';
        if (!confirm(msg)) {
          e.preventDefault();
        }
      });
    });

    /* ══════════════════════════════════════════════════════
       PANNEAU DE DÉTAIL — clic sur une ligne de réservation
    ═══════════════════════════════════════════════════════ */
    document.querySelectorAll('.cfeb-booking-row').forEach(function (row) {
      row.addEventListener('click', function (e) {
        if (e.target.tagName === 'A' || e.target.tagName === 'SELECT' ||
            e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' ||
            e.target.closest('.cfeb-paye-toggle') || e.target.closest('.cfeb-delete') ||
            e.target.closest('.cfeb-statut-select')) {
          return;
        }
        var id         = row.dataset.id;
        var detailRow  = document.getElementById('cfeb-detail-' + id);
        if (!detailRow) return;

        var isOpen = detailRow.style.display !== 'none';
        if (isOpen) {
          detailRow.style.display = 'none';
          row.classList.remove('cfeb-row-open');
          return;
        }

        // Fermer tous les autres panneaux ouverts
        document.querySelectorAll('.cfeb-detail-row').forEach(function (dr) {
          dr.style.display = 'none';
        });
        document.querySelectorAll('.cfeb-booking-row').forEach(function (r) {
          r.classList.remove('cfeb-row-open');
        });

        detailRow.style.display = '';
        row.classList.add('cfeb-row-open');

        // Charger le contenu si pas encore chargé
        var loadingDiv = detailRow.querySelector('.cfeb-detail-loading');
        if (!loadingDiv) return; // déjà chargé

        var fd = new FormData();
        fd.append('action', 'cfeb_booking_detail');
        fd.append('nonce',  cfebAdmin.nonce);
        fd.append('id',     id);

        fetch(cfebAdmin.ajaxurl, {
          method:  'POST',
          body:    fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            var td = detailRow.querySelector('td');
            if (res.success && td) {
              td.innerHTML = res.data.html;
            } else if (td) {
              td.innerHTML = '<div style="padding:16px;color:#b91c1c;">Erreur lors du chargement.</div>';
            }
          })
          .catch(function () {
            var td = detailRow.querySelector('td');
            if (td) td.innerHTML = '<div style="padding:16px;color:#b91c1c;">Erreur réseau.</div>';
          });
      });
    });

    /* ═══════════════════════════════════════════════════════
       CALENDRIER HEBDO ADMIN — panneau détail réservation
    ════════════════════════════════════════════════════════ */
    document.querySelectorAll('.cfeb-week-booking').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var bookingId = btn.dataset.bookingId;
        var detailBox = document.querySelector('.cfeb-week-detail-body');
        if (!bookingId || !detailBox) return;

        document.querySelectorAll('.cfeb-week-booking').forEach(function (b) {
          b.classList.remove('is-active');
        });
        btn.classList.add('is-active');
        detailBox.innerHTML = '<div style="color:#6b7280;">Chargement…</div>';

        var fd = new FormData();
        fd.append('action', 'cfeb_booking_detail');
        fd.append('nonce',  cfebAdmin.nonce);
        fd.append('id',     bookingId);

        fetch(cfebAdmin.ajaxurl, {
          method:  'POST',
          body:    fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.success && res.data && res.data.html) {
              detailBox.innerHTML = res.data.html;
            } else {
              detailBox.innerHTML = '<div style="color:#b91c1c;">Détail indisponible.</div>';
            }
          })
          .catch(function () {
            detailBox.innerHTML = '<div style="color:#b91c1c;">Erreur réseau.</div>';
          });
      });
    });

    /* ═══════════════════════════════════════════════════════
       TYPES RDV — grille disponibilité hebdomadaire
    ════════════════════════════════════════════════════════ */

    /* Toggle jour activé/désactivé */
    document.querySelectorAll('.cfeb-day-toggle').forEach(function (cb) {
      function update() {
        var row      = cb.closest('.cfeb-avail-row');
        var slots    = row ? row.querySelector('.cfeb-avail-slots') : null;
        var unavail  = row ? row.querySelector('.cfeb-avail-unavailable') : null;
        if (slots)   slots.style.display = cb.checked ? '' : 'none';
        if (row)     row.classList.toggle('cfeb-avail-row--on', cb.checked);
        if (unavail) unavail.style.display = cb.checked ? 'none' : '';
      }
      cb.addEventListener('change', update);
      update();
    });

    /* Options du sélecteur de lieu (liste partagée, voir CF_Admin::get_locations()) */
    function cfebLieuOptionsHtml() {
      var html = '<option value="">📍 Lieu par défaut</option>';
      (window.CFEB_LOCATIONS || []).forEach(function (loc) {
        var label = document.createElement('div');
        label.textContent = loc.nom || loc.adresse || loc.id;
        html += '<option value="' + loc.id + '">' + label.innerHTML + '</option>';
      });
      return html;
    }

    /* Ajouter une plage horaire */
    document.querySelectorAll('.cfeb-add-avail-slot').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var day      = btn.dataset.day;
        var slotsDiv = btn.parentElement;
        if (!slotsDiv) return;

        var idx = slotsDiv.querySelectorAll('.cfeb-avail-slot-row').length;
        var row = document.createElement('div');
        row.className = 'cfeb-avail-slot-row';
        var lieuOptions = cfebLieuOptionsHtml();
        row.innerHTML =
          '<input type="time" name="cfeb_avail[' + day + '][slots][' + idx + '][debut]" value="09:00" />'
          + '<span class="cfeb-slot-arrow">→</span>'
          + '<input type="time" name="cfeb_avail[' + day + '][slots][' + idx + '][fin]" value="17:00" />'
          + '<select name="cfeb_avail[' + day + '][slots][' + idx + '][modalite]" class="cfeb-slot-modalite">'
          + '<option value="presentiel">📍 Présentiel</option>'
          + '<option value="distanciel">🖥️ Distanciel</option>'
          + '</select>'
          + '<select name="cfeb_avail[' + day + '][slots][' + idx + '][lieu_id]" class="cfeb-slot-lieu">' + lieuOptions + '</select>'
          + '<button type="button" class="cfeb-rm-avail-slot button-link" title="Supprimer ce créneau">'
          + '<span class="dashicons dashicons-no-alt"></span></button>';
        slotsDiv.insertBefore(row, btn);
        row.querySelector('.cfeb-rm-avail-slot').addEventListener('click', function () {
          row.remove();
          renumberAvailSlots(day);
        });
      });
    });

    /* Supprimer une plage horaire existante */
    document.querySelectorAll('.cfeb-rm-avail-slot').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row      = btn.closest('.cfeb-avail-slot-row');
        var slotsDiv = row ? row.parentElement : null;
        var addBtn   = slotsDiv ? slotsDiv.querySelector('.cfeb-add-avail-slot') : null;
        var day      = addBtn ? addBtn.dataset.day : '';
        if (row) row.remove();
        if (day) renumberAvailSlots(day);
      });
    });

    function renumberAvailSlots(day) {
      var avRow    = document.querySelector('.cfeb-avail-row[data-day="' + day + '"]');
      if (!avRow) return;
      var slotsDiv = avRow.querySelector('.cfeb-avail-slots');
      if (!slotsDiv) return;
      slotsDiv.querySelectorAll('.cfeb-avail-slot-row').forEach(function (slotRow, i) {
        slotRow.querySelectorAll('[name]').forEach(function (el) {
          el.name = el.name.replace(/\[slots\]\[\d+\]/, '[slots][' + i + ']');
        });
      });
    }

    /* ═══════════════════════════════════════════════════════
       PLANNING — page créneaux/modèles (#cfeb-add-slot)
    ════════════════════════════════════════════════════════ */
    var addSlotBtn = document.getElementById('cfeb-add-slot');
    if (addSlotBtn) {
      addSlotBtn.addEventListener('click', function () {
        var list = document.getElementById('cfeb-slots-list');
        if (!list) return;

        var row = document.createElement('div');
        row.className = 'cfeb-slot-row';
        row.innerHTML =
          '<input type="time" name="cfeb_tpl_slot_debut[]" value="09:00" required />'
          + '<span class="cfeb-slot-arrow">→</span>'
          + '<input type="time" name="cfeb_tpl_slot_fin[]"   value="10:00" required />'
          + '<button type="button" class="button button-small cfeb-rm-slot" title="Retirer ce créneau">✕</button>';
        list.appendChild(row);
        row.querySelector('.cfeb-rm-slot').addEventListener('click', function () {
          row.remove();
        });
      });
    }

    /* Supprimer créneaux existants (planning) */
    document.querySelectorAll('.cfeb-rm-slot').forEach(function (btn) {
      btn.addEventListener('click', function () {
        btn.closest('.cfeb-slot-row').remove();
      });
    });

    /* ═══════════════════════════════════════════════════════
       PLANNING — remplir date du jour automatiquement
    ════════════════════════════════════════════════════════ */
    var today = new Date().toISOString().slice(0, 10);
    document.querySelectorAll('input[type="date"].cfeb-date-auto-today').forEach(function (inp) {
      if (!inp.value) inp.value = today;
    });

  });

})();
