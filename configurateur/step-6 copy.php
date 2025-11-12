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
  <h2 class="mb-4">6. Récapitulatif de votre configuration</h2>

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

              <div class="recap-abonnements mb-4">
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

              <div class="recap-materiels mb-4">
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

              <div class="recap-installations mb-4">
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

    <div class="recap-global mt-5">
      <div class="mt-4 d-flex flex-column flex-md-row justify-content-between gap-2">
        <button class="btn btn-outline-secondary btn-precedent" data-step="5">← Étape précédente</button>
        <button id="btn-commander" class="btn btn-success">Valider ma configuration</button>
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
