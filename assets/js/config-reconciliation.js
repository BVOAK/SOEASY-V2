/**
 * =====================================================
 * MODULE DE RÉCONCILIATION LOCALSTORAGE ↔ SESSION PHP
 * =====================================================
 * VERSION 1.1 - Corrige les problèmes de synchronisation
 * 
 * @version 1.1.0
 * @date 2025-01-22
 */

(function($) {
  'use strict';

  // Flag pour éviter les boucles infinies de rechargement
  const RECONCILIATION_FLAG = 'soeasy_reconciliation_done';
  const RECONCILIATION_TIMESTAMP = 'soeasy_last_reconciliation';

  /**
   * ========================================
   * UTILITAIRES LOCALSTORAGE
   * ========================================
   */

  function getLocalStorageConfig() {
    try {
      const config = {
        userId: parseInt(localStorage.getItem('soeasyUserId')) || 0,
        configId: localStorage.getItem('soeasyConfigId') || null,
        lastSync: localStorage.getItem('soeasyLastSync') || null,
        adresses: JSON.parse(localStorage.getItem('soeasyAdresses') || '[]'),
        config: JSON.parse(localStorage.getItem('soeasyConfig') || '{}'),
        dureeEngagement: localStorage.getItem('selectedDureeEngagement') || '0',
        modeFinancement: localStorage.getItem('selectedFinancementMode') || 'comptant'
      };

      console.log('📱 Lecture localStorage :', {
        userId: config.userId,
        hasConfig: Object.keys(config.config).length > 0,
        nbAdresses: config.adresses.length,
        duree: config.dureeEngagement,
        mode: config.modeFinancement
      });

      return config;
    } catch (e) {
      console.error('❌ Erreur lecture localStorage:', e);
      return null;
    }
  }

  function clearLocalStorageConfig() {
    const keys = [
      'soeasyUserId',
      'soeasyConfigId',
      'soeasyLastSync',
      'soeasyConfig',
      'soeasyAdresses',
      'selectedDureeEngagement',
      'selectedFinancementMode'
    ];

    keys.forEach(key => localStorage.removeItem(key));
    console.log('🧹 localStorage vidé');
    
    // Déclencher un événement custom pour notifier les autres modules
    $(document).trigger('soeasy:localStorage:cleared');
  }

  function restoreConfigurationToLocalStorage(configData, userId = null) {
    try {
      const finalUserId = userId || configData.userId || 0;

      localStorage.setItem('soeasyUserId', finalUserId.toString());

      if (configData.configId) {
        localStorage.setItem('soeasyConfigId', configData.configId.toString());
      }

      if (configData.adresses && Array.isArray(configData.adresses)) {
        localStorage.setItem('soeasyAdresses', JSON.stringify(configData.adresses));
      }

      if (configData.config && typeof configData.config === 'object') {
        localStorage.setItem('soeasyConfig', JSON.stringify(configData.config));
      }

      if (configData.dureeEngagement) {
        localStorage.setItem('selectedDureeEngagement', configData.dureeEngagement.toString());
      }

      if (configData.modeFinancement) {
        localStorage.setItem('selectedFinancementMode', configData.modeFinancement);
      }

      localStorage.setItem('soeasyLastSync', new Date().toISOString());
      console.log('✅ Configuration restaurée dans localStorage');

      // Déclencher un événement custom
      $(document).trigger('soeasy:localStorage:restored', [configData]);

    } catch (e) {
      console.error('❌ Erreur restauration localStorage:', e);
    }
  }

  function ensureUserIdInLocalStorage() {
    if (typeof soeasyVars !== 'undefined' && soeasyVars.userId) {
      localStorage.setItem('soeasyUserId', soeasyVars.userId.toString());
      console.log('🔐 userId ajouté au localStorage:', soeasyVars.userId);
    }
  }

  function updateLastSyncTimestamp() {
    localStorage.setItem('soeasyLastSync', new Date().toISOString());
  }

  /**
   * ========================================
   * COMMUNICATION AJAX
   * ========================================
   */

  function clearSessionConfig() {
    return $.post(soeasyVars.ajaxurl, {
      action: 'soeasy_ajax_clear_session',
      nonce: soeasyVars.nonce_config
    })
    .done(function(response) {
      console.log('🧹 Session PHP vidée', response);
      $(document).trigger('soeasy:session:cleared');
    })
    .fail(function(xhr, status, error) {
      console.warn('⚠️ Échec vidage session:', error);
    });
  }

  function syncLocalStorageToSession(localConfig) {
    return $.post(soeasyVars.ajaxurl, {
      action: 'soeasy_ajax_sync_config_to_session',
      config: JSON.stringify(localConfig),
      nonce: soeasyVars.nonce_config
    })
    .done(function(response) {
      if (response.success) {
        console.log('✅ Configuration synchronisée en session PHP');
        updateLastSyncTimestamp();
        $(document).trigger('soeasy:session:synced', [localConfig]);
      } else {
        console.warn('⚠️ Échec sync session:', response.data?.message);
      }
    })
    .fail(function(xhr, status, error) {
      console.warn('⚠️ Erreur AJAX sync session:', error);
    });
  }

  function loadLastConfigurationFromDB(userId) {
    return $.post(soeasyVars.ajaxurl, {
      action: 'soeasy_ajax_load_last_configuration',
      nonce: soeasyVars.nonce_config
    })
    .then(function(response) {
      if (response.success && response.data.config) {
        console.log('💾 Configuration chargée depuis la DB:', response.data.config_name);
        
        // Restaurer dans localStorage
        restoreConfigurationToLocalStorage(response.data.config, userId);
        
        // Synchroniser en session
        return syncLocalStorageToSession(response.data.config).then(function() {
          return response.data.config;
        });
      } else {
        console.log('ℹ️ Aucune configuration trouvée en DB');
        return null;
      }
    })
    .fail(function(xhr, status, error) {
      console.error('❌ Erreur chargement config DB:', error);
      return null;
    });
  }

  function checkSessionHasConfig() {
    return $.post(soeasyVars.ajaxurl, {
      action: 'soeasy_ajax_check_session_config',
      nonce: soeasyVars.nonce_config
    })
    .then(function(response) {
      if (response.success) {
        return response.data.hasConfig === true;
      }
      return false;
    })
    .fail(function() {
      return false;
    });
  }

  function checkSessionAndRestore() {
    return checkSessionHasConfig().then(function(hasSessionConfig) {
      if (hasSessionConfig) {
        console.warn('⚠️ Incohérence détectée : session pleine, localStorage vide');
        
        return clearSessionConfig().then(function() {
          const userId = parseInt(soeasyVars.userId) || 0;
          if (userId > 0) {
            console.log('🔄 Chargement dernière config utilisateur...');
            return loadLastConfigurationFromDB(userId);
          } else {
            console.log('ℹ️ Utilisateur non connecté, démarrage avec config vide');
            return Promise.resolve(null);
          }
        });
      } else {
        console.log('✅ Session et localStorage vides (OK)');
        
        const userId = parseInt(soeasyVars.userId) || 0;
        if (userId > 0) {
          console.log('🔄 Tentative chargement dernière config...');
          return loadLastConfigurationFromDB(userId);
        }
        
        return Promise.resolve(null);
      }
    });
  }

  /**
   * ========================================
   * GESTION DU RECHARGEMENT DE PAGE
   * ========================================
   */

  /**
   * Vérifie si une réconciliation récente a eu lieu
   * @returns {boolean}
   */
  function hasRecentReconciliation() {
    const lastReconciliation = sessionStorage.getItem(RECONCILIATION_TIMESTAMP);
    if (!lastReconciliation) return false;

    const timeDiff = Date.now() - parseInt(lastReconciliation);
    // Considérer comme récent si moins de 3 secondes
    return timeDiff < 3000;
  }

  /**
   * Marque qu'une réconciliation vient d'avoir lieu
   */
  function markReconciliationDone() {
    sessionStorage.setItem(RECONCILIATION_FLAG, 'true');
    sessionStorage.setItem(RECONCILIATION_TIMESTAMP, Date.now().toString());
  }

  /**
   * Recharge la page si nécessaire après réconciliation
   */
  function reloadPageIfNeeded() {
    if (!hasRecentReconciliation()) {
      console.log('🔄 Rechargement de la page pour appliquer les changements...');
      markReconciliationDone();
      location.reload();
      return true;
    }
    return false;
  }

  /**
   * ========================================
   * FONCTION PRINCIPALE DE RÉCONCILIATION
   * ========================================
   */

  window.reconcileConfiguration = function(forceReload = false) {
    console.log('🔄 === DÉBUT RÉCONCILIATION ===');

    // Éviter les boucles infinies
    if (hasRecentReconciliation() && !forceReload) {
      console.log('⏭️ Réconciliation récente détectée, skip');
      return Promise.resolve();
    }

    if (typeof soeasyVars === 'undefined') {
      console.error('❌ soeasyVars non défini, impossible de continuer');
      return Promise.reject('soeasyVars non défini');
    }

    const currentUserId = parseInt(soeasyVars.userId) || 0;
    console.log('👤 Utilisateur actuel:', currentUserId === 0 ? 'NON CONNECTÉ' : `ID ${currentUserId}`);

    const localConfig = getLocalStorageConfig();

    if (!localConfig) {
      console.error('❌ Erreur lecture localStorage');
      return Promise.reject('Erreur lecture localStorage');
    }

    // ========================================
    // CAS 1 : UTILISATEUR NON CONNECTÉ
    // ========================================
    if (currentUserId === 0) {
      console.log('ℹ️ Utilisateur non connecté');

      if (Object.keys(localConfig.config).length === 0) {
        console.log('✅ Nouveau visiteur, rien à synchroniser');
        markReconciliationDone();
        return Promise.resolve();
      }

      console.log('📤 Synchronisation localStorage → session PHP');
      return syncLocalStorageToSession(localConfig).then(function() {
        markReconciliationDone();
      });
    }

    // ========================================
    // CAS 2 : UTILISATEUR CONNECTÉ
    // ========================================
    console.log('ℹ️ Utilisateur connecté');

    if (localConfig.userId && localConfig.userId !== currentUserId) {
      // ========================================
      // CONFLIT : Configuration d'un autre utilisateur
      // ========================================
      console.warn('⚠️ CONFLIT DÉTECTÉ !');
      console.warn(`   → localStorage contient userId=${localConfig.userId}`);
      console.warn(`   → Utilisateur actuel = ${currentUserId}`);
      console.warn('   → Nettoyage complet et chargement config utilisateur');

      clearLocalStorageConfig();

      return clearSessionConfig().then(function() {
        console.log('💾 Chargement dernière configuration...');
        return loadLastConfigurationFromDB(currentUserId);
      }).then(function() {
        // Recharger la page pour réinitialiser le DOM
        if (!hasRecentReconciliation()) {
          reloadPageIfNeeded();
        }
      });
    }

    // ========================================
    // User_id match OU pas encore défini
    // ========================================
    ensureUserIdInLocalStorage();

    if (localConfig.config && Object.keys(localConfig.config).length > 0) {
      console.log('✅ Configuration locale valide, synchronisation...');
      return syncLocalStorageToSession(localConfig).then(function() {
        markReconciliationDone();
      });
    }

    console.log('ℹ️ Pas de localStorage, vérification session...');
    return checkSessionAndRestore().then(function() {
      markReconciliationDone();
    });
  };

  /**
   * ========================================
   * FONCTION DE NETTOYAGE COMPLET AVEC RELOAD
   * ========================================
   */
  window.clearConfigurationAndReload = function() {
    if (!confirm('Voulez-vous vraiment effacer toute la configuration ?\n\nCette action est irréversible.')) {
      return;
    }

    console.log('🗑️ Nettoyage complet demandé');
    
    clearLocalStorageConfig();
    
    clearSessionConfig().then(function() {
      console.log('✅ Configuration effacée, rechargement...');
      sessionStorage.removeItem(RECONCILIATION_FLAG);
      sessionStorage.removeItem(RECONCILIATION_TIMESTAMP);
      location.reload();
    });
  };

  /**
   * ========================================
   * HELPERS POUR DEBUGGING
   * ========================================
   */
  window.SoEasyReconciliation = {
    getConfig: getLocalStorageConfig,
    clearLocal: function() {
      clearLocalStorageConfig();
      console.log('💡 Utilisez clearConfigurationAndReload() pour recharger la page automatiquement');
    },
    clearSession: clearSessionConfig,
    syncToSession: syncLocalStorageToSession,
    loadFromDB: loadLastConfigurationFromDB,
    checkSession: checkSessionHasConfig,
    reconcile: window.reconcileConfiguration,
    clearAndReload: window.clearConfigurationAndReload,
    forceReconcile: function() {
      sessionStorage.removeItem(RECONCILIATION_FLAG);
      sessionStorage.removeItem(RECONCILIATION_TIMESTAMP);
      return window.reconcileConfiguration(true);
    }
  };

  window.updateConfigSyncTimestamp = function() {
    updateLastSyncTimestamp();
  };

  /**
   * ========================================
   * INITIALISATION AUTOMATIQUE
   * ========================================
   */
  $(document).ready(function() {
    
    if (typeof soeasyVars === 'undefined') {
      console.log('ℹ️ soeasyVars non défini, pas de réconciliation');
      return;
    }

    if ($('.config-step').length === 0 && !$('#configurateur-container').length) {
      console.log('ℹ️ Pas sur une page configurateur, pas de réconciliation');
      return;
    }

    console.log('🎯 Page configurateur détectée, lancement réconciliation...');

    // IMPORTANT : Attendre un peu que page-configurateur.php injecte les adresses PHP
    setTimeout(function() {
      window.reconcileConfiguration()
        .then(function() {
          console.log('✅ === RÉCONCILIATION TERMINÉE ===');
        })
        .catch(function(error) {
          console.error('❌ === ERREUR RÉCONCILIATION ===');
          console.error(error);
        });
    }, 100); // Délai de 100ms pour laisser le script PHP s'exécuter
  });

})(jQuery);