<div class="d-none d-lg-block sidebar p-0" id="config-sidebar-container">
  <div id="config-sidebar">

    <div class="sidebar-header d-flex justify-content-between align-items-center border-bottom">
      <button class="btn btn-sm btn-outline-secondary sidebar-close-btn d-lg-none" aria-label="Fermer">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="blockParameter p-4">
      <h5 class="mb-3 title">
        <img src="<?php echo get_template_directory_uri() ?>/assets/img/cart-orange.svg" />Votre configuration
      </h5>
      <!-- Engagement -->
      <div class="mb-3">
        <label for="engagement" class="form-label">Durée d’engagement</label>
        <select id="engagement" class="form-select">
          <option value="0">Sans engagement</option>
          <option value="24">24 mois</option>
          <option value="36">36 mois</option>
          <option value="48">48 mois</option>
          <option value="63">63 mois</option>
        </select>
      </div>
      <!-- Mode de financement -->
      <div class="mb-0">
        <p class="form-label d-block mb-1">Financement du matériel</p>
        <div class="form-check checkbox-wrapper p-0 mb-0">
          <input class="form-check-input inp-cbx" type="radio" name="financement" id="financement_comptant"
            value="comptant" checked style="display: none;" />
          <label class="cbx form-check-label" for="financement_comptant">
            <span>
              <svg width="12px" height="9px" viewbox="0 0 12 9">
                <polyline points="1 5 4 8 11 1"></polyline>
              </svg>
            </span>
            <div>Achat comptant</div>
          </label>
        </div>
        <div class="form-check checkbox-wrapper p-0 g-4">
          <input class="form-check-input inp-cbx" type="radio" name="financement" id="financement_leasing"
            value="leasing" style="display: none;" />
          <label class="cbx form-check-label" for="financement_leasing">
            <span>
              <svg width="12px" height="9px" viewbox="0 0 12 9">
                <polyline points="1 5 4 8 11 1"></polyline>
              </svg>
            </span>
            <div>Location (leasing)</div>
          </label>
        </div>
      </div>
    </div>

    <div class="blockRecap p-4">
      <!-- Résumé dynamique -->
      <div id="config-recapitulatif">
        <div id="accordionSidebarRecap">
          <!-- Accordéons dynamiques injectés ici via JS -->
        </div>
      </div>

      <!-- Total -->
      <div id="config-sidebar-total" class="mt-4 border-top pt-3">
        <!-- Contenu injecté dynamiquement -->
      </div>


      <!-- Bouton Sauvegarder (visible seulement si connecté) -->
      <button type="button" class="btn btn-sm btn-success mt-4" id="btn-save-config" style="display: none;">
        <i class="fas fa-save me-1"></i> Sauvegarder
      </button>

      <!-- Indicateur auto-save -->
      <div id="auto-save-indicator" 
         class="small text-muted" 
         style="display: none;">
        <i class="fas fa-spinner fa-spin me-1"></i> Initialisation...
      </div>


      <?php get_template_part('template-parts/reassurance'); ?>

    </div>
  </div>
</div>



<!-- ============================================ -->
<!-- MODAL SAUVEGARDE CONFIGURATION -->
<!-- ============================================ -->
<div class="modal fade" id="modal-save-config" tabindex="-1" aria-labelledby="modalSaveConfigLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalSaveConfigLabel">
          <i class="fas fa-save me-2"></i>
          Sauvegarder la configuration
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="config-name-input" class="form-label">
            Nom de la configuration
          </label>
          <input type="text" class="form-control" id="config-name-input" placeholder="Ma configuration télécom"
            maxlength="100">
          <div class="form-text">
            Laissez vide pour générer un nom automatique
          </div>
        </div>

        <div id="save-config-message" class="alert" style="display: none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Annuler
        </button>
        <button type="button" class="btn btn-success" id="btn-confirm-save">
          <i class="fas fa-save me-1"></i>
          Sauvegarder
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================ -->
<!-- MODAL CONNEXION -->
<!-- ============================================ -->
<div class="modal fade" id="modal-login" tabindex="-1" aria-labelledby="modalLoginLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLoginLabel">
          <i class="fas fa-user-circle me-2"></i>
          Connexion requise
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-4">
          <i class="fas fa-info-circle me-1"></i>
          Connectez-vous pour sauvegarder votre configuration et la retrouver plus tard.
        </p>

        <form id="form-login-ajax">
          <div class="mb-3">
            <label for="login-username" class="form-label">
              <i class="fas fa-user me-1"></i>
              Nom d'utilisateur ou email
            </label>
            <input type="text" class="form-control" id="login-username" name="username" required autocomplete="username"
              placeholder="votre-email@exemple.com">
          </div>

          <div class="mb-3">
            <label for="login-password" class="form-label">
              <i class="fas fa-lock me-1"></i>
              Mot de passe
            </label>
            <input type="password" class="form-control" id="login-password" name="password" required
              autocomplete="current-password" placeholder="••••••••">
          </div>

          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="login-remember" name="remember" value="1">
            <label class="form-check-label" for="login-remember">
              Se souvenir de moi
            </label>
          </div>

          <div id="login-error-message" class="alert alert-danger" style="display: none;"></div>
        </form>

        <div class="text-center mt-3">
          <small class="text-muted">
            Pas encore de compte ?
            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" target="_blank">
              Créer un compte
            </a>
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Annuler
        </button>
        <button type="submit" form="form-login-ajax" class="btn btn-primary" id="btn-submit-login">
          <i class="fas fa-sign-in-alt me-1"></i>
          Se connecter
        </button>
      </div>
    </div>
  </div>
</div>