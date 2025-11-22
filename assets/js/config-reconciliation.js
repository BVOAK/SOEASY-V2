/**
 * =====================================================
 * MODULE DE RÉCONCILIATION LOCALSTORAGE ↔ SESSION PHP
 * =====================================================
 * VERSION 1.3 - Correction timing rechargement après conflit
 * 
 * @version 1.3.0
 * @date 2025-01-22
 * 
 * CORRECTIF : Attendre explicitement la fin des sync AJAX avant reload
 */

(function ($) {
    'use strict';

    const RECONCILIATION_FLAG = 'soeasy_reconciliation_done';
    const RECONCILIATION_TIMESTAMP = 'soeasy_last_reconciliation';
    const RECONCILIATION_TIMEOUT = 5 * 60 * 1000; // 5 minutes

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

        if (typeof window.resetSidebarCompletely === 'function') {
            window.resetSidebarCompletely();
        }

        $(document).trigger('soeasy:localStorage:cleared');
    }

    function restoreConfigurationToLocalStorage(configData, userId = null) {
        try {
            const finalUserId = userId || configData.userId || 0;

            // Restaurer toutes les clés localStorage
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

            $(document).trigger('soeasy:localStorage:restored', [configData]);

            // ✅ CRITIQUE : RETOURNER LA PROMISE de synchronisation des adresses
            return $.post(soeasyVars.ajaxurl, {
                action: 'soeasy_ajax_sync_adresses_to_session',
                adresses: JSON.stringify(configData.adresses || []),
                nonce: soeasyVars.nonce_config
            }).done(function (response) {
                console.log('✅ Adresses restaurées en session PHP:', response.data?.count || 0);
            }).fail(function (xhr, status, error) {
                console.error('❌ Échec sync adresses:', error);
            });

        } catch (e) {
            console.error('❌ Erreur restauration localStorage:', e);
            return $.Deferred().reject(e).promise();
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
            .done(function (response) {
                console.log('🧹 Session PHP vidée', response);
                $(document).trigger('soeasy:session:cleared');
            })
            .fail(function (xhr, status, error) {
                console.warn('⚠️ Échec vidage session:', error);
            });
    }

    function syncLocalStorageToSession(localConfig) {
        console.log('🔄 Synchronisation vers session PHP...');

        // ✅ ÉTAPE 1 : Synchroniser les adresses EN PREMIER
        const adressesPromise = $.post(soeasyVars.ajaxurl, {
            action: 'soeasy_ajax_sync_adresses_to_session',
            adresses: JSON.stringify(localConfig.adresses || []),
            nonce: soeasyVars.nonce_config
        })
            .done(function (response) {
                if (response.success) {
                    console.log('✅ Adresses synchronisées:', response.data?.count || 0, 'adresses');
                }
            })
            .fail(function (xhr, status, error) {
                console.warn('⚠️ Échec sync adresses:', error);
            });

        // ✅ ÉTAPE 2 : Synchroniser la config complète
        const configPromise = $.post(soeasyVars.ajaxurl, {
            action: 'soeasy_ajax_sync_config_to_session',
            config: JSON.stringify(localConfig),
            nonce: soeasyVars.nonce_config
        })
            .done(function (response) {
                if (response.success) {
                    console.log('✅ Configuration synchronisée en session PHP');
                    updateLastSyncTimestamp();
                    $(document).trigger('soeasy:session:synced', [localConfig]);
                } else {
                    console.warn('⚠️ Échec sync config:', response.data?.message);
                }
            })
            .fail(function (xhr, status, error) {
                console.warn('⚠️ Erreur AJAX sync config:', error);
            });

        // ✅ Retourner $.when qui attend LES DEUX promises
        return $.when(adressesPromise, configPromise).then(function () {
            console.log('✅ Synchronisation complète terminée');
        });
    }

    // Dans loadLastConfigurationFromDB()
    function loadLastConfigurationFromDB(userId) {
        return $.post(soeasyVars.ajaxurl, {
            action: 'soeasy_ajax_load_last_configuration',
            nonce: soeasyVars.nonce_config
        })
            .then(function (response) {
                if (response.success && response.data.config) {
                    console.log('💾 Configuration chargée depuis la DB:', response.data.config_name);

                    return restoreConfigurationToLocalStorage(response.data.config, userId)
                        .then(function () {
                            console.log('✅ Restauration localStorage terminée');
                            return syncLocalStorageToSession(response.data.config);
                        })
                        .then(function () {
                            console.log('✅ Synchronisation session terminée');

                            // ✅ NOUVEAU : Reset sidebar avant rechargement
                            if (typeof window.resetSidebarCompletely === 'function') {
                                window.resetSidebarCompletely();
                            }

                            return new Promise(function (resolve) {
                                setTimeout(function () {
                                    console.log('🔄 Rechargement page...');
                                    resolve();
                                }, 500);
                            });
                        })
                        .then(function () {
                            if (!hasRecentReconciliation()) {
                                markReconciliationDone();
                                location.reload();
                            }
                            return response.data.config;
                        });
                } else {
                    console.log('ℹ️ Aucune configuration trouvée en DB');

                    // ✅ NOUVEAU : Reset sidebar même si pas de config
                    if (typeof window.resetSidebarCompletely === 'function') {
                        window.resetSidebarCompletely();
                    }

                    return null;
                }
            })
            .fail(function (xhr, status, error) {
                console.error('❌ Erreur chargement config DB:', error);
                return null;
            });
    }

    function checkSessionHasConfig() {
        return $.post(soeasyVars.ajaxurl, {
            action: 'soeasy_ajax_check_session_config',
            nonce: soeasyVars.nonce_config
        })
            .then(function (response) {
                if (response.success) {
                    return response.data.hasConfig === true;
                }
                return false;
            })
            .fail(function () {
                return false;
            });
    }

    function checkSessionAndRestore() {
        return checkSessionHasConfig().then(function (hasSessionConfig) {
            if (hasSessionConfig) {
                console.warn('⚠️ Incohérence détectée : session pleine, localStorage vide');

                return clearSessionConfig().then(function () {
                    const userId = parseInt(soeasyVars.userId) || 0;
                    if (userId > 0) {
                        console.log('🔄 Chargement dernière config utilisateur...');
                        return loadLastConfigurationFromDB(userId);
                    } else {
                        console.log('ℹ️ Utilisateur non connecté, session vidée');
                        return null;
                    }
                });
            } else {
                console.log('✅ Session vide OK');
                return null;
            }
        });
    }

    /**
     * ========================================
     * GESTION RECONCILIATION
     * ========================================
     */

    function hasRecentReconciliation() {
        const lastReconciliation = sessionStorage.getItem(RECONCILIATION_TIMESTAMP);
        if (!lastReconciliation) {
            return false;
        }

        const elapsed = Date.now() - parseInt(lastReconciliation);
        const isRecent = elapsed < RECONCILIATION_TIMEOUT;

        if (!isRecent) {
            console.log('⏰ Réconciliation expirée');
            sessionStorage.removeItem(RECONCILIATION_FLAG);
            sessionStorage.removeItem(RECONCILIATION_TIMESTAMP);
        }

        return isRecent;
    }

    function markReconciliationDone() {
        sessionStorage.setItem(RECONCILIATION_FLAG, 'true');
        sessionStorage.setItem(RECONCILIATION_TIMESTAMP, Date.now().toString());
        console.log('✅ Réconciliation marquée comme effectuée');
    }

    function reloadPageIfNeeded() {
        if (!hasRecentReconciliation()) {
            console.log('🔄 Rechargement requis...');
            markReconciliationDone();
            location.reload();
        } else {
            console.log('ℹ️ Rechargement ignoré (réconciliation récente)');
        }
    }

    /**
     * ========================================
     * FONCTION PRINCIPALE DE RÉCONCILIATION
     * ========================================
     */

    window.reconcileConfiguration = function (force = false) {
        console.log('🔄 === DÉBUT RÉCONCILIATION ===');

        // Skip si déjà réconcilié récemment (sauf si force)
        if (!force && hasRecentReconciliation()) {
            console.log('ℹ️ Réconciliation récente détectée, skip');
            return Promise.resolve();
        }

        const currentUserId = parseInt(soeasyVars.userId) || 0;
        console.log('👤 Utilisateur actuel: ID', currentUserId);

        const localConfig = getLocalStorageConfig();

        if (!localConfig) {
            console.warn('❌ Impossible de lire localStorage');
            return checkSessionAndRestore();
        }

        // CAS 1 : CONFLIT UTILISATEUR
        if (typeof localConfig.userId !== 'undefined' && localConfig.userId !== currentUserId) {
            console.warn('⚠️ CONFLIT DÉTECTÉ !');
            console.warn('→ localStorage userId=' + localConfig.userId);
            console.warn('→ Utilisateur actuel =', currentUserId);

            clearLocalStorageConfig(); // ✅ Appelle déjà resetSidebarCompletely()

            return clearSessionConfig().then(function() {
                console.log('💾 Chargement dernière configuration...');
                return loadLastConfigurationFromDB(currentUserId);
            }).then(function() {
                console.log('✅ Conflit résolu');
            });
        }

        // CAS 2 : UTILISATEUR CONNECTÉ
        if (currentUserId > 0) {
            console.log('ℹ️ Utilisateur connecté');

            if (localConfig.adresses.length > 0 || Object.keys(localConfig.config).length > 0) {
                console.log('✅ Configuration locale valide, synchronisation...');
                return syncLocalStorageToSession(localConfig).then(function () {
                    markReconciliationDone();
                    console.log('✅ Réconciliation terminée, démarrage configurateur');
                });
            } else {
                console.log('ℹ️ localStorage vide, vérification session...');
                return checkSessionAndRestore().then(function () {
                    markReconciliationDone();
                });
            }
        }

        // CAS 3 : UTILISATEUR NON CONNECTÉ
        console.log('ℹ️ Utilisateur non connecté');
        ensureUserIdInLocalStorage();

        if (localConfig.adresses.length > 0 || Object.keys(localConfig.config).length > 0) {
            console.log('✅ Configuration anonyme existante, synchronisation...');
            return syncLocalStorageToSession(localConfig).then(function () {
                markReconciliationDone();
            });
        } else {
            console.log('ℹ️ Aucune configuration locale');
            return checkSessionAndRestore().then(function () {
                markReconciliationDone();
            });
        }
    };

    /**
     * Fonction de nettoyage complet (pour debugging)
     */
    window.clearConfigurationAndReload = function () {
        if (!confirm('Voulez-vous vraiment effacer toute la configuration et recharger ?')) {
            return;
        }

        console.log('🗑️ Nettoyage complet demandé');
        clearLocalStorageConfig();

        clearSessionConfig().then(function () {
            console.log('✅ Configuration effacée, rechargement...');
            sessionStorage.removeItem(RECONCILIATION_FLAG);
            sessionStorage.removeItem(RECONCILIATION_TIMESTAMP);
            location.reload();
        });
    };

    /**
     * ========================================
     * HELPERS DEBUGGING
     * ========================================
     */

    window.SoEasyReconciliation = {
        getConfig: getLocalStorageConfig,
        clearLocal: function () {
            clearLocalStorageConfig();
            console.log('💡 Utilisez clearAndReload() pour recharger la page');
        },
        clearSession: clearSessionConfig,
        syncToSession: syncLocalStorageToSession,
        loadFromDB: loadLastConfigurationFromDB,
        checkSession: checkSessionHasConfig,
        reconcile: window.reconcileConfiguration,
        clearAndReload: window.clearConfigurationAndReload,
        forceReconcile: function () {
            sessionStorage.removeItem(RECONCILIATION_FLAG);
            sessionStorage.removeItem(RECONCILIATION_TIMESTAMP);
            return window.reconcileConfiguration(true);
        }
    };

    window.updateConfigSyncTimestamp = function () {
        updateLastSyncTimestamp();
    };

    /**
     * ========================================
     * INITIALISATION
     * ========================================
     */

    $(document).ready(function () {

        if (typeof soeasyVars === 'undefined') {
            console.log('ℹ️ soeasyVars non défini, pas de réconciliation');
            return;
        }

        if ($('.config-step').length === 0 && !$('#configurateur-container').length) {
            console.log('ℹ️ Pas sur une page configurateur');
            return;
        }

        console.log('🎯 Page configurateur détectée, lancement réconciliation...');

        // Petite pause pour laisser WordPress s'initialiser
        setTimeout(function () {
            window.reconcileConfiguration()
                .then(function () {
                    console.log('✅ === RÉCONCILIATION TERMINÉE ===');
                })
                .catch(function (error) {
                    console.error('❌ === ERREUR RÉCONCILIATION ===');
                    console.error(error);
                });
        }, 100);
    });

})(jQuery);