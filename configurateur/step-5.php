<?php
/**
 * Étape 5 – Frais d'installation - AFFICHAGE LOCALSTORAGE
 * Refonte complète pour éliminer les race conditions
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

<div class="config-step step-5 container py-4">

  <div class="header-configurateur">
    <?php get_template_part('configurateur/header'); ?>

    <ul class="config-steps nav nav-pills justify-content-center py-5">
      <li class="nav-item"><a class="nav-link completed" data-step="1" href="#">1. Adresses</a></li>
      <li class="nav-item"><a class="nav-link completed" data-step="2" href="#">2. Internet</a></li>
      <li class="nav-item"><a class="nav-link completed" data-step="3" href="#">3. Téléphone mobile</a></li>
      <li class="nav-item"><a class="nav-link completed" data-step="4" href="#">4. Téléphonie fixe</a></li>
      <li class="nav-item"><span class="nav-link active">5. Frais d'installation</span></li>
      <li class="nav-item"><span class="nav-link">6. Récapitulatif</span></li>
    </ul>

    <h2 class="mb-4 title-step"><span>5</span> Frais d'installation</h2>
  </div>
    
  <!-- Contenu généré par JavaScript -->
  <div id="step5-content"></div>
  
  <!-- Navigation -->
  <div id="step5-navigation" class="d-flex justify-content-between mt-0" style="display: none;">
    <button class="btn btn-outline-secondary btn-precedent" data-step="4">Étape précédente</button>
    <button class="btn btn-primary btn-suivant" data-step="6">Étape suivante <i class="fa-solid fa-arrow-right"></i></button>
  </div>
</div>

<!-- Variables pour JavaScript -->
<script>
window.step5Data = {
  sessionConfig: <?php echo json_encode($session_config); ?>,
  duree: <?php echo json_encode($duree); ?>,
  mode: <?php echo json_encode($mode); ?>,
  adresses: <?php echo json_encode($adresses); ?>
};
</script>