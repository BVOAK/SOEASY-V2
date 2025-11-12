<?php
/**
 * Étape 6 – Récapitulatif - AFFICHAGE LOCALSTORAGE
 * Version simplifiée : génération JavaScript uniquement
 */
if (!function_exists('get_template_directory')) {
  require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}
require_once get_template_directory() . '/configurateur/functions-configurateur.php';

// Récupérer les données de session comme fallback uniquement
$session_config = WC()->session->get('soeasy_configurateur', []);
$duree = soeasy_get_selected_duree_engagement();
$mode = soeasy_get_selected_financement();
$adresses = soeasy_get_adresses_configurateur();
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

  <!-- Loader pendant génération JS -->
  <div id="step6-loader" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Chargement...</span>
    </div>
    <p class="mt-3">Génération du récapitulatif...</p>
  </div>
  
  <!-- Contenu généré par JavaScript -->
  <div id="step6-content" style="display:none;"></div>
  
  <!-- Navigation -->
  <div id="step6-navigation" class="validation-section mt-3" style="display:none;">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
      <button class="btn btn-outline-secondary btn-precedent" data-step="5">
        <i class="fa-solid fa-arrow-left"></i> Étape précédente
      </button>
      <div class="summary-info d-flex gap-4 align-items-center">
        <p class="mb-0">
          <i class="fas fa-check-circle text-success"></i>
          Configuration validée 
        </p>
        <button id="btn-commander" class="btn btn-success btn-primary">
          <img src="<?php echo get_template_directory_uri(); ?>/assets/img/shopping-cart.svg" alt="Panier">
          Commander
        </button>
      </div>
    </div>
  </div>

</div>

<!-- Variables pour JavaScript -->
<script>
window.step6Data = {
  sessionConfig: <?php echo json_encode($session_config); ?>,
  duree: <?php echo json_encode($duree); ?>,
  mode: <?php echo json_encode($mode); ?>,
  adresses: <?php echo json_encode($adresses); ?>
};
</script>