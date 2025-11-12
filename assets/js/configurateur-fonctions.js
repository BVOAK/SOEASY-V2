/**
   * Fonction centrale de récupération du mode de financement
   * 'comptant' ou 'leasing'
   */

function initGoogleAutocomplete() {
  if (!window.google || !google.maps || !google.maps.places) {
    setTimeout(initGoogleAutocomplete, 300);
    return;
  }

  const $input = document.querySelector('#adresse');
  if (!$input) return;

  const autocomplete = new google.maps.places.Autocomplete($input, {
    types: ['address'],
    componentRestrictions: { country: 'fr' }
  });

  autocomplete.addListener('place_changed', function () {
    const place = autocomplete.getPlace();
  });
}


function getSelectedFinancementMode() {
  return jQuery('input[name="financement"]:checked').val() || 'comptant';
}

/**
 * Fonction centrale de récupération de la durée d'engagement
 * retourne 24, 36, 48, 63 ou null
 */
function getSelectedEngagement() {
  return parseInt(jQuery('#engagement').val()) || null;
}

function updateEngagementVisibility() {
  const mode = getSelectedFinancementMode();

  // Masquer "Sans engagement" si un forfait Internet est sélectionné
  let hasInternet = false;
  const config = JSON.parse(localStorage.getItem('soeasyConfig') || '{}');
  Object.values(config).forEach(data => {
    (data.abonnements || []).forEach(prod => {
      if (prod.type === 'internet') {
        hasInternet = true;
      }
    });
  });

  const $optionSansEngagement = jQuery('#engagement option[value="0"]');

  if (mode === 'leasing' || hasInternet) {
    $optionSansEngagement.hide();

    // Si "Sans engagement" est actuellement sélectionné, forcer à 24 mois
    if (jQuery('#engagement').val() === '0') {
      jQuery('#engagement').val('24').trigger('change');
    }
  } else {
    $optionSansEngagement.show();
  }
}

//Fonction helper pour obtenir le nom d'affichage d'une adresse
function getAdresseDisplayName(index, format = 'long') {
  const adresses = JSON.parse(localStorage.getItem('soeasyAdresses')) || [];
  const adresseData = adresses[index];

  if (!adresseData) {
    return `Adresse #${parseInt(index) + 1}`;
  }

  if (format === 'short' && adresseData.ville_courte) {
    return adresseData.ville_courte;
  }

  if (format === 'long' && adresseData.ville_longue) {
    return adresseData.ville_longue;
  }

  // Fallback vers l'adresse complète
  return adresseData.adresse || `Adresse #${parseInt(index) + 1}`;
}

/**
 * Fonction centrale de récupération des adresses
 */
function getAdresseByIndex(index) {
  return getAdresseDisplayName(index, 'long');
}


/**
* Met à jour dynamiquement le prix total affiché
*/
function updatePrixTotal($input) {
  const $container = $input.closest('.border, .list-group-item, [data-prix-comptant], [data-prix-leasing-24]');
  const qty = parseInt($input.val()) || 0;

  // Prix unitaire depuis data-unit (mis à jour par updatePrices)
  const $prixAffiche = $container.find('.prix-affiche');
  const prixUnitaire = parseFloat($prixAffiche.data('unit')) || 0;

  // Détecter le suffixe
  const texteActuel = $prixAffiche.text();
  const suffix = texteActuel.includes('/ mois') ? ' / mois' : '';

  // Calcul
  const total = prixUnitaire * qty;

  // Mise à jour affichage total
  const $prixTotal = $container.find('.prix-total');
  if ($prixTotal.length) {
    const totalFormate = new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2
    }).format(total);

    $prixTotal.text(totalFormate + suffix);
    $prixTotal.data('unit', prixUnitaire);
  }

  console.log(`✅ Total: ${qty} × ${prixUnitaire}€ = ${total}€${suffix}`);
}


function updateAllPrixTotaux() {
  console.log('🔄 updateAllPrixTotaux() - Mise à jour globale des prix totaux');

  jQuery('.input-qty').each(function () {
    const $input = jQuery(this);
    updatePrixTotal($input);
  });

  console.log('✅ Tous les prix totaux mis à jour');
}

// Calcul total global si souhaité
function saveToLocalConfig(adresseId, section, nouveauxProduits, options = {}) {
  const key = 'soeasyConfig';
  const config = JSON.parse(localStorage.getItem(key)) || {};

  if (!config[adresseId]) config[adresseId] = {};
  if (!Array.isArray(config[adresseId][section])) config[adresseId][section] = [];

  let existants = config[adresseId][section];
  let fusionnes = [];

  if (options.replace === true && options.type) {
    fusionnes = existants.filter(p => p.type !== options.type);
  } else {
    fusionnes = [...existants];
  }

  const indexés = {};
  fusionnes.forEach(p => {
    const key = p.id || p.nom;
    indexés[key] = p;
  });
  if (Array.isArray(nouveauxProduits)) {
    nouveauxProduits.forEach(p => {
      const key = p.id || p.nom;
      indexés[key] = p;
    });
  }

  config[adresseId][section] = Object.values(indexés);
  localStorage.setItem(key, JSON.stringify(config));

  jQuery.post(soeasyVars.ajaxurl, {
    action: 'soeasy_set_config_part',
    index: adresseId,
    key: section,
    items: config[adresseId][section]
  });

  if (section === 'fraisInstallation') {
    jQuery.post(soeasyVars.ajaxurl, {
      action: 'soeasy_set_frais_installation',
      index: adresseId,
      items: config[adresseId][section]
    });
  }
}


/**
   * Parcours tous les prix produits en fonction de la durée et du financement
   */
function updatePrixProduits() {
  console.log('🔄 updatePrixProduits() - Mise à jour prix dans localStorage (VERSION CORRIGÉE)');

  const mode = getSelectedFinancementMode();
  const duree = getSelectedEngagement();
  const recapData = JSON.parse(localStorage.getItem('soeasyConfig')) || {};

  Object.entries(recapData).forEach(([adresseId, config]) => {
    ['abonnements', 'materiels', 'fraisInstallation'].forEach(section => {
      if (!Array.isArray(config[section])) return;

      config[section].forEach(produit => {
        if (!produit || typeof produit !== 'object') return;

        // ✅ CORRECTION : Définition cohérente des abonnements
        const isAbonnement = ['internet', 'forfait-mobile', 'forfait-data', 'licence-centrex',
          'forfait', 'abonnement', 'mobile', 'centrex', 'forfait-centrex'].includes(produit.type);

        let nouveauPrix = parseFloat(produit.prixUnitaire) || 0;

        if (isAbonnement) {
          // ✅ CORRECTION : Les abonnements changent selon l'engagement (PEU IMPORTE le mode)
          if (duree) {
            const clePrixLeasing = `prixLeasing${duree}`;
            nouveauPrix = parseFloat(produit[clePrixLeasing]) || parseFloat(produit.prixUnitaire) || 0;
          } else {
            // Fallback si pas de durée
            nouveauPrix = parseFloat(produit.prixLeasing24) || parseFloat(produit.prixComptant) || 0;
          }

          console.log(`📱 Abonnement ${produit.nom}: prix mis à jour à ${nouveauPrix}€/mois (engagement: ${duree})`);

        } else {
          // ✅ MATÉRIELS : Prix selon le mode ET l'engagement
          if (mode === 'comptant') {
            nouveauPrix = parseFloat(produit.prixComptant) || produit.prixUnitaire || 0;
          } else if (mode === 'leasing' && duree) {
            const clePrixLeasing = `prixLeasing${duree}`;
            nouveauPrix = parseFloat(produit[clePrixLeasing]) || produit.prixUnitaire || 0;
          }

          console.log(`🔧 ${section} ${produit.nom}: prix mis à jour à ${nouveauPrix}€ (mode: ${mode}, durée: ${duree})`);
        }

        // Mettre à jour le prix unitaire dans l'objet
        produit.prixUnitaire = nouveauPrix;
      });
    });
  });

  // Sauvegarder les modifications dans localStorage
  localStorage.setItem('soeasyConfig', JSON.stringify(recapData));

  console.log('✅ Prix produits mis à jour dans localStorage');
}

window.initGoogleAutocomplete = initGoogleAutocomplete;
window.getSelectedEngagement = getSelectedEngagement;
window.getSelectedFinancementMode = getSelectedFinancementMode;
window.updatePrixTotal = updatePrixTotal;
window.saveToLocalConfig = saveToLocalConfig;
window.updatePrixProduits = updatePrixProduits;
window.updateAllPrixTotaux = updateAllPrixTotaux;
window.updateEngagementVisibility = updateEngagementVisibility;

jQuery(document).ready(function ($) {

  /**
   * MAJ des prix affichés selon mode de financement + engagement
   */

  function updatePrices() {
    console.log('🔄 updatePrices() - Logique hybride DOM + localStorage');

    const mode = getSelectedFinancementMode();
    const duree = getSelectedEngagement();

    console.log(`📋 Mode: ${mode}, Engagement: ${duree} mois`);

    // ✅ CAS SPÉCIAL STEP-6 : Utiliser localStorage
    if (jQuery('.step-6').length) {
      console.log('📊 Step-6 détecté - Mise à jour via localStorage');

      // Mettre à jour localStorage d'abord
      if (typeof updatePrixProduits === 'function') {
        updatePrixProduits();
      }

      // Puis regénérer l'affichage
      setTimeout(() => {
        if (typeof updateRecapitulatif === 'function') {
          updateRecapitulatif();
        }
        if (typeof updateSidebarTotauxRecap === 'function') {
          updateSidebarTotauxRecap();
        }
      }, 50);

      console.log('✅ Step-6 traité via localStorage');
      return; // Sortir pour step-6
    }

    // ✅ STEPS 2-5 : Utiliser les attributs HTML

    // 1️⃣ ABONNEMENTS

    // Step-2 : data sur input checkbox
    jQuery('input[data-prix-leasing-24]:not([data-prix-comptant])').each(function () {
      const $input = jQuery(this);
      const $container = $input.closest('label');
      const prixMensuel = $input.data(`prix-leasing-${duree}`) || $input.data('prix-leasing-24') || 0;

      console.log(`📱 Abonnement step-2: ${prixMensuel}€/mois`);

      const $prixAffiche = $container.find('.prix-affiche');
      if ($prixAffiche.length) {
        updatePrixAffiche($prixAffiche, prixMensuel, ' / mois');
      }
    });

    // Steps 3-4-5 : data sur container
    jQuery('[data-prix-leasing-24]:not([data-prix-comptant])').not('input').each(function () {
      const $container = jQuery(this);
      const prixMensuel = $container.data(`prix-leasing-${duree}`) || $container.data('prix-leasing-24') || 0;

      console.log(`📱 Abonnement steps 3-4-5: ${prixMensuel}€/mois`);

      const $prixAffiche = $container.find('.prix-affiche');
      if ($prixAffiche.length) {
        updatePrixAffiche($prixAffiche, prixMensuel, ' / mois');
      }
    });

    // 2️⃣ MATÉRIELS
    jQuery('[data-prix-comptant]').each(function () {
      const $container = jQuery(this);

      let prix = 0;
      let suffix = '';

      if (mode === 'comptant') {
        prix = $container.data('prix-comptant') || 0;
        suffix = '';
      } else if (mode === 'leasing') {
        prix = $container.data(`prix-leasing-${duree}`) || 0;
        suffix = ' / mois';
      }

      console.log(`🔧 Matériel: ${prix}€${suffix}`);

      const $prixAffiche = $container.find('.prix-affiche');
      if ($prixAffiche.length) {
        updatePrixAffiche($prixAffiche, prix, suffix);
      }
    });

    // 3️⃣ MISE À JOUR LOCALSTORAGE pour cohérence
    if (typeof updatePrixProduits === 'function') {
      updatePrixProduits();
    }

    // 4️⃣ RECALCUL DES TOTAUX
    setTimeout(() => {
      if (typeof updateAllPrixTotaux === 'function') {
        updateAllPrixTotaux();
      }

      if (typeof updateSidebarTotauxRecap === 'function') {
        updateSidebarTotauxRecap();
      }
    }, 50);

    console.log('✅ updatePrices() terminé');
  }

  function updatePrixAffiche($element, prix, suffix) {
    const $bdi = $element.find('.woocommerce-Price-amount bdi');

    if ($bdi.length > 0) {
      // Structure WooCommerce complète
      const $currency = $bdi.find('.woocommerce-Price-currencySymbol');
      const currencySymbol = $currency.text() || '€';

      const prixFormate = parseFloat(prix).toLocaleString('fr-FR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      $bdi.html(`${prixFormate}&nbsp;<span class="woocommerce-Price-currencySymbol">${currencySymbol}</span>`);

      // Gérer le suffixe
      const $priceAmount = $element.find('.woocommerce-Price-amount');
      if ($priceAmount.get(0).nextSibling && $priceAmount.get(0).nextSibling.nodeType === 3) {
        $priceAmount.get(0).nextSibling.remove();
      }
      if (suffix) {
        $priceAmount.after(suffix);
      }

    } else {
      // Structure simple
      const prixFormate = parseFloat(prix).toLocaleString('fr-FR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      $element.text(`${prixFormate} €${suffix}`);
    }

    // ✅ CRUCIAL : Mettre à jour data-unit pour les calculs de totaux
    $element.data('unit', parseFloat(prix));
  }

  function updateFraisTotal(index) {
    const mode = getSelectedFinancementMode();
    const duree = getSelectedEngagement();
    let total = 0;

    const $list = $(`.frais-installation-list[data-index="${index}"]`);
    const checked = $list.find('.frais-checkbox:checked');

    if (checked.length === 0) {
      $(`.frais-total[data-index="${index}"]`).text('0 €');
      return;
    }

    checked.each(function () {
      const $cb = $(this);
      const qty = parseInt($cb.data('quantite')) || 1;
      let unit = 0;

      if (mode === 'comptant') {
        unit = parseFloat($cb.data('prix-comptant')) || 0;
      } else if (mode === 'leasing' && duree) {
        const raw = $cb.data(`prix-leasing-${duree}`);
        unit = typeof raw !== 'undefined' ? parseFloat(raw) || 0 : 0;
      }

      if (isNaN(unit)) unit = 0;

      total += unit * qty;
    });

    //const safeTotal = isNaN(total) ? 0 : total;
    const formatted = new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR'
    }).format(total) + (mode === 'leasing' ? ' / mois' : '');

    $(`.frais-total[data-index="${index}"]`).text(formatted);
  }

  /* Ajout d'une adresse en session via AJAX */
  function addAdresseToSession(adresse, services) {
    $.ajax({
      url: soeasyVars.ajaxurl,
      method: 'POST',
      data: {
        action: 'soeasy_add_adresse',
        adresse: adresse,
        services: services
      },
      success: function (response) {
        console.log('Adresse enregistrée :', response);
      }
    });
  }

  // Mise en forme du récap de l'étape 6
function updateRecapitulatif() {
  console.log('🔄 updateRecapitulatif() - Version responsive avec logique prix corrigée');

  const recapData = JSON.parse(localStorage.getItem('soeasyConfig')) || {};
  const mode = getSelectedFinancementMode();
  const engagement = getSelectedEngagement();

  console.log(`📋 Mode: ${mode}, Engagement: ${engagement} mois`);

  if (Object.keys(recapData).length === 0) {
    console.log('⚠️ Aucune configuration trouvée');
    return;
  }

  Object.entries(recapData).forEach(([adresseId, data]) => {
    console.log(`📋 Génération récap pour adresse ${adresseId}:`, data);

    // 1. ✅ ABONNEMENTS
    const $abonnementsContainer = $(`#collapse-${adresseId} .abonnements-grid .products-list`);
    $abonnementsContainer.empty();
    
    if (data.abonnements && data.abonnements.length > 0) {
      data.abonnements.forEach(item => {
        $abonnementsContainer.append(generateProductRowCorrect(item, 'abonnement', mode, engagement));
      });
    } else {
      $abonnementsContainer.append('<div class="no-products">Aucun abonnement sélectionné</div>');
    }

    // 2. ✅ MATÉRIELS  
    const $materielsContainer = $(`#collapse-${adresseId} .materiels-grid .products-list`);
    $materielsContainer.empty();
    
    if (data.materiels && data.materiels.length > 0) {
      data.materiels.forEach(item => {
        $materielsContainer.append(generateProductRowCorrect(item, 'materiel', mode, engagement));
      });
    } else {
      $materielsContainer.append('<div class="no-products">Aucun matériel sélectionné</div>');
    }

    // 3. ✅ FRAIS D'INSTALLATION
    const $installationsContainer = $(`#collapse-${adresseId} .installations-grid .products-list`);
    $installationsContainer.empty();
    
    if (data.fraisInstallation && data.fraisInstallation.length > 0) {
      data.fraisInstallation.forEach(item => {
        $installationsContainer.append(generateProductRowCorrect(item, 'frais', mode, engagement));
      });
    } else {
      $installationsContainer.append('<div class="no-products">Aucun frais d\'installation</div>');
    }
  });

  console.log('✅ Récapitulatif responsive généré avec prix corrects');
}


  /**
   * ✅ NOUVELLE fonction : Génère une ligne de produit responsive
   */
  function generateProductRowCorrect(item, type, mode, engagement) {
  const quantite = parseInt(item.quantite) || 0;
  
  // ✅ CORRECTION : Logique prix selon le type et mode (comme l'ancien code)
  let prixUnitaire = 0;
  let suffix = '';

  if (type === 'abonnement') {
    // ✅ ABONNEMENTS : Toujours mensuels, prix selon engagement
    prixUnitaire = parseFloat(item.prixUnitaire) || 0; // Déjà mis à jour par updatePrixProduits()
    suffix = ' / mois';
    
  } else if (type === 'materiel') {
    // ✅ MATÉRIELS : Prix selon mode ET engagement
    if (mode === 'comptant') {
      prixUnitaire = parseFloat(item.prixComptant) || parseFloat(item.prixUnitaire) || 0;
      suffix = '';
    } else if (mode === 'leasing' && engagement) {
      prixUnitaire = parseFloat(item[`prixLeasing${engagement}`]) || parseFloat(item.prixUnitaire) || 0;
      suffix = ' / mois';
    }
    
  } else if (type === 'frais') {
    // ✅ FRAIS D'INSTALLATION : Même logique que matériels
    if (mode === 'leasing') {
      prixUnitaire = parseFloat(
        item[`prixLeasing${engagement}`] ??
        item.prixLeasing24 ??
        item.prixLeasing36 ??
        item.prixLeasing48 ??
        item.prixLeasing63 ??
        0
      );
      suffix = ' / mois';
    } else {
      prixUnitaire = parseFloat(item.prixComptant) || 0;
      suffix = '';
    }
  }

  const total = prixUnitaire * quantite;
  
  // Format des prix
  const prixFormate = prixUnitaire.toLocaleString('fr-FR', { 
    minimumFractionDigits: 2, 
    maximumFractionDigits: 2 
  });
  const totalFormate = total.toLocaleString('fr-FR', { 
    minimumFractionDigits: 2, 
    maximumFractionDigits: 2 
  });

  console.log(`💰 ${item.nom}: ${prixUnitaire}€${suffix} x ${quantite} = ${total}€${suffix}`);

  return `
    <div class="product-row">
      <!-- Info produit -->
      <div class="product-info">
        <div class="product-name">${escapeHtml(item.nom)}</div>
        ${item.description ? `<div class="product-description">${escapeHtml(item.description)}</div>` : ''}
      </div>
      
      <!-- Desktop: colonnes -->
      <div class="unit-price d-none d-md-block">${prixFormate} €${suffix}</div>
      <div class="quantity d-none d-md-block">${quantite}</div>
      <div class="total-price d-none d-md-block">${totalFormate} €${suffix}</div>
      
      <!-- Mobile: layout flexible -->
      <div class="mobile-details d-md-none">
        <div class="detail-item">
          <div class="detail-label">Quantité</div>
          <div class="detail-value quantity">${quantite}</div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Prix unit.</div>
          <div class="detail-value">${prixFormate} €${suffix}</div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Total</div>
          <div class="detail-value total-price">${totalFormate} €${suffix}</div>
        </div>
      </div>
    </div>
  `;
}


  /**
   * Mise à jour des totaux par adresse
   */
  function updateRecapTotals() {
  const config = JSON.parse(localStorage.getItem('soeasyConfig')) || {};
  const mode = getSelectedFinancementMode();
  const engagement = getSelectedEngagement();

  Object.entries(config).forEach(([index, data]) => {
    let totalComptant = 0;
    let totalMensuel = 0;

    // ✅ CORRECTION : Reprendre la logique exacte de initStep6Events

    // 3a. Abonnements (toujours mensuels)
    (data.abonnements || []).forEach(item => {
      const prix = parseFloat(item.prixUnitaire) || 0;
      const qty = parseInt(item.quantite) || 0;
      totalMensuel += prix * qty;
    });

    // 3b. Matériels
    (data.materiels || []).forEach(item => {
      const qty = parseInt(item.quantite) || 0;

      // Prix comptant toujours calculé
      const prixComptant = parseFloat(item.prixComptant) || 0;
      totalComptant += prixComptant * qty;

      // Prix leasing si mode leasing
      if (mode === 'leasing' && engagement) {
        const prixLeasing = parseFloat(item[`prixLeasing${engagement}`]) || 0;
        totalMensuel += prixLeasing * qty;
      }
    });

    // 3c. Frais d'installation
    (data.fraisInstallation || []).forEach(item => {
      const qty = parseInt(item.quantite) || 0;

      // Prix comptant toujours calculé
      const prixComptant = parseFloat(item.prixComptant) || 0;
      totalComptant += prixComptant * qty;

      // Prix leasing si mode leasing
      if (mode === 'leasing' && engagement) {
        const prixLeasing = parseFloat(item[`prixLeasing${engagement}`]) || 0;
        totalMensuel += prixLeasing * qty;
      }
    });

    // ✅ AFFICHAGE : Reprendre la logique exacte
    const $accordionBody = $(`#collapse-${index} .accordion-body`);
    $accordionBody.find('.totaux-adresse').remove();

    const $totauxDiv = $('<div class="totaux-adresse border mt-3 p-3 rounded"></div>');

    if (mode === 'comptant') {
      $totauxDiv.append(`
        <div class="d-flex justify-content-between">
          <small>Total mensuel (abonnements) :</small>
          <strong>${totalMensuel.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € / mois</strong>
        </div>
        <div class="d-flex justify-content-between">
          <small>Total comptant (équipements + frais) :</small>
          <strong>${totalComptant.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</strong>
        </div>
      `);
    } else {
      $totauxDiv.append(`
        <div class="d-flex justify-content-between align-items-center">
          <strong>Total mensuel :</strong>
          <strong class="h5 mb-0">${totalMensuel.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € / mois</strong>
        </div>
      `);
    }

    $accordionBody.append($totauxDiv);
  });
}

  /**
   * ✅ Helper pour échapper le HTML
   */
  function escapeHtml(text) {
    if (!text) return '';
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  function updateSidebarProduitsRecap() {
  const recapData = JSON.parse(localStorage.getItem('soeasyConfig')) || {};
  const adressesData = JSON.parse(localStorage.getItem('soeasyAdresses')) || [];
  const $container = $('#config-recapitulatif');
  $container.empty();

  const engagement = getSelectedEngagement();
  const mode = getSelectedFinancementMode();

  if (Object.keys(recapData).length === 0) {
    $container.append('<p>Aucune sélection pour le moment.</p>');
    return;
  }

  Object.entries(recapData).forEach(([index, config]) => {
    // ✅ NOUVEAU : Utiliser ville_longue depuis les données enrichies
    const adresseData = adressesData[index];
    let displayName;
    
    if (adresseData && adresseData.ville_longue) {
      displayName = adresseData.ville_longue;
    } else if (adresseData && adresseData.adresse) {
      displayName = adresseData.adresse;
    } else {
      displayName = `Adresse #${parseInt(index) + 1}`;
    }

    const abonnements = config.abonnements || [];
    const materiels = config.materiels || [];
    const frais = config.fraisInstallation || [];

    const $accordion = $(`
      <div class="accordion-item mb-2">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                  data-bs-target="#sidebar-collapse-${index}" aria-expanded="false">
            ${displayName}
          </button>
        </h2>
        <div id="sidebar-collapse-${index}" class="accordion-collapse collapse">
          <div class="accordion-body p-2">
            <div class="products-recap"></div>
          </div>
        </div>
      </div>
    `);

    // Reste du code inchangé...
    const $body = $accordion.find('.products-recap');
    
    if (abonnements.length > 0) {
      $body.append('<h6 class="text-primary mb-1">Abonnements</h6>');
      abonnements.forEach(item => {
        $body.append(`<div class="small mb-1">${item.nom} <span class="float-end">${item.quantite}x</span></div>`);
      });
    }

    if (materiels.length > 0) {
      $body.append('<h6 class="text-primary mb-1 mt-2">Matériels</h6>');
      materiels.forEach(item => {
        $body.append(`<div class="small mb-1">${item.nom} <span class="float-end">${item.quantite}x</span></div>`);
      });
    }

    if (frais.length > 0) {
      $body.append('<h6 class="text-primary mb-1 mt-2">Frais installation</h6>');
      frais.forEach(item => {
        $body.append(`<div class="small mb-1">${item.nom} <span class="float-end">${item.quantite}x</span></div>`);
      });
    }

    $container.append($accordion);
  });
}



  // Mise en forme du récap de la sidebar des totaux
  function updateSidebarTotauxRecap() {
    const mode = getSelectedFinancementMode();
    const duree = getSelectedEngagement(); // ex: 24, 36, etc.
    const recapData = JSON.parse(localStorage.getItem('soeasyConfig')) || {};

    let totalAbonnement = 0;
    let totalComptant = 0;
    let totalMensuelLeasing = 0;

    Object.values(recapData).forEach(config => {
      (config.abonnements || []).forEach(item => {
        const prix = item.prixUnitaire || 0;
        totalAbonnement += prix * item.quantite;
      });

      (config.materiels || []).forEach(item => {
        const prixComptant = item.prixComptant || 0;
        const prixLeasing = item[`prixLeasing${duree}`] || 0;

        if (mode === 'leasing') {
          totalMensuelLeasing += prixLeasing * item.quantite;
          totalComptant += prixComptant * item.quantite;
        } else {
          totalComptant += prixComptant * item.quantite;
          totalMensuelLeasing += prixLeasing * item.quantite;
        }
      });
    });

    const $container = $('#config-sidebar-total');
    $container.empty();

    if (mode === 'leasing') {
      const totalMensuel = totalAbonnement + totalMensuelLeasing;
      $container.append(`
        <div class="fw-bold mb-1 d-flex justify-content-between align-items-center"><small class="col">Abonnement + Leasing :</small> <span class="col">${totalMensuel.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € / mois</span></div>
        <div class="text-muted small">
        ou abonnements : ${totalAbonnement.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € / mois<br />
        + équipements : ${totalComptant.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
      </div>
      `);
    } else {
      $container.append(`
        <div class="fw-bold mb-1 d-flex justify-content-between align-items-center"><small class="col">Abonnements mensuels :</small> <span>${totalAbonnement.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € / mois</span></div>
        <div class="fw-bold mb-1 d-flex justify-content-between align-items-center"><small class="col">Équipements :</small> <span>${totalComptant.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</span></div>
        <div class="text-muted small">ou abonnements + leasing : ${(totalAbonnement + totalMensuelLeasing).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € / mois</div>
      `);
    }
  }


  /**
 * ========================================
 * NOUVELLES FONCTIONS STEP 5 LOCALSTORAGE
 * ========================================
 */

  /**
   * Récupération des données pour Step 5 (localStorage prioritaire)
   */
  function getConfigForStep5() {
    const localConfig = JSON.parse(localStorage.getItem('soeasyConfig') || '{}');
    const sessionConfig = window.step5Data?.sessionConfig || {};

    if (Object.keys(localConfig).length > 0) {
      console.log('📱 Utilisation des données localStorage');
      return localConfig;
    } else if (Object.keys(sessionConfig).length > 0) {
      console.log('🖥️ Fallback vers données session');
      return sessionConfig;
    } else {
      console.log('⚠️ Aucune donnée trouvée');
      return {};
    }
  }

  /**
   * Génération principale du contenu Step 5
   */
  function generateStep5Content() {
    console.log('🎯 Génération du contenu Step 5 depuis localStorage');

    try {
      // 1. Récupérer les données (localStorage prioritaire, session fallback)
      const config = getConfigForStep5();
      const adresses = JSON.parse(localStorage.getItem('soeasyAdresses') || '[]');

      if (Object.keys(config).length === 0) {
        $('#step5-content').html('<div class="alert alert-warning">Aucune configuration trouvée. Veuillez reprendre depuis l\'étape 1.</div>').show();
        $('#step5-loader').hide();
        $('#step5-navigation').show();
        return;
      }

      // 2. Générer le HTML pour chaque adresse
      let html = '';
      Object.keys(config).forEach(index => {
        const adresseData = config[index];
        const adresseInfo = adresses[index];
        html += generateAdresseBlock(index, adresseData, adresseInfo);
      });

      // 3. Injecter dans le DOM et afficher
      $('#step5-content').html(html).show();
      $('#step5-loader').hide();
      $('#step5-navigation').show();

      // 4. Initialiser les totaux pour chaque adresse
      Object.keys(config).forEach(index => {
        updateFraisTotal(index);
      });

      console.log('✅ Contenu Step 5 généré avec succès');

    } catch (error) {
      console.error('❌ Erreur génération Step 5:', error);
      $('#step5-content').html('<div class="alert alert-danger">Erreur lors du chargement. Veuillez recharger la page.</div>').show();
      $('#step5-loader').hide();
      $('#step5-navigation').show();
    }
  }

  /**
   * Génération du HTML pour un bloc d'adresse
   */
  function generateAdresseBlock(index, adresseData, adresseInfo) {
    const adresseTexte = getAdresseByIndex(index);
    const mode = getSelectedFinancementMode();
    const duree = getSelectedEngagement();

    const fraisInstallation = adresseData.fraisInstallation || [];

    let fraisHTML = '';
    if (Array.isArray(fraisInstallation) && fraisInstallation.length > 0) {
      fraisInstallation.forEach(frais => {
        fraisHTML += generateFraisItem(index, frais, mode, duree);
      });
    }

    return `
    <div class="card item-list-product mb-3">
      <div class="card-body p-5">
      <h5 class="mb-3 card-title">Adresse : ${adresseTexte}</h5>
      
      ${fraisInstallation.length > 0 ? `
        <ul class="list-group frais-installation-list" data-index="${index}">
          ${fraisHTML}
        </ul>
        
        <div class="mt-3 d-flex justify-content-between align-items-center">
          <div class="form-check">
            <input class="form-check-input report-frais-checkbox" 
                   type="checkbox" 
                   id="report_frais_${index}" 
                   data-index="${index}">
            <label class="form-check-label" for="report_frais_${index}">
              Reporter ces frais d'installation
            </label>
          </div>
          <strong class="frais-total badge bg-primary" data-index="${index}">0 €</strong>
        </div>
      ` : `
        <div class="alert alert-info">Aucun frais d'installation pour cette adresse.</div>
      `}
    </div>
    </div>
  `;
  }

  /**
   * Génération du HTML pour une ligne de frais
   */
  /**
   * Génération du HTML pour une ligne de frais - VERSION CORRIGÉE
   */
  function generateFraisItem(index, frais, mode, duree) {
    // Récupérer le prix unitaire selon le mode
    let prixUnitaire = 0;
    if (mode === 'comptant') {
      prixUnitaire = parseFloat(frais.prixComptant) || 0;
    } else {
      prixUnitaire = parseFloat(frais[`prixLeasing${duree}`]) || 0;
    }

    // Calculer le prix total (prix unitaire × quantité)
    const quantite = parseInt(frais.quantite) || 1;
    const prixTotal = prixUnitaire * quantite;

    const prixFormate = prixTotal.toLocaleString('fr-FR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

    const suffix = mode === 'leasing' ? ' / mois' : '';

    return `
    <li class="list-group-item d-flex justify-content-between align-items-center p-3 item-frais">
      <div class="form-check checkbox-wrapper p-0">
        <input class="form-check-input frais-checkbox inp-cbx" 
               type="checkbox"
               id="frais-checkbox-${frais.id}"
               data-index="${index}"
               data-id="${frais.id}"
               data-quantite="${quantite}"
               data-type="${frais.type || 'internet'}"
               data-nom="${escapeHtml(frais.nom || 'Frais d\'installation')}"
               data-prix-comptant="${frais.prixComptant || 0}"
               data-prix-leasing-24="${frais.prixLeasing24 || 0}"
               data-prix-leasing-36="${frais.prixLeasing36 || 0}"
               data-prix-leasing-48="${frais.prixLeasing48 || 0}"
               data-prix-leasing-63="${frais.prixLeasing63 || 0}"
               checked style="display: none;" />
        <label class="cbx" for="frais-checkbox-${frais.id}">
          <span>
            <svg width="12px" height="9px" viewbox="0 0 12 9">
              <polyline points="1 5 4 8 11 1"></polyline>
            </svg>
          </span>
          <div>
            ${escapeHtml(frais.nom || 'Frais d\'installation')}
            ${quantite > 1 ? ` <small class="text-muted">(×${quantite})</small>` : ''}
          </div>
        </label>
      </div>
      <span class="price badge fs-6" data-prix-affiche="${prixTotal}">
        ${prixFormate} €${suffix}
      </span>
    </li>
  `;
  }

  /**
   * Fonction d'échappement HTML (tu l'as oubliée)
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Fonction de synchronisation session en arrière-plan (optionnel)
   */
  function syncFraisToSession(index, frais) {
    if (typeof soeasyVars !== 'undefined' && soeasyVars.ajaxurl) {
      $.post(soeasyVars.ajaxurl, {
        action: 'soeasy_set_frais_installation',
        index: index,
        items: frais,
        nonce: soeasyVars.nonce_config
      })
        .done(function () {
          console.log(`🔄 Sync session réussie pour adresse ${index}`);
        })
        .fail(function () {
          console.warn(`⚠️ Échec sync session pour adresse ${index} (non bloquant)`);
        });
    }
  }


  /**
 * Ajoute les attributs de variation à tous les produits de la config
 */
  function enrichConfigWithVariations(config) {
    const engagement = getSelectedEngagement();
    const financement = getSelectedFinancementMode();

    console.log(`🔧 enrichConfigWithVariations - engagement=${engagement}, financement=${financement}`);

    // Format correct : ajouter "-mois"
    let engagementValue;
    if (!engagement || engagement === 0 || engagement === '0') {
      engagementValue = 'sans-engagement';
    } else {
      engagementValue = engagement + '-mois';
    }

    // ✅ CORRECTION FINALE : pa_duree-dengagement (avec le "d")
    const attributes = {
      'pa_duree-dengagement': engagementValue
    };

    console.log('🎯 Attributes à ajouter:', attributes);

    Object.keys(config).forEach(adresseIndex => {
      ['abonnements', 'materiels', 'fraisInstallation'].forEach(section => {
        if (Array.isArray(config[adresseIndex][section])) {
          config[adresseIndex][section].forEach(produit => {
            if (!produit.attributes) {
              produit.attributes = { ...attributes };
              console.log(`✅ Attributes ajoutés pour ${produit.nom}: ${engagementValue}`);
            }
          });
        }
      });
    });

    return config;
  }

  /**
* Fonction de validation finale et envoi vers le panier WooCommerce
* Gère toutes les adresses configurées
*/
  function sendToCart() {
    console.log('🛒 Début sendToCart()');

    // 1. Récupération de la configuration
    let config = JSON.parse(localStorage.getItem('soeasyConfig') || '{}');
    const adresses = JSON.parse(localStorage.getItem('soeasyAdresses') || '[]');

    if (Object.keys(config).length === 0) {
      showToastError('Aucune configuration trouvée. Veuillez configurer au moins une adresse.');
      return false;
    }

    console.log('📦 Configuration trouvée:', config);
    console.log('📍 Adresses:', adresses);

    // ✅ NOUVEAU : ENRICHIR LA CONFIG AVEC LES VARIATIONS
    config = enrichConfigWithVariations(config);
    console.log('✨ Configuration enrichie avec variations:', config);

    // 2. Validation : au moins un produit configuré
    let hasProducts = false;
    Object.values(config).forEach(adresseData => {
      const sections = ['abonnements', 'materiels', 'fraisInstallation'];
      sections.forEach(section => {
        if (Array.isArray(adresseData[section]) && adresseData[section].length > 0) {
          hasProducts = true;
        }
      });
    });

    if (!hasProducts) {
      showToastError('Veuillez sélectionner au moins un produit ou service avant de valider.');
      return false;
    }

    // 3. Préparation des données pour l'envoi
    const payload = {
      action: 'soeasy_ajouter_au_panier_multi',
      config: config,
      adresses: adresses,
      nonce: soeasyVars.nonce_cart
    };

    console.log('📤 Payload envoyé:', payload);

    // 4. Affichage loading
    const $btn = jQuery('#btn-commander');
    const originalText = $btn.text();
    $btn.prop('disabled', true).text('Ajout au panier...');

    // 5. Envoi AJAX
    return jQuery.post(soeasyVars.ajaxurl, payload)
      .done(response => {
        console.log('✅ Réponse serveur:', response);

        if (response.success) {
          console.log('🎉 Configuration ajoutée avec succès au panier');
          $btn.text('Redirection...');

          setTimeout(() => {
            window.location.href = response.data.redirect_url || '/panier';
          }, 500);

        } else {
          const errorMsg = response.data?.message || 'Erreur lors de l\'ajout au panier.';
          console.error('❌ Erreur business:', errorMsg);
          if (typeof showToastError === 'function') {
            showToastError(errorMsg);
          } else {
            alert(errorMsg);
          }
          $btn.prop('disabled', false).text(originalText);
        }
      })
      .fail((xhr, status, error) => {
        console.error('💥 Erreur technique:', { xhr, status, error });

        let errorMsg = 'Erreur technique. Veuillez réessayer.';

        if (xhr.responseJSON?.data?.message) {
          errorMsg = xhr.responseJSON.data.message;
        } else if (xhr.status === 500) {
          errorMsg = 'Erreur serveur (500). Vérifiez les logs PHP.';
        } else if (xhr.status === 0) {
          errorMsg = 'Problème de connexion. Vérifiez votre réseau.';
        }

        if (typeof showToastError === 'function') {
          showToastError(errorMsg);
        } else {
          alert(errorMsg);
        }
        $btn.prop('disabled', false).text(originalText);
      });
  }
  /**
   * Affichage des erreurs avec toast Bootstrap
   */
  function showToastError(message) {
    console.warn('🚨 Toast error:', message);

    const toastEl = document.getElementById('toast-error');
    if (toastEl) {
      toastEl.querySelector('.toast-body').textContent = message;
      const toast = new bootstrap.Toast(toastEl);
      toast.show();
    } else {
      // Fallback si pas de toast
      alert(message);
    }
  }


  // Exposition globale
  window.sendToCart = sendToCart;
  window.showToastError = showToastError;
  window.updatePrices = updatePrices;
  window.addAdresseToSession = addAdresseToSession;
  window.updateRecapitulatif = updateRecapitulatif;
  window.updateRecapTotals = updateRecapTotals;
  window.updateSidebarProduitsRecap = updateSidebarProduitsRecap;
  window.updateSidebarTotauxRecap = updateSidebarTotauxRecap;
  window.updateFraisTotal = updateFraisTotal;
  window.getConfigForStep5 = getConfigForStep5;
  window.generateStep5Content = generateStep5Content;
  window.generateAdresseBlock = generateAdresseBlock;
  window.generateFraisItem = generateFraisItem;
  window.syncFraisToSession = syncFraisToSession;
  window.escapeHtml = escapeHtml;

});