<?php
/**
 * Étape 2 – Configuration Internet par adresse
 */
if (!function_exists('get_template_directory')) {
  require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}
require_once get_template_directory() . '/configurateur/functions-configurateur.php';

$forfaits = soeasy_session_get('soeasy_forfaits_internet', []);
$equipements = soeasy_session_get('soeasy_equipements_internet', []);
$duree = soeasy_get_selected_duree_engagement() ?? 0;
$mode = soeasy_get_selected_financement();

$adresses = soeasy_get_adresses_configurateur();
?>

<div class="config-step step-2 container py-4">
  <h2 class="mb-4">2. Choix de la connexion Internet</h2>

  <?php if (!empty($adresses)): ?>
    <ul class="nav nav-tabs mb-3">
      <?php foreach ($adresses as $i => $adresse): ?>
        <li class="nav-item">
          <button class="nav-link <?php echo $i === 0 ? 'active' : ''; ?>" data-bs-toggle="tab"
            data-bs-target="#tab-<?php echo $i; ?>">
            <?php echo esc_html($adresse['adresse']); ?>
          </button>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="tab-content">
      <?php foreach ($adresses as $i => $adresse): ?>
        <div class="tab-pane fade <?php echo $i === 0 ? 'show active' : ''; ?>" id="tab-<?php echo $i; ?>">

          <!-- FORFAIT INTERNET PRINCIPAL -->
          <h5 class="mt-4">Forfait Internet</h5>
          <div class="row gy-3">
            <?php
            $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'forfait-internet'];
            $loop = new WP_Query($args);
            while ($loop->have_posts()):
              $loop->the_post();
              $product = wc_get_product(get_the_ID());
              $product_id = $product->get_id();

              $equipements_associes = get_field('equipements_associes', $product_id) ?: [];
              $forfaits_secours_associes = get_field('forfaits_secours_associes', $product_id) ?: [];

              $equipements_ids = is_array($equipements_associes) ? array_map(function ($p) {
                return is_object($p) ? $p->ID : intval($p);
              }, $equipements_associes) : [];

              $forfaits_secours_ids = is_array($forfaits_secours_associes) ? array_map(function ($p) {
                return is_object($p) ? $p->ID : intval($p);
              }, $forfaits_secours_associes) : [];

              $variations = $product->get_available_variations();
              $data_attrs = '';
              $prix_affiche = 0;

              foreach ($variations as $variation) {
                $attr = $variation['attributes']['attribute_pa_duree-dengagement'] ?? '';
                $duree_var = (stripos($attr, 'sans') !== false || empty($attr)) ? 0 : intval(preg_replace('/[^0-9]/', '', $attr));
                if (!is_null($duree_var)) {
                  $prix_var = $variation['display_price'];
                  $data_attrs .= ' data-prix-leasing-' . $duree_var . '="' . esc_attr($prix_var) . '"';
                  if ($duree == $duree_var) {
                    $prix_affiche = $prix_var;
                  }
                }
              }

              $checked = isset($forfaits[$i]) && $forfaits[$i] == $product_id ? 'checked' : '';

              ?>
              <div class="col-md-6">
                <label class="border p-3 d-block rounded shadow-sm h-100">
                  <?php
                  $equipements_json = htmlspecialchars(json_encode($equipements_ids), ENT_QUOTES, 'UTF-8');
                  $secours_json = htmlspecialchars(json_encode($forfaits_secours_ids), ENT_QUOTES, 'UTF-8');
                  ?>
                  <input type="checkbox"
                  name="forfait_internet_<?= $i; ?>"
                  value="<?= $product_id; ?>"
                  class="me-2 forfait-internet-checkbox"
                  data-id="<?= $product_id; ?>"
                  data-index="<?= $i; ?>"
                  data-equipements='<?= $equipements_json ?>'
                  data-secours='<?= $secours_json ?>'
                  <?= $data_attrs ?> />
                  <strong><?php the_title(); ?></strong><br>
                  <span class="text-muted"><?php echo get_the_excerpt(); ?></span><br>
                  <div class="fw-bold mt-2 prix-affiche" data-unit="<?php echo esc_attr($prix_affiche); ?>">
                    <?php echo wc_price($prix_affiche); ?> / mois
                  </div>
                </label>
              </div>
            <?php endwhile;
            wp_reset_postdata(); ?>
          </div>

          <!-- FORFAIT INTERNET DE SECOURS (caché par défaut) -->
          <div class="bloc-secours d-none mt-5">
            <h5 class="mb-3">Connexion de secours</h5>
            <div class="row gy-3">
              <?php
              $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'forfait-internet'];
              $loop = new WP_Query($args);
              while ($loop->have_posts()):
                $loop->the_post();
                $product = wc_get_product(get_the_ID());
                $product_id = $product->get_id();
                $prix = floatval($product->get_price());
                ?>
                <div class="col-md-6 forfait-secours" data-secours-index="<?= $i; ?>" data-id="<?= $product_id; ?>">
                  <label class="border p-3 d-block rounded shadow-sm h-100">
                    <input type="checkbox" name="forfait_secours_<?php echo $i; ?>[]" value="<?= $product_id; ?>"
                      class="me-2 forfait-secours-checkbox" data-index="<?php echo $i; ?>">
                    <strong><?php the_title(); ?></strong><br>
                    <span class="text-muted"><?php echo get_the_excerpt(); ?></span><br>
                    <span class="fw-bold"><?php echo wc_price($prix); ?> / mois</span>
                  </label>
                </div>
              <?php endwhile;
              wp_reset_postdata(); ?>
            </div>
          </div>

          <!-- MATÉRIEL INTERNET (caché par défaut) -->
          <div class="bloc-equipements d-none mt-5">
            <h5 class="mb-3">Matériel Internet</h5>
            <div class="row gy-3">
              <?php
              $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'internet-routeurs'];
              $loop = new WP_Query($args);
              while ($loop->have_posts()):
                $loop->the_post();
                $product = wc_get_product(get_the_ID());
                $product_id = $product->get_id();

                $obligatoire = get_field('obligatoire', $product_id) === true;

                $prix_comptant = floatval($product->get_regular_price());
                $prix_leasing_map = [];
                foreach ([0, 24, 36, 48, 63] as $d) {
                  $prix_leasing_map[$d] = floatval(get_field("prix_leasing_$d", $product_id)) ?: 0;
                }

                $prix = ($mode === 'leasing') ? ($prix_leasing_map[$duree] ?? $prix_leasing_map[0]) : $prix_comptant;
                $is_selected = isset($equipements[$i]) && in_array($product_id, $equipements[$i]);
                $checked = $is_selected ? 'checked' : '';
                ?>
                <div class="col-md-6 equipement" data-equipement-index="<?= $i; ?>" data-id="<?= $product_id; ?>">
                  <label class="border p-3 d-block rounded shadow-sm h-100"
                    data-prix-comptant="<?php echo esc_attr($prix_comptant); ?>" <?php foreach ([0, 24, 36, 48, 63] as $d): ?>
                      data-prix-leasing-<?php echo $d; ?>="<?php echo esc_attr($prix_leasing_map[$d]); ?>" <?php endforeach; ?>>
                    <input type="checkbox" name="equipement_<?php echo $i; ?>[]" value="<?php echo $product_id; ?>"
                      class="me-2 equipement-checkbox" data-index="<?php echo $i; ?>" data-id="<?= $product_id; ?>"
                      <?php echo $checked; ?>       <?php if ($obligatoire): ?>checked disabled data-obligatoire="1" <?php endif; ?>>
                    <strong><?php the_title(); ?></strong><br>
                    <span class="text-muted"><?php echo get_the_excerpt(); ?></span><br>
                    <span class="fw-bold d-block prix-affiche" data-unit="<?php echo esc_attr($prix); ?>"
                      data-type="equipement">
                      <?php echo wc_price($prix) . ($mode === 'leasing' ? ' / mois' : ''); ?>
                    </span>
                  </label>
                </div>
              <?php endwhile;
              wp_reset_postdata(); ?>
            </div>
          </div>

        </div>
      <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <button class="btn btn-outline-secondary btn-precedent" data-step="1">← Étape précédente</button>
      <button class="btn btn-primary btn-suivant" data-step="3">Étape suivante →</button>
    </div>

    <?php
