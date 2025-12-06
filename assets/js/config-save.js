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
    initAutoSaveIndicator();
  });


  /**
 * Initialiser l'indicateur d'auto-save au chargement
 */
  function initAutoSaveIndicator() {
    const userId = parseInt(soeasyVars.userId) || 0;

    if (userId === 0) {
      // Guest : masquer l'indicateur
      $('#auto-save-indicator').hide();
      return;
    }

    // User connecté : afficher le dernier timestamp si disponible
    const lastSave = localStorage.getItem('soeasyLastAutoSave');

    if (lastSave) {
      updateAutoSaveIndicator('saved');
    } else {
      $('#auto-save-indicator').hide();
    }
  }

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
    const userId = parseInt(soeasyVars.userId) || 0;

    if (userId === 0) {
      console.log('⚠️ Auto-save impossible : utilisateur non connecté');
      return;
    }

    console.log('💾 Auto-save en cours...');

    // Afficher l'indicateur "Sauvegarde en cours..."
    updateAutoSaveIndicator('saving');

    $.ajax({
      url: soeasyVars.ajaxurl,
      type: 'POST',
      data: {
        action: 'soeasy_ajax_auto_save_configuration',
        nonce: soeasyVars.nonce_config
      }
    }).done(function (response) {
      if (response.success) {
        lastAutoSave = Date.now();

        // Stocker le timestamp de dernière sauvegarde
        localStorage.setItem('soeasyLastAutoSave', lastAutoSave);

        // Stocker l'ID du draft si nouveau
        if (response.data.is_new && response.data.config_id) {
          localStorage.setItem('soeasyDraftId', response.data.config_id);
        }

        console.log('✅ Auto-save réussie (ID: ' + response.data.config_id + ')');

        // Afficher l'indicateur "Sauvegardé"
        updateAutoSaveIndicator('saved');

      } else {
        console.error('❌ Erreur auto-save:', response.data);
        updateAutoSaveIndicator('error');
      }
    }).fail(function (xhr, status, error) {
      console.error('💥 Échec auto-save:', { status, error });
      updateAutoSaveIndicator('error');
    });
  }

  /**
   * Mettre à jour l'indicateur visuel d'auto-save
   * 
   * @param {string} state - 'saving', 'saved', 'error'
   */
  function updateAutoSaveIndicator(state) {
    const $indicator = $('#auto-save-indicator');

    if ($indicator.length === 0) {
      return;
    }

    switch (state) {
      case 'saving':
        $indicator
          .removeClass('text-success text-danger')
          .addClass('text-muted')
          .html('<i class="fas fa-spinner fa-spin me-1"></i> Sauvegarde...')
          .show();
        break;

      case 'saved':
        const lastSave = localStorage.getItem('soeasyLastAutoSave');
        const timeAgo = lastSave ? getTimeAgo(parseInt(lastSave)) : 'à l\'instant';

        $indicator
          .removeClass('text-muted text-danger')
          .addClass('text-success')
          .html('<i class="fas fa-check-circle me-1"></i> Sauvegardé ' + timeAgo)
          .show();

        // Mettre à jour le texte toutes les 30 secondes
        updateAutoSaveTimestamp();
        break;

      case 'error':
        $indicator
          .removeClass('text-success text-muted')
          .addClass('text-danger')
          .html('<i class="fas fa-exclamation-triangle me-1"></i> Erreur de sauvegarde')
          .show();
        break;
    }
  }

  /**
   * Calculer "il y a X min/sec"
   */
  function getTimeAgo(timestamp) {
    const now = Date.now();
    const diff = Math.floor((now - timestamp) / 1000); // secondes

    if (diff < 10) {
      return 'à l\'instant';
    } else if (diff < 60) {
      return 'il y a ' + diff + ' sec';
    } else if (diff < 3600) {
      const minutes = Math.floor(diff / 60);
      return 'il y a ' + minutes + ' min';
    } else {
      const hours = Math.floor(diff / 3600);
      return 'il y a ' + hours + ' h';
    }
  }

  /**
   * Mettre à jour le timestamp toutes les 30 secondes
   */
  function updateAutoSaveTimestamp() {
    clearInterval(window.autoSaveTimestampInterval);

    window.autoSaveTimestampInterval = setInterval(function () {
      const lastSave = localStorage.getItem('soeasyLastAutoSave');

      if (lastSave) {
        const timeAgo = getTimeAgo(parseInt(lastSave));
        const $indicator = $('#auto-save-indicator');

        if ($indicator.hasClass('text-success')) {
          $indicator.html('<i class="fas fa-check-circle me-1"></i> Sauvegardé ' + timeAgo);
        }
      }
    }, 30000); // Toutes les 30 secondes
  }

})(jQuery);