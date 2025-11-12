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

      <?php get_template_part('template-parts/reassurance'); ?>
    </div>
  </div>
</div>