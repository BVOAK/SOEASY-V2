<?php
/**
 * Étape 3 – Forfaits & Matériel Mobile
 */
if (!function_exists('get_template_directory')) {
  require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}
require_once get_template_directory() . '/configurateur/functions-configurateur.php';

$adresses = soeasy_get_adresses_configurateur();
$duree = soeasy_get_selected_duree_engagement();
$mode = soeasy_get_selected_financement();

$forfaits_mobile = soeasy_session_get('soeasy_forfaits_mobile', []);
$forfaits_data = soeasy_session_get('soeasy_forfaits_data', []);
$equipements_mobile = soeasy_session_get('soeasy_equipements_mobile', []);
?>

<div class="config-step step-3 container py-4">
  <h2 class="mb-4">3. Téléphonie Mobile</h2>

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

          <!-- === Forfaits Mobile === -->
          <h5 class="mt-4">Forfaits mobiles</h5>
          <div class="row gy-3">
            <?php
            $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'forfait-mobile'];
            $loop = new WP_Query($args);
            while ($loop->have_posts()):
              $loop->the_post();
              $product = wc_get_product(get_the_ID());
              $product_id = $product->get_id();

              $variations = $product->get_available_variations();
              $data_attrs = '';
              $prix = 0;

              foreach ($variations as $variation) {
                $attr = $variation['attributes']['attribute_pa_duree-dengagement'] ?? '';
                $duree_var = null;

                if (stripos($attr, 'sans') !== false || stripos($attr, 'engagement') !== false || empty($attr)) {
                  $duree_var = 0;
                } else {
                  $duree_var = intval(preg_replace('/[^0-9]/', '', $attr));
                }

                if (!is_null($duree_var)) {
                  $prix_var = $variation['display_price'];
                  $data_attrs .= ' data-prix-leasing-' . $duree_var . '="' . esc_attr($prix_var) . '"';
                  if ($duree == $duree_var) {
                    $prix = $prix_var;
                  }
                }
              }

              // Par défaut (aucune variation ou fallback)
              if ($prix == 0) {
                $prix = $product->get_price();
              }

              $quantite = 0;
              foreach ($forfaits_mobile[$i] ?? [] as $item) {
                if ($item['id'] == $product_id) {
                  $quantite = intval($item['qty']);
                  break;
                }
              }

              $checked = $quantite > 0 ? 'checked' : '';
              $prix_total = $prix * $quantite;
              ?>
              <div class="col-md-6">
                <div class="border p-3 rounded h-100" data-prix-leasing-0="<?php echo esc_attr($prix); ?>" <?php echo $data_attrs; ?>>
                  <div class="form-check">
                    <input class="form-check-input forfait-checkbox mobile-checkbox" type="checkbox" data-type="mobile"
                      data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>" <?php echo $checked; ?>>
                    <label class="form-check-label"><strong><?php the_title(); ?></strong></label>
                  </div>
                  <span class="text-muted"><?php echo get_the_excerpt(); ?></span>
                  <div class="small fw-bold prix-affiche"><?php echo wc_price($prix); ?> / mois</div>
                  <input type="number" min="0" class="form-control text-end input-qty"
                    name="quantite_forfait_mobile_<?php echo $i; ?>[]" value="<?php echo esc_attr($quantite); ?>"
                    data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>"
                    data-unit="<?php echo esc_attr($prix); ?>" data-type="forfait">
                  <div class="small text-muted">Total :
                    <span class="prix-total" data-unit="<?php echo esc_attr($prix); ?>">
                      <?php echo wc_price($prix_total); ?> / mois
                    </span>
                  </div>
                </div>
              </div>
            <?php endwhile;
            wp_reset_postdata(); ?>
          </div>


          <!-- === Forfaits Data === -->
          <h5 class="mt-5">Forfaits Data</h5>
          <div class="row gy-3">
            <?php
            $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'forfait-data'];
            $loop = new WP_Query($args);
            while ($loop->have_posts()):
              $loop->the_post();
              $product = wc_get_product(get_the_ID());
              $product_id = $product->get_id();

              $variations = $product->get_available_variations();
              $data_attrs = '';
              $prix_affiche = 0;

              foreach ($variations as $v) {
                $attr = $v['attributes']['attribute_pa_duree-dengagement'] ?? '';
                $duree_var = null;

                if (stripos($attr, 'sans') !== false || empty($attr)) {
                  $duree_var = 0;
                } else {
                  $duree_var = intval(preg_replace('/[^0-9]/', '', $attr));
                }

                if (!is_null($duree_var)) {
                  $prix_var = $v['display_price'];
                  $data_attrs .= ' data-prix-leasing-' . $duree_var . '="' . esc_attr($prix_var) . '"';

                  if (intval($duree) === $duree_var) {
                    $prix_affiche = $prix_var;
                  }
                }
              }

              $quantite = 0;
              foreach ($forfaits_data[$i] ?? [] as $item) {
                if ($item['id'] == $product_id) {
                  $quantite = intval($item['qty']);
                  break;
                }
              }

              $checked = $quantite > 0 ? 'checked' : '';
              $prix_total = $prix_affiche * $quantite;
              ?>
              <div class="col-md-6">
                <div class="border p-3 rounded h-100" <?php echo $data_attrs; ?>
                  data-prix-comptant="<?php echo esc_attr($prix_affiche); ?>">
                  <div class="form-check">
                    <input class="form-check-input forfait-checkbox mobile-checkbox" type="checkbox" data-type="data"
                      data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>" <?php echo $checked; ?>>
                    <label class="form-check-label"><strong><?php the_title(); ?></strong></label>
                  </div>
                  <span class="text-muted"><?php echo get_the_excerpt(); ?></span>
                  <div class="small fw-bold prix-affiche"><?php echo wc_price($prix_affiche); ?> / mois</div>
                  <input type="number" min="0" class="form-control text-end input-qty"
                    name="quantite_forfait_data_<?php echo $i; ?>[]" value="<?php echo esc_attr($quantite); ?>"
                    data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>"
                    data-unit="<?php echo esc_attr($prix_affiche); ?>" data-type="forfait">
                  <div class="small text-muted">
                    Total : <span class="prix-total" data-unit="<?php echo esc_attr($prix_affiche); ?>">
                      <?php echo wc_price($prix_total); ?> / mois
                    </span>
                  </div>
                </div>
              </div>
            <?php endwhile;
            wp_reset_postdata(); ?>
          </div>


          <!-- === Équipements Mobiles === -->
          <h5 class="mt-5">Équipements Mobiles</h5>
          <div class="row gy-3">
            <?php
            $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'telephonie-mobile'];
            $loop = new WP_Query($args);
            while ($loop->have_posts()):
              $loop->the_post();
              $product = wc_get_product(get_the_ID());
              $product_id = $product->get_id();

              $prix_comptant = $product->get_regular_price();
              $prix_leasing = soeasy_get_leasing_price($product_id, $duree);
              $prix = ($mode === 'leasing') ? floatval($prix_leasing) : floatval($prix_comptant);

              $data_attrs = 'data-prix-comptant="' . esc_attr($prix_comptant) . '" ';
              foreach ([0, 24, 36, 48, 63] as $d) {
                $pl = get_field("prix_leasing_$d", $product_id);
                $data_attrs .= 'data-prix-leasing-' . $d . '="' . esc_attr($pl) . '" ';
              }

              $quantite = 0;
              foreach ($equipements_mobile[$i] ?? [] as $item) {
                if ($item['id'] == $product_id) {
                  $quantite = intval($item['qty']);
                  break;
                }
              }

              $checked = $quantite > 0 ? 'checked' : '';
              $prix_total = $prix * $quantite;
              ?>
              <div class="col-md-6">
                <div class="border p-3 rounded h-100" <?php echo $data_attrs; ?>>
                  <div class="form-check">
                    <input class="form-check-input forfait-checkbox mobile-checkbox" type="checkbox" data-type="equipement"
                      data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>" <?php echo $checked; ?>>
                    <label class="form-check-label"><strong><?php the_title(); ?></strong></label>
                  </div>
                  <span class="text-muted"><?php echo get_the_excerpt(); ?></span>
                  <div class="small prix-affiche fw-bold">
                    <?php echo wc_price($prix); ?>      <?php echo ($mode === 'leasing') ? ' / mois' : ''; ?>
                  </div>
                  <input type="number" min="0" class="form-control text-end input-qty"
                    name="quantite_equipement_<?php echo $i; ?>[]" value="<?php echo esc_attr($quantite); ?>"
                    data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>"
                    data-unit="<?php echo esc_attr($prix); ?>" data-type="equipement">
                  <div class="small text-muted">
                    Total : <span class="prix-total" data-unit="<?php echo esc_attr($prix); ?>">
                      <?php echo wc_price($prix_total); ?>      <?php echo ($mode === 'leasing') ? ' / mois' : ''; ?>
                    </span>
                  </div>
                </div>
              </div>
            <?php endwhile;
            wp_reset_postdata(); ?>
          </div>


        </div>
      <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <button class="btn btn-outline-secondary btn-precedent" data-step="2">← Étape précédente</button>
      <button class="btn btn-primary btn-suivant" data-step="4">Étape suivante →</button>
    </div>

  <?php else: ?>
    <div class="alert alert-warning">Aucune adresse ajoutée. Veuillez retourner à l’étape 1.</div>
  <?php endif; ?>
</div>