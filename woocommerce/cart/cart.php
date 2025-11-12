<?php
/**
 * Cart Page
 * Template custom SoEasy pour le panier
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_cart'); ?>

<div class="soeasy-cart-container">
    <div class="container">
        <div class="row">
            <!-- Zone principale du panier -->
            <div class="col-lg-8">
                <div class="cart-main-content">
                    <h1 class="cart-title mb-4">Votre panier</h1>

                    <form class="woocommerce-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>"
                        method="post">
                        <?php do_action('woocommerce_before_cart_table'); ?>

                        <?php
                        // Grouper les produits par type
                        $cart = WC()->cart;
                        $items_by_address = [];
                        $items_without_address = [];

                        // Séparer les produits configuration des produits classiques
                        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                            $address = $cart_item['soeasy_adresse'] ?? '';

                            if (!empty($address)) {
                                if (!isset($items_by_address[$address])) {
                                    $items_by_address[$address] = [];
                                }
                                $items_by_address[$address][$cart_item_key] = $cart_item;
                            } else {
                                $items_without_address[$cart_item_key] = $cart_item;
                            }
                        }
                        ?>

                        <!-- CONFIGURATIONS PAR ADRESSE -->
                        <?php if (!empty($items_by_address)): ?>
                            <div class="configurations-section mb-3">
                                <?php
                                // Récupérer les paramètres globaux de session
                                $duree_engagement = soeasy_get_selected_duree_engagement();
                                $mode_financement = soeasy_get_selected_financement();

                                foreach ($items_by_address as $address => $items):
                                    // Calculer les totaux par type
                                    $total_abonnements = 0;
                                    $total_equipements = 0;
                                    $premier_versement = 0;

                                    foreach ($items as $item) {
                                        $categorie = $item['soeasy_categorie'] ?? '';
                                        if (stripos($categorie, 'abonnement') !== false) {
                                            $total_abonnements += $item['line_total'];
                                        } else {
                                            $total_equipements += $item['line_total'];
                                        }
                                    }

                                    // Calcul du premier versement selon le mode de financement
                                    if ($mode_financement === 'leasing') {
                                        // Mode leasing : 1 mois d'abonnement + leasing
                                        $premier_versement = $total_abonnements + $total_equipements;
                                        $prix_mensuel = $total_abonnements + $total_equipements;
                                    } else {
                                        // Mode comptant : 1 mois d'abonnement + équipements comptant
                                        $premier_versement = $total_abonnements + $total_equipements;
                                        $prix_mensuel = $total_abonnements;
                                    }
                                    ?>
                                    <div class="configuration-group card mb-2" data-address="<?php echo esc_attr($address); ?>">
                                        <div class="card-body d-flex w-100">
                                            <div class="col">
                                                <div class="img-product ">
                                                    <img src="<?php echo get_template_directory_uri() ?>/assets/img/config-cart.svg" />
                                                </div>
                                            </div>
                                            <div class="col-md-8 config-info px-3">
                                                <p><a href="/configurateur/"><strong>Configuration</strong></a></p>
                                                <p class="mb-2 address-info"><i class="fas fa-map-marker-alt me-1"></i> Adresse :
                                                    <?php echo esc_html($address); ?>
                                                </p>
                                                <?php if ($mode_financement === 'leasing'): ?>
                                                    <div class="financing-info">
                                                        <span class="text-muted">Abonnement + Leasing : </span>
                                                        <strong><?php echo wc_price($prix_mensuel); ?> / mois</strong>
                                                    </div>
                                                    <div class="first-payment-info">
                                                        <span class="text-muted">= 1er versement : </span>
                                                        <strong><?php echo wc_price($premier_versement); ?></strong>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="financing-info">
                                                        <span class="text-muted">Abonnements mensuels : </span>
                                                        <strong><?php echo wc_price($total_abonnements); ?> / mois</strong>
                                                        <span class="text-muted"> + Équipements : </span>
                                                        <strong><?php echo wc_price($total_equipements); ?></strong>
                                                    </div>
                                                    <div class="first-payment-info">
                                                        <span class="text-muted ">= 1er versement : </span>
                                                        <strong><?php echo wc_price($premier_versement); ?></strong>
                                                    </div>
                                                    <button type="button" class="btn btn-details mt-2"
                                                        onclick="showConfigDetails('<?php echo esc_js($address); ?>')">
                                                        Voir le détails <i class="fa-solid fa-angle-right"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <div class="contract-info mt-2 pt-2 border-top">
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <span class="text-muted small">Durée d'engagement :</span>
                                                            <span class="fw-semibold small">
                                                                <?php echo $duree_engagement ? $duree_engagement . ' mois' : 'Sans engagement'; ?>
                                                            </span>
                                                        </div>
                                                        <div class="col-6">
                                                            <span class="text-muted small">Financement du matériel :</span>
                                                            <span class="fw-semibold small">
                                                                <?php echo $mode_financement === 'leasing' ? 'Location (leasing)' : 'Achat comptant'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="config-price pt-4">
                                                    <strong class="mb-0"><?php echo wc_price($premier_versement); ?></strong>
                                                </div>
                                            </div>
                                            <div class="col-md-1 text-end pt-4">
                                                <button type="button" class="btn btn-remove"
                                                    onclick="confirmRemoveAddress('<?php echo esc_js($address); ?>')">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- PRODUITS CLASSIQUES -->
                        <?php if (!empty($items_without_address)): ?>
                            <div class="classic-products-section">
                                <h5 class="mb-3">Produits</h5>

                                <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents"
                                    cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th class="product-remove">&nbsp;</th>
                                            <th class="product-thumbnail">&nbsp;</th>
                                            <th class="product-name">Produit</th>
                                            <th class="product-price">Prix</th>
                                            <th class="product-quantity">Quantité</th>
                                            <th class="product-subtotal">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items_without_address as $cart_item_key => $cart_item): ?>
                                            <?php
                                            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                                            $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

                                            if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                                                $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
                                                ?>
                                                <tr
                                                    class="woocommerce-cart-form__cart-item <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">

                                                    <td class="product-remove">
                                                        <?php
                                                        echo apply_filters('woocommerce_cart_item_remove_link', sprintf(
                                                            '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s"><i class="fas fa-times"></i></a>',
                                                            esc_url(wc_get_cart_remove_url($cart_item_key)),
                                                            esc_html__('Remove this item', 'woocommerce'),
                                                            esc_attr($product_id),
                                                            esc_attr($_product->get_sku())
                                                        ), $cart_item_key);
                                                        ?>
                                                    </td>

                                                    <td class="product-thumbnail">
                                                        <?php
                                                        $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);

                                                        if (!$product_permalink) {
                                                            echo $thumbnail; // PHPCS: XSS ok.
                                                        } else {
                                                            printf('<a href="%s">%s</a>', esc_url($product_permalink), $thumbnail); // PHPCS: XSS ok.
                                                        }
                                                        ?>
                                                    </td>

                                                    <td class="product-name"
                                                        data-title="<?php esc_attr_e('Product', 'woocommerce'); ?>">
                                                        <?php
                                                        if (!$product_permalink) {
                                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key) . '&nbsp;');
                                                        } else {
                                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a href="%s">%s</a>', esc_url($product_permalink), $_product->get_name()), $cart_item, $cart_item_key));
                                                        }

                                                        do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);

                                                        // Meta data.
                                                        echo wc_get_formatted_cart_item_data($cart_item); // PHPCS: XSS ok.
                                            
                                                        // Backorder notification.
                                                        if ($_product->backorders_require_notification() && $_product->is_on_backorder($cart_item['quantity'])) {
                                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__('Available on backorder', 'woocommerce') . '</p>', $product_id));
                                                        }
                                                        ?>
                                                    </td>

                                                    <td class="product-price"
                                                        data-title="<?php esc_attr_e('Price', 'woocommerce'); ?>">
                                                        <?php
                                                        echo apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key); // PHPCS: XSS ok.
                                                        ?>
                                                    </td>

                                                    <td class="product-quantity"
                                                        data-title="<?php esc_attr_e('Quantity', 'woocommerce'); ?>">
                                                        <?php
                                                        if ($_product->is_sold_individually()) {
                                                            $min_quantity = 1;
                                                            $max_quantity = 1;
                                                        } else {
                                                            $min_quantity = 0;
                                                            $max_quantity = $_product->get_max_purchase_quantity();
                                                        }

                                                        $product_quantity = woocommerce_quantity_input(
                                                            array(
                                                                'input_name' => "cart[{$cart_item_key}][qty]",
                                                                'input_value' => $cart_item['quantity'],
                                                                'max_value' => $max_quantity,
                                                                'min_value' => $min_quantity,
                                                                'product_name' => $_product->get_name(),
                                                            ),
                                                            $_product,
                                                            false
                                                        );

                                                        echo apply_filters('woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item); // PHPCS: XSS ok.
                                                        ?>
                                                    </td>

                                                    <td class="product-subtotal"
                                                        data-title="<?php esc_attr_e('Subtotal', 'woocommerce'); ?>">
                                                        <?php
                                                        echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); // PHPCS: XSS ok.
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        endforeach; ?>
                                    </tbody>
                                </table>

                                <!-- Actions pour produits classiques -->
                                <div class="cart-actions mt-3">
                                    <button type="submit" class="btn btn-primary" name="update_cart"
                                        value="<?php esc_attr_e('Update cart', 'woocommerce'); ?>">
                                        <?php esc_html_e('Update cart', 'woocommerce'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($items_by_address) && empty($items_without_address)): ?>
                            <div class="cart-empty text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <h4>Votre panier est vide</h4>
                                <p class="text-muted">Configurez votre solution télécoms dès maintenant</p>
                                <a href="<?php echo esc_url(get_permalink(get_page_by_path('configurateur'))); ?>"
                                    class="btn btn-primary">
                                    Configurer votre projet
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php do_action('woocommerce_cart_actions'); ?>
                        <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
                    </form>
                </div>
            </div>

            <!-- Sidebar totaux -->
            <div class="col-lg-4">
                <div class="cart-sidebar sticky-top">
                    <div class="cart-summary">
                        <h5 class="summary-title mb-3">Total panier</h5>

                        <!-- Codes promo -->
                        <div class="coupon-section mb-3">
                            <div class="d-flex">
                                <input type="text" name="coupon_code" class="form-control me-2"
                                    placeholder="Code de réduction" id="coupon_code" value="" />
                                <button type="button" class="btn btn-outline-primary" onclick="applyCoupon()">
                                    Appliquer
                                </button>
                            </div>
                        </div>

                        <!-- Totaux -->
                        <?php do_action('woocommerce_cart_collaterals'); ?>
                        <?php do_action('woocommerce_after_cart_collaterals'); ?>

                        <?php get_template_part('template-parts/reassurance'); ?>
                    
                        <!-- Moyens de paiement -->
                        <div class="payment-methods mt-3 text-center">
                            <img src="<?php echo get_template_directory_uri(); ?>/assets/images/payment-icons.png"
                                alt="Moyens de paiement" class="img-fluid">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal détails configuration -->
<div class="modal fade" id="configDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="configDetailsContent">
                    <!-- Contenu chargé via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal confirmation suppression -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer tous les produits pour cette adresse ?</p>
                <p class="text-muted mb-0">Cette action est irréversible.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<?php do_action('woocommerce_after_cart'); ?>