$produits_equipements = wc_get_products([
  'status' => 'publish',
  'limit' => -1,
  'type' => 'simple',
  'category' => ['internet-routeurs'],
]);

// 1. Compter les frais globalement par ID
$frais_global = [];

foreach ($produits_equipements as $produit) {
  $equipement_id = $produit->get_id();
  $frais_associes = get_field('frais_installation_associes', $equipement_id);

  if (!empty($frais_associes)) {
    foreach ($frais_associes as $frais) {
      $product_frais = null;

      if (is_numeric($frais)) {
        $product_frais = wc_get_product((int) $frais);
      } elseif ($frais instanceof WP_Post) {
        $product_frais = wc_get_product($frais->ID);
      } elseif ($frais instanceof WC_Product) {
        $product_frais = $frais;
      }

      if (!$product_frais) continue;

      $frais_id = $product_frais->get_id();

      if (!isset($frais_global[$frais_id])) {
        $frais_global[$frais_id] = [
          'id' => $frais_id,
          'nom' => $product_frais->get_title(),
          'quantite' => 1,
          'prixComptant' => (float) $product_frais->get_regular_price(),
          'prixLeasing24' => (float) get_field('prix_leasing_24', $frais_id) ?: 0,
          'prixLeasing36' => (float) get_field('prix_leasing_36', $frais_id) ?: 0,
          'prixLeasing48' => (float) get_field('prix_leasing_48', $frais_id) ?: 0,
          'prixLeasing63' => (float) get_field('prix_leasing_63', $frais_id) ?: 0
        ];
      } else {
        $frais_global[$frais_id]['quantite'] += 1;
      }
    }
  }
}

