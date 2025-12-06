/**
 * ============================================================================
 * MODULE DE SAUVEGARDE - Manuelle + Auto-save
 * ============================================================================
 * 
 * Gère :
 * - Bouton de sauvegarde dynamique (guest vs connecté)
 * - Modal de saisie du nom
 * - Sauvegarde manuelle en BDD
 * - Auto-save intelligente (Phase 3)
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

  let saveModal = null;
  let autoSaveTimer = null;
  let lastAutoSave = 0;
  const AUTO_SAVE_COOLDOWN = 10000; // 10 secondes minimum entre auto-saves

  /**
   * ========================================
   * INITIALISATION
   * ========================================
   */

  $(document).ready(function () {
    initSaveModal();
    bindSaveEvents();
    updateSaveButton();
    checkJustLoggedIn();
  });

  /**
 * Vérifier si on vient de se connecter et ouvrir le modal
 */
  function checkJustLoggedIn() {
    const justLoggedIn = localStorage.getItem('soeasy_just_logged_in');

    if (justLoggedIn === '1') {
      console.log('🔓 Détection login récent, ouverture modal sauvegarde');

      // Supprimer le flag
      localStorage.removeItem('soeasy_just_logged_in');

      // Ouvrir le modal de sauvegarde après un court délai
      setTimeout(function () {
        const userId = parseInt(soeasyVars.userId) || 0;

        if (userId > 0) {
          // User connecté : ouvrir modal sauvegarde
          if (typeof window.showSaveConfigModal === 'function') {
            window.showSaveConfigModal();
          }
        }
      }, 500);
    }
  }

  /**
   * ========================================
   * BOUTON SAUVEGARDER - GESTION DYNAMIQUE
   * ========================================
   */

  /**
   * Mettre à jour l'apparence du bouton selon l'état de connexion
   */
  function updateSaveButton() {
    const $btn = $('#btn-save-config');
    if ($btn.length === 0) {
      return;
    }

    //const currentStep = parseInt(localStorage.getItem('soeasyCurrentStep') || '1');
    const userId = parseInt(soeasyVars.userId) || 0;
    const adresses = JSON.parse(localStorage.getItem('soeasyAdresses') || '[]');

    // Afficher si step >= 2 et au moins une adresse
    if (adresses.length > 0) {
      if (userId > 0) {
        // User connecté : bouton sauvegarde normal
        $btn.html('<i class="fas fa-save me-1"></i> Sauvegarder')
          .removeClass('btn-outline-primary')
          .addClass('btn-success')
          .attr('title', 'Sauvegarder cette configuration')
          .show();
      } else {
        // Guest : bouton qui ouvre la connexion
        $btn.html('<i class="fas fa-lock me-1"></i> Se connecter pour sauvegarder')
          .removeClass('btn-success')
          .addClass('btn-outline-primary')
          .attr('title', 'Connectez-vous pour sauvegarder votre configuration')
          .show();
      }
    } else {
      $btn.hide();
    }
  }

  // Exposer globalement pour mise à jour depuis d'autres fichiers
  window.updateSaveButton = updateSaveButton;

  /**
   * ========================================
   * MODAL DE SAUVEGARDE
   * ========================================
   */

  function initSaveModal() {
    const modalElement = document.getElementById('modal-save-config');
    if (modalElement) {
      saveModal = new bootstrap.Modal(modalElement);
      console.log('✅ Modal de sauvegarde initialisé');
    }
  }

  /**
   * Ouvrir le modal de sauvegarde
   */
  window.showSaveConfigModal = function () {
    const userId = parseInt(soeasyVars.userId) || 0;

    if (userId === 0) {
      console.warn('⚠️ Tentative de sauvegarde sans être connecté');
      return;
    }

    if (!saveModal) {
      initSaveModal();
    }

    if (!saveModal) {
      console.error('❌ Impossible d\'ouvrir le modal de sauvegarde');
      return;
    }

    // Pré-remplir le nom si config déjà sauvegardée
    const configId = localStorage.getItem('soeasyConfigId');

    if (configId) {
      $('#config-name-input').attr('placeholder', 'Configuration existante (sera mise à jour)');
    } else {
      const date = new Date();
      const dateStr = date.toLocaleDateString('fr-FR');
      const timeStr = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
      $('#config-name-input').attr('placeholder', 'Configuration du ' + dateStr + ' à ' + timeStr);
    }

    $('#config-name-input').val('');
    $('#save-config-message').hide();

    // Focus sur input après ouverture
    $('#modal-save-config').one('shown.bs.modal', function () {
      $('#config-name-input').focus();
    });

    saveModal.show();

    console.log('💾 Modal de sauvegarde ouvert');
  };

  /**
   * ========================================
   * GESTION ÉVÉNEMENTS
   * ========================================
   */

  function bindSaveEvents() {
    // Clic sur bouton "Sauvegarder" dans la sidebar
    $(document).on('click', '#btn-save-config', function () {
      const userId = parseInt(soeasyVars.userId) || 0;

      if (userId > 0) {
        // User connecté : ouvrir modal sauvegarde
        window.showSaveConfigModal();
      } else {
        // Guest : ouvrir modal connexion
        if (typeof window.showLoginModal === 'function') {
          window.showLoginModal();
        } else {
          console.error('❌ Fonction showLoginModal non disponible');
          alert('Veuillez vous connecter pour sauvegarder votre configuration.');
        }
      }
    });

    // Clic sur bouton "Confirmer" dans modal sauvegarde
    $(document).on('click', '#btn-confirm-save', function () {
      handleSaveConfiguration();
    });

    // Touche Entrée dans l'input du nom
    $(document).on('keypress', '#config-name-input', function (e) {
      if (e.which === 13) {
        e.preventDefault();
        handleSaveConfiguration();
      }
    });
  }

  /**
   * ========================================
   * SAUVEGARDE MANUELLE
   * ========================================
   */

  /**
   * Traiter la sauvegarde manuelle
   */
  function handleSaveConfiguration() {
    const configName = $('#config-name-input').val().trim();

    console.log('💾 Sauvegarde manuelle demandée, nom:', configName || '(auto)');

    // Désactiver le bouton pendant la sauvegarde
    const $btn = $('#btn-confirm-save');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Sauvegarde...');

    // Appeler la fonction de sauvegarde (définie dans config-reconciliation.js)
    if (typeof window.saveConfigurationToDB === 'function') {
      window.saveConfigurationToDB(configName || null)
        .then(function () {
          // Fermer la modal après succès
          setTimeout(function () {
            if (saveModal) {
              saveModal.hide();
            }
          }, 1000);
        })
        .always(function () {
          // Réactiver le bouton
          $btn.prop('disabled', false).html(originalHtml);
        });
    } else {
      console.error('❌ Fonction saveConfigurationToDB non disponible');
      $btn.prop('disabled', false).html(originalHtml);
      alert('Erreur : fonction de sauvegarde non disponible');
    }
  }

  /**
   * ========================================
   * AUTO-SAVE INTELLIGENTE (Phase 3)
   * ========================================
   */

  /**
   * Planifier une auto-save avec debouncing
   * Appelé après chaque modification de la config
   */
  window.scheduleAutoSave = function () {
    // Vérifier que user est connecté
    const userId = parseInt(soeasyVars.userId) || 0;
    if (userId === 0) {
      return;
    }

    // Débouncer pour éviter trop de requêtes
    clearTimeout(autoSaveTimer);

    autoSaveTimer = setTimeout(function () {
      const now = Date.now();

      // Cooldown : ne pas sauvegarder si dernière sauvegarde < 10 secondes
      if (now - lastAutoSave < AUTO_SAVE_COOLDOWN) {
        console.log('⏳ Auto-save skipped (cooldown actif)');
        return;
      }

      performAutoSave();
    }, 2000); // Attendre 2 secondes après la dernière modification
  };

  /**
   * Exécuter l'auto-save
   */
  function performAutoSave() {
    console.log('💾 Auto-save en cours...');

    // TODO Phase 3 : Implémenter l'endpoint soeasy_ajax_auto_save_configuration
    // Pour l'instant, on skip
    console.log('ℹ️ Auto-save non encore implémenté (Phase 3)');

    lastAutoSave = Date.now();
  }

})(jQuery);