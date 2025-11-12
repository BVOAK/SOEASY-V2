<?php
/**
 * Template Name: Panier SoEasy Adapté Multi-Adresses
 * Template pour: wp-content/themes/soeasy/woocommerce/cart/cart.php
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' ); ?>

<div class="soeasy-cart-wrapper">
    <div class="container py-4">
        <div class="row">
            <!-- Colonne principale - Panier -->
            <div class="col-lg-8">
                <h1 class="mb-4">Votre panier</h1>
                
                <?php do_action( 'woocommerce_before_cart_table' ); ?>
                
                <form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
                    
                    <?php 
                    // Grouper les items par adresse
                    $items_by_address = [];
                    $items_without_address = [];
                    
                    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                        $address = $cart_item['soeasy_adresse'] ?? '';
                        
                        if ( !empty($address) ) {
                            if ( !isset($items_by_address[$address]) ) {
                                $items_by_address[$address] = [];
                            }
                            $items_by_address[$address][$cart_item_key] = $cart_item;
                        } else {
                            $items_without_address[$cart_item_key] = $cart_item;
                        }
                    }
                    
                    // Affichage par adresse
                    if ( !empty($items_by_address) ) : ?>
                        
                        <div class="cart-by-address">
                            <?php foreach ( $items_by_address as $address => $items ) : ?>
                                
                                <div class="address-section mb-5">
                                    <div class="address-header d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                                        <h4 class="mb-0">
                                            <i class="fas fa-map-marker-alt me-2"></i>
                                            <?php echo esc_html($address); ?>
                                        </h4>
                                        <div class="address-actions">
                                            <button type="button" class="btn btn-outline-primary btn-sm me-2" 
                                                    onclick="modifierConfiguration('<?php echo esc_js($address); ?>')">
                                                <i class="fas fa-edit me-1"></i>Modifier
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                    onclick="supprimerAdresse('<?php echo esc_js($address); ?>')">
                                                <i class="fas fa-trash me-1"></i>Supprimer
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Grouper par catégorie pour cette adresse -->
                                    <?php 
                                    $items_by_category = [];
                                    foreach ( $items as $cart_item_key => $cart_item ) {
                                        $category = $cart_item['soeasy_categorie'] ?? 'Autre';
                                        if ( !isset($items_by_category[$category]) ) {
                                            $items_by_category[$category] = [];
                                        }
                                        $items_by_category[$category][$cart_item_key] = $cart_item;
                                    }
                                    ?>
                                    
                                    <?php foreach ( $items_by_category as $category => $category_items ) : ?>
                                        
                                        <div class="category-section mb-4">
                                            <h5 class="category-title mb-3">
                                                <span class="badge bg-secondary me-2"><?php echo esc_html($category); ?></span>
                                                <small class="text-muted"><?php echo count($category_items); ?> produit(s)</small>
                                            </h5>
                                            
                                            <div class="table-responsive">
                                                <table class="table table-cart">
                                                    <thead>
                                                        <tr>
                                                            <th class="product-thumbnail">Produit</th>
                                                            <th class="product-name">Détails</th>
                                                            <th class="product-price">Prix unitaire</th>
                                                            <th class="product-quantity">Quantité</th>
                                                            <th class="product-subtotal">Total</th>
                                                            <th class="product-remove">&nbsp;</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ( $category_items as $cart_item_key => $cart_item ) : 
                                                            $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                                                            $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
                                                            
                                                            if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) :
                                                                $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                                                        ?>
                                                            
                                                            <tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
                                                                
                                                                <!-- Thumbnail -->
                                                                <td class="product-thumbnail">
                                                                    <?php
                                                                    $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
                                                                    if ( ! $product_permalink ) {
                                                                        echo $thumbnail;
                                                                    } else {
                                                                        printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail );
                                                                    }
                                                                    ?>
                                                                </td>
                                                                
                                                                <!-- Nom produit + métadonnées -->
                                                                <td class="product-name" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
                                                                    <?php
                                                                    if ( ! $product_permalink ) {
                                                                        echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;' );
                                                                    } else {
                                                                        echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
                                                                    }
                                                                    
                                                                    // Métadonnées custom SoEasy
                                                                    do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
                                                                    
                                                                    // Affichage des données item
                                                                    echo wc_get_formatted_cart_item_data( $cart_item );
                                                                    
                                                                    // Backorder notification
                                                                    if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
                                                                        echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
                                                                    }
                                                                    ?>
                                                                </td>
                                                                
                                                                <!-- Prix unitaire -->
                                                                <td class="product-price" data-title="<?php esc_attr_e( 'Price', 'woocommerce' ); ?>">
                                                                    <?php
                                                                        echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
                                                                    ?>
                                                                </td>
                                                                
                                                                <!-- Quantité -->
                                                                <td class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce' ); ?>">
                                                                    <?php
                                                                    if ( $_product->is_sold_individually() ) {
                                                                        $min_quantity = 1;
                                                                        $max_quantity = 1;
                                                                    } else {
                                                                        $min_quantity = 0;
                                                                        $max_quantity = $_product->get_max_purchase_quantity();
                                                                    }
                                                                    
                                                                    $product_quantity = woocommerce_quantity_input(
                                                                        array(
                                                                            'input_name'   => "cart[{$cart_item_key}][qty]",
                                                                            'input_value'  => $cart_item['quantity'],
                                                                            'max_value'    => $max_quantity,
                                                                            'min_value'    => $min_quantity,
                                                                            'product_name' => $_product->get_name(),
                                                                        ),
                                                                        $_product,
                                                                        false
                                                                    );
                                                                    
                                                                    echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item );
                                                                    ?>
                                                                </td>
                                                                
                                                                <!-- Total -->
                                                                <td class="product-subtotal" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
                                                                    <?php
                                                                        echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
                                                                    ?>
                                                                </td>
                                                                
                                                                <!-- Supprimer -->
                                                                <td class="product-remove">
                                                                    <?php
                                                                        echo apply_filters(
                                                                            'woocommerce_cart_item_remove_link',
                                                                            sprintf(
                                                                                '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s"><i class="fas fa-times"></i></a>',
                                                                                esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                                                                esc_html__( 'Remove this item', 'woocommerce' ),
                                                                                esc_attr( $product_id ),
                                                                                esc_attr( $_product->get_sku() )
                                                                            ),
                                                                            $cart_item_key
                                                                        );
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                            
                                                        <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                    <?php endforeach; ?>
                                    
                                    <!-- Total par adresse -->
                                    <div class="address-total text-end mb-4">
                                        <?php 
                                        $address_total = 0;
                                        foreach ( $items as $cart_item ) {
                                            $address_total += $cart_item['line_total'];
                                        }
                                        ?>
                                        <strong>Total pour cette adresse : <?php echo wc_price($address_total); ?></strong>
                                    </div>
                                </div>
                                
                            <?php endforeach; ?>
                        </div>
                        
                    <?php endif; ?>
                    
                    <!-- Items sans adresse (cas legacy) -->
                    <?php if ( !empty($items_without_address) ) : ?>
                        <div class="other-items mb-4">
                            <h4>Autres produits</h4>
                            <!-- Template classique WooCommerce pour ces items -->
                        </div>
                    <?php endif; ?>
                    
                    <!-- Actions panier -->
                    <div class="cart-actions mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <?php if ( wc_coupons_enabled() ) { ?>
                                    <div class="coupon">
                                        <label for="coupon_code" class="form-label"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
                                        <div class="input-group">
                                            <input type="text" name="coupon_code" class="form-control" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" />
                                            <button type="submit" class="btn btn-outline-secondary" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>">
                                                <?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?>
                                            </button>
                                        </div>
                                        <?php do_action( 'woocommerce_cart_coupon' ); ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="submit" class="btn btn-primary" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>">
                                    <i class="fas fa-sync-alt me-1"></i><?php esc_html_e( 'Update cart', 'woocommerce' ); ?>
                                </button>
                                
                                <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'configurateur' ) ) ); ?>" class="btn btn-outline-primary ms-2">
                                    <i class="fas fa-plus me-1"></i>Ajouter une adresse
                                </a>
                            </div>
                        </div>
                        
                        <?php do_action( 'woocommerce_cart_actions' ); ?>
                        <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                    </div>
                    
                </form>
                
                <?php do_action( 'woocommerce_after_cart_table' ); ?>
            </div>
            
            <!-- Sidebar - Totaux et checkout -->
            <div class="col-lg-4">
                <div class="cart-summary sticky-top">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Récapitulatif de commande</h5>
                        </div>
                        <div class="card-body">
                            <?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
                            
                            <div class="cart-collaterals">
                                <?php
                                    /**
                                     * Cart collaterals hook.
                                     *
                                     * @hooked woocommerce_cross_sell_display
                                     * @hooked woocommerce_cart_totals - 10
                                     */
                                    do_action( 'woocommerce_cart_collaterals' );
                                ?>
                            </div>
                            
                            <div class="checkout-actions mt-4">
                                <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="btn btn-success btn-lg w-100 mb-3">
                                    <i class="fas fa-lock me-2"></i>Procéder au paiement
                                </a>
                                
                                <div class="text-center">
                                    <small class="text-muted">Paiement sécurisé</small><br>
                                    <div class="payment-icons mt-2">
                                        <i class="fab fa-cc-visa"></i>
                                        <i class="fab fa-cc-mastercard"></i>
                                        <i class="fab fa-paypal"></i>
                                        <i class="fas fa-university"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aide/Support -->
                    <div class="cart-help mt-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6>Besoin d'aide ?</h6>
                                <p class="small mb-2">Nos experts sont là pour vous accompagner</p>
                                <a href="#" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-phone me-1"></i>Nous contacter
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonctions JavaScript pour les actions panier
function modifierConfiguration(address) {
    // Redirection vers configurateur avec adresse pré-sélectionnée
    const configUrl = '<?php echo esc_url( get_permalink( wc_get_page_id( "configurateur" ) ) ); ?>';
    window.location.href = configUrl + '?edit_address=' + encodeURIComponent(address);
}

function supprimerAdresse(address) {
    if (confirm('Êtes-vous sûr de vouloir supprimer tous les produits pour l\'adresse "' + address + '" ?')) {
        // AJAX call pour supprimer les items de cette adresse
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'soeasy_remove_address_from_cart',
            address: address,
            security: '<?php echo wp_create_nonce('soeasy_cart_action'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Erreur lors de la suppression');
            }
        });
    }
}
</script>

<?php do_action( 'woocommerce_after_cart' ); ?>