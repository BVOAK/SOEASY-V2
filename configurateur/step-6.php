<?php
/**
 * NOUVEAU step-6.php responsive avec flexbox/grid
 * Remplace les tableaux par des structures flexibles
 */

if (!function_exists('get_template_directory')) {
  require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}

require_once get_template_directory() . '/configurateur/functions-configurateur.php';
$adresses = soeasy_get_adresses_configurateur();
$duree = soeasy_get_selected_duree_engagement();
$mode = soeasy_get_selected_financement();
?>

<div class="config-step step-6 container py-4">

  <div class="header-configurateur">
    <?php get_template_part('configurateur/header'); ?>

    <ul class="config-steps nav nav-pills justify-content-center py-5">
      <li class="nav-item"><a class="nav-link completed" data-step="1" href="#">1. Adresses</a></li>
      <li class="nav-item"><a class="nav-link completed" data-step="2" href="#">2. Internet</a></li>
      <li class="nav-item"><a class="nav-link completed" data-step="3" href="#">3. Téléphone mobile</a></li>
      <li class="nav-item"><a class="nav-link completed" data-step="4" href="#">4. Téléphonie fixe</a></li>
      <li class="nav-item"><a class="nav-link completed" data-step="5" href="#">5. Frais d'installation</a></li>
      <li class="nav-item"><span class="nav-link active">6. Récapitulatif</span></li>
    </ul>

    <h2 class="mb-4 title-step"><span>6</span> Récapitulatif de votre configuration</h2>
  </div>

  <?php if (!empty($adresses)) : ?>
    <div class="accordion">
      <?php foreach ($adresses as $i => $adresse) : ?>
        <div class="accordion-item">
          <h2 class="accordion-header" id="heading-<?php echo $i; ?>">
            <button class="accordion-button <?php echo $i !== 0 ? 'collapsed' : ''; ?>" type="button" 
                    data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $i; ?>" 
                    aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $i; ?>">
              <i class="fas fa-map-marker-alt me-1"></i> <?php echo esc_html(soeasy_get_ville_longue($adresse['adresse'])); ?>
            </button>
          </h2>
          <div id="collapse-<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" 
               aria-labelledby="heading-<?php echo $i; ?>">
            <div class="accordion-body">

              <!-- ✅ NOUVEAU : Abonnements en Flexbox -->
              <div class="recap-section recap-abonnements mb-3">
                <h5 class="section-title">Abonnements</h5>
                <div class="products-grid abonnements-grid">
                  <!-- En-tête responsive -->
                  <div class="grid-header d-none d-md-grid">
                    <div class="col-product">Produit</div>
                    <div class="col-unit-price">Prix unitaire<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></div>
                    <div class="col-qty justify-content-center">Qté</div>
                    <div class="col-total-price justify-content-end">Total<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></div>
                  </div>
                  <!-- Contenu généré par JS -->
                  <div class="products-list">
                    <!-- À remplir dynamiquement -->
                  </div>
                </div>
              </div>

              <!-- ✅ NOUVEAU : Matériels en Flexbox -->
              <div class="recap-section recap-materiels mb-3">
                <h5 class="section-title">
                  Matériels & Accessoires
                </h5>
                <div class="products-grid materiels-grid">
                  <!-- En-tête responsive -->
                  <div class="grid-header d-none d-md-grid">
                    <div class="col-product">Produit</div>
                    <div class="col-unit-price">Prix unitaire<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></div>
                    <div class="col-qty justify-content-center">Qté</div>
                    <div class="col-total-price justify-content-end">Total<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></div>
                  </div>
                  <!-- Contenu généré par JS -->
                  <div class="products-list">
                    <!-- À remplir dynamiquement -->
                  </div>
                </div>
              </div>

              <!-- ✅ NOUVEAU : Frais d'installation en Flexbox -->
              <div class="recap-section recap-installations mb-3">
                <h5 class="section-title">
                  Frais de mise en service / Installation
                </h5>
                <div class="products-grid installations-grid">
                  <!-- En-tête responsive -->
                  <div class="grid-header d-none d-md-grid">
                    <div class="col-product">Produit</div>
                    <div class="col-unit-price">Prix unitaire<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></div>
                    <div class="col-qty justify-content-center">Qté</div>
                    <div class="col-total-price justify-content-end">Total<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></div>
                  </div>
                  <!-- Contenu généré par JS -->
                  <div class="products-list">
                    <!-- À remplir dynamiquement -->
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>  

    <div class="validation-section mt-3">
      <div class="d-flex flex-column flex-md-row justify-content-between gap-2" id="footer-buttons">
        <button class="btn btn-outline-secondary btn-precedent" data-step="5">
          Étape précédente
        </button>
        <div class="summary-info d-flex gap-4 align-items-center">
          <p class="mb-0">
            <i class="fas fa-check-circle text-success"></i>
            Configuration validée 
          </p>
          <button id="btn-commander" class="btn btn-success btn-primary mt-2">
            <img src="<?php echo get_template_directory_uri(); ?>/assets/img/shopping-cart.svg" alt="">
            Commander
          </button>
        </div>
      </div>
    </div>

  <?php else: ?>
    <div class="alert alert-warning">
      <h5><i class="fas fa-exclamation-triangle me-2"></i>Aucune adresse configurée</h5>
      <p class="mb-0">Veuillez retourner à l'étape 1 pour ajouter au moins une adresse.</p>
    </div>
  <?php endif; ?>

</div>