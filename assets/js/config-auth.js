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

(function ($) {
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

  $(document).ready(function () {
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
  window.showLoginModal = function () {
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
    $('#modal-login').one('shown.bs.modal', function () {
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
    $(document).on('submit', '#form-login-ajax', function (e) {
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

    // ✅ RÉCUPÉRER les données localStorage pour les envoyer avec le login
    let localConfig = null;
    let configData = {};
    let adressesData = [];

    try {
      const configStr = localStorage.getItem('soeasyConfig') || '{}';
      const adressesStr = localStorage.getItem('soeasyAdresses') || '[]';

      configData = JSON.parse(configStr);
      adressesData = JSON.parse(adressesStr);

      console.log('📦 Données à synchroniser:', {
        config: Object.keys(configData).length,
        adresses: adressesData.length
      });
    } catch (e) {
      console.warn('⚠️ Erreur parsing localStorage:', e);
    }

    // Appel AJAX WordPress
    $.ajax({
      url: soeasyVars.ajaxurl,
      type: 'POST',
      data: {
        action: 'soeasy_ajax_login',
        username: username,
        password: password,
        remember: remember ? '1' : '0',
        nonce: soeasyVars.nonce_config,
        // ✅ ENVOYER les données guest
        config: JSON.stringify(configData),
        adresses: JSON.stringify(adressesData),
        duree_engagement: localStorage.getItem('selectedDureeEngagement') || '0',
        mode_financement: localStorage.getItem('selectedFinancementMode') || 'comptant'
      }
    }).done(function (response) {
      if (response.success) {
        console.log('✅ Connexion réussie');

        if (response.data.sync_done) {
          console.log('✅ Données synchronisées côté serveur');
        }

        // Fermer le modal
        if (loginModal) {
          loginModal.hide();
        }

        // Notification succès
        if (typeof showToastSuccess === 'function') {
          showToastSuccess('Connexion réussie ! Bienvenue ' + response.data.user_display_name);
        }

        // ✅ METTRE À JOUR soeasyVars
        if (typeof soeasyVars !== 'undefined') {
          soeasyVars.userId = response.data.user_id;
          soeasyVars.userDisplayName = response.data.user_display_name;
          soeasyVars.nonce_config = response.data.nonce_config;
          soeasyVars.nonce_cart = response.data.nonce_cart;
          soeasyVars.nonce_address = response.data.nonce_address;
        }

        // Mettre à jour localStorage
        localStorage.setItem('soeasyUserId', response.data.user_id);

        // Mettre à jour le bouton de sauvegarde
        if (typeof window.updateSaveButton === 'function') {
          window.updateSaveButton();
        }

        // Ouvrir le modal de sauvegarde
        setTimeout(function () {
          if (typeof window.showSaveConfigModal === 'function') {
            window.showSaveConfigModal();
          }
        }, 500);

      } else {
        // Erreur de connexion
        console.error('❌ Erreur connexion:', response.data);
        $errorDiv.text(response.data.message || 'Identifiants incorrects').show();
      }
    }).fail(function (xhr, status, error) {
      console.error('❌ Échec réseau:', { status, error });
      $errorDiv.text('Erreur de communication avec le serveur.').show();
    }).always(function () {
      // Réactiver le bouton
      $btn.prop('disabled', false).html(originalHtml);
    });
  }

})(jQuery);