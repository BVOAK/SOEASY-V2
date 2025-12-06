/**
 * ============================================================================
 * MODULE D'AUTHENTIFICATION - Modal login/logout
 * ============================================================================
 * 
 * Gère la connexion/déconnexion sans rechargement de page
 * 
 * @version 1.0
 * @date 2025-12-06
 */

(function($) {
  'use strict';

  /**
   * ========================================
   * VARIABLES GLOBALES
   * ========================================
   */
  
  let loginModal = null;

  /**
   * ========================================
   * INITIALISATION
   * ========================================
   */
  
  $(document).ready(function() {
    initLoginModal();
    bindLoginEvents();
  });

  /**
   * ========================================
   * MODAL DE CONNEXION
   * ========================================
   */

  function initLoginModal() {
    const modalElement = document.getElementById('modal-login');
    if (modalElement) {
      loginModal = new bootstrap.Modal(modalElement);
      console.log('✅ Modal de connexion initialisé');
    } else {
      console.warn('⚠️ Modal #modal-login non trouvé dans le DOM');
    }
  }

  /**
   * Ouvrir le modal de connexion
   */
  window.showLoginModal = function() {
    if (!loginModal) {
      initLoginModal();
    }

    if (!loginModal) {
      console.error('❌ Impossible d\'ouvrir le modal de connexion');
      return;
    }

    // Reset formulaire
    $('#form-login-ajax')[0].reset();
    $('#login-error-message').hide();

    // Focus sur username après ouverture
    $('#modal-login').one('shown.bs.modal', function() {
      $('#login-username').focus();
    });

    loginModal.show();

    console.log('🔓 Modal de connexion ouvert');
  };

  /**
   * ========================================
   * GESTION ÉVÉNEMENTS
   * ========================================
   */

  function bindLoginEvents() {
    // Soumission du formulaire de connexion
    $(document).on('submit', '#form-login-ajax', function(e) {
      e.preventDefault();
      handleLogin();
    });
  }

  /**
   * Traiter la connexion AJAX
   */
  function handleLogin() {
    const $form = $('#form-login-ajax');
    const $btn = $('#btn-submit-login');
    const $errorDiv = $('#login-error-message');

    // Récupérer les données
    const username = $('#login-username').val().trim();
    const password = $('#login-password').val();
    const remember = $('#login-remember').is(':checked');

    // Validation basique
    if (!username || !password) {
      $errorDiv.text('Veuillez remplir tous les champs.').show();
      return;
    }

    // Désactiver le bouton
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Connexion...');
    $errorDiv.hide();

    console.log('🔐 Tentative de connexion pour:', username);

    // Appel AJAX WordPress
    $.ajax({
      url: soeasyVars.ajaxurl,
      type: 'POST',
      data: {
        action: 'soeasy_ajax_login',
        username: username,
        password: password,
        remember: remember ? '1' : '0',
        nonce: soeasyVars.nonce_config
      }
    }).done(function(response) {
      if (response.success) {
        console.log('✅ Connexion réussie');

        // Fermer le modal
        if (loginModal) {
          loginModal.hide();
        }

        // Notification succès
        if (typeof showToastSuccess === 'function') {
          showToastSuccess('Connexion réussie ! Bienvenue ' + response.data.user_display_name);
        }

        // Mettre à jour soeasyVars
        if (typeof soeasyVars !== 'undefined') {
          soeasyVars.userId = response.data.user_id;
          soeasyVars.userDisplayName = response.data.user_display_name;
        }

        // Mettre à jour localStorage
        localStorage.setItem('soeasyUserId', response.data.user_id);

        // Appeler la réconciliation pour gérer le passage guest → user
        if (typeof reconcileConfiguration === 'function') {
          reconcileConfiguration().then(function() {
            console.log('✅ Réconciliation après login terminée');

            // Mettre à jour le bouton de sauvegarde
            if (typeof window.updateSaveButton === 'function') {
              window.updateSaveButton();
            }

            // Ouvrir automatiquement le modal de sauvegarde après un court délai
            setTimeout(function() {
              if (typeof window.showSaveConfigModal === 'function') {
                window.showSaveConfigModal();
              }
            }, 800);
          }).catch(function(error) {
            console.error('❌ Erreur réconciliation:', error);
          });
        } else {
          console.warn('⚠️ reconcileConfiguration non disponible, rechargement de la page');
          setTimeout(function() {
            window.location.reload();
          }, 1000);
        }

      } else {
        // Erreur de connexion
        console.error('❌ Erreur connexion:', response.data);
        $errorDiv.text(response.data.message || 'Identifiants incorrects').show();
      }
    }).fail(function(xhr, status, error) {
      console.error('❌ Échec réseau:', {status, error});
      $errorDiv.text('Erreur de communication avec le serveur.').show();
    }).always(function() {
      // Réactiver le bouton
      $btn.prop('disabled', false).html(originalHtml);
    });
  }

})(jQuery);