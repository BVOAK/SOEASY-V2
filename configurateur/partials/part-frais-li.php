<?php
$product = null;
if (is_numeric($frais)) {
  $product = wc_get_product(intval($frais));
} elseif ($frais instanceof WP_Post) {
  $product = wc_get_product($frais->ID);
} elseif ($frais instanceof WC_Product) {
  $product = $frais;
}
if (!$product) return;

$product_id = $product->get_id();
$quantite = isset($frais_quantite) ? intval($frais_quantite) : 1;

$prix_comptant    = floatval($product->get_regular_price());
$prix_leasing_24  = floatval(get_field('prix_leasing_24', $product_id)) ?: 0;
$prix_leasing_36  = floatval(get_field('prix_leasing_36', $product_id)) ?: 0;
$prix_leasing_48  = floatval(get_field('prix_leasing_48', $product_id)) ?: 0;
$prix_leasing_63  = floatval(get_field('prix_leasing_63', $product_id)) ?: 0;

$total = $prix_comptant * $quantite;
?>
<li class="list-group-item d-flex justify-content-between align-items-center">
  <label class="form-check-label flex-grow-1">
    <input type="checkbox" class="form-check-input frais-checkbox me-2"
           data-id="<?= esc_attr($product_id); ?>"
           data-index="<?= esc_attr($i); ?>"
           data-quantite="<?= esc_attr($quantite); ?>"
           data-prix-comptant="<?= esc_attr($prix_comptant); ?>"
           data-prix-leasing-24="<?= esc_attr($prix_leasing_24); ?>"
           data-prix-leasing-36="<?= esc_attr($prix_leasing_36); ?>"
           data-prix-leasing-48="<?= esc_attr($prix_leasing_48); ?>"
           data-prix-leasing-63="<?= esc_attr($prix_leasing_63); ?>"
           checked>
    <?= esc_html($product->get_title()); ?>
  </label>
  <span class="fw-bold"><?= wc_price($total); ?></span>
</li>
