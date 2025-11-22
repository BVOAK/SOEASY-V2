/**
 * =====================================================
 * MODULE DE RÉCONCILIATION LOCALSTORAGE ↔ SESSION PHP
 * =====================================================
 * 
 * Ce module gère la synchronisation entre les données du configurateur
 * stockées en localStorage (frontend) et en session PHP (backend).
 * 
 * Il résout notamment les problèmes de :
 * - Désynchronisation après déconnexion/reconnexion
 * - Configurations mélangées entre différents utilisateurs
 * - Affichages incohérents ("Étape undefined", "Adresse #1")
 * 
 * @version 1.0.0
 * @date 2025-01-22
 */

(function($) {
  'use strict';

  /**
   * ========================================
   * UTILITAIRES LOCALSTORAGE
   * ========================================
   */

  /**
   * Récupère toutes les données du configurateur depuis localStorage
   * @returns {Object|null} Objet contenant toutes les clés ou null si erreur
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

  /**
   * Vide complètement le localStorage des données configurateur
   */
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
  }

  /**
   * Restaure une configuration complète dans le localStorage
   * @param {Object} configData - Objet configuration à restaurer
   * @param {number} userId - ID de l'utilisateur (optionnel si déjà dans configData)
   */
  function restoreConfigurationToLocalStorage(configData, userId = null) {
    try {
      // Si userId est fourni en paramètre, l'utiliser ; sinon utiliser celui de configData
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

      // Mettre à jour le timestamp de sync
      localStorage.setItem('soeasyLastSync', new Date().toISOString());

      console.log('✅ Configuration restaurée dans localStorage');

    } catch (e) {
      console.error('❌ Erreur restauration localStorage:', e);
    }
  }

  /**
   * Ajoute ou met à jour le userId dans localStorage
   * (utilisé après vérification que user_id match)
   */
  function ensureUserIdInLocalStorage() {
    if (typeof soeasyVars !== 'undefined' && soeasyVars.userId) {
      localStorage.setItem('soeasyUserId', soeasyVars.userId.toString());
      console.log('🔐 userId ajouté au localStorage:', soeasyVars.userId);
    }
  }

  /**
   * Met à jour le timestamp de dernière synchronisation
   */
  function updateLastSyncTimestamp() {
    localStorage.setItem('soeasyLastSync', new Date().toISOString());
  }

  /**
   * ========================================
   * COMMUNICATION AJAX
   * ========================================
   */

  /**
   * Vide la session PHP via AJAX
   * @returns {Promise}
   */
  function clearSessionConfig() {
    return $.post(soeasyVars.ajaxurl, {
      action: 'soeasy_ajax_clear_session',
      nonce: soeasyVars.nonce_config
    })
    .done(function() {
      console.log('🧹 Session PHP vidée');
    })
    .fail(function(xhr, status, error) {
      console.warn('⚠️ Échec vidage session (non bloquant):', error);
    });
  }

  /**
   * Synchronise le localStorage vers la session PHP
   * @param {Object} localConfig - Configuration à synchroniser
   * @returns {Promise}
   */
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
      } else {
        console.warn('⚠️ Échec sync session:', response.data?.message);
      }
    })
    .fail(function(xhr, status, error) {
      console.warn('⚠️ Erreur AJAX sync session:', error);
    });
  }

  /**
   * Charge la dernière configuration de l'utilisateur depuis la base de données
   * @param {number} userId - ID de l'utilisateur
   * @returns {Promise<Object|null>}
   */
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

  /**
   * Vérifie si la session PHP contient une configuration
   * @returns {Promise<boolean>}
   */
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

  /**
   * Vérifie la session et restaure si incohérence détectée
   * (localStorage vide mais session pleine = problème)
   * @returns {Promise}
   */
  function checkSessionAndRestore() {
    return checkSessionHasConfig().then(function(hasSessionConfig) {
      if (hasSessionConfig) {
        // Incohérence : session pleine mais localStorage vide
        console.warn('⚠️ Incohérence détectée : session pleine, localStorage vide');
        
        // Vider la session pour repartir sur une base saine
        return clearSessionConfig().then(function() {
          
          // Si utilisateur connecté, charger sa dernière config
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
        // Session vide + localStorage vide = OK, nouvel utilisateur
        console.log('✅ Session et localStorage vides (OK)');
        
        // Si utilisateur connecté, tenter de charger dernière config
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
   * FONCTION PRINCIPALE DE RÉCONCILIATION
   * ========================================
   */

  /**
   * Fonction principale qui orchestre toute la logique de réconciliation
   * @returns {Promise}
   */
  window.reconcileConfiguration = function() {
    console.log('🔄 === DÉBUT RÉCONCILIATION ===');

    // Vérifier que soeasyVars est disponible
    if (typeof soeasyVars === 'undefined') {
      console.error('❌ soeasyVars non défini, impossible de continuer');
      return Promise.reject('soeasyVars non défini');
    }

    // Récupérer l'utilisateur actuel (0 si non connecté)
    const currentUserId = parseInt(soeasyVars.userId) || 0;
    console.log('👤 Utilisateur actuel:', currentUserId === 0 ? 'NON CONNECTÉ' : `ID ${currentUserId}`);

    // Lire le localStorage
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

      // Si localStorage vide, rien à faire
      if (Object.keys(localConfig.config).length === 0) {
        console.log('✅ Nouveau visiteur, rien à synchroniser');
        return Promise.resolve();
      }

      // Si localStorage plein, synchroniser vers session
      console.log('📤 Synchronisation localStorage → session PHP');
      return syncLocalStorageToSession(localConfig);
    }

    // ========================================
    // CAS 2 : UTILISATEUR CONNECTÉ
    // ========================================
    console.log('ℹ️ Utilisateur connecté');

    // Vérifier si userId match
    if (localConfig.userId && localConfig.userId !== currentUserId) {
      // ========================================
      // CONFLIT : Configuration d'un autre utilisateur !
      // ========================================
      console.warn('⚠️ CONFLIT DÉTECTÉ !');
      console.warn(`   → localStorage contient userId=${localConfig.userId}`);
      console.warn(`   → Utilisateur actuel = ${currentUserId}`);
      console.warn('   → Nettoyage complet et chargement config utilisateur');

      // 1. Vider localStorage
      clearLocalStorageConfig();

      // 2. Vider session PHP
      return clearSessionConfig().then(function() {
        
        // 3. Charger dernière config de l'utilisateur depuis DB
        console.log('💾 Chargement dernière configuration...');
        return loadLastConfigurationFromDB(currentUserId);
      });
    }

    // ========================================
    // User_id match OU pas encore défini
    // ========================================
    
    // S'assurer que le userId est bien stocké
    ensureUserIdInLocalStorage();

    // Si localStorage contient une config
    if (localConfig.config && Object.keys(localConfig.config).length > 0) {
      console.log('✅ Configuration locale valide, synchronisation...');
      return syncLocalStorageToSession(localConfig);
    }

    // ========================================
    // Pas de config locale : vérifier session puis DB
    // ========================================
    console.log('ℹ️ Pas de localStorage, vérification session...');
    return checkSessionAndRestore();
  };

  /**
   * ========================================
   * FONCTION DE MISE À JOUR DU TIMESTAMP
   * ========================================
   * À appeler après chaque modification dans saveToLocalConfig()
   */
  window.updateConfigSyncTimestamp = function() {
    updateLastSyncTimestamp();
  };

  /**
   * ========================================
   * HELPERS POUR DEBUGGING
   * ========================================
   */
  window.SoEasyReconciliation = {
    getConfig: getLocalStorageConfig,
    clearLocal: clearLocalStorageConfig,
    clearSession: clearSessionConfig,
    syncToSession: syncLocalStorageToSession,
    loadFromDB: loadLastConfigurationFromDB,
    checkSession: checkSessionHasConfig,
    reconcile: window.reconcileConfiguration
  };

  /**
   * ========================================
   * INITIALISATION AUTOMATIQUE
   * ========================================
   */
  $(document).ready(function() {
    
    // Vérifier qu'on est sur une page configurateur
    if (typeof soeasyVars === 'undefined') {
      console.log('ℹ️ soeasyVars non défini, pas de réconciliation');
      return;
    }

    // Vérifier qu'il y a au moins un élément de step configurateur
    if ($('.config-step').length === 0 && !$('#configurateur-container').length) {
      console.log('ℹ️ Pas sur une page configurateur, pas de réconciliation');
      return;
    }

    console.log('🎯 Page configurateur détectée, lancement réconciliation...');

    // Lancer la réconciliation
    window.reconcileConfiguration()
      .then(function() {
        console.log('✅ === RÉCONCILIATION TERMINÉE ===');
      })
      .catch(function(error) {
        console.error('❌ === ERREUR RÉCONCILIATION ===');
        console.error(error);
        // Ne pas bloquer le chargement de la page
      });
  });

})(jQuery);