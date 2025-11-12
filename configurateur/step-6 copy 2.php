<?php
/**
 * Étape 5 – Récapitulatif de la configuration et validation commande
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
            <button class="accordion-button <?php echo $i !== 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $i; ?>" aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $i; ?>">
              <?php echo esc_html($adresse['adresse']); ?>
            </button>
          </h2>
          <div id="collapse-<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo $i; ?>">
            <div class="accordion-body">

              <div class="recap-abonnements mb-2">
                <h5>Abonnements</h5>
                <div class="table-responsive">
                  <table class="table">
                    <thead>
                      <tr>
                        <th class="colProduct">Produit</th>
                        <th class="colQty">Quantité</th>
                        <th class="colPrice">Prix unitaire<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></th>
                        <th class="colPrice">Total<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- À remplir dynamiquement via JS -->
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="recap-materiels mb-2">
                <h5>Matériels & Accessoires</h5>
                <div class="table-responsive">
                  <table class="table">
                    <thead>
                      <tr>
                        <th class="colProduct">Produit</th>
                        <th class="colQty">Quantité</th>
                        <th class="th-prix-unitaire colPrice">Prix unitaire<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></th>
                        <th class="th-prix-total colPrice">Total<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- À remplir dynamiquement via JS -->
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="recap-installations mb-1">
                <h5>Frais de mise en service / Installation</h5>
                <div class="table-responsive">
                  <table class="table">
                    <thead>
                      <tr>
                        <th class="colProduct">Produit</th>
                        <th class="colQty">Quantité</th>
                        <th class="th-prix-unitaire colPrice">Prix unitaire<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></th>
                        <th class="th-prix-total colPrice">Total<?php echo ($mode === 'leasing') ? '/mois' : ''; ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- À remplir dynamiquement via JS -->
                    </tbody>
                  </table>
                </div>
              </div>

            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="recap-global mt-3">
      <div class="d-flex flex-column flex-md-row justify-content-between gap-2" id="footer-buttons">
        <button class="btn btn-outline-secondary btn-precedent" data-step="5">Étape précédente</button>
        <button id="btn-commander" class="btn btn-primary btn-success"><img src="<?php echo get_template_directory_uri() ?>/assets/img/shopping-cart.svg" /> Valider ma configuration</button>
      </div>
    </div>
  <?php else : ?>
    <p>Aucune configuration trouvée.</p>
  <?php endif; ?>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
  <div id="toast-error" class="toast align-items-center text-white bg-danger border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
    </div>
  </div>
</div>
