/**
 * ============================================================================
 * MODULE DE RÉCONCILIATION - localStorage ↔ Session PHP
 * ============================================================================
 * 
 * Version corrigée : SANS rechargements automatiques (problème timing local)
 * 
 * @version 2.1
 * @date 2025-11-23
 */

(function ($) {
  'use strict';

  /**
   * ========================================
   * FONCTIONS UTILITAIRES
   * ========================================
   */

  function getLocalStorageConfig() {
    try {
      const config = {
        userId: parseInt(localStorage.getItem('soeasyUserId') || '0'),
        configId: localStorage.getItem('soeasyConfigId') || null,
        lastSync: localStorage.getItem('soeasyLastSync') || null,
        adresses: JSON.parse(localStorage.getItem('soeasyAdresses') || '[]'),
        config: JSON.parse(localStorage.getItem('soeasyConfig') || '{}'),
        dureeEngagement: localStorage.getItem('selectedDureeEngagement') || '0',
        modeFinancement: localStorage.getItem('selectedFinancementMode') || 'comptant'
      };
      return config;
    } catch (error) {
      console.error('❌ Erreur lecture localStorage:', error);
      return null;
    }
  }

  function clearLocalStorageConfig() {
    try {
      localStorage.removeItem('soeasyUserId');
      localStorage.removeItem('soeasyConfigId');
      localStorage.removeItem('soeasyLastSync');
      localStorage.removeItem('soeasyAdresses');
      localStorage.removeItem('soeasyConfig');
      localStorage.removeItem('selectedDureeEngagement');
      localStorage.removeItem('selectedFinancementMode');
      localStorage.removeItem('soeasyCurrentStep');
      console.log('🧹 localStorage vidé');
    } catch (error) {
      console.error('❌ Erreur vidage localStorage:', error);
    }
  }

  function clearSessionConfig() {
    return $.ajax({
      url: soeasyVars.ajaxurl,
      type: 'POST',
      data: {
        action: 'soeasy_ajax_clear_usermeta',  // ✅ NOUVEAU endpoint
        nonce: soeasyVars.nonce_config
      }
    }).done(function () {
      console.log('🧹 Données backend vidées');
    }).fail(function () {
      console.warn('⚠️ Échec vidage (non bloquant)');
    });
  }

  function syncLocalStorageToSession(localConfig) {
    if (!localConfig || Object.keys(localConfig.config).length === 0) {
      console.log('ℹ️ Aucune config à synchroniser');
      return Promise.resolve();
    }

    console.log('📤 Envoi données vers backend:', {
      config_count: Object.keys(localConfig.config).length,
      adresses_count: localConfig.adresses.length
    });

    return $.ajax({
      url: soeasyVars.ajaxurl,
      type: 'POST',
      data: {
        action: 'soeasy_ajax_sync_config_to_usermeta',  // ✅ NOUVEAU endpoint
        config: JSON.stringify(localConfig.config),
        adresses: JSON.stringify(localConfig.adresses),
        duree_engagement: localConfig.dureeEngagement,
        mode_financement: localConfig.modeFinancement,
        nonce: soeasyVars.nonce_config
      }
    }).done(function (response) {
      if (response.success) {
        console.log('✅ Config synchronisée:', response.data);
      } else {
        console.error('❌ Erreur sync:', response.data);
      }
    }).fail(function (xhr, status, error) {
      console.error('❌ Échec sync:', { status, error });
    });
  }

  function restoreConfigurationToLocalStorage(configData) {
    try {
      if (!configData) {
        console.warn('⚠️ Aucune donnée à restaurer');
        return false;
      }

      const data = typeof configData.config_data === 'string'
        ? JSON.parse(configData.config_data)
        : configData.config_data;

      localStorage.setItem('soeasyUserId', data.userId || '0');
      localStorage.setItem('soeasyConfigId', configData.id || '');
      localStorage.setItem('soeasyAdresses', JSON.stringify(data.adresses || []));
      localStorage.setItem('soeasyConfig', JSON.stringify(data.config || {}));
      localStorage.setItem('selectedDureeEngagement', data.dureeEngagement || '0');
      localStorage.setItem('selectedFinancementMode', data.modeFinancement || 'comptant');
      localStorage.setItem('soeasyLastSync', new Date().toISOString());

      console.log('✅ Configuration restaurée dans localStorage');
      return true;
    } catch (error) {
      console.error('❌ Erreur restauration config:', error);
      return false;
    }
  }

  function loadLastConfigurationFromDB(userId) {
    return $.ajax({
      url: soeasyVars.ajaxurl,
      type: 'POST',
      data: {
        action: 'soeasy_ajax_load_last_configuration',
        user_id: userId,
        nonce: soeasyVars.nonce_config
      }
    }).done(function (response) {
      if (response.success && response.data && response.data.configuration) {
        console.log('📥 Configuration chargée depuis BDD');

        restoreConfigurationToLocalStorage(response.data.configuration);

        if (typeof showToastInfo === 'function') {
          showToastInfo('Votre dernière configuration a été restaurée.');
        }

        // ✅ CORRECTION : NE PAS recharger automatiquement
        console.log('✅ Config restaurée, rechargez manuellement si besoin');
      } else {
        console.log('ℹ️ Aucune configuration sauvegardée trouvée');
      }
    }).fail(function () {
      console.warn('⚠️ Erreur chargement config BDD');
    });
  }

  function checkSessionAndRestore() {
    const currentUserId = parseInt(soeasyVars.userId) || 0;

    if (currentUserId === 0) {
      return Promise.resolve();
    }

    return $.ajax({
      url: soeasyVars.ajaxurl,
      type: 'POST',
      data: {
        action: 'soeasy_ajax_check_session_config',
        nonce: soeasyVars.nonce_config
      }
    }).done(function (response) {
      const localConfig = getLocalStorageConfig();

      if (response.data && response.data.has_session &&
        (!localConfig || Object.keys(localConfig.config).length === 0)) {
        console.warn('⚠️ Conflit détecté : session pleine, localStorage vide');

        clearSessionConfig().then(() => {
          if (typeof showToastWarning === 'function') {
            showToastWarning('Configuration réinitialisée pour éviter les conflits.');
          }
        });
      } else if (!localConfig || Object.keys(localConfig.config).length === 0) {
        loadLastConfigurationFromDB(currentUserId);
      }
    });
  }

  function ensureUserIdInLocalStorage() {
    const currentUserId = parseInt(soeasyVars.userId) || 0;
    const storedUserId = parseInt(localStorage.getItem('soeasyUserId') || '0');

    if (currentUserId !== storedUserId) {
      localStorage.setItem('soeasyUserId', currentUserId);
      console.log('✅ userId mis à jour : ' + currentUserId);
    }
  }

  /**
   * ========================================
   * FONCTION PRINCIPALE DE RÉCONCILIATION
   * ========================================
   */

  window.reconcileConfiguration = function () {
    console.log('🔄 Démarrage réconciliation...');

    const currentUserId = parseInt(soeasyVars.userId) || 0;
    const localConfig = getLocalStorageConfig();

    if (!localConfig) {
      console.error('❌ Impossible de lire localStorage');
      return Promise.reject('localStorage inaccessible');
    }

    // ========================================
    // CAS 1 : GUEST (utilisateur non connecté)
    // ========================================
    if (currentUserId === 0) {
      console.log('👤 Mode GUEST détecté');

      if (!localConfig.config || Object.keys(localConfig.config).length === 0) {
        console.log('ℹ️ Nouveau visiteur, pas de config');
        return Promise.resolve();
      }

      console.log('✅ Config guest existante, synchronisation session');
      return syncLocalStorageToSession(localConfig);
    }

    // ========================================
    // CAS 2 : GUEST → LOGIN (connexion détectée)
    // ========================================
    if (currentUserId > 0 && localConfig.userId === 0) {
      console.log('🔄 Connexion détectée : guest → user ' + currentUserId);

      localStorage.setItem('soeasyUserId', currentUserId);

      if (localConfig.config && Object.keys(localConfig.config).length > 0) {
        return syncLocalStorageToSession(localConfig).then(() => {
          console.log('✅ Config guest convertie en config user ' + currentUserId);

          if (typeof showToastInfo === 'function') {
            showToastInfo('Vous êtes connecté. Votre configuration est préservée.');
          }

          // ✅ CORRECTION : NE PAS recharger l'étape automatiquement
          // L'utilisateur continue son parcours normalement
          console.log('✅ Continuez votre configuration normalement');
        });
      }

      return Promise.resolve();
    }

    // ========================================
    // CAS 3 : USER CONNECTÉ (utilisateur déjà connecté)
    // ========================================
    if (currentUserId > 0 && localConfig.userId === currentUserId) {
      console.log('✅ Utilisateur connecté : ' + currentUserId);

      ensureUserIdInLocalStorage();

      if (localConfig.config && Object.keys(localConfig.config).length > 0) {
        return syncLocalStorageToSession(localConfig);
      } else {
        // ✅ CORRECTION : Ne pas charger depuis BDD si config vide temporairement
        console.log('ℹ️ Pas de config locale, on attend le chargement');
        return Promise.resolve();
      }
    }

    // ========================================
    // CAS 4 : USER → LOGOUT (déconnexion détectée)
    // ========================================
    if (currentUserId === 0 && localConfig.userId > 0) {
      console.log('🔄 Déconnexion détectée : user ' + localConfig.userId + ' → guest');

      clearLocalStorageConfig();

      return clearSessionConfig().then(() => {
        console.log('✅ Déconnexion complète');

        if (typeof showToastInfo === 'function') {
          showToastInfo('Vous avez été déconnecté.');
        }

        // ✅ CORRECTION : NE PAS recharger automatiquement
        console.log('✅ Déconnexion terminée');
      });
    }

    // ========================================
    // CAS 5 : USER A → USER B (changement utilisateur)
    // ========================================
    if (localConfig.userId > 0 && currentUserId > 0 && localConfig.userId !== currentUserId) {
      console.log('⚠️ Changement utilisateur détecté : ' + localConfig.userId + ' → ' + currentUserId);

      clearLocalStorageConfig();

      return clearSessionConfig().then(() => {
        return loadLastConfigurationFromDB(currentUserId);
      });
    }

    console.warn('⚠️ Scénario non géré');
    return Promise.resolve();
  };

  /**
   * ========================================
   * FONCTION DE SAUVEGARDE MANUELLE
   * ========================================
   */

  window.saveConfigurationToDB = function (configName) {
    const userId = parseInt(soeasyVars.userId) || 0;

    if (userId === 0) {
      console.warn('⚠️ Sauvegarde impossible : utilisateur non connecté');
      if (typeof showToastWarning === 'function') {
        showToastWarning('Vous devez être connecté pour sauvegarder.');
      } else {
        alert('Vous devez être connecté pour sauvegarder.');
      }
      return Promise.reject('Not logged in');
    }

    const localConfig = getLocalStorageConfig();

    if (!localConfig || Object.keys(localConfig.config).length === 0) {
      console.warn('⚠️ Aucune configuration à sauvegarder');
      if (typeof showToastWarning === 'function') {
        showToastWarning('Aucune configuration à sauvegarder.');
      } else {
        alert('Aucune configuration à sauvegarder.');
      }
      return Promise.reject('No config');
    }

    const name = configName || ('Configuration ' + new Date().toLocaleDateString('fr-FR'));

    const configData = {
      userId: userId,
      adresses: localConfig.adresses,
      config: localConfig.config,
      dureeEngagement: localConfig.dureeEngagement,
      modeFinancement: localConfig.modeFinancement
    };

    console.log('💾 Sauvegarde config : ' + name);

    return $.ajax({
      url: soeasyVars.ajaxurl,
      type: 'POST',
      data: {
        action: 'soeasy_ajax_save_configuration',
        config_id: localConfig.configId || null,
        config_name: name,
        config_data: JSON.stringify(configData),
        status: 'active',
        nonce: soeasyVars.nonce_config
      }
    }).done(function (response) {
      if (response.success) {
        localStorage.setItem('soeasyConfigId', response.data.config_id);

        console.log('✅ Config sauvegardée (ID: ' + response.data.config_id + ')');

        if (typeof showToastSuccess === 'function') {
          showToastSuccess('Configuration sauvegardée : ' + name);
        } else {
          alert('Configuration sauvegardée avec succès !');
        }
      } else {
        console.error('❌ Erreur sauvegarde:', response.data?.message);

        if (typeof showToastError === 'function') {
          showToastError('Erreur : ' + (response.data?.message || 'Erreur inconnue'));
        } else {
          alert('Erreur : ' + (response.data?.message || 'Erreur inconnue'));
        }
      }
    }).fail(function () {
      console.error('❌ Erreur réseau sauvegarde');

      if (typeof showToastError === 'function') {
        showToastError('Erreur de communication avec le serveur.');
      } else {
        alert('Erreur de communication avec le serveur.');
      }
    });
  };


  /**
 * ========================================
 * SAUVEGARDE MANUELLE
 * ========================================
 */

  /**
   * Sauvegarder la configuration manuellement en BDD
   * 
   * @param {string} configName - Nom de la configuration (optionnel)
   * @returns {Promise}
   */
  window.saveConfigurationToDB = function (configName = null) {
    console.log('💾 Sauvegarde manuelle demandée');

    const userId = parseInt(soeasyVars.userId) || 0;

    if (userId === 0) {
      if (typeof showToastError === 'function') {
        showToastError('Vous devez être connecté pour sauvegarder.');
      } else {
        alert('Vous devez être connecté pour sauvegarder.');
      }
      return Promise.reject('Not logged in');
    }

    // Récupérer configId si déjà sauvegardée
    const configId = localStorage.getItem('soeasyConfigId') || null;

    // Générer nom par défaut si non fourni
    if (!configName) {
      const date = new Date();
      const dateStr = date.toLocaleDateString('fr-FR');
      const timeStr = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
      configName = 'Configuration du ' + dateStr + ' à ' + timeStr;
    }

    console.log('💾 Sauvegarde:', {
      configId: configId,
      configName: configName,
      isUpdate: !!configId
    });

    return $.ajax({
      url: soeasyVars.ajaxurl,
      type: 'POST',
      data: {
        action: 'soeasy_ajax_save_configuration',
        config_id: configId,
        config_name: configName,
        nonce: soeasyVars.nonce_config
      }
    }).done(function (response) {
      if (response.success) {
        // Stocker le config_id dans localStorage
        localStorage.setItem('soeasyConfigId', response.data.config_id);

        console.log('✅ Configuration sauvegardée:', response.data);

        if (typeof showToastSuccess === 'function') {
          showToastSuccess(response.data.message);
        } else {
          alert(response.data.message);
        }
      } else {
        console.error('❌ Erreur sauvegarde:', response.data);

        if (typeof showToastError === 'function') {
          showToastError(response.data.message || 'Erreur de sauvegarde');
        } else {
          alert('Erreur: ' + (response.data.message || 'Erreur inconnue'));
        }
      }
    }).fail(function (xhr, status, error) {
      console.error('❌ Échec réseau:', { status, error });

      if (typeof showToastError === 'function') {
        showToastError('Erreur de communication avec le serveur.');
      } else {
        alert('Erreur de communication avec le serveur.');
      }
    });
  };

  /**
   * ========================================
   * INITIALISATION
   * ========================================
   */

  $(document).ready(function () {
    setTimeout(() => {
      if ($('.config-step').length > 0 ||
        $('#configurateur-container').length > 0 ||
        window.location.pathname.includes('/configurateur')) {
        reconcileConfiguration()
          .then(() => {
            console.log('✅ Réconciliation terminée avec succès');
          })
          .catch((error) => {
            console.error('❌ Erreur lors de la réconciliation:', error);
          });
      } else {
        console.log('ℹ️ Pas sur la page configurateur, réconciliation ignorée');
      }
    }, 100);
  });

})(jQuery);