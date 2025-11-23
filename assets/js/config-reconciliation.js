/**
 * ============================================================================
 * MODULE DE RÉCONCILIATION - localStorage ↔ Session PHP
 * ============================================================================
 * 
 * Gère intelligemment la synchronisation entre localStorage (frontend) et 
 * session PHP (backend) en fonction de l'état de connexion de l'utilisateur.
 * 
 * Scénarios gérés :
 * 1. Guest (utilisateur non connecté)
 * 2. Guest → Login (connexion avec config en cours)
 * 3. User connecté (utilisateur déjà connecté)
 * 4. User → Logout (déconnexion)
 * 5. User A → User B (changement d'utilisateur)
 * 
 * @version 2.0
 * @date 2025-11-23
 */

(function ($) {
    'use strict';

    /**
     * ========================================
     * FONCTIONS UTILITAIRES
     * ========================================
     */

    /**
     * Récupère toute la configuration depuis localStorage
     * 
     * @returns {Object|null} Config complète ou null si erreur
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

    /**
     * Vide complètement la configuration localStorage
     */
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

    /**
     * Vide la session PHP via AJAX
     * 
     * @returns {Promise}
     */
    function clearSessionConfig() {
        return $.ajax({
            url: soeasyVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'soeasy_ajax_clear_session',
                nonce: soeasyVars.nonce_config
            }
        }).done(function () {
            console.log('🧹 Session PHP vidée');
        }).fail(function () {
            console.warn('⚠️ Échec vidage session (non bloquant)');
        });
    }

    /**
     * Synchronise localStorage vers session PHP
     * 
     * @param {Object} localConfig - Configuration depuis localStorage
     * @returns {Promise}
     */
    function syncLocalStorageToSession(localConfig) {
        if (!localConfig || Object.keys(localConfig.config).length === 0) {
            console.log('ℹ️ Aucune config à synchroniser');
            return Promise.resolve();
        }

        // ✅ IMPORTANT : Envoyer les adresses complètes, pas juste le tableau
        return $.ajax({
            url: soeasyVars.ajaxurl,
            type: 'POST',
            data: {
            action: 'soeasy_ajax_sync_config_to_session',
            config: localConfig.config,
            adresses: JSON.stringify(localConfig.adresses), // ← Stringifier ici
            duree_engagement: localConfig.dureeEngagement,
            mode_financement: localConfig.modeFinancement,
            nonce: soeasyVars.nonce_config
            }
        }).done(function(response) {
            if (response.success) {
            console.log('✅ Config synchronisée en session');
            }
        }).fail(function() {
            console.warn('⚠️ Échec sync session (non bloquant)');
        });
    }

    /**
     * Restaure une configuration complète dans localStorage
     * 
     * @param {Object} configData - Données de config à restaurer
     * @returns {boolean} True si succès
     */
    function restoreConfigurationToLocalStorage(configData) {
        try {
            if (!configData) {
                console.warn('⚠️ Aucune donnée à restaurer');
                return false;
            }

            // Parser le JSON si c'est une string
            const data = typeof configData.config_data === 'string'
                ? JSON.parse(configData.config_data)
                : configData.config_data;

            // Stocker toutes les données
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

    /**
     * Charge la dernière configuration d'un utilisateur depuis la BDD
     * 
     * @param {number} userId - ID utilisateur
     * @returns {Promise}
     */
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

                // Recharger la page pour appliquer la config
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                console.log('ℹ️ Aucune configuration sauvegardée trouvée');
            }
        }).fail(function () {
            console.warn('⚠️ Erreur chargement config BDD');
        });
    }

    /**
     * Vérifie la session PHP et restaure si nécessaire
     * 
     * @returns {Promise}
     */
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
                // Session pleine mais localStorage vide → Conflit
                console.warn('⚠️ Conflit détecté : session pleine, localStorage vide');

                clearSessionConfig().then(() => {
                    if (typeof showToastWarning === 'function') {
                        showToastWarning('Configuration réinitialisée pour éviter les conflits.');
                    }

                    if (typeof loadStep === 'function') {
                        loadStep(1);
                    }
                });
            } else if (!localConfig || Object.keys(localConfig.config).length === 0) {
                // Pas de config locale, charger depuis BDD
                loadLastConfigurationFromDB(currentUserId);
            }
        });
    }

    /**
     * S'assure que le userId est bien dans localStorage
     */
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

    /**
     * Réconcilie localStorage et session selon l'état de connexion
     * 
     * Gère 5 scénarios :
     * - CAS 1 : Guest (currentUserId = 0)
     * - CAS 2 : Guest → Login (localConfig.userId = 0, currentUserId > 0)
     * - CAS 3 : User connecté (localConfig.userId = currentUserId)
     * - CAS 4 : User → Logout (localConfig.userId > 0, currentUserId = 0)
     * - CAS 5 : User A → User B (localConfig.userId ≠ currentUserId, tous deux > 0)
     * 
     * @returns {Promise}
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

            // Nouveau visiteur ou localStorage vide
            if (!localConfig.config || Object.keys(localConfig.config).length === 0) {
                console.log('ℹ️ Nouveau visiteur, pas de config');
                return Promise.resolve();
            }

            // Guest avec config existante → Synchroniser
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
                
                // ✅ NOUVEAU : Recharger l'étape actuelle pour afficher la config
                const currentStep = parseInt(localStorage.getItem('soeasyCurrentStep') || '1');
                if (typeof loadStep === 'function') {
                    setTimeout(() => loadStep(currentStep), 500);
                }
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

            // Config valide → Synchroniser
            if (localConfig.config && Object.keys(localConfig.config).length > 0) {
                return syncLocalStorageToSession(localConfig);
            } else {
                // Pas de config locale → Vérifier session et BDD
                return checkSessionAndRestore();
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
                
                // ✅ PAS de redirection, juste notification
                if (typeof showToastInfo === 'function') {
                showToastInfo('Vous avez été déconnecté.');
                }
                
                // ✅ Recharger étape 1 proprement
                if (typeof loadStep === 'function') {
                setTimeout(() => loadStep(1), 500);
                }
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

        // Cas non prévu (ne devrait pas arriver)
        console.warn('⚠️ Scénario non géré');
        return Promise.resolve();
    };

    /**
     * ========================================
     * FONCTION DE SAUVEGARDE MANUELLE
     * ========================================
     */

    /**
     * Sauvegarde la configuration actuelle en BDD
     * 
     * @param {string|null} configName - Nom de la config (optionnel)
     * @returns {Promise}
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

        // Nom par défaut si non fourni
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
     * INITIALISATION
     * ========================================
     */

    $(document).ready(function () {
        // Uniquement sur page configurateur
        setTimeout(() => {
            if ($('.config-step').length > 0 || $('#configurateur-container').length > 0) {
                reconcileConfiguration()
                    .then(() => {
                        console.log('✅ Réconciliation terminée avec succès');
                    })
                    .catch((error) => {
                        console.error('❌ Erreur lors de la réconciliation:', error);
                    });
            }
        }, 1500);
    });

})(jQuery);