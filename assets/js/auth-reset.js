/**
 * =====================================================
 * RESET TOTAL CONNEXION/DÉCONNEXION
 * =====================================================
 * Détecte le cookie "soeasy_force_clear" posé par PHP
 * et vide TOUT (localStorage + session + rechargement)
 */

(function($) {
  'use strict';

  /**
   * Vide TOUT le localStorage du configurateur
   */
  function clearAllConfig() {
    const keys = [
      'soeasyUserId',
      'soeasyConfigId',
      'soeasyLastSync',
      'soeasyConfig',
      'soeasyAdresses',
      'selectedDureeEngagement',
      'selectedFinancementMode',
      'soeasyCurrentStep'
    ];
    
    keys.forEach(key => localStorage.removeItem(key));
    console.log('🧹 localStorage TOTALEMENT vidé');
  }

  /**
   * Vide la session PHP via AJAX
   */
  function clearSession() {
    if (typeof soeasyVars === 'undefined') {
      console.warn('⚠️ soeasyVars non défini, skip clear session');
      return $.Deferred().resolve().promise();
    }
    
    return $.post(soeasyVars.ajaxurl, {
      action: 'soeasy_ajax_clear_session',
      nonce: soeasyVars.nonce_config
    }).done(function() {
      console.log('🧹 Session PHP vidée');
    }).fail(function() {
      console.warn('⚠️ Échec vidage session (non bloquant)');
    });
  }

  /**
   * Détection cookie "force_clear" posé par PHP
   */
  function checkForceClear() {
    const forceClear = document.cookie.match(/soeasy_force_clear=1/);
    
    if (forceClear) {
      console.log('🔴 RESET TOTAL DÉTECTÉ (connexion/déconnexion WordPress)');
      
      // 1. Vider localStorage
      clearAllConfig();
      
      // 2. Vider session PHP
      clearSession().always(function() {
        // 3. Supprimer le cookie
        document.cookie = 'soeasy_force_clear=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        
        // 4. Recharger page propre
        console.log('🔄 Rechargement page propre...');
        setTimeout(function() {
          window.location.href = window.location.pathname;
        }, 100);
      });
    }
  }

  // Vérifier au chargement de CHAQUE page
  $(document).ready(function() {
    checkForceClear();
  });

  // Exposer globalement pour debug
  window.SoEasyAuthReset = {
    clearAll: clearAllConfig,
    clearSession: clearSession,
    check: checkForceClear
  };

})(jQuery);