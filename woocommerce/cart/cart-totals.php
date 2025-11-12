<?php
/**
 * Cart totals
 * Template personnalisé SoEasy pour les totaux du panier
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="cart_totals <?php echo ( WC()->customer->has_calculated_shipping() ) ? 'calculated_shipping' : ''; ?>">

    <?php do_action( 'woocommerce_before_cart_totals' ); ?>

    <!-- Livraison -->
    <div class="delivery-info mb-3">
        <div class="d-flex align-items-center">
            <i class="fas fa-truck text-success me-2"></i>
            <span class="text-muted">Livraison</span>
            <span class="ms-auto fw-semibold text-success">Gratuit</span>
        </div>
    </div>

    <!-- TVA -->
    <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
        <div class="tax-info mb-3">
            <div class="d-flex align-items-center">
                <span class="text-muted">TVA</span>
                <span class="ms-auto">
                    <?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
                        <?php 
                        $tax_total = 0;
                        foreach ( WC()->cart->get_tax_totals() as $code => $tax ) {
                            $tax_total += $tax->amount;
                        }
                        echo wc_price( $tax_total );
                        ?>
                    <?php else : ?>
                        <?php echo WC()->cart->get_taxes_total(); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Total estimé -->
    <div class="estimated-total mb-3">
        <div class="d-flex align-items-center">
            <span class="text-muted">Total estimé</span>
            <span class="ms-auto h6 mb-0"><?php wc_cart_totals_order_total_html(); ?></span>
        </div>
    </div>

    <!-- Coupons appliqués -->
    <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
        <div class="applied-coupon mb-2">
            <div class="d-flex align-items-center">
                <i class="fas fa-tag text-success me-2"></i>
                <span class="text-success"><?php echo esc_html( $coupon->get_code() ); ?></span>
                <span class="ms-auto text-success">
                    <?php wc_cart_totals_coupon_html( $coupon ); ?>
                </span>
                <button type="button" class="btn btn-sm btn-outline-danger ms-2" 
                        onclick="removeCoupon('<?php echo esc_js( $coupon->get_code() ); ?>')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Frais supplémentaires -->
    <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
        <div class="cart-fee mb-2">
            <div class="d-flex align-items-center">
                <span class="text-muted"><?php echo esc_html( $fee->name ); ?></span>
                <span class="ms-auto"><?php wc_cart_totals_fee_html( $fee ); ?></span>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Méthodes de livraison -->
    <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
        <div class="shipping-methods mb-3">
            <?php do_action( 'woocommerce_cart_totals_before_shipping' ); ?>
            <?php wc_cart_totals_shipping_html(); ?>
            <?php do_action( 'woocommerce_cart_totals_after_shipping' ); ?>
        </div>
    <?php endif; ?>

    <!-- Bouton de commande -->
    <div class="wc-proceed-to-checkout">
        <?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
    </div>

    <?php do_action( 'woocommerce_after_cart_totals' ); ?>

</div>

<script>
/**
 * Supprimer un coupon
 */
function removeCoupon(couponCode) {
    if (!confirm('Supprimer ce code de réduction ?')) {
        return;
    }
    
    $.ajax({
        url: soeasyCartVars.ajaxurl,
        type: 'POST',
        data: {
            action: 'soeasy_remove_coupon',
            coupon_code: couponCode,
            security: soeasyCartVars.security
        },
        success: function(response) {
            if (response.success) {
                location.reload(); // Recharger pour mettre à jour les totaux
            } else {
                alert('Erreur lors de la suppression du coupon: ' + response.data);
            }
        },
        error: function() {
            alert('Erreur de connexion');
        }
    });
}
</script>