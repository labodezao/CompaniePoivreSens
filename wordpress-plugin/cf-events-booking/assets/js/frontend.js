/* ============================================================
   CF Événements & Réservations — Frontend JS
   Vanilla JS — Zéro dépendance — Compatible Astra
   ============================================================ */
(function () {
  'use strict';

  /* ── Attendre le DOM ──────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    initModal();
    initCalendar();
    prefillFromStorage();
    initRdvWidget();
    initRdvTabs();
    initRdvAnchorHighlight();
  });

  /* ── [cf_rdv_onglets] : bascule d'onglet + sélection auto via l'ancre ── */
  function initRdvTabs() {
    document.querySelectorAll('.cf-rdv-tabs').forEach(function (tabs) {
      var navBtns = tabs.querySelectorAll('.cf-rdv-tab');
      var panels  = tabs.querySelectorAll('.cf-rdv-tab-panel');

      function activate(panelId) {
        navBtns.forEach(function (btn) {
          var match = btn.dataset.panel === panelId;
          btn.classList.toggle('is-active', match);
          btn.setAttribute('aria-selected', match ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
          panel.classList.toggle('is-active', panel.dataset.panel === panelId);
        });
      }

      navBtns.forEach(function (btn) {
        btn.addEventListener('click', function () { activate(btn.dataset.panel); });
      });

      // Un lien direct vers un widget (#cf-rdv-<slug>, ex. email MailPoet)
      // doit d'abord ouvrir l'onglet qui le contient, avant que
      // initRdvAnchorHighlight() ne défile jusqu'à lui.
      var hash = window.location.hash.replace('#', '');
      if (hash) {
        var panel;
        try { panel = tabs.querySelector('.cf-rdv-tab-panel[data-panel="' + hash + '"]'); } catch (err) { panel = null; }
        if (panel) activate(hash);
      }
    });
  }

  /* ── Lien direct vers un widget RDV (#cf-rdv-<slug>, ex. email MailPoet) ──
     Un léger délai laisse le temps à un éventuel sélecteur d'onglets
     (voir [cf_rdv_onglets]) de rendre la cible visible avant de défiler. */
  function initRdvAnchorHighlight() {
    var hash = window.location.hash;
    if (!hash || hash.indexOf('#cf-rdv-') !== 0) return;
    var target;
    try { target = document.querySelector(hash); } catch (err) { return; }
    if (!target || !target.classList.contains('cf-rdv-widget')) return;

    setTimeout(function () {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      target.classList.add('cf-rdv-widget--highlighted');
      setTimeout(function () {
        target.classList.remove('cf-rdv-widget--highlighted');
      }, 2000);
    }, 50);
  }

  /* ══════════════════════════════════════════════════════════
     MODAL RÉSERVATION
  ══════════════════════════════════════════════════════════ */
  var modal     = null;
  var overlay   = null;
  var formWrap  = null;
  var successWrap = null;
  var form      = null;
  var submitBtn = null;
  var alertBox  = null;

  function initModal() {
    overlay    = document.getElementById('cfeb-modal');
    if (!overlay) return;

    formWrap    = document.getElementById('cfeb-modal-form-wrap');
    successWrap = document.getElementById('cfeb-modal-success');
    form        = document.getElementById('cfeb-form');
    submitBtn   = document.getElementById('cfeb-submit');
    alertBox    = form.querySelector('.cfeb-form-alert');

    /* Ouvrir la modal au clic sur "Réserver" */
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.cfeb-open-modal');
      if (!btn) return;
      e.preventDefault();
      openModal(btn);
    });

    /* Fermer */
    document.addEventListener('click', function (e) {
      if (e.target.closest('.cfeb-modal-close') || e.target === overlay) {
        closeModal();
      }
    });
    document.addEventListener('keydown', function (e) {
      if ('Escape' === e.key) closeModal();
    });

    /* Soumettre */
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitBooking();
    });
  }

  function openModal(btn) {
    if (!overlay) return;

    var data = {};
    try { data = JSON.parse(btn.dataset.event || '{}'); } catch (err) { /* noop */ }

    /* Titre et méta */
    var titre = overlay.querySelector('.cfeb-modal-event-titre');
    var meta  = overlay.querySelector('.cfeb-modal-event-meta');
    if (titre) titre.textContent = data.titre || '';
    if (meta) {
      var parts = [];
      if (data.date)  parts.push('📅 ' + data.date);
      if (data.lieu)  parts.push('📍 ' + data.lieu);
      else if (data.visio) parts.push('💻 En ligne');
      if (data.prix)  parts.push('💶 ' + data.prix);
      if (data.dispo > 0) parts.push('👥 ' + data.dispo + ' ' + (cfeb.l10n.places_dispo || 'place(s) disponible(s)'));
      meta.textContent = parts.join('  ·  ');
    }

    /* Remplir event_id et nonce */
    form.querySelector('[name="event_id"]').value = data.id || '';
    form.querySelector('[name="nonce"]').value    = cfeb.nonce || '';

    /* Bon cadeau : aucun sens sur un événement gratuit (rien à régler) */
    var voucherField = form.querySelector('.cfeb-field-voucher');
    if (voucherField) {
      voucherField.style.display = (data.prix_brut > 0) ? '' : 'none';
    }

    /* Mettre à jour le max de places */
    var placesInput = form.querySelector('[name="nb_places"]');
    if (placesInput && data.dispo && data.dispo > 0 && data.dispo !== -1) {
      placesInput.max = data.dispo;
    }

    /* Afficher form, cacher succès */
    formWrap.style.display  = '';
    successWrap.style.display = 'none';
    hideAlert();

    overlay.style.display = 'flex';
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        overlay.classList.add('cfeb-open');
      });
    });

    /* Focus premier champ vide */
    setTimeout(function () {
      var first = form.querySelector('input:not([type="hidden"]):not([value])');
      if (first) first.focus();
    }, 220);
  }

  function closeModal() {
    if (!overlay) return;
    overlay.classList.remove('cfeb-open');
    setTimeout(function () { overlay.style.display = 'none'; }, 200);
  }

  /* ── Soumission AJAX ──────────────────────────────────────── */
  function submitBooking() {
    hideAlert();
    if (!validateForm()) return;

    var data = new FormData(form);
    setLoading(true);

    /* Sauvegarder dans localStorage pour pré-remplissage futur (prénom/nom seulement) */
    saveToStorage({
      prenom: data.get('prenom'),
      nom:    data.get('nom'),
    });

    fetch(cfeb.ajaxurl, {
      method:  'POST',
      body:    data,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        setLoading(false);
        if (res.success) {
          showSuccess(res.data);
        } else {
          var msg = (res.data && res.data.message) ? res.data.message : (cfeb.l10n.erreur || 'Erreur.');
          showAlert(msg, 'error');
        }
      })
      .catch(function () {
        setLoading(false);
        showAlert(cfeb.l10n.erreur || 'Erreur réseau. Veuillez réessayer.', 'error');
      });
  }

  function showSuccess(data) {
    formWrap.style.display  = 'none';
    successWrap.style.display = '';
    var msgEl = document.getElementById('cfeb-success-msg');
    if (msgEl) msgEl.textContent = data.message || '';

    /* Acompte SumUp : bouton + redirection auto (uniquement vers pay.sumup.com) */
    if (data.payment_url) {
      try {
        var pay = new URL(data.payment_url);
        if (pay.hostname === 'pay.sumup.com') {
          var old = document.getElementById('cfeb-success-pay');
          if (old) old.remove();
          var box = document.createElement('div');
          box.id = 'cfeb-success-pay';
          box.style.cssText = 'margin-top:16px;padding:16px 20px;background:#fffbeb;border:2px solid #f59e0b;border-radius:8px;text-align:center;';
          var amount = data.acompte ? data.acompte + ' €' : '';
          box.innerHTML =
            '<p style="margin:0 0 12px;font-weight:600;color:#92400e;">Un acompte' + (amount ? ' de ' + amount : '') + ' est demandé pour confirmer votre créneau.</p>' +
            '<a href="' + pay.href + '" style="display:inline-block;background:#f59e0b;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:700;">💳 Payer l\'acompte maintenant →</a>' +
            '<p style="margin:10px 0 0;font-size:12px;color:#78350f;">Redirection automatique dans 5 secondes… Le lien de paiement est aussi dans votre email de confirmation.</p>';
          successWrap.appendChild(box);
          setTimeout(function () { window.location.href = pay.href; }, 5000);
          return; /* la redirection paiement remplace la redirection standard */
        }
      } catch (e) { /* URL invalide, on continue sans paiement */ }
    }

    if (data.redirect_url) {
      try {
        var target = new URL(data.redirect_url, window.location.origin);
        if (target.origin === window.location.origin) {
          setTimeout(function () { window.location.href = target.href; }, 1500);
        }
      } catch (e) { /* URL invalide, pas de redirection */ }
    }
  }

  function setLoading(on) {
    if (!submitBtn) return;
    submitBtn.disabled = on;
    submitBtn.innerHTML = on
      ? '<span class="cfeb-spinner"></span> Traitement…'
      : '✅ Confirmer ma réservation →';
  }

  function validateForm() {
    var ok    = true;
    var msgs  = [];
    var prenom = form.querySelector('[name="prenom"]');
    var nom    = form.querySelector('[name="nom"]');
    var email  = form.querySelector('[name="email"]');
    var tel    = form.querySelector('[name="telephone"]');

    if (prenom && !prenom.value.trim()) { msgs.push('Le prénom est requis.'); ok = false; }
    if (nom    && !nom.value.trim())    { msgs.push('Le nom est requis.'); ok = false; }
    if (email  && (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value))) {
      msgs.push('Une adresse email valide est requise.'); ok = false;
    }
    if (tel && tel.required && !tel.value.trim()) {
      msgs.push('Le téléphone est requis.'); ok = false;
    }

    if (!ok) showAlert(msgs.join(' '), 'error');
    return ok;
  }

  function showAlert(msg, type) {
    if (!alertBox) return;
    alertBox.textContent = msg;
    alertBox.className   = 'cfeb-form-alert cfeb-alert-' + (type || 'error');
    alertBox.style.display = '';
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function hideAlert() {
    if (alertBox) alertBox.style.display = 'none';
  }

  /* ══════════════════════════════════════════════════════════
     LOCALSTORAGE — pré-remplissage
  ══════════════════════════════════════════════════════════ */
  var LS_KEY = 'cfeb_user';

  function saveToStorage(obj) {
    try { localStorage.setItem(LS_KEY, JSON.stringify(obj)); } catch (e) { /* noop */ }
  }

  function prefillFromStorage() {
    var frm = document.getElementById('cfeb-form');
    if (!frm) return;
    var raw = null;
    try { raw = localStorage.getItem(LS_KEY); } catch (e) { return; }
    if (!raw) return;
    var obj = {};
    try { obj = JSON.parse(raw); } catch (e) { return; }

    var map = { prenom: 'prenom', nom: 'nom' };
    Object.keys(map).forEach(function (key) {
      var input = frm.querySelector('[name="' + key + '"]');
      if (input && obj[key]) input.value = obj[key];
    });
  }

  /* ══════════════════════════════════════════════════════════
     CALENDRIER — navigation par URL param ?mois=YYYY-MM
  ══════════════════════════════════════════════════════════ */
  function initCalendar() {
    /* Lire le param mois dans l'URL et mettre à jour les liens nav */
    var cal = document.querySelector('.cfeb-calendrier');
    if (!cal) return;

    var params = new URLSearchParams(window.location.search);
    var mois   = params.get('mois') || cal.dataset.mois || '';
    if (!mois) return;

    /* Corriger les liens prev/next si shortcode est dans une page avec d'autres params */
    cal.querySelectorAll('.cfeb-cal-prev, .cfeb-cal-next').forEach(function (a) {
      var href   = a.getAttribute('href');
      var mP     = new URLSearchParams(href.replace(/^.*\?/, ''));
      var base   = window.location.pathname + window.location.search;
      var up     = new URLSearchParams(base.replace(/^.*\?/, ''));
      up.set('mois', mP.get('mois') || mois);
      a.href = window.location.pathname + '?' + up.toString();
    });
  }

  /* ══════════════════════════════════════════════════════════
     WIDGET [cf_rdv] — Sélection de créneau + réservation
  ══════════════════════════════════════════════════════════ */
  function initRdvWidget() {
    document.querySelectorAll('.cf-rdv-widget').forEach(function (widget) {
      var formContainer = widget.querySelector('.cf-rdv-form-container');
      var rdvForm       = widget.querySelector('.cf-rdv-form');
      var slotTitle     = widget.querySelector('.cf-rdv-form-slot-title');
      var msgDiv        = widget.querySelector('.cf-rdv-message');
      if (!formContainer || !rdvForm) return;

      /* Navigation semaine précédente / suivante */
      widget.addEventListener('click', function (e) {
        var navBtn = e.target.closest('.cf-rdv-prev-week, .cf-rdv-next-week');
        if (!navBtn || navBtn.disabled) return;

        var typeId    = navBtn.dataset.typeId;
        var weekStart = navBtn.dataset.week;
        var calBody   = widget.querySelector('.cf-rdv-cal-body');
        var loading   = widget.querySelector('.cf-rdv-cal-loading');
        if (!calBody || !typeId || !weekStart) return;

        formContainer.style.display = 'none';
        calBody.style.opacity = '0.4';
        calBody.style.pointerEvents = 'none';
        if (loading) loading.style.display = '';

        var fd = new FormData();
        fd.append('action',     'cfeb_week_slots');
        fd.append('nonce',      typeof cfeb !== 'undefined' ? cfeb.nonce : '');
        fd.append('type_id',    typeId);
        fd.append('week_start', weekStart);

        var ajaxurl = typeof cfeb !== 'undefined' ? cfeb.ajaxurl : '/wp-admin/admin-ajax.php';
        fetch(ajaxurl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (loading) loading.style.display = 'none';
            calBody.style.opacity = '1';
            calBody.style.pointerEvents = '';
            if (res.success) calBody.innerHTML = res.data.html;
          })
          .catch(function () {
            if (loading) loading.style.display = 'none';
            calBody.style.opacity = '1';
            calBody.style.pointerEvents = '';
          });
      });

      /* List view: Voir plus de dates */
      widget.addEventListener('click', function (e) {
        var moreBtn = e.target.closest('.cf-rdv-list-more');
        if (!moreBtn || moreBtn.disabled) return;

        var typeId   = moreBtn.dataset.typeId;
        var fromDate = moreBtn.dataset.from;
        var limit    = moreBtn.dataset.limit || '15';
        var listWrap = widget.querySelector('.cf-rdv-list-wrap');
        if (!listWrap) return;

        moreBtn.disabled = true;
        moreBtn.textContent = 'Chargement…';

        var fd = new FormData();
        fd.append('action',    'cfeb_more_slots');
        fd.append('nonce',     typeof cfeb !== 'undefined' ? cfeb.nonce : '');
        fd.append('type_id',   typeId);
        fd.append('from_date', fromDate);
        fd.append('limit',     limit);

        var ajaxurl = typeof cfeb !== 'undefined' ? cfeb.ajaxurl : '/wp-admin/admin-ajax.php';
        fetch(ajaxurl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.success) {
              listWrap.innerHTML = res.data.html;
            } else {
              moreBtn.disabled = false;
              moreBtn.textContent = 'Voir plus de dates ›';
            }
          })
          .catch(function () {
            moreBtn.disabled = false;
            moreBtn.textContent = 'Voir plus de dates ›';
          });
      });

      /* List view: Dates précédentes */
      widget.addEventListener('click', function (e) {
        var prevBtn = e.target.closest('.cf-rdv-list-prev');
        if (!prevBtn || prevBtn.disabled) return;

        var typeId  = prevBtn.dataset.typeId;
        var before  = prevBtn.dataset.before;
        var limit   = prevBtn.dataset.limit || '15';
        var listWrap = widget.querySelector('.cf-rdv-list-wrap');
        if (!listWrap) return;

        prevBtn.disabled = true;
        prevBtn.textContent = 'Chargement…';

        var fd = new FormData();
        fd.append('action',      'cfeb_prev_slots');
        fd.append('nonce',       typeof cfeb !== 'undefined' ? cfeb.nonce : '');
        fd.append('type_id',     typeId);
        fd.append('before_date', before);
        fd.append('limit',       limit);

        var ajaxurl = typeof cfeb !== 'undefined' ? cfeb.ajaxurl : '/wp-admin/admin-ajax.php';
        fetch(ajaxurl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.success) {
              listWrap.innerHTML = res.data.html;
            } else {
              prevBtn.disabled = false;
              prevBtn.textContent = '‹ Dates précédentes';
            }
          })
          .catch(function () {
            prevBtn.disabled = false;
            prevBtn.textContent = '‹ Dates précédentes';
          });
      });

      /* Clic sur un créneau disponible */
      widget.addEventListener('click', function (e) {
        var slot = e.target.closest('.cf-rdv-slot:not([disabled])');
        if (!slot) return;

        widget.querySelectorAll('.cf-rdv-slot').forEach(function (s) {
          s.classList.remove('cf-rdv-slot--active');
        });
        slot.classList.add('cf-rdv-slot--active');

        rdvForm.querySelector('.cf-rdv-event-id').value   = slot.dataset.eventId  || '0';
        rdvForm.querySelector('.cf-rdv-slot-debut').value = slot.dataset.debut    || '';
        rdvForm.querySelector('.cf-rdv-slot-fin').value   = slot.dataset.fin      || '';

        var typeInput = rdvForm.querySelector('[name="appt_type_id"]');
        if (typeInput) typeInput.value = slot.dataset.typeId || '';

        var nonceInput = rdvForm.querySelector('[name="cfeb_nonce"]') || rdvForm.querySelector('[name="nonce"]');
        if (nonceInput && typeof cfeb !== 'undefined') nonceInput.value = cfeb.nonce;

        /* Modalité déjà déterminée par le créneau choisi (présentiel ou
           distanciel réglé sur cette plage horaire côté admin) : on
           pré-coche le bon choix et on masque la question au client — le
           champ reste dans le formulaire (juste caché) pour que sa valeur
           soit bien envoyée à la validation. */
        var modaliteFieldset = rdvForm.querySelector('.cf-rdv-field-modalite');
        if (modaliteFieldset) {
          var slotModalite = slot.dataset.modalite;
          var radio = (slotModalite === 'presentiel' || slotModalite === 'distanciel')
            ? modaliteFieldset.querySelector('input[value="' + slotModalite + '"]')
            : null;
          if (radio) {
            radio.checked = true;
            modaliteFieldset.style.display = 'none';
          } else {
            modaliteFieldset.style.display = '';
          }
        }

        var debutParts = (slot.dataset.debut || '').split('T');
        var dateLabel  = '';
        if (debutParts[0]) {
          try {
            dateLabel = new Date(debutParts[0] + 'T00:00:00').toLocaleDateString('fr-FR', {
              weekday: 'long', day: 'numeric', month: 'long'
            });
          } catch (err) { dateLabel = debutParts[0]; }
        }
        var timeText = slot.childNodes[0] ? slot.childNodes[0].textContent.trim() : '';
        if (slotTitle) slotTitle.textContent = (dateLabel ? dateLabel + ' — ' : '') + timeText;

        formContainer.style.display = '';
        if (msgDiv) { msgDiv.style.display = 'none'; msgDiv.textContent = ''; }
        formContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });

      /* Annuler */
      var cancelBtn = rdvForm.querySelector('.cf-rdv-cancel');
      if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
          formContainer.style.display = 'none';
          widget.querySelectorAll('.cf-rdv-slot').forEach(function (s) {
            s.classList.remove('cf-rdv-slot--active');
          });
        });
      }

      /* Soumettre */
      rdvForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var submitBtn = rdvForm.querySelector('.cf-rdv-submit');
        var fd = new FormData(rdvForm);

        if (!fd.get('action'))  fd.set('action', 'cfeb_reserver');
        if (typeof cfeb !== 'undefined' && !fd.get('nonce')) fd.set('nonce', cfeb.nonce);

        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Traitement…'; }
        if (msgDiv)    { msgDiv.style.display = 'none'; msgDiv.textContent = ''; }

        var ajaxurl = typeof cfeb !== 'undefined' ? cfeb.ajaxurl : '/wp-admin/admin-ajax.php';
        fetch(ajaxurl, {
          method:  'POST',
          body:    fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.success) {
              formContainer.innerHTML =
                '<div style="padding:20px;background:#f0fdf4;border-radius:8px;text-align:center;">'
                + '<p style="font-size:22px;margin:0 0 8px">&#x1F389;</p>'
                + '<p style="font-weight:600;color:#166534;margin:0 0 6px">R\xe9servation confirm\xe9e\xa0!</p>'
                + '<p style="color:#4b7a55;margin:0;font-size:14px">' + (res.data.message || '') + '</p>'
                + '</div>';
              widget.querySelectorAll('.cf-rdv-slot--active').forEach(function (s) {
                s.disabled = true;
                s.classList.add('cf-rdv-slot--full');
                s.classList.remove('cf-rdv-slot--active');
              });
              if (res.data.redirect_url) {
                setTimeout(function () { window.location.href = res.data.redirect_url; }, 1500);
              }
            } else {
              if (msgDiv) {
                msgDiv.textContent       = (res.data && res.data.message) ? res.data.message : 'Une erreur est survenue.';
                msgDiv.style.display     = '';
                msgDiv.style.color       = '#b91c1c';
                msgDiv.style.padding     = '10px';
                msgDiv.style.background  = '#fef2f2';
                msgDiv.style.borderRadius = '6px';
                msgDiv.style.marginTop   = '10px';
              }
              if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Confirmer la r\xe9servation'; }
            }
          })
          .catch(function () {
            if (msgDiv) {
              msgDiv.textContent   = 'Erreur r\xe9seau. Veuillez r\xe9essayer.';
              msgDiv.style.display = '';
            }
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Confirmer la r\xe9servation'; }
          });
      });
    });
  }

})();