// 2. Appliquer les mêmes frais cumulés à tous les équipements (clé par ID d'équipement)
$frais_js = [];
foreach ($produits_equipements as $produit) {
  $equipement_id = $produit->get_id();
  $frais_associes = get_field('frais_installation_associes', $equipement_id);

  if (!empty($frais_associes)) {
    foreach ($frais_associes as $frais) {
      $product_frais = is_numeric($frais) ? wc_get_product((int) $frais) :
                        ($frais instanceof WP_Post ? wc_get_product($frais->ID) :
                        ($frais instanceof WC_Product ? $frais : null));

      if (!$product_frais) continue;

      $frais_js[$equipement_id][] = [
        'id' => $product_frais->get_id(),
        'nom' => $product_frais->get_title(),
        'quantite' => 1,
        'prixComptant' => (float) $product_frais->get_regular_price(),
        'prixLeasing24' => (float) get_field('prix_leasing_24', $product_frais->get_id()) ?: 0,
        'prixLeasing36' => (float) get_field('prix_leasing_36', $product_frais->get_id()) ?: 0,
        'prixLeasing48' => (float) get_field('prix_leasing_48', $product_frais->get_id()) ?: 0,
        'prixLeasing63' => (float) get_field('prix_leasing_63', $product_frais->get_id()) ?: 0
      ];
    }
  }
}
?>

<script>
  window.fraisInstallationInternetParProduit = <?= json_encode($frais_js, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>





  <?php else: ?>
    <div class="alert alert-warning">Veuillez ajouter une adresse à l’étape 1.</div>
  <?php endif; ?>
</div>