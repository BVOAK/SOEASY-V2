/**
 * SoEasy Cart - JavaScript
 * Gestion du panier personnalisé
 */

(function($) {
    'use strict';

    // Variables globales
    let currentAddressToDelete = '';
    
    /**
     * Initialisation
     */
    $(document).ready(function() {
        initCartEvents();
        initBootstrapModals();
    });

    /**
     * Initialisation des événements
     */
    function initCartEvents() {
        // Mise à jour automatique des quantités
        $(document).on('change', '.qty', function() {
            updateCartQuantity($(this));
        });

        // Suppression de produits individuels
        $(document).on('click', '.product-remove a', function(e) {
            e.preventDefault();
            removeCartItem($(this));
        });

        // Application de codes promo
        $(document).on('click', '[onclick="applyCoupon()"]', function(e) {
            e.preventDefault();
            applyCoupon();
        });

        // Gestion des touches dans le champ coupon
        $('#coupon_code').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                applyCoupon();
            }
        });

        // Confirmation de suppression d'adresse
        $('#confirmDeleteBtn').on('click', function() {
            if (currentAddressToDelete) {
                removeAddressProducts(currentAddressToDelete);
            }
        });
    }

    /**
     * Initialisation des modals Bootstrap
     */
    function initBootstrapModals() {
        // Vérifier si Bootstrap est chargé
        if (typeof bootstrap !== 'undefined') {
            window.configDetailsModal = new bootstrap.Modal(document.getElementById('configDetailsModal'));
            window.confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        } else {
            console.warn('Bootstrap non détecté, utilisation de jQuery modal');
        }
    }

    /**
     * Afficher les détails d'une configuration
     */
    window.showConfigDetails = function(address) {
        const $content = $('#configDetailsContent');
        
        // Afficher le loader
        $content.html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Chargement des détails...</p>
            </div>
        `);

        // Ouvrir la modal
        if (window.configDetailsModal) {
            window.configDetailsModal.show();
        } else {
            $('#configDetailsModal').modal('show');
        }

        // Requête AJAX
        $.ajax({
            url: soeasyCartVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'soeasy_get_config_details',
                address: address,
                security: soeasyCartVars.security
            },
            success: function(response) {
                if (response.success) {
                    $content.html(response.data.html);
                } else {
                    showError('Erreur lors du chargement des détails: ' + response.data);
                }
            },
            error: function() {
                showError('Erreur de connexion lors du chargement des détails.');
            }
        });
    };

    /**
     * Confirmer la suppression d'une adresse
     */
    window.confirmRemoveAddress = function(address) {
        currentAddressToDelete = address;
        
        // Mettre à jour le contenu de la modal
        $('#confirmDeleteModal .modal-body p:first').text(
            `Êtes-vous sûr de vouloir supprimer tous les produits pour l'adresse "${address}" ?`
        );

        // Ouvrir la modal de confirmation
        if (window.confirmDeleteModal) {
            window.confirmDeleteModal.show();
        } else {
            $('#confirmDeleteModal').modal('show');
        }
    };

    /**
     * Supprimer tous les produits d'une adresse
     */
    function removeAddressProducts(address) {
        // Fermer la modal
        if (window.confirmDeleteModal) {
            window.confirmDeleteModal.hide();
        } else {
            $('#confirmDeleteModal').modal('hide');
        }

        // Afficher le loader sur la configuration
        const $configGroup = $(`.configuration-group[data-address="${address}"]`);
        $configGroup.addClass('loading');

        // Requête AJAX
        $.ajax({
            url: soeasyCartVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'soeasy_remove_address_products',
                address: address,
                security: soeasyCartVars.security
            },
            success: function(response) {
                if (response.success) {
                    // Animation de suppression
                    $configGroup.removeClass('loading').fadeOut(400, function() {
                        $(this).remove();
                        
                        // Vérifier si le panier est vide
                        checkEmptyCart();
                        
                        // Recharger les totaux
                        reloadCartTotals();
                    });
                    
                    showSuccess('Configuration supprimée avec succès');
                } else {
                    $configGroup.removeClass('loading');
                    showError('Erreur lors de la suppression: ' + response.data);
                }
            },
            error: function() {
                $configGroup.removeClass('loading');
                showError('Erreur de connexion lors de la suppression.');
            }
        });
    }

    /**
     * Mise à jour de la quantité d'un produit
     */
    function updateCartQuantity($input) {
        const $row = $input.closest('tr');
        const cartItemKey = $input.attr('name').match(/cart\[([a-f0-9]+)\]/)[1];
        const newQuantity = parseInt($input.val()) || 0;

        if (newQuantity === 0) {
            removeCartItem($row.find('.product-remove a'));
            return;
        }

        // Afficher le loader sur la ligne
        $row.addClass('loading');

        // Requête AJAX pour mise à jour
        $.ajax({
            url: soeasyCartVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'soeasy_update_cart_quantity',
                cart_item_key: cartItemKey,
                quantity: newQuantity,
                security: soeasyCartVars.security
            },
            success: function(response) {
                $row.removeClass('loading');
                
                if (response.success) {
                    // Mettre à jour le sous-total de la ligne
                    $row.find('.product-subtotal').html(response.data.line_total);
                    
                    // Recharger les totaux
                    reloadCartTotals();
                    
                    showSuccess('Quantité mise à jour');
                } else {
                    // Restaurer la quantité précédente
                    $input.val(response.data.old_quantity || 1);
                    showError('Erreur lors de la mise à jour: ' + response.data.message);
                }
            },
            error: function() {
                $row.removeClass('loading');
                showError('Erreur de connexion lors de la mise à jour.');
            }
        });
    }

    /**
     * Suppression d'un produit individuel
     */
    function removeCartItem($removeLink) {
        const $row = $removeLink.closest('tr');
        const productId = $removeLink.data('product_id');
        const cartItemKey = $removeLink.attr('href').match(/remove_item=([a-f0-9]+)/)[1];

        $row.addClass('loading');

        $.ajax({
            url: soeasyCartVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'soeasy_remove_cart_item',
                cart_item_key: cartItemKey,
                security: soeasyCartVars.security
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                        checkEmptyCart();
                        reloadCartTotals();
                    });
                    
                    showSuccess('Produit supprimé du panier');
                } else {
                    $row.removeClass('loading');
                    showError('Erreur lors de la suppression: ' + response.data);
                }
            },
            error: function() {
                $row.removeClass('loading');
                showError('Erreur de connexion lors de la suppression.');
            }
        });
    }

    /**
     * Application d'un code promo
     */
    function applyCoupon() {
        const $input = $('#coupon_code');
        const $button = $('[onclick="applyCoupon()"]');
        const couponCode = $input.val().trim();

        if (!couponCode) {
            showError('Veuillez saisir un code de réduction');
            $input.focus();
            return;
        }

        // État de chargement
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Application...');

        $.ajax({
            url: soeasyCartVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'soeasy_apply_coupon',
                coupon_code: couponCode,
                security: soeasyCartVars.security
            },
            success: function(response) {
                $button.prop('disabled', false).html('Appliquer');
                
                if (response.success) {
                    $input.val('');
                    showSuccess('Code de réduction appliqué avec succès');
                    reloadCartTotals();
                } else {
                    showError('Code de réduction invalide: ' + response.data);
                    $input.focus().select();
                }
            },
            error: function() {
                $button.prop('disabled', false).html('Appliquer');
                showError('Erreur lors de l\'application du code de réduction.');
            }
        });
    }

    /**
     * Rechargement des totaux du panier
     */
    function reloadCartTotals() {
        $.ajax({
            url: soeasyCartVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'soeasy_get_cart_totals',
                security: soeasyCartVars.security
            },
            success: function(response) {
                if (response.success) {
                    $('.cart-totals').html(response.data.totals_html);
                }
            },
            error: function() {
                console.warn('Erreur lors du rechargement des totaux');
            }
        });
    }

    /**
     * Vérifier si le panier est vide
     */
    function checkEmptyCart() {
        const hasConfigs = $('.configuration-group').length > 0;
        const hasProducts = $('.classic-products-section tbody tr').length > 0;
        
        if (!hasConfigs && !hasProducts) {
            // Recharger la page pour afficher l'état "panier vide"
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    }

    /**
     * Affichage des messages de succès
     */
    function showSuccess(message) {
        showNotification(message, 'success');
    }

    /**
     * Affichage des messages d'erreur
     */
    function showError(message) {
        showNotification(message, 'error');
    }

    /**
     * Système de notifications
     */
    function showNotification(message, type) {
        // Créer le conteneur de notifications s'il n'existe pas
        if (!$('#soeasy-notifications').length) {
            $('body').append('<div id="soeasy-notifications" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>');
        }

        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        
        const $notification = $(`
            <div class="alert ${alertClass} alert-dismissible fade show shadow-sm" role="alert" style="min-width: 300px; margin-bottom: 10px;">
                <i class="fas ${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);

        $('#soeasy-notifications').append($notification);

        // Auto-suppression après 5 secondes
        setTimeout(() => {
            $notification.alert('close');
        }, 5000);
    }

    /**
     * Utilitaire pour échapper les caractères HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

})(jQuery);