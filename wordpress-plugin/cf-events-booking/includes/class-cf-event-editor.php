<?php
/**
 * class-cf-event-editor.php
 *
 * Interface d'édition d'un événement : champs regroupés par thème
 * (quand / où / billetterie) et surtout un aperçu en direct montrant
 * le rendu réel — carte du site et extrait Google — mis à jour à
 * chaque frappe, sans avoir à enregistrer.
 *
 * Déplacé depuis le thème poivre-sens (inc/event-meta-box.php) le
 * 2026-09-04 : éditer un événement est une responsabilité du plugin,
 * pas du thème. Ce fichier continue toutefois d'appeler les fonctions
 * de lecture que le thème actif fournit (ps_evt_cpt(), ps_evt_champ(),
 * ps_evt_plugin_actif(), ps_evt_liste_types(), ps_evt_places_restantes()
 * — voir inc/event-data.php du thème poivre-sens) : il suppose donc
 * qu'un thème les définissant est actif. Le rendu public (agenda,
 * fiche événement…) reste du ressort du thème, qui lit les événements
 * via ces mêmes fonctions quelle que soit leur source.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CF_Event_Editor {

	/** Types d'événement proposés. */
	public static function types() {
		return [
			'spectacle' => __( 'Spectacle vivant', 'cf-events' ),
			'jam'       => __( 'Jam contact-improvisation', 'cf-events' ),
			'atelier'   => __( 'Atelier / Stage', 'cf-events' ),
			'residence' => __( 'Résidence', 'cf-events' ),
			'concert'   => __( 'Concert', 'cf-events' ),
			'autre'     => __( 'Autre', 'cf-events' ),
		];
	}

	/*
	 * Cet écran sert quelle que soit la source des événements. Le plugin
	 * CF, lui, ne sait créer des événements qu'en série depuis ses
	 * modèles de créneaux : sans cette métaboîte, on ne pourrait plus
	 * saisir à la main la date, le lieu ou le tarif d'un spectacle.
	 */
	public static function register_meta_box() {
		add_meta_box(
			'ps_evt_details',
			__( 'Détails de l\'événement', 'cf-events' ),
			[ __CLASS__, 'render' ],
			ps_evt_cpt(),
			'normal',
			'high'
		);
	}

	public static function render( $post ) {
		wp_nonce_field( 'ps_evt_save', 'ps_evt_nonce' );

		$date        = ps_evt_champ( $post->ID, 'date' );
		$heure       = ps_evt_champ( $post->ID, 'heure' );
		$heure_fin   = ps_evt_champ( $post->ID, 'heure_fin' );
		$lieu        = ps_evt_champ( $post->ID, 'lieu' );
		$adresse     = ps_evt_champ( $post->ID, 'adresse' );
		$ville       = ps_evt_champ( $post->ID, 'ville' );
		$type        = ps_evt_champ( $post->ID, 'type' );
		$prix        = ps_evt_champ( $post->ID, 'prix' );
		$billetterie = ps_evt_champ( $post->ID, 'billetterie' );
		$complet     = ps_evt_champ( $post->ID, 'complet' );

		// Champs propres au plugin de réservation : sans équivalent dans
		// l'ancien module, donc affichés seulement quand il pilote l'événement.
		$plugin_actif   = ps_evt_plugin_actif();
		$all_day        = $plugin_actif ? ps_evt_champ( $post->ID, 'all_day' )       : false;
		$lien_visio     = $plugin_actif ? ps_evt_champ( $post->ID, 'lien_visio' )    : '';
		$max_places     = $plugin_actif ? ps_evt_champ( $post->ID, 'max_places' )    : 0;
		$deadline       = $plugin_actif ? ps_evt_champ( $post->ID, 'deadline' )      : '';
		$email_contact  = $plugin_actif ? ps_evt_champ( $post->ID, 'email_contact' ) : '';
		$animateur      = $plugin_actif ? ps_evt_champ( $post->ID, 'animateur' )     : '';
		$statut_resa    = $plugin_actif ? ( get_post_meta( $post->ID, '_cfeb_statut', true ) ?: 'ouvert' ) : '';
		$statut_event   = $plugin_actif ? ps_evt_champ( $post->ID, 'statut_event' )  : 'publie';
		$featured       = $plugin_actif ? ps_evt_champ( $post->ID, 'featured' )      : false;
		$places_restantes = $plugin_actif ? ps_evt_places_restantes( $post->ID ) : null;

		// Aux types du plugin s'ajoutent les catégories déjà créées côté
		// plugin, pour ne pas perdre celles nées de la migration.
		$types  = self::types() + ps_evt_liste_types();
		$vignette = has_post_thumbnail( $post->ID ) ? get_the_post_thumbnail_url( $post->ID, 'evt-card' ) : '';
		?>
		<style>
		.ps-evt{ display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:24px; padding:6px 0 2px; }
		@media(max-width:1100px){ .ps-evt{ grid-template-columns:1fr; } }

		.ps-evt-sec{ border:1px solid #e3e3e3; border-radius:8px; padding:16px 18px 18px; margin-bottom:16px; background:#fff; }
		.ps-evt-sec:last-child{ margin-bottom:0; }
		.ps-evt-sec__t{ font-size:12px; font-weight:600; letter-spacing:.08em; text-transform:uppercase;
		                color:#646970; margin:0 0 14px; display:flex; align-items:center; gap:7px; }
		.ps-evt-grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px 20px; }
		.ps-evt-grid--3{ grid-template-columns:1fr 1fr 1fr; }
		.ps-evt-full{ grid-column:1/-1; }
		@media(max-width:782px){ .ps-evt-grid, .ps-evt-grid--3{ grid-template-columns:1fr; } }

		.ps-evt-lab{ display:block; font-weight:600; font-size:13px; margin-bottom:5px; color:#1d2327; }
		.ps-evt-hint{ font-size:11px; color:#8c8f94; margin:4px 0 0; line-height:1.5; }
		.ps-evt input[type=text], .ps-evt input[type=date], .ps-evt input[type=time],
		.ps-evt input[type=url], .ps-evt select{
		    width:100%; padding:7px 10px; border:1px solid #dcdcde; border-radius:4px;
		    font-size:13px; background:#fff; box-sizing:border-box;
		}
		.ps-evt input:focus, .ps-evt select:focus{ outline:none; border-color:#c28b36; box-shadow:0 0 0 2px rgba(194,139,54,.18); }
		.ps-evt-check{ display:flex; align-items:center; gap:9px; font-size:13px; color:#1d2327; padding:9px 12px;
		               border:1px solid #dcdcde; border-radius:4px; background:#fafafa; }
		.ps-evt-check input{ margin:0; }

		/* ── Colonne aperçu ────────────────────────────────── */
		.ps-evt-side{ position:sticky; top:36px; align-self:start; }
		.ps-evt-side__t{ font-size:12px; font-weight:600; letter-spacing:.08em; text-transform:uppercase;
		                 color:#646970; margin:0 0 10px; }

		.ps-evt-card{
		    background:#100e0b; padding:26px 24px; position:relative; overflow:hidden;
		    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; border-radius:6px;
		}
		.ps-evt-card::before{ content:''; position:absolute; left:0; top:0; bottom:0; width:2px;
		                      background:linear-gradient(to bottom,#9e3710,#c28b36); }
		.ps-evt-card__img{ width:100%; aspect-ratio:16/9; object-fit:cover; margin-bottom:18px;
		                   filter:brightness(.8) saturate(.85); display:block; border-radius:2px; }
		.ps-evt-card__ph{ width:100%; aspect-ratio:16/9; margin-bottom:18px; border-radius:2px;
		                  background:repeating-linear-gradient(45deg,#1a1713,#1a1713 8px,#181510 8px,#181510 16px);
		                  display:flex; align-items:center; justify-content:center;
		                  color:#5f5747; font-size:11px; letter-spacing:.1em; text-transform:uppercase; }
		.ps-evt-card__date{ font-size:10.5px; letter-spacing:.22em; text-transform:uppercase; color:#c28b36; margin-bottom:12px; }
		.ps-evt-card__type{ display:inline-block; font-size:10px; letter-spacing:.12em; text-transform:uppercase;
		                    color:rgba(194,139,54,.75); border:1px solid rgba(194,139,54,.28);
		                    padding:2px 8px; margin-bottom:14px; }
		.ps-evt-card__t{ font-family:Georgia,'Cormorant Garamond',serif; font-size:20px; font-weight:400;
		                 color:#ece3cb; line-height:1.25; margin:0 0 10px; }
		.ps-evt-card__lieu{ font-size:12px; color:#7f7463; margin:0 0 4px; }
		.ps-evt-card__prix{ font-size:12px; color:#7f7463; margin:0; }
		.ps-evt-card__complet{ display:inline-block; margin-top:12px; font-size:10px; letter-spacing:.1em;
		                       text-transform:uppercase; color:#eb8e6f; border:1px solid rgba(158,55,16,.4); padding:2px 8px; }
		.ps-evt-card__btn{ display:inline-block; margin-top:14px; font-size:10.5px; letter-spacing:.14em;
		                   text-transform:uppercase; color:#080705; background:#c28b36; padding:7px 14px; }

		/* Aperçu résultat Google */
		.ps-evt-goog{ border:1px solid #e3e3e3; border-radius:6px; padding:14px 16px; background:#fff; margin-top:16px;
		              font-family:arial,sans-serif; }
		.ps-evt-goog__url{ font-size:12px; color:#4d5156; margin-bottom:2px; }
		.ps-evt-goog__t{ font-size:17px; color:#1a0dab; line-height:1.3; margin:0 0 3px; }
		.ps-evt-goog__d{ font-size:12.5px; color:#4d5156; line-height:1.55; margin:0; }
		.ps-evt-goog__rich{ font-size:12.5px; color:#4d5156; margin-top:6px; padding-top:6px; border-top:1px solid #f0f0f0; }
		.ps-evt-goog__rich b{ color:#1a0dab; font-weight:400; }

		.ps-evt-warn{ margin-top:12px; font-size:12px; line-height:1.5; color:#8a6d3b;
		              background:#fcf8e3; border:1px solid #faebcc; border-radius:4px; padding:9px 11px; display:none; }
		.ps-evt-warn.on{ display:block; }
		</style>

		<div class="ps-evt" id="ps-evt">

		  <div>
		    <div class="ps-evt-sec">
		      <h4 class="ps-evt-sec__t">🗓 <?= esc_html__( 'Quand', 'cf-events' ) ?></h4>
		      <div class="ps-evt-grid ps-evt-grid--3">
		        <div>
		          <label class="ps-evt-lab" for="evt_date"><?= esc_html__( 'Date *', 'cf-events' ) ?></label>
		          <input type="date" id="evt_date" name="evt_date" value="<?= esc_attr( $date ) ?>">
		          <p class="ps-evt-hint"><?= esc_html__( 'Sans date, l\'événement n\'apparaît ni dans l\'agenda ni sur Google.', 'cf-events' ) ?></p>
		        </div>
		        <div>
		          <label class="ps-evt-lab" for="evt_heure"><?= esc_html__( 'Début', 'cf-events' ) ?></label>
		          <input type="time" id="evt_heure" name="evt_heure" value="<?= esc_attr( $heure ) ?>">
		        </div>
		        <div>
		          <label class="ps-evt-lab" for="evt_heure_fin"><?= esc_html__( 'Fin', 'cf-events' ) ?></label>
		          <input type="time" id="evt_heure_fin" name="evt_heure_fin" value="<?= esc_attr( $heure_fin ) ?>">
		          <p class="ps-evt-hint"><?= esc_html__( 'Une fin plus tôt que le début = après minuit.', 'cf-events' ) ?></p>
		        </div>
		        <div class="ps-evt-full">
		          <label class="ps-evt-lab" for="evt_type"><?= esc_html__( 'Type d\'événement', 'cf-events' ) ?></label>
		          <select id="evt_type" name="evt_type">
		            <?php foreach ( $types as $k => $v ): ?>
		            <option value="<?= esc_attr( $k ) ?>" <?= selected( $type, $k, false ) ?>><?= esc_html( $v ) ?></option>
		            <?php endforeach; ?>
		          </select>
		        </div>
		        <?php if ( $plugin_actif ): ?>
		        <div class="ps-evt-full">
		          <label class="ps-evt-check">
		            <input type="checkbox" id="evt_all_day" name="evt_all_day" value="1" <?= checked( $all_day, true, false ) ?>>
		            <?= esc_html__( 'Journée entière (stage, résidence sur plusieurs jours…)', 'cf-events' ) ?>
		          </label>
		          <p class="ps-evt-hint"><?= esc_html__( 'Les horaires ci-dessus sont alors ignorés à l\'affichage.', 'cf-events' ) ?></p>
		        </div>
		        <?php endif; ?>
		      </div>
		    </div>

		    <div class="ps-evt-sec">
		      <h4 class="ps-evt-sec__t">📍 <?= esc_html__( 'Où', 'cf-events' ) ?></h4>
		      <div class="ps-evt-grid">
		        <div class="ps-evt-full">
		          <label class="ps-evt-lab" for="evt_lieu"><?= esc_html__( 'Lieu', 'cf-events' ) ?></label>
		          <input type="text" id="evt_lieu" name="evt_lieu" value="<?= esc_attr( $lieu ) ?>" placeholder="<?= esc_attr__( 'Théâtre Athénor', 'cf-events' ) ?>">
		        </div>
		        <div>
		          <label class="ps-evt-lab" for="evt_adresse"><?= esc_html__( 'Adresse', 'cf-events' ) ?></label>
		          <input type="text" id="evt_adresse" name="evt_adresse" value="<?= esc_attr( $adresse ) ?>" placeholder="<?= esc_attr__( '12 rue du Port', 'cf-events' ) ?>">
		        </div>
		        <div>
		          <label class="ps-evt-lab" for="evt_ville"><?= esc_html__( 'Ville', 'cf-events' ) ?></label>
		          <input type="text" id="evt_ville" name="evt_ville" value="<?= esc_attr( $ville ) ?>" placeholder="<?= esc_attr__( 'Saint-Nazaire', 'cf-events' ) ?>">
		          <p class="ps-evt-hint"><?= esc_html__( 'Sert aussi de filtre dans l\'agenda.', 'cf-events' ) ?></p>
		        </div>
		        <?php if ( $plugin_actif ): ?>
		        <div class="ps-evt-full">
		          <label class="ps-evt-lab" for="evt_lien_visio"><?= esc_html__( 'Lien de visioconférence', 'cf-events' ) ?></label>
		          <input type="url" id="evt_lien_visio" name="evt_lien_visio" value="<?= esc_attr( $lien_visio ) ?>" placeholder="https://meet.google.com/…">
		          <p class="ps-evt-hint"><?= esc_html__( 'Pour un atelier en ligne ou hybride. Envoyé aux personnes inscrites.', 'cf-events' ) ?></p>
		        </div>
		        <?php endif; ?>
		      </div>
		    </div>

		    <div class="ps-evt-sec">
		      <h4 class="ps-evt-sec__t">🎟 <?= esc_html__( 'Tarif et billetterie', 'cf-events' ) ?></h4>
		      <div class="ps-evt-grid">
		        <div>
		          <label class="ps-evt-lab" for="evt_prix"><?= esc_html__( 'Tarif', 'cf-events' ) ?></label>
		          <input type="text" id="evt_prix" name="evt_prix" value="<?= esc_attr( $prix ) ?>" placeholder="<?= esc_attr__( '12€ · gratuit · prix libre', 'cf-events' ) ?>">
		          <p class="ps-evt-hint"><?= esc_html__( '« 12€ » et « gratuit » sont compris par Google. Un texte libre s\'affiche mais n\'annonce aucun prix.', 'cf-events' ) ?></p>
		        </div>
		        <div>
		          <label class="ps-evt-lab" for="evt_billetterie"><?= esc_html__( 'Lien billetterie', 'cf-events' ) ?></label>
		          <input type="url" id="evt_billetterie" name="evt_billetterie" value="<?= esc_attr( $billetterie ) ?>" placeholder="https://">
		          <p class="ps-evt-hint"><?= esc_html__( 'Billetweb, HelloAsso… Laissez vide pour utiliser la réservation en ligne du site (jauge, email de confirmation) plutôt qu\'un lien externe.', 'cf-events' ) ?></p>
		        </div>
		        <?php if ( ! $plugin_actif ): ?>
		        <div class="ps-evt-full">
		          <label class="ps-evt-check">
		            <input type="checkbox" id="evt_complet" name="evt_complet" value="1" <?= checked( $complet, '1', false ) ?>>
		            <?= esc_html__( 'Événement complet', 'cf-events' ) ?>
		          </label>
		        </div>
		        <?php endif; ?>
		      </div>
		    </div>

		    <?php if ( $plugin_actif ): ?>
		    <div class="ps-evt-sec">
		      <h4 class="ps-evt-sec__t">🎫 <?= esc_html__( 'Réservations', 'cf-events' ) ?></h4>
		      <div class="ps-evt-grid">
		        <div>
		          <label class="ps-evt-lab" for="evt_max_places"><?= esc_html__( 'Places disponibles', 'cf-events' ) ?></label>
		          <input type="number" id="evt_max_places" name="evt_max_places" value="<?= esc_attr( $max_places ) ?>" min="0" step="1">
		          <p class="ps-evt-hint">
		            <?php if ( $max_places > 0 && $places_restantes !== null ): ?>
		              <?= esc_html( sprintf(
		                    /* translators: %d: number of remaining spots */
		                    _n( '%d place restante à ce jour.', '%d places restantes à ce jour.', $places_restantes, 'cf-events' ),
		                    $places_restantes
		                  ) ) ?>
		            <?php else: ?>
		              <?= esc_html__( '0 = illimité. Au-delà, l\'événement passe automatiquement « Complet ».', 'cf-events' ) ?>
		            <?php endif; ?>
		          </p>
		        </div>
		        <div>
		          <label class="ps-evt-lab" for="evt_statut"><?= esc_html__( 'Statut des réservations', 'cf-events' ) ?></label>
		          <select id="evt_statut" name="evt_statut">
		            <option value="ouvert" <?= selected( $statut_resa, 'ouvert', false ) ?>><?= esc_html__( 'Ouvert', 'cf-events' ) ?></option>
		            <option value="complet" <?= selected( $statut_resa, 'complet', false ) ?>><?= esc_html__( 'Complet (forcé manuellement)', 'cf-events' ) ?></option>
		            <option value="ferme" <?= selected( $statut_resa, 'ferme', false ) ?>><?= esc_html__( 'Fermé', 'cf-events' ) ?></option>
		          </select>
		          <p class="ps-evt-hint"><?= esc_html__( 'Laissez « Ouvert » si le nombre de places suffit à gérer le complet.', 'cf-events' ) ?></p>
		        </div>
		        <div>
		          <label class="ps-evt-lab" for="evt_deadline"><?= esc_html__( 'Date limite d\'inscription', 'cf-events' ) ?></label>
		          <input type="date" id="evt_deadline" name="evt_deadline" value="<?= esc_attr( $deadline ) ?>">
		          <p class="ps-evt-hint"><?= esc_html__( 'Ferme les réservations après cette date. Laissez vide sinon.', 'cf-events' ) ?></p>
		        </div>
		        <div>
		          <label class="ps-evt-lab" for="evt_email_contact"><?= esc_html__( 'Email de contact pour cet événement', 'cf-events' ) ?></label>
		          <input type="text" id="evt_email_contact" name="evt_email_contact" value="<?= esc_attr( $email_contact ) ?>" placeholder="<?= esc_attr__( 'vide = adresse habituelle', 'cf-events' ) ?>">
		          <p class="ps-evt-hint"><?= esc_html__( 'Reçoit les notifications de réservation pour cet événement précis.', 'cf-events' ) ?></p>
		        </div>
		      </div>
		    </div>

		    <div class="ps-evt-sec">
		      <h4 class="ps-evt-sec__t">👁 <?= esc_html__( 'Visibilité', 'cf-events' ) ?></h4>
		      <div class="ps-evt-grid">
		        <div>
		          <label class="ps-evt-lab" for="evt_statut_event"><?= esc_html__( 'État de l\'événement', 'cf-events' ) ?></label>
		          <select id="evt_statut_event" name="evt_statut_event">
		            <option value="publie"  <?= selected( $statut_event, 'publie', false ) ?>><?= esc_html__( 'Publié', 'cf-events' ) ?></option>
		            <option value="reporte" <?= selected( $statut_event, 'reporte', false ) ?>><?= esc_html__( 'Reporté', 'cf-events' ) ?></option>
		            <option value="annule"  <?= selected( $statut_event, 'annule', false ) ?>><?= esc_html__( 'Annulé', 'cf-events' ) ?></option>
		          </select>
		          <p class="ps-evt-hint"><?= esc_html__( '« Reporté » et « Annulé » gardent la page en ligne, avec un bandeau visible.', 'cf-events' ) ?></p>
		        </div>
		        <div>
		          <label class="ps-evt-lab" for="evt_animateur"><?= esc_html__( 'Intervenant·e / animé par', 'cf-events' ) ?></label>
		          <input type="text" id="evt_animateur" name="evt_animateur" value="<?= esc_attr( $animateur ) ?>" placeholder="<?= esc_attr__( 'facultatif', 'cf-events' ) ?>">
		        </div>
		        <div class="ps-evt-full">
		          <label class="ps-evt-check">
		            <input type="checkbox" id="evt_featured" name="evt_featured" value="1" <?= checked( $featured, true, false ) ?>>
		            <?= esc_html__( 'Mettre en avant (mis en évidence dans l\'agenda)', 'cf-events' ) ?>
		          </label>
		        </div>
		      </div>
		    </div>
		    <?php endif; ?>
		  </div>

		  <aside class="ps-evt-side">
		    <p class="ps-evt-side__t"><?= esc_html__( 'Aperçu en direct', 'cf-events' ) ?></p>

		    <div class="ps-evt-card">
		      <?php if ( $vignette ): ?>
		      <img class="ps-evt-card__img" src="<?= esc_url( $vignette ) ?>" alt="">
		      <?php else: ?>
		      <div class="ps-evt-card__ph"><?= esc_html__( 'Image à la une', 'cf-events' ) ?></div>
		      <?php endif; ?>
		      <div class="ps-evt-card__date" id="ps-pv-date">—</div>
		      <span class="ps-evt-card__type" id="ps-pv-type"></span>
		      <h3 class="ps-evt-card__t" id="ps-pv-titre"><?= esc_html__( 'Titre de l\'événement', 'cf-events' ) ?></h3>
		      <p class="ps-evt-card__lieu" id="ps-pv-lieu"></p>
		      <p class="ps-evt-card__prix" id="ps-pv-prix"></p>
		      <div id="ps-pv-etat"></div>
		    </div>

		    <div class="ps-evt-goog">
		      <div class="ps-evt-goog__url">cie.poivresens.fr › evenements</div>
		      <p class="ps-evt-goog__t" id="ps-pv-gt"><?= esc_html__( 'Titre de l\'événement', 'cf-events' ) ?></p>
		      <p class="ps-evt-goog__d" id="ps-pv-gd"><?= esc_html__( 'Compagnie Poivre &amp; Sens', 'cf-events' ) ?></p>
		      <div class="ps-evt-goog__rich" id="ps-pv-grich"></div>
		    </div>

		    <div class="ps-evt-warn" id="ps-pv-warn"></div>
		  </aside>
		</div>

		<script>
		(function(){
		  var $  = function(id){ return document.getElementById(id); };
		  var val = function(id){ var el = $(id); return el ? el.value : ''; };
		  var champs = [
		    'evt_date', 'evt_heure', 'evt_heure_fin', 'evt_lieu', 'evt_adresse', 'evt_ville',
		    'evt_type', 'evt_prix', 'evt_billetterie', 'evt_complet', 'evt_all_day',
		    'evt_statut', 'evt_statut_event', 'evt_animateur', 'evt_lien_visio'
		  ];
		  var LIBELLES = <?= wp_json_encode( $types ) ?>;
		  var ETATS = {
		    annule:  <?= wp_json_encode( __( 'Annulé', 'cf-events' ) ) ?>,
		    reporte: <?= wp_json_encode( __( 'Reporté', 'cf-events' ) ) ?>
		  };

		  function titre() {
		    // Gutenberg d'abord, éditeur classique en repli.
		    try {
		      if (window.wp && wp.data && wp.data.select('core/editor')) {
		        var t = wp.data.select('core/editor').getEditedPostAttribute('title');
		        if (t) return t;
		      }
		    } catch (e) {}
		    var cl = document.getElementById('title');
		    return (cl && cl.value) || '';
		  }

		  function dateLongue(iso, h, hf, jourEntier) {
		    if (!iso) return '';
		    var d = new Date(iso + 'T' + (h || '00:00'));
		    if (isNaN(d)) return '';
		    var s = new Intl.DateTimeFormat('fr-FR', { weekday:'long', day:'numeric', month:'long', year:'numeric' }).format(d);
		    if (!jourEntier) {
		      if (h)  s += ' · ' + h.replace(':', 'h');
		      if (hf) s += ' — ' + hf.replace(':', 'h');
		    }
		    return s;
		  }

		  function maj() {
		    var date = val('evt_date'), h = val('evt_heure'), hf = val('evt_heure_fin');
		    var lieu = val('evt_lieu'), ville = val('evt_ville');
		    var prix = val('evt_prix'), billet = val('evt_billetterie');
		    var jourEntier = $('evt_all_day') ? $('evt_all_day').checked : false;
		    // Ancien module : case à cocher. Plugin : liste déroulante Ouvert/Complet/Fermé.
		    var statutResa = $('evt_statut') ? val('evt_statut')
		                    : ($('evt_complet') && $('evt_complet').checked ? 'complet' : 'ouvert');
		    var statutEvt  = val('evt_statut_event') || 'publie';
		    var animateur  = val('evt_animateur');
		    var visio      = val('evt_lien_visio');
		    var t = titre() || <?= wp_json_encode( __( 'Titre de l\'événement', 'cf-events' ) ) ?>;

		    // Griser les horaires en journée entière : évite de laisser croire
		    // qu'ils comptent alors que l'affichage les ignore.
		    [$('evt_heure'), $('evt_heure_fin')].forEach(function (el) {
		      if (el) el.disabled = jourEntier;
		    });

		    $('ps-pv-titre').textContent = t;
		    $('ps-pv-gt').textContent    = t;

		    var dl = dateLongue(date, h, hf, jourEntier);
		    $('ps-pv-date').textContent = dl || <?= wp_json_encode( __( 'Date à définir', 'cf-events' ) ) ?>;

		    $('ps-pv-type').textContent = LIBELLES[val('evt_type')] || '';

		    var ouTxt = [lieu, ville].filter(Boolean).join(' · ');
		    if (visio) ouTxt = ouTxt ? (ouTxt + ' · ' + <?= wp_json_encode( __( 'en ligne', 'cf-events' ) ) ?>) : <?= wp_json_encode( __( 'En ligne', 'cf-events' ) ) ?>;
		    $('ps-pv-lieu').textContent = ouTxt ? '📍 ' + ouTxt : '';
		    $('ps-pv-prix').textContent = [prix ? '🎟 ' + prix : '', animateur ? '· ' + animateur : ''].filter(Boolean).join(' ');

		    var etat = '';
		    if (statutEvt === 'annule' || statutEvt === 'reporte') {
		      etat = '<span class="ps-evt-card__complet">' + ETATS[statutEvt] + '</span>';
		    } else if (statutResa === 'complet') {
		      etat = '<span class="ps-evt-card__complet"><?= esc_js( __( 'Complet', 'cf-events' ) ) ?></span>';
		    } else if (statutResa === 'ferme') {
		      etat = '<span class="ps-evt-card__complet"><?= esc_js( __( 'Réservations fermées', 'cf-events' ) ) ?></span>';
		    } else if (billet) {
		      etat = '<span class="ps-evt-card__btn"><?= esc_js( __( 'Réserver', 'cf-events' ) ) ?></span>';
		    }
		    $('ps-pv-etat').innerHTML = etat;

		    // Extrait Google : ce que les données structurées permettent d'afficher
		    var rich = [];
		    if (dl) rich.push('<b>' + dl + '</b>');
		    if (ouTxt) rich.push(ouTxt);
		    if (prix) rich.push(prix);
		    $('ps-pv-grich').innerHTML = rich.join(' · ');
		    $('ps-pv-gd').textContent = [LIBELLES[val('evt_type')], ouTxt].filter(Boolean).join(' — ')
		      || <?= wp_json_encode( __( 'Compagnie Poivre & Sens', 'cf-events' ) ) ?>;

		    // Avertissements utiles plutôt que silence
		    var avert = [];
		    if (!date) avert.push(<?= wp_json_encode( __( 'Sans date, l\'événement n\'apparaîtra pas dans l\'agenda ni dans les résultats Google.', 'cf-events' ) ) ?>);
		    if (date && !lieu && !ville && !visio) avert.push(<?= wp_json_encode( __( 'Ajoutez un lieu, une ville ou un lien de visio : Google affiche l\'endroit dans ses résultats.', 'cf-events' ) ) ?>);
		    if (statutEvt === 'annule' || statutEvt === 'reporte') avert.push(<?= wp_json_encode( __( 'Un bandeau signalera ce changement sur la page de l\'événement.', 'cf-events' ) ) ?>);
		    var w = $('ps-pv-warn');
		    w.innerHTML = avert.join('<br>');
		    w.classList.toggle('on', avert.length > 0);
		  }

		  champs.forEach(function(id){
		    var el = $(id);
		    if (el) { el.addEventListener('input', maj); el.addEventListener('change', maj); }
		  });

		  // Suivre le titre saisi dans Gutenberg
		  try {
		    if (window.wp && wp.data && wp.data.subscribe) {
		      var dernier = null;
		      wp.data.subscribe(function(){
		        var t = titre();
		        if (t !== dernier) { dernier = t; maj(); }
		      });
		    }
		  } catch (e) {}
		  var clas = document.getElementById('title');
		  if (clas) clas.addEventListener('input', maj);

		  maj();
		})();
		</script>
		<?php
	}

	/* ── Enregistrement ───────────────────────────────────────── */

	/** Relit le formulaire sous la forme des champs hérités de l'ancien module. */
	public static function read_form( array $post ) {
		$champs = [
			'_evt_date'        => [ 'evt_date',        'sanitize_text_field' ],
			'_evt_heure'       => [ 'evt_heure',       'sanitize_text_field' ],
			'_evt_heure_fin'   => [ 'evt_heure_fin',   'sanitize_text_field' ],
			'_evt_lieu'        => [ 'evt_lieu',        'sanitize_text_field' ],
			'_evt_adresse'     => [ 'evt_adresse',     'sanitize_text_field' ],
			'_evt_ville'       => [ 'evt_ville',       'sanitize_text_field' ],
			'_evt_type'        => [ 'evt_type',        'sanitize_text_field' ],
			'_evt_prix'        => [ 'evt_prix',        'sanitize_text_field' ],
			'_evt_billetterie' => [ 'evt_billetterie', 'esc_url_raw' ],
		];

		$valeurs = [];
		foreach ( $champs as $meta_key => [ $champ, $nettoyage ] ) {
			if ( isset( $post[ $champ ] ) ) {
				$valeurs[ $meta_key ] = $nettoyage( wp_unslash( $post[ $champ ] ) );
			}
		}
		$valeurs['_evt_complet'] = isset( $post['evt_complet'] ) ? '1' : '';

		return $valeurs;
	}

	/**
	 * Champs propres au plugin de réservation (capacité, visibilité…), sans
	 * équivalent dans l'ancien module. Traités séparément de
	 * CF_Event_Migration::map_to_cfeb(), qui ne connaît que les champs hérités.
	 */
	public static function read_plugin_fields( array $post ) {
		$valeurs = [];

		if ( isset( $post['evt_max_places'] ) ) {
			$valeurs['_cfeb_max_places'] = max( 0, (int) $post['evt_max_places'] );
		}
		if ( isset( $post['evt_deadline'] ) ) {
			$valeurs['_cfeb_deadline'] = sanitize_text_field( wp_unslash( $post['evt_deadline'] ) );
		}
		if ( isset( $post['evt_email_contact'] ) ) {
			$valeurs['_cfeb_email_contact'] = sanitize_email( wp_unslash( $post['evt_email_contact'] ) );
		}
		if ( isset( $post['evt_animateur'] ) ) {
			$valeurs['_cfeb_animateur'] = sanitize_text_field( wp_unslash( $post['evt_animateur'] ) );
		}
		if ( isset( $post['evt_lien_visio'] ) ) {
			$valeurs['_cfeb_lien_visio'] = esc_url_raw( wp_unslash( $post['evt_lien_visio'] ) );
		}
		$valeurs['_cfeb_all_day']  = isset( $post['evt_all_day'] )  ? 1 : 0;
		$valeurs['_cfeb_featured'] = isset( $post['evt_featured'] ) ? 1 : 0;

		if ( isset( $post['evt_statut'] ) && in_array( $post['evt_statut'], [ 'ouvert', 'complet', 'ferme' ], true ) ) {
			$valeurs['_cfeb_statut'] = $post['evt_statut'];
		}
		if ( isset( $post['evt_statut_event'] ) && in_array( $post['evt_statut_event'], [ 'publie', 'annule', 'reporte' ], true ) ) {
			$valeurs['_cfeb_statut_event'] = $post['evt_statut_event'];
		}

		return $valeurs;
	}

	public static function save( $post_id ) {
		if ( get_post_type( $post_id ) !== ps_evt_cpt() ) return;
		if ( ! isset( $_POST['ps_evt_nonce'] ) || ! wp_verify_nonce( $_POST['ps_evt_nonce'], 'ps_evt_save' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$valeurs = self::read_form( $_POST );

		// Ancien module : les champs du formulaire sont déjà les bons.
		if ( ! ps_evt_plugin_actif() ) {
			foreach ( $valeurs as $cle => $valeur ) {
				update_post_meta( $post_id, $cle, $valeur );
			}
			return;
		}

		// Plugin : même traduction que celle de l'outil de migration.
		foreach ( CF_Event_Migration::map_to_cfeb( $valeurs ) as $cle => $valeur ) {
			update_post_meta( $post_id, $cle, $valeur );
		}

		// Champs propres au plugin (capacité, visibilité…), après la
		// traduction ci-dessus pour que le statut choisi ici l'emporte sur
		// le repli « ouvert » qu'elle pose par défaut.
		foreach ( self::read_plugin_fields( $_POST ) as $cle => $valeur ) {
			update_post_meta( $post_id, $cle, $valeur );
		}

		// Le type devient une catégorie du plugin.
		$type = (string) ( $valeurs['_evt_type'] ?? '' );
		if ( defined( 'CFEB_TAX' ) && taxonomy_exists( CFEB_TAX ) ) {
			if ( $type === '' ) {
				wp_set_object_terms( $post_id, [], CFEB_TAX );
			} else {
				$libelles = self::types();
				$existant = term_exists( $type, CFEB_TAX );
				$terme    = $existant ?: wp_insert_term( $libelles[ $type ] ?? ucfirst( $type ), CFEB_TAX, [ 'slug' => $type ] );
				if ( ! is_wp_error( $terme ) ) {
					wp_set_object_terms( $post_id, [ (int) $terme['term_id'] ], CFEB_TAX );
				}
			}
		}
	}
}

// Compatibilité : inc/event-data.php (thème poivre-sens) lit encore
// ps_evt_types() via function_exists() pour construire la liste des
// types affichés dans l'agenda — voir ps_evt_liste_types().
if ( ! function_exists( 'ps_evt_types' ) ) {
	function ps_evt_types() { return CF_Event_Editor::types(); }
}
