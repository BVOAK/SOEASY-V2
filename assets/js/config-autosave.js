jQuery(document).ready(function ($) {

  /**
   * ========================================
   * BOUTON SAUVEGARDE MANUELLE
   * ========================================
   */

  // Afficher le bouton sauvegarder si step >= 2 et user connecté
  function updateSaveButton() {
    const currentStep = parseInt(localStorage.getItem('soeasyCurrentStep') || '1');
    const userId = parseInt(soeasyVars.userId) || 0;
    const adresses = JSON.parse(localStorage.getItem('soeasyAdresses') || '[]');

    if (userId > 0 && currentStep >= 2 && adresses.length > 0) {
      $('#btn-save-config').show();
    } else {
      $('#btn-save-config').hide();
    }
  }

  // Appeler au chargement et à chaque changement d'étape
  updateSaveButton();

  // Event: Clic bouton "Sauvegarder"
  $(document).on('click', '#btn-save-config', function () {
    console.log('🖱️ Clic bouton Sauvegarder');

    // Pré-remplir le nom si config déjà sauvegardée
    const configId = localStorage.getItem('soeasyConfigId');

    if (configId) {
      $('#config-name-input').attr('placeholder', 'Configuration existante (sera mise à jour)');
    } else {
      $('#config-name-input').attr('placeholder', 'Ma configuration télécom');
    }

    $('#config-name-input').val('');
    $('#save-config-message').hide();

    // Ouvrir la modal
    const modal = new bootstrap.Modal(document.getElementById('modal-save-config'));
    modal.show();
  });

  // Event: Clic bouton "Confirmer" dans la modal
  $(document).on('click', '#btn-confirm-save', function () {
    const configName = $('#config-name-input').val().trim();

    console.log('💾 Confirmation sauvegarde, nom:', configName || '(auto)');

    // Désactiver le bouton pendant la sauvegarde
    $('#btn-confirm-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Sauvegarde...');

    // Appeler la fonction de sauvegarde
    window.saveConfigurationToDB(configName || null)
      .then(function () {
        // Fermer la modal après succès
        setTimeout(function () {
          bootstrap.Modal.getInstance(document.getElementById('modal-save-config')).hide();
        }, 1000);
      })
      .always(function () {
        // Réactiver le bouton
        $('#btn-confirm-save').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Sauvegarder');
      });
  });
});


/**
 * Afficher un toast de succès
 */
window.showToastSuccess = function (message) {
  console.log('✅ Toast success:', message);

  // Utiliser Bootstrap Toast si disponible
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    const toastHtml = `
      <div class="toast align-items-center text-white bg-success border-0" role="alert">
        <div class="d-flex">
          <div class="toast-body">
            <i class="fas fa-check-circle me-2"></i> ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    `;

    // Créer container si n'existe pas
    if ($('#toast-container').length === 0) {
      $('body').append('<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 11000;"></div>');
    }

    $('#toast-container').append(toastHtml);
    const toastElement = $('#toast-container .toast').last()[0];
    const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
    toast.show();

    $(toastElement).on('hidden.bs.toast', function () {
      $(this).remove();
    });
  } else {
    // Fallback alert
    alert(message);
  }
};

/**
 * Afficher un toast d'erreur
 */
window.showToastError = function (message) {
  console.error('❌ Toast error:', message);

  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    const toastHtml = `
      <div class="toast align-items-center text-white bg-danger border-0" role="alert">
        <div class="d-flex">
          <div class="toast-body">
            <i class="fas fa-exclamation-circle me-2"></i> ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    `;

    if ($('#toast-container').length === 0) {
      $('body').append('<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 11000;"></div>');
    }

    $('#toast-container').append(toastHtml);
    const toastElement = $('#toast-container .toast').last()[0];
    const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 5000 });
    toast.show();

    $(toastElement).on('hidden.bs.toast', function () {
      $(this).remove();
    });
  } else {
    alert(message);
  }
};