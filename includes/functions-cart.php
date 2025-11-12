<?php
/**
 * Fonctions PHP pour le panier SoEasy
 * À ajouter dans functions.php
 */

// ============================================================================
// ENQUEUE DES ASSETS PANIER
// ============================================================================

function soeasy_enqueue_cart_assets() {
    if (!is_cart()) return;
    
    // CSS panier
    wp_enqueue_style( 
        'soeasy-cart', 
        get_template_directory_uri() . '/assets/css/cart.css',
        array( 'woocommerce-general' ),
        filemtime( get_template_directory() . '/assets/css/cart.css' )
    );
    
    // JS panier
    wp_enqueue_script( 
        'soeasy-cart', 
        get_template_directory_uri() . '/assets/js/cart.js', 
        array( 'jquery', 'woocommerce' ), 
        filemtime( get_template_directory() . '/assets/js/cart.js' ), 
        true 
    );
    
    // Variables JS
    wp_localize_script( 'soeasy-cart', 'soeasyCartVars', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'security' => wp_create_nonce( 'soeasy_cart_action' ),
        'configurateur_url' => get_permalink( get_page_by_path( 'configurateur' ) ),
        'messages' => array(
            'confirm_remove_address' => 'Êtes-vous sûr de vouloir supprimer tous les produits pour cette adresse ?',
            'error_generic' => 'Une erreur est survenue. Veuillez réessayer.',
            'success_updated' => 'Panier mis à jour avec succès'
        )
    ) );
}
add_action( 'wp_enqueue_scripts', 'soeasy_enqueue_cart_assets' );

// ============================================================================
// ACTIONS AJAX POUR LE PANIER
// ============================================================================

/**
 * Récupérer les détails d'une configuration par adresse
 */
function soeasy_get_config_details() {
    // Vérification de sécurité
    if (!wp_verify_nonce($_POST['security'], 'soeasy_cart_action')) {
        wp_send_json_error('Sécurité : accès non autorisé');
    }
    
    $address = sanitize_text_field($_POST['address'] ?? '');
    if (empty($address)) {
        wp_send_json_error('Adresse manquante');
    }
    
    $cart = WC()->cart;
    $products_by_category = [];
    $total_config = 0;
    
    // Récupérer tous les produits pour cette adresse
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (($cart_item['soeasy_adresse'] ?? '') === $address) {
            $category = $cart_item['soeasy_categorie'] ?? 'Autre';
            
            if (!isset($products_by_category[$category])) {
                $products_by_category[$category] = [];
            }
            
            $_product = $cart_item['data'];
            $products_by_category[$category][] = [
                'name' => $_product->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => WC()->cart->get_product_price($_product),
                'subtotal' => WC()->cart->get_product_subtotal($_product, $cart_item['quantity']),
                'thumbnail' => $_product->get_image('thumbnail'),
                'cart_item_key' => $cart_item_key
            ];
            
            $total_config += $cart_item['line_total'];
        }
    }
    
    if (empty($products_by_category)) {
        wp_send_json_error('Aucun produit trouvé pour cette adresse');
    }
    
    // Générer le HTML
    $html = '<div class="config-details">';
    $html .= '<div class="config-address mb-3">';
    $html .= '<h6><i class="fas fa-map-marker-alt me-2"></i>' . esc_html($address) . '</h6>';
    $html .= '</div>';
    
    foreach ($products_by_category as $category => $products) {
        $html .= '<div class="config-category mb-4">';
        $html .= '<h6 class="category-title mb-3">' . esc_html($category) . '</h6>';
        $html .= '<div class="products-list">';
        
        foreach ($products as $product) {
            $html .= '<div class="product-item d-flex align-items-center mb-2 p-2 bg-light rounded">';
            $html .= '<div class="product-thumb me-3">' . $product['thumbnail'] . '</div>';
            $html .= '<div class="product-info flex-grow-1">';
            $html .= '<div class="product-name fw-semibold">' . esc_html($product['name']) . '</div>';
            $html .= '<div class="product-meta text-muted small">Qté : ' . $product['quantity'] . ' × ' . $product['price'] . '</div>';
            $html .= '</div>';
            $html .= '<div class="product-total fw-semibold text-primary">' . $product['subtotal'] . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '<div class="config-total mt-3 pt-3 border-top">';
    $html .= '<div class="d-flex justify-content-between align-items-center">';
    $html .= '<span class="h6 mb-0">Total configuration :</span>';
    $html .= '<span class="h5 mb-0 text-primary">' . wc_price($total_config) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_soeasy_get_config_details', 'soeasy_get_config_details');
add_action('wp_ajax_nopriv_soeasy_get_config_details', 'soeasy_get_config_details');

/**
 * Supprimer tous les produits d'une adresse
 */
function soeasy_remove_address_products() {
    // Vérification de sécurité
    if (!wp_verify_nonce($_POST['security'], 'soeasy_cart_action')) {
        wp_send_json_error('Sécurité : accès non autorisé');
    }
    
    $address = sanitize_text_field($_POST['address'] ?? '');
    if (empty($address)) {
        wp_send_json_error('Adresse manquante');
    }
    
    $cart = WC()->cart;
    $removed_count = 0;
    $items_to_remove = [];
    
    // Identifier les produits à supprimer
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (($cart_item['soeasy_adresse'] ?? '') === $address) {
            $items_to_remove[] = $cart_item_key;
        }
    }
    
    // Supprimer les produits
    foreach ($items_to_remove as $cart_item_key) {
        if ($cart->remove_cart_item($cart_item_key)) {
            $removed_count++;
        }
    }
    
    if ($removed_count > 0) {
        wp_send_json_success([
            'message' => "Configuration supprimée ({$removed_count} produits)",
            'removed_count' => $removed_count
        ]);
    } else {
        wp_send_json_error('Aucun produit supprimé');
    }
}
add_action('wp_ajax_soeasy_remove_address_products', 'soeasy_remove_address_products');
add_action('wp_ajax_nopriv_soeasy_remove_address_products', 'soeasy_remove_address_products');

/**
 * Mise à jour de la quantité d'un produit
 */
function soeasy_update_cart_quantity() {
    // Vérification de sécurité
    if (!wp_verify_nonce($_POST['security'], 'soeasy_cart_action')) {
        wp_send_json_error('Sécurité : accès non autorisé');
    }
    
    $cart_item_key = sanitize_text_field($_POST['cart_item_key'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    
    if (empty($cart_item_key) || $quantity < 0) {
        wp_send_json_error('Paramètres invalides');
    }
    
    $cart = WC()->cart;
    $cart_item = $cart->get_cart_item($cart_item_key);
    
    if (!$cart_item) {
        wp_send_json_error('Produit non trouvé dans le panier');
    }
    
    // Vérifier si c'est un produit configuration (non modifiable)
    if (isset($cart_item['soeasy_adresse'])) {
        wp_send_json_error('Les quantités des configurations ne peuvent pas être modifiées');
    }
    
    $old_quantity = $cart_item['quantity'];
    
    if ($quantity === 0) {
        // Supprimer le produit
        if ($cart->remove_cart_item($cart_item_key)) {
            wp_send_json_success(['message' => 'Produit supprimé', 'removed' => true]);
        } else {
            wp_send_json_error('Erreur lors de la suppression');
        }
    } else {
        // Mettre à jour la quantité
        if ($cart->set_quantity($cart_item_key, $quantity)) {
            $_product = $cart_item['data'];
            $line_total = WC()->cart->get_product_subtotal($_product, $quantity);
            
            wp_send_json_success([
                'message' => 'Quantité mise à jour',
                'line_total' => $line_total,
                'old_quantity' => $old_quantity
            ]);
        } else {
            wp_send_json_error('Erreur lors de la mise à jour');
        }
    }
}
add_action('wp_ajax_soeasy_update_cart_quantity', 'soeasy_update_cart_quantity');
add_action('wp_ajax_nopriv_soeasy_update_cart_quantity', 'soeasy_update_cart_quantity');

/**
 * Supprimer un produit individuel
 */
function soeasy_remove_cart_item() {
    // Vérification de sécurité
    if (!wp_verify_nonce($_POST['security'], 'soeasy_cart_action')) {
        wp_send_json_error('Sécurité : accès non autorisé');
    }
    
    $cart_item_key = sanitize_text_field($_POST['cart_item_key'] ?? '');
    
    if (empty($cart_item_key)) {
        wp_send_json_error('Clé de produit manquante');
    }
    
    $cart = WC()->cart;
    
    if ($cart->remove_cart_item($cart_item_key)) {
        wp_send_json_success(['message' => 'Produit supprimé du panier']);
    } else {
        wp_send_json_error('Erreur lors de la suppression du produit');
    }
}
add_action('wp_ajax_soeasy_remove_cart_item', 'soeasy_remove_cart_item');
add_action('wp_ajax_nopriv_soeasy_remove_cart_item', 'soeasy_remove_cart_item');

/**
 * Appliquer un code promo
 */
function soeasy_apply_coupon() {
    // Vérification de sécurité
    if (!wp_verify_nonce($_POST['security'], 'soeasy_cart_action')) {
        wp_send_json_error('Sécurité : accès non autorisé');
    }
    
    $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
    
    if (empty($coupon_code)) {
        wp_send_json_error('Code de réduction manquant');
    }
    
    $cart = WC()->cart;
    
    // Vérifier si le coupon existe
    $coupon = new WC_Coupon($coupon_code);
    if (!$coupon->is_valid()) {
        wp_send_json_error('Code de réduction invalide ou expiré');
    }
    
    // Appliquer le coupon
    if ($cart->apply_coupon($coupon_code)) {
        wp_send_json_success(['message' => 'Code de réduction appliqué avec succès']);
    } else {
        wp_send_json_error('Erreur lors de l\'application du code de réduction');
    }
}
add_action('wp_ajax_soeasy_apply_coupon', 'soeasy_apply_coupon');
add_action('wp_ajax_nopriv_soeasy_apply_coupon', 'soeasy_apply_coupon');

/**
 * Récupérer les totaux du panier
 */
function soeasy_get_cart_totals() {
    // Vérification de sécurité
    if (!wp_verify_nonce($_POST['security'], 'soeasy_cart_action')) {
        wp_send_json_error('Sécurité : accès non autorisé');
    }
    
    ob_start();
    wc_cart_totals();
    $totals_html = ob_get_clean();
    
    wp_send_json_success(['totals_html' => $totals_html]);
}
add_action('wp_ajax_soeasy_get_cart_totals', 'soeasy_get_cart_totals');
add_action('wp_ajax_nopriv_soeasy_get_cart_totals', 'soeasy_get_cart_totals');

// ============================================================================
// PERSONNALISATION DES TOTAUX WOOCOMMERCE
// ============================================================================

/**
 * Personnaliser l'affichage des totaux du panier
 */
function soeasy_custom_cart_totals() {
    ?>
    <div class="cart_totals <?php echo ( WC()->customer->has_calculated_shipping() ) ? 'calculated_shipping' : ''; ?>">
        
        <table cellspacing="0" class="shop_table shop_table_responsive">
            <tbody>
                <tr class="cart-subtotal">
                    <th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
                    <td data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>"><?php wc_cart_totals_subtotal_html(); ?></td>
                </tr>

                <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
                    <tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
                        <th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
                        <td data-title="<?php echo esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ); ?>">
                            <?php wc_cart_totals_coupon_html( $coupon ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
                    <?php wc_cart_totals_shipping_html(); ?>
                <?php endif; ?>

                <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
                    <tr class="fee">
                        <th><?php echo esc_html( $fee->name ); ?></th>
                        <td data-title="<?php echo esc_attr( $fee->name ); ?>"><?php wc_cart_totals_fee_html( $fee ); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
                    <?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
                        <?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
                            <tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
                                <th><?php echo esc_html( $tax->label ); ?></th>
                                <td data-title="<?php echo esc_attr( $tax->label ); ?>"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="tax-total">
                            <th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
                            <td data-title="<?php echo esc_attr( WC()->countries->tax_or_vat() ); ?>"><?php wc_cart_totals_taxes_total_html(); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>

                <tr class="order-total">
                    <th><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
                    <td data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>"><?php wc_cart_totals_order_total_html(); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="wc-proceed-to-checkout">
            <?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
        </div>

        <?php do_action( 'woocommerce_after_cart_totals' ); ?>
    </div>
    <?php
}

/**
 * Remplacer l'affichage des totaux par défaut
 */
function soeasy_replace_cart_totals() {
    remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cart_totals', 10 );
    add_action( 'woocommerce_cart_collaterals', 'soeasy_custom_cart_totals', 10 );
}
add_action( 'init', 'soeasy_replace_cart_totals' );

/**
 * Personnaliser le bouton checkout
 */
function soeasy_custom_proceed_to_checkout() {
    ?>
    <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" 
       class="checkout-button button alt wc-forward">
        Valider la commande
    </a>
    <?php
}
remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
add_action( 'woocommerce_proceed_to_checkout', 'soeasy_custom_proceed_to_checkout', 20 );

// ============================================================================
// HOOKS WOOCOMMERCE SPÉCIFIQUES AU PANIER
// ============================================================================

/**
 * Désactiver la redirection automatique vers le panier après ajout
 */
add_filter( 'woocommerce_add_to_cart_redirect', '__return_false' );

/**
 * Afficher des informations supplémentaires pour les produits configuration
 */
function soeasy_cart_item_configuration_badge( $product_name, $cart_item, $cart_item_key ) {
    if ( isset( $cart_item['soeasy_adresse'] ) ) {
        $badge = '<span class="badge bg-primary ms-2">Configuration</span>';
        return $product_name . $badge;
    }
    return $product_name;
}
add_filter( 'woocommerce_cart_item_name', 'soeasy_cart_item_configuration_badge', 10, 3 );

/**
 * Désactiver la modification des quantités pour les produits configuration
 */
function soeasy_cart_item_quantity_configuration( $product_quantity, $cart_item_key, $cart_item ) {
    if ( isset( $cart_item['soeasy_adresse'] ) ) {
        // Pour les produits configuration, afficher juste la quantité sans input
        return '<span class="quantity-display">' . $cart_item['quantity'] . '</span>';
    }
    return $product_quantity;
}
add_filter( 'woocommerce_cart_item_quantity', 'soeasy_cart_item_quantity_configuration', 10, 3 );

/**
 * Ajouter des classes CSS spécifiques aux lignes de configuration
 */
function soeasy_cart_item_class_configuration( $class, $cart_item, $cart_item_key ) {
    if ( isset( $cart_item['soeasy_adresse'] ) ) {
        $class .= ' soeasy-configuration-item';
    }
    return $class;
}
add_filter( 'woocommerce_cart_item_class', 'soeasy_cart_item_class_configuration', 10, 3 );

/**
 * Optimiser le chargement de la page panier
 */
function soeasy_optimize_cart_page() {
    if ( is_cart() ) {
        // Désactiver les widgets sur la page panier
        remove_action( 'wp_head', 'wp_widget_cache_print_scripts' );
        
        // Précharger les ressources critiques
        echo '<link rel="preload" href="' . get_template_directory_uri() . '/assets/css/cart.css" as="style">';
        echo '<link rel="preload" href="' . get_template_directory_uri() . '/assets/js/cart.js" as="script">';
    }
}
add_action( 'wp_head', 'soeasy_optimize_cart_page' );

/**
 * Ajouter des métadonnées structurées pour le panier
 */
function soeasy_cart_structured_data() {
    if ( ! is_cart() || WC()->cart->is_empty() ) {
        return;
    }
    
    $structured_data = [
        '@context' => 'https://schema.org',
        '@type' => 'ShoppingCart',
        'url' => wc_get_cart_url(),
        'totalPrice' => WC()->cart->get_total( 'edit' ),
        'priceCurrency' => get_woocommerce_currency(),
        'numberOfItems' => WC()->cart->get_cart_contents_count()
    ];
    
    echo '<script type="application/ld+json">' . wp_json_encode( $structured_data ) . '</script>';
}
add_action( 'wp_head', 'soeasy_cart_structured_data' );

// ============================================================================
// UTILITAIRES ET HELPERS
// ============================================================================

/**
 * Obtenir le nombre de configurations par adresse dans le panier
 */
function soeasy_get_cart_configurations_count() {
    $configurations = [];
    
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['soeasy_adresse'] ) ) {
            $address = $cart_item['soeasy_adresse'];
            if ( !in_array( $address, $configurations ) ) {
                $configurations[] = $address;
            }
        }
    }
    
    return count( $configurations );
}

/**
 * Obtenir le total des produits configuration dans le panier
 */
function soeasy_get_cart_configurations_total() {
    $total = 0;
    
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['soeasy_adresse'] ) ) {
            $total += $cart_item['line_total'];
        }
    }
    
    return $total;
}

/**
 * Vérifier si le panier contient uniquement des configurations
 */
function soeasy_cart_has_only_configurations() {
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( !isset( $cart_item['soeasy_adresse'] ) ) {
            return false;
        }
    }
    return true;
}

/**
 * Debug helper pour le panier
 */
function soeasy_debug_cart_contents() {
    if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['debug_cart'] ) ) {
        return;
    }
    
    echo '<div style="background: #f1f1f1; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">';
    echo '<h3>Debug Panier SoEasy</h3>';
    echo '<pre>';
    
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        echo "=== Produit: {$cart_item['data']->get_name()} ===\n";
        echo "Cart Item Key: {$cart_item_key}\n";
        echo "Quantité: {$cart_item['quantity']}\n";
        echo "Prix ligne: {$cart_item['line_total']}\n";
        
        if ( isset( $cart_item['soeasy_adresse'] ) ) {
            echo "Adresse: {$cart_item['soeasy_adresse']}\n";
        }
        if ( isset( $cart_item['soeasy_categorie'] ) ) {
            echo "Catégorie: {$cart_item['soeasy_categorie']}\n";
        }
        if ( isset( $cart_item['soeasy_config_id'] ) ) {
            echo "Config ID: {$cart_item['soeasy_config_id']}\n";
        }
        echo "\n";
    }
    
    echo "Configurations: " . soeasy_get_cart_configurations_count() . "\n";
    echo "Total configurations: " . wc_price( soeasy_get_cart_configurations_total() ) . "\n";
    echo "Total panier: " . WC()->cart->get_total() . "\n";
    
    echo '</pre>';
    echo '</div>';
}
add_action( 'woocommerce_before_cart', 'soeasy_debug_cart_contents' );



function soeasy_remove_coupon() {
    // Vérification de sécurité
    if (!wp_verify_nonce($_POST['security'], 'soeasy_cart_action')) {
        wp_send_json_error('Sécurité : accès non autorisé');
    }
    
    $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
    
    if (empty($coupon_code)) {
        wp_send_json_error('Code de réduction manquant');
    }
    
    $cart = WC()->cart;
    
    // Supprimer le coupon
    if ($cart->remove_coupon($coupon_code)) {
        wp_send_json_success(['message' => 'Code de réduction supprimé']);
    } else {
        wp_send_json_error('Erreur lors de la suppression du code de réduction');
    }
}
add_action('wp_ajax_soeasy_remove_coupon', 'soeasy_remove_coupon');
add_action('wp_ajax_nopriv_soeasy_remove_coupon', 'soeasy_remove_coupon');

?>