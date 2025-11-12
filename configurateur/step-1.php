<?php
/**
 * Étape 1 – Saisie des adresses de mise en service
 */

if (!function_exists('get_template_directory')) {
  require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}

require_once get_template_directory() . '/configurateur/functions-configurateur.php';
$adresses = soeasy_get_adresses_configurateur();
?>

<div class="config-step step-1 container py-4">
  
  <div class="header-configurateur">
    <?php get_template_part('configurateur/header'); ?>

    <ul class="config-steps nav nav-pills justify-content-center py-5">
      <li class="nav-item"><span class="nav-link active">1. Adresses</span></li>
      <li class="nav-item"><span class="nav-link">2. Internet</span></li>
      <li class="nav-item"><span class="nav-link">3. Téléphone mobile</span></li>
      <li class="nav-item"><span class="nav-link">4. Téléphonie fixe</span></li>
      <li class="nav-item"><span class="nav-link">5. Frais d'installation</span></li>
      <li class="nav-item"><span class="nav-link">6. Récapitulatif</span></li>
    </ul>

    <h2 class="mb-4 title-step"><span>1</span> Adresse de mise en service</h2>
  </div>  

  <!-- Formulaire d'ajout d'une adresse -->
  <div class="card item-list-product">
    <div class="card-body p-md-5 p-4">
      <form id="form-ajout-adresse" class="row g-3 mb-4 align-items-end">
        <div class="col-md-6">
          <label for="adresse" class="form-label card-title">Où souhaitez-vous activer vos services ?</label>
          <input type="text" class="form-control" id="adresse" name="adresse" placeholder="Entrez l'adresse">
        </div>
        <!-- <div class="col-12">
      <label for="services" class="form-label">Services souhaités</label><br>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" id="internet" name="services[]" value="internet">
        <label class="form-check-label" for="internet">Internet</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" id="mobile" name="services[]" value="mobile">
        <label class="form-check-label" for="mobile">Mobile</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" id="centrex" name="services[]" value="centrex">
        <label class="form-check-label" for="centrex">Centrex</label>
      </div>
    </div> -->
        <div class="col-md-6">
          <button type="submit" class="btn btn-primary" id="btn-add-adresse">Ajouter cette adresse</button>
        </div>
      </form>

      <!-- Liste des adresses enregistrées -->
      <div id="liste-adresses">
        <?php if (!empty($adresses)): ?>
          <h5 class="card-title">Adresses enregistrées :</h5>
          <ul class="list-group mb-4">
            <?php foreach ($adresses as $i => $adresse): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center p-3 mb-1">
                <span>
                  <?php echo esc_html($adresse['adresse']); ?>
                </span>
                <button class="btn-remove-adresse"
                  data-index="<?php echo $i; ?>"><i class="fa-solid fa-circle-xmark"></i></button>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-end mt-4" id="footer-buttons">
    <button class="btn btn-primary btn-suivant float-end disabled" data-step="2">Étape suivante →</button>
  </div>
</div>