<?php
/**
 * Étape 4 – Téléphonie Fixe – Centrex
 */
if (!function_exists('get_template_directory')) {
  require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}
require_once get_template_directory() . '/configurateur/functions-configurateur.php';

$adresses = soeasy_get_adresses_configurateur();
$duree = soeasy_get_selected_duree_engagement();
$mode = soeasy_get_selected_financement();

$licences = soeasy_session_get('soeasy_licences_centrex', []);
$services = soeasy_session_get('soeasy_services_centrex', []);
$postes = soeasy_session_get('soeasy_postes_centrex', []);
$switchs = soeasy_session_get('soeasy_switchs_centrex', []);
$accessoires = soeasy_session_get('soeasy_accessoires_centrex', []);

?>

<div class="config-step step-4 container py-4">
  
  <div class="header-configurateur">
    <?php get_template_part('configurateur/header'); ?>

    <ul class="config-steps nav nav-pills justify-content-center py-5">
      <li class="nav-item"><a class="nav-link completed" data-step="1" href="#">1. Adresses</a></li>
      <li class="nav-item"><a class="nav-link completed" data-step="2" href="#">2. Internet</a></li>
      <li class="nav-item"><a class="nav-link completed" data-step="3" href="#">3. Téléphone mobile</a></li>
      <li class="nav-item"><span class="nav-link active">4. Téléphonie fixe</span></li>
      <li class="nav-item"><span class="nav-link">5. Frais d'installation</span></li>
      <li class="nav-item"><span class="nav-link">6. Récapitulatif</span></li>
    </ul>

    <h2 class="mb-4 title-step"><span>4</span> Téléphonie Fixe – Centrex</h2>
  </div>

  <?php if (!empty($adresses)): ?>
    <ul class="nav nav-tabs mb-3 <?php if(count($adresses) <= 1) : ?>d-none<?php endif; ?>" id="nav-adresses">
      <?php foreach ($adresses as $i => $adresse): ?>
        <li class="nav-item">
          <button class="nav-link <?php echo $i === 0 ? 'active' : ''; ?>" data-bs-toggle="tab"
            data-bs-target="#tab-<?php echo $i; ?>">
            <?php echo esc_html(soeasy_get_ville_courte($adresse['adresse'])); ?>
          </button>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="tab-content">
      <?php foreach ($adresses as $i => $adresse): ?>
        <div class="tab-pane fade <?php echo $i === 0 ? 'show active' : ''; ?>" id="tab-<?php echo $i; ?>">

          <!-- === 1. Licences utilisateurs === -->
          <div class="card item-list-product mt-3">
            <div class="card-body p-md-5 p-4">
              <h5 class="mb-3 card-title">1. Licences utilisateurs</h5>
              <div class="row p-0 gap-3">
                <?php
                $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'telephonie-fixe-centrex'];
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
                      $prix_var = floatval($variation['display_price']);
                      $data_attrs .= ' data-prix-leasing-' . $duree_var . '="' . esc_attr($prix_var) . '"';
                      if ((int) $duree === (int) $duree_var) {
                        $prix = $prix_var;
                      }
                    }
                  }

                  // Fallback si aucune correspondance trouvée
                  if ($prix == 0) {
                    $prix = $product->get_price();
                  }

                  $quantite = 0;
                  foreach ($licences[$i] ?? [] as $item) {
                    if ($item['id'] == $product_id) {
                      $quantite = intval($item['qty']);
                      break;
                    }
                  }

                  $checked = $quantite > 0 ? 'checked' : '';
                  $prix_total = $prix * $quantite;

                  $infos = get_field("tooltip-infos");
                  ?>
                  <div class="item-product" data-prix-leasing-0="<?php echo esc_attr($prix); ?>" <?php echo $data_attrs; ?>>
                    <div class="col-md-6 checkbox-wrapper">
                      <input class="form-check-input centrex-checkbox inp-cbx" type="checkbox" data-type="forfait"
                        id="licence-checkbox-<?php echo $product_id; ?>" data-id="<?php echo $product_id; ?>"
                        data-index="<?php echo $i; ?>" <?php echo $checked; ?> style="display: none;" />
                      <label class="cbx" for="licence-checkbox-<?php echo $product_id; ?>">
                        <span>
                          <svg width="12px" height="9px" viewbox="0 0 12 9">
                            <polyline points="1 5 4 8 11 1"></polyline>
                          </svg>
                        </span>
                        <div class="col">
                          <h3 class="product-title"><?php the_title(); ?></h3>
                          <span class="text-muted"><?php echo get_the_excerpt(); ?></span>
                        </div>
                      </label>
                    </div>
                    <div class="col-md-6 bloc-price-end">
                      <div class="small prix-affiche price col-md-3" data-unit="<?php echo esc_attr($prix); ?>">
                        <?php echo wc_price($prix); ?> / mois
                      </div>
                      <div class="col-md-2">
                        <input type="number" min="0" class="form-control input-qty text-end mt-2"
                          name="quantite_licence_<?php echo $i; ?>[]" value="<?php echo esc_attr($quantite); ?>"
                          data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>"
                          data-unit="<?php echo esc_attr($prix); ?>" data-type="forfait" data-sous-type="forfait-centrex">
                      </div>
                      <div class="prix-total price col-md-3" data-unit="<?php echo esc_attr($prix); ?>">
                        <?php echo wc_price($prix_total); ?> / mois
                      </div>
                      <?php if ($infos): ?>
                        <div class="icon-info" data-bs-toggle="tooltip" data-bs-placement="top"
                          data-bs-custom-class="custom-tooltip" data-bs-title="<?php echo $infos ?>">
                          <img src="<?php echo get_template_directory_uri() ?>/assets/img/info.svg" />
                        </div>
                      <?php endif ?>
                    </div>
                  </div>
                <?php endwhile;
                wp_reset_postdata(); ?>
              </div>
            </div>
          </div>

          <!-- === 2. Services complémentaires === -->
          <div class="card item-list-product mt-3">
            <div class="card-body p-md-5 p-4">
              <h5 class="mb-3 card-title">2. Services complémentaires</h5>
              <div class="row p-0 gap-3">
                <?php
                $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'services-centrex'];
                $loop = new WP_Query($args);
                while ($loop->have_posts()):
                  $loop->the_post();
                  $product = wc_get_product(get_the_ID());
                  $product_id = $product->get_id();

                  $prix_comptant = $product->get_regular_price();
                  $prix_leasing = soeasy_get_leasing_price($product_id, $duree);
                  $prix = ($mode === 'leasing') ? floatval($prix_leasing) : floatval($prix_comptant);

                  $data_attrs = 'data-prix-comptant="' . esc_attr($prix_comptant) . '" ';
                  foreach ([24, 36, 48, 63] as $d) {
                    $pl = get_field("prix_leasing_$d", $product_id);
                    $data_attrs .= 'data-prix-leasing-' . $d . '="' . esc_attr($pl) . '" ';
                  }

                  $quantite = 0;
                  foreach ($services[$i] ?? [] as $item) {
                    if ($item['id'] == $product_id) {
                      $quantite = intval($item['qty']);
                      break;
                    }
                  }

                  $checked = $quantite > 0 ? 'checked' : '';
                  $prix_total = $prix * $quantite;

                  $infos = get_field("tooltip-infos");
                  ?>
                  <div class="item-product" data-prix-leasing-0="<?php echo esc_attr($prix); ?>" <?php echo $data_attrs; ?>>
                    <div class="col-md-6 checkbox-wrapper">
                      <input class="form-check-input centrex-checkbox inp-cbx" type="checkbox" data-type="equipement"
                        id="service-checkbox-<?php echo $product_id; ?>" data-id="<?php echo $product_id; ?>"
                        data-index="<?php echo $i; ?>" <?php echo $checked; ?> style="display: none;" />
                      <label class="cbx" for="service-checkbox-<?php echo $product_id; ?>">
                        <span>
                          <svg width="12px" height="9px" viewbox="0 0 12 9">
                            <polyline points="1 5 4 8 11 1"></polyline>
                          </svg>
                        </span>
                        <div class="col-md-9">
                          <h3 class="product-title"><?php the_title(); ?></h3>
                          <span class="text-muted"><?php echo get_the_excerpt(); ?></span>
                        </div>
                      </label>
                    </div>
                    <div class="col-md-6 bloc-price-end">
                      <div class="small prix-affiche price col-md-3" data-unit="<?php echo esc_attr($prix); ?>">
                        <?php echo wc_price($prix); ?> / mois
                      </div>
                      <div class="col-md-2">
                        <input type="number" min="0" class="form-control input-qty text-end mt-2"
                          name="quantite_service_<?php echo $i; ?>[]" value="<?php echo esc_attr($quantite); ?>"
                          data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>"
                          data-unit="<?php echo esc_attr($prix); ?>" data-type="equipement" data-sous-type="service-centrex">
                      </div>
                      <div class="prix-total price col-md-3" data-unit="<?php echo esc_attr($prix); ?>">
                        <?php echo wc_price($prix_total); ?> / mois
                      </div>
                      <?php if ($infos): ?>
                        <div class="icon-info" data-bs-toggle="tooltip" data-bs-placement="top"
                          data-bs-custom-class="custom-tooltip" data-bs-title="<?php echo $infos ?>">
                          <img src="<?php echo get_template_directory_uri() ?>/assets/img/info.svg" />
                        </div>
                      <?php endif ?>
                    </div>
                  </div>
                <?php endwhile;
                wp_reset_postdata(); ?>
              </div>
            </div>
          </div>


          <!-- === 3. Postes téléphoniques === -->
          <div class="card item-list-product mt-3">
            <div class="card-body p-md-5 p-4">
              <h5 class="mb-3 card-title">3. Postes Téléphoniques</h5>
              <div class="row p-0 gap-3">
                <?php
                $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'telephonie-fixe-centrex-equipements-telecom'];
                $loop = new WP_Query($args);
                while ($loop->have_posts()):
                  $loop->the_post();
                  $product = wc_get_product(get_the_ID());
                  $product_id = $product->get_id();

                  $prix_comptant = $product->get_regular_price();
                  $prix_leasing = soeasy_get_leasing_price($product_id, $duree);
                  $prix = ($mode === 'leasing') ? floatval($prix_leasing) : floatval($prix_comptant);

                  $data_attrs = 'data-prix-comptant="' . esc_attr($prix_comptant) . '" ';
                  foreach ([24, 36, 48, 63] as $d) {
                    $pl = get_field("prix_leasing_$d", $product_id);
                    $data_attrs .= 'data-prix-leasing-' . $d . '="' . esc_attr($pl) . '" ';
                  }

                  $quantite = 0;
                  foreach ($postes[$i] ?? [] as $item) {
                    if ($item['id'] == $product_id) {
                      $quantite = intval($item['qty']);
                      break;
                    }
                  }

                  $checked = $quantite > 0 ? 'checked' : '';
                  $prix_total = $prix * $quantite;

                  $image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'single-post-thumbnail');
                  $infos = get_field("tooltip-infos");
                  ?>
                  <div class="item-product" <?php echo $data_attrs; ?>>
                    <div class="col-md-6 checkbox-wrapper">
                      <input class="form-check-input centrex-checkbox inp-cbx" type="checkbox" data-type="equipement"
                        id="poste-checkbox-<?php echo $product_id; ?>" data-id="<?php echo $product_id; ?>"
                        data-index="<?php echo $i; ?>" <?php echo $checked; ?> style="display: none;" />
                      <label class="cbx" for="poste-checkbox-<?php echo $product_id; ?>">
                        <span>
                          <svg width="12px" height="9px" viewbox="0 0 12 9">
                            <polyline points="1 5 4 8 11 1"></polyline>
                          </svg>
                        </span>
                        <img src="<?php echo $image[0]; ?>" data-id="<?php echo $product_id; ?>" class="img-responsive"
                          alt="<?php echo get_the_excerpt(); ?>">
                        <div class="col">
                          <h3 class="product-title"><?php the_title(); ?></h3>
                          <small class="text-muted"><?php echo get_the_excerpt(); ?></small>
                        </div>
                      </label>
                    </div>
                    <div class="col-md-6 bloc-price-end">
                      <div class="small prix-affiche price">
                        <?php echo wc_price($prix); ?>       <?php echo ($mode === 'leasing') ? ' / mois' : ''; ?>
                      </div>
                      <div class="col-md-2">
                        <input type="number" min="0" class="form-control input-qty text-end mt-2"
                          name="quantite_poste_<?php echo $i; ?>[]" value="<?php echo esc_attr($quantite); ?>"
                          data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>"
                          data-unit="<?php echo esc_attr($prix); ?>" data-type="equipement" data-sous-type="poste-centrex">
                      </div>
                      <div class="prix-total price col-md-3" data-unit="<?php echo esc_attr($prix); ?>">
                        <?php echo wc_price($prix_total); ?>       <?php echo ($mode === 'leasing') ? ' / mois' : ''; ?>
                      </div>
                      <?php if ($infos): ?>
                        <div class="icon-info" data-bs-toggle="tooltip" data-bs-placement="top"
                          data-bs-custom-class="custom-tooltip" data-bs-title="<?php echo $infos ?>">
                          <img src="<?php echo get_template_directory_uri() ?>/assets/img/info.svg" />
                        </div>
                      <?php endif ?>
                    </div>
                  </div>
                <?php endwhile;
                wp_reset_postdata(); ?>
              </div>
            </div>
          </div>

          <!-- === 4. Switchs réseau === -->
          <div class="card item-list-product mt-3 bloc-switch d-none">
            <div class="card-body p-md-5 p-4">
              <h5 class="mb-3 card-title">4. Switchs réseau</h5>
              <div class="row p-0 gap-3">
                <?php
                $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'reseau-switchs'];
                $loop = new WP_Query($args);
                while ($loop->have_posts()):
                  $loop->the_post();
                  $product = wc_get_product(get_the_ID());
                  $product_id = $product->get_id();

                  $nombre_ports = get_field('nombre_ports', $product_id) ?: 0;

                  $prix_comptant = $product->get_regular_price();
                  $prix_leasing = soeasy_get_leasing_price($product_id, $duree);
                  $prix = ($mode === 'leasing') ? floatval($prix_leasing) : floatval($prix_comptant);

                  $data_attrs = 'data-prix-comptant="' . esc_attr($prix_comptant) . '" ';
                  foreach ([24, 36, 48, 63] as $d) {
                    $pl = get_field("prix_leasing_$d", $product_id);
                    $data_attrs .= 'data-prix-leasing-' . $d . '="' . esc_attr($pl) . '" ';
                  }

                  $quantite = 0;
                  foreach ($switchs[$i] ?? [] as $item) {
                    if ($item['id'] == $product_id) {
                      $quantite = intval($item['qty']);
                      break;
                    }
                  }

                  $checked = $quantite > 0 ? 'checked' : '';
                  $prix_total = $prix * $quantite;

                  $image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'single-post-thumbnail');
                  $infos = get_field("tooltip-infos");
                  ?>
                  <div class="item-product blocSwitch" <?php echo $data_attrs; ?>>
                    <div class="col-md-6 checkbox-wrapper">
                      <input class="form-check-input centrex-checkbox inp-cbx" type="checkbox" data-type="equipement"
                        id="switch-checkbox-<?php echo $product_id; ?>" data-role="switch-centrex"
                        data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>"
                        data-nombre-ports="<?= $nombre_ports; ?>" <?php echo $checked; ?> style="display: none;" />
                      <label class="cbx" for="switch-checkbox-<?php echo $product_id; ?>">
                        <span>
                          <svg width="12px" height="9px" viewbox="0 0 12 9">
                            <polyline points="1 5 4 8 11 1"></polyline>
                          </svg>
                        </span>
                        <img src="<?php echo $image[0]; ?>" data-id="<?php echo $product_id; ?>" class="img-responsive"
                          alt="<?php echo get_the_excerpt(); ?>">
                        <div class="col">
                          <h3 class="product-title"><?php the_title(); ?></h3>
                          <span class="text-muted"><?php echo get_the_excerpt(); ?></span>
                        </div>
                      </label>
                    </div>
                    <div class="col-md-6 bloc-price-end">
                      <div class="small prix-affiche price">
                        <?php echo wc_price($prix); ?>       <?php echo ($mode === 'leasing') ? ' / mois' : ''; ?>
                      </div>
                      <div class="col-md-2">
                        <input type="number" min="0" class="form-control input-qty text-end mt-2"
                          name="quantite_switch_<?php echo $i; ?>[]" value="<?php echo esc_attr($quantite); ?>"
                          data-id="<?php echo $product_id; ?>" data-switch-index="<?php echo $i; ?>"
                          data-unit="<?php echo esc_attr($prix); ?>" data-type="equipement" data-sous-type="switch-centrex"
                          data-nombre-ports="<?= $nombre_ports; ?>">
                      </div>
                      <div class="prix-total price col-md-3" data-unit="<?php echo esc_attr($prix); ?>">
                        <?php echo wc_price($prix_total); ?>       <?php echo ($mode === 'leasing') ? ' / mois' : ''; ?>
                      </div>
                      <?php if ($infos): ?>
                        <div class="icon-info" data-bs-toggle="tooltip" data-bs-placement="top"
                          data-bs-custom-class="custom-tooltip" data-bs-title="<?php echo $infos ?>">
                          <img src="<?php echo get_template_directory_uri() ?>/assets/img/info.svg" />
                        </div>
                      <?php endif ?>
                    </div>
                  </div>
                <?php endwhile;
                wp_reset_postdata(); ?>
              </div>
            </div>
          </div>

          <!-- === 5. Accessoires === -->
          <div class="card item-list-product mt-3">
            <div class="card-body p-md-5 p-4">
              <h5 class="mb-3 card-title">5. Accessoires</h5>
              <div class="row p-0 gap-3">
                <?php
                $args = ['post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 'accessoires-telecoms'];
                $loop = new WP_Query($args);
                while ($loop->have_posts()):
                  $loop->the_post();
                  $product = wc_get_product(get_the_ID());
                  $product_id = $product->get_id();

                  $prix_comptant = $product->get_regular_price();
                  $prix_leasing = soeasy_get_leasing_price($product_id, $duree);
                  $prix = ($mode === 'leasing') ? floatval($prix_leasing) : floatval($prix_comptant);

                  $data_attrs = 'data-prix-comptant="' . esc_attr($prix_comptant) . '" ';
                  foreach ([24, 36, 48, 63] as $d) {
                    $pl = get_field("prix_leasing_$d", $product_id);
                    $data_attrs .= 'data-prix-leasing-' . $d . '="' . esc_attr($pl) . '" ';
                  }

                  $quantite = 0;
                  foreach ($accessoires[$i] ?? [] as $item) {
                    if ($item['id'] == $product_id) {
                      $quantite = intval($item['qty']);
                      break;
                    }
                  }

                  $checked = $quantite > 0 ? 'checked' : '';
                  $prix_total = $prix * $quantite;

                  $image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'single-post-thumbnail');
                  $infos = get_field("tooltip-infos");
                  ?>
                  <div class="item-product" <?php echo $data_attrs; ?>>
                    <div class="col-md-6 checkbox-wrapper">
                      <input class="form-check-input centrex-checkbox inp-cbx" type="checkbox" data-type="equipement"
                        id="accessoire-checkbox-<?php echo $product_id; ?>" data-role="switch-centrex"
                        data-id="<?php echo $product_id; ?>" data-index="<?php echo $i; ?>"
                        data-nombre-ports="<?= $nombre_ports; ?>" <?php echo $checked; ?> style="display: none;" />
                      <label class="cbx" for="accessoire-checkbox-<?php echo $product_id; ?>">
                        <span>
                          <svg width="12px" height="9px" viewbox="0 0 12 9">
                            <polyline points="1 5 4 8 11 1"></polyline>
                          </svg>
                        </span>
                        <img src="<?php echo $image[0]; ?>" data-id="<?php echo $product_id; ?>" class="img-responsive"
                          alt="<?php echo get_the_excerpt(); ?>">
                        <div class="col">
                          <h3 class="product-title"><?php the_title(); ?></h3>
                          <span class="text-muted"><?php echo get_the_excerpt(); ?></span>
                        </div>
                      </label>
                    </div>
                    <div class="col-md-6 bloc-price-end">
                      <div class="small prix-affiche price">
                        <?php echo wc_price($prix); ?>       <?php echo ($mode === 'leasing') ? ' / mois' : ''; ?>
                      </div>
                      <div class="col-md-2">
                        <input type="number" min="0" class="form-control input-qty text-end mt-2"
                          name="quantite_switch_<?php echo $i; ?>[]" value="<?php echo esc_attr($quantite); ?>"
                          data-id="<?php echo $product_id; ?>" data-switch-index="<?php echo $i; ?>"
                          data-unit="<?php echo esc_attr($prix); ?>" data-type="equipement" data-sous-type="switch-centrex"
                          data-nombre-ports="<?= $nombre_ports; ?>">
                      </div>
                      <div class="prix-total price col-md-3" data-unit="<?php echo esc_attr($prix); ?>">
                        <?php echo wc_price($prix_total); ?>       <?php echo ($mode === 'leasing') ? ' / mois' : ''; ?>
                      </div>
                      <?php if ($infos): ?>
                        <div class="icon-info" data-bs-toggle="tooltip" data-bs-placement="top"
                          data-bs-custom-class="custom-tooltip" data-bs-title="<?php echo $infos ?>">
                          <img src="<?php echo get_template_directory_uri() ?>/assets/img/info.svg" />
                        </div>
                      <?php endif ?>
                    </div>
                  </div>
                <?php endwhile;
                wp_reset_postdata(); ?>
              </div>
            </div>
          </div>

        </div>
      <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-between mt-3" id="footer-buttons">
      <button class="btn btn-outline-secondary btn-precedent" data-step="3">← Étape précédente</button>
      <button class="btn btn-primary btn-suivant" data-step="5">Étape suivante <i class="fa-solid fa-arrow-right"></i></button>
    </div>

  <?php else: ?>
    <div class="alert alert-warning">Aucune adresse ajoutée. Veuillez retourner à l’étape 1.</div>
  <?php endif; ?>

  <?php
  $produits_centrex = wc_get_products([
    'status' => 'publish',
    'limit' => -1,
    'category' => [
      'reseau-switchs',
      'telephonie-fixe-centrex',
      'accessoires-telecoms',
      'telephonie-fixe-centrex-equipements-telecom'
    ]
  ]);

  $frais_js_centrex = [];

  foreach ($produits_centrex as $produit) {
    $frais_associes = get_field('frais_installation_associes', $produit->get_id());

    if (!empty($frais_associes)) {
      foreach ($frais_associes as $frais) {
        $product_frais = null;

        if (is_numeric($frais)) {
          $product_frais = wc_get_product(intval($frais));
        } elseif ($frais instanceof WP_Post) {
          $product_frais = wc_get_product($frais->ID);
        } elseif ($frais instanceof WC_Product) {
          $product_frais = $frais;
        }

        if (!$product_frais)
          continue;

        $id = $produit->get_id();
        $frais_js_centrex[$id][] = [
          'id' => $product_frais->get_id(),
          'nom' => $product_frais->get_title(),
          'quantite' => 1,
          'prixComptant' => (float) $product_frais->get_regular_price(),
          'prixLeasing24' => (float) get_field('prix_leasing_24', $product_frais->get_id()) ?: 0,
          'prixLeasing36' => (float) get_field('prix_leasing_36', $product_frais->get_id()) ?: 0,
          'prixLeasing48' => (float) get_field('prix_leasing_48', $product_frais->get_id()) ?: 0,
          'prixLeasing63' => (float) get_field('prix_leasing_63', $product_frais->get_id()) ?: 0,
          'minPostes' => (int) get_field('min_postes', $product_frais->get_id()) ?: 0,
          'maxPostes' => (int) get_field('max_postes', $product_frais->get_id()) ?: 0,
          'offertAPartirDe' => get_field('offert_a_partir_de_licences', $product_frais->get_id()) ?: null,
        ];
      }
    }
  }
  ?>

  <script>
    window.fraisInstallationCentrexParProduit = <?= json_encode($frais_js_centrex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>

</div>