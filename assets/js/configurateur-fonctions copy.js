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


/**
 * Fonction centrale de récupération des adresses
 */
function getAdresseByIndex(index) {
  const adresses = JSON.parse(localStorage.getItem('soeasyAdresses')) || [];

  // Gestion du format : [{ adresse: '12 rue Voltaire, Paris', ... }]
  const adresseObj = adresses[index];
  if (adresseObj && typeof adresseObj === 'object') {
    if (adresseObj.adresse) return adresseObj.adresse;
    if (adresseObj.nom) return adresseObj.nom;
  }

  return `Adresse #${parseInt(index) + 1}`;
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
    const recapData = JSON.parse(localStorage.getItem('soeasyConfig')) || {};
    const mode = getSelectedFinancementMode();
    const engagement = getSelectedEngagement();

    Object.entries(recapData).forEach(([adresseId, data]) => {
      const $adresseBlock = jQuery(`#collapse-${adresseId}`);

      // — abonnements
      const abonnementsTbody = $adresseBlock.find('.recap-abonnements tbody');
      abonnementsTbody.empty();
      (data.abonnements || []).forEach(item => {
        abonnementsTbody.append(`
        <tr>
          <td>${item.nom} <small class="d-block">${item.description || ''}</small></td>
          <td>${item.quantite}</td>
          <td>${(item.prixUnitaire ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
          <td>${((item.prixUnitaire ?? 0) * item.quantite).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
        </tr>
      `);
      });

      // — matériels
      const materielsTbody = $adresseBlock.find('.recap-materiels tbody');
      materielsTbody.empty();
      (data.materiels || []).forEach(item => {
        materielsTbody.append(`
        <tr>
          <td>${item.nom} <small class="d-block">${item.description || ''}</small></td>
          <td>${item.quantite}</td>
          <td>${(item.prixUnitaire ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
          <td>${((item.prixUnitaire ?? 0) * item.quantite).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
        </tr>
      `);
      });

      // — frais d'installation (version propre)
      const fraisTbody = $adresseBlock.find('.recap-installations tbody');
      fraisTbody.empty();

      (data.fraisInstallation || []).forEach(item => {
        const qty = parseInt(item.quantite) || 0;
        let unit = 0;

        if (mode === 'leasing') {
          unit = parseFloat(
            item[`prixLeasing${engagement}`] ??
            item.prixLeasing24 ??
            item.prixLeasing36 ??
            item.prixLeasing48 ??
            item.prixLeasing63 ??
            0
          );
        } else {
          unit = parseFloat(item.prixComptant) || 0;
        }

        const total = unit * qty;
        const suffix = mode === 'leasing' ? ' € / mois' : ' €';

        fraisTbody.append(`
          <tr>
            <td>${item.nom}</td>
            <td>${qty}</td>
            <td>${unit.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${suffix}</td>
            <td>${total.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${suffix}</td>
          </tr>
        `);
      });

    });
  }


  /**
 * Fonction améliorée pour afficher les villes dans les onglets
 */
  function afficherVillesDansOnglets() {
    console.log('🏷️ Application des noms de villes dans les onglets');

    // Sélecteurs pour les onglets
    const selecteurs = [
      '.nav-tabs .nav-link[data-bs-target^="#tab-"]',
      '.nav-tabs .nav-link[href^="#tab-"]',
      '.nav-pills .nav-link[data-bs-target^="#tab-"]',
      '.nav-pills .nav-link[href^="#tab-"]'
    ];

    let ongletsTraites = 0;
    let $ongletsATtraiter = $();

    // 1. D'abord, identifier tous les onglets à traiter
    selecteurs.forEach(selecteur => {
      $(selecteur).each(function () {
        const $onglet = $(this);
        let target = $onglet.attr('data-bs-target') || $onglet.attr('href');
        if (!target) return;

        const index = parseInt(target.replace('#tab-', ''));
        if (isNaN(index)) return;

        $ongletsATtraiter = $ongletsATtraiter.add($onglet);
      });
    });

    if ($ongletsATtraiter.length === 0) {
      console.log('⚠️ Aucun onglet trouvé');
      return;
    }

    // 2. Masquer temporairement les onglets (opacity pour éviter le décalage)
    $ongletsATtraiter.css('opacity', '0');

    // 3. Traiter tous les onglets
    setTimeout(() => {
      $ongletsATtraiter.each(function () {
        const $onglet = $(this);
        let target = $onglet.attr('data-bs-target') || $onglet.attr('href');
        const index = parseInt(target.replace('#tab-', ''));

        const adresses = JSON.parse(localStorage.getItem('soeasyAdresses') || '[]');
        const adresseComplete = adresses[index]?.adresse || `Adresse ${index + 1}`;
        const ville = extraireVille(adresseComplete);

        $onglet.text(ville);
        ongletsTraites++;
      });

      // 4. Réafficher tous les onglets en même temps
      $ongletsATtraiter.css('opacity', '1');

      console.log(`✅ ${ongletsTraites} onglets traités sans flash`);
    }, 50);
  }

  /**
   * Fonction d'extraction de ville améliorée
   */
  function extraireVille(adresseComplete) {
    if (!adresseComplete) return 'Adresse';

    const parties = adresseComplete.split(',').map(p => p.trim());

    if (parties.length >= 3) {
      // "Rue, Ville, Pays" → prendre la ville (avant-dernière)
      return parties[parties.length - 2];
    } else if (parties.length === 2) {
      // "Rue, Ville" → prendre la ville (dernière)
      return parties[parties.length - 1];
    }

    // Fallback : prendre les 20 premiers caractères
    return adresseComplete.length > 20
      ? adresseComplete.substring(0, 20) + '...'
      : adresseComplete;
  }


  /**
   * Fonction pour afficher les villes dans la sidebar de récap
   */
  function afficherVillesDansSidebar() {
    console.log('🏷️ Application des noms de villes dans la sidebar');

    // Sélecteurs pour les accordéons de la sidebar
    const selecteurs = [
      '#config-recapitulatif .accordion-button',
      '#accordionSidebarRecap .accordion-button',
      '.accordion-header .accordion-button'
    ];

    let ongletsTraites = 0;
    let $elementsATtraiter = $();

    // 1. Identifier tous les éléments à traiter
    selecteurs.forEach(selecteur => {
      $(selecteur).each(function () {
        const $bouton = $(this);

        // Vérifier si c'est bien un bouton d'accordéon avec une adresse
        const texteActuel = $bouton.text().trim();
        if (texteActuel && texteActuel.length > 10) { // Filtre de base
          $elementsATtraiter = $elementsATtraiter.add($bouton);
        }
      });
    });

    if ($elementsATtraiter.length === 0) {
      console.log('⚠️ Aucun élément sidebar trouvé');
      return;
    }

    // 2. Masquer temporairement pour éviter le flash
    $elementsATtraiter.css('opacity', '0');

    // 3. Traiter tous les éléments
    setTimeout(() => {
      $elementsATtraiter.each(function () {
        const $bouton = $(this);
        const texteActuel = $bouton.text().trim();

        // Extraire l'index depuis les attributs data ou depuis l'ID
        let index = null;

        // Essayer de récupérer l'index depuis les attributs
        const target = $bouton.attr('data-bs-target') || $bouton.attr('aria-controls');
        if (target) {
          const match = target.match(/sidebar-collapse-(\d+)|accordion-(\d+)/);
          if (match) {
            index = parseInt(match[1] || match[2]);
          }
        }

        // Si on n'a pas trouvé l'index, essayer de le déduire depuis le texte ou la position
        if (index === null) {
          // Compter la position dans la liste des accordéons
          index = $elementsATtraiter.index($bouton);
        }

        // Récupérer l'adresse correspondante
        const adresses = JSON.parse(localStorage.getItem('soeasyAdresses') || '[]');
        const adresseComplete = adresses[index]?.adresse || texteActuel;

        // Extraire la ville
        const ville = extraireVille(adresseComplete);

        // Mettre à jour si différent
        if (texteActuel !== ville) {
          $bouton.text(ville);
          ongletsTraites++;
          console.log(`🏷️ Sidebar accordion ${index} mis à jour: "${ville}"`);
        }
      });

      // 4. Réafficher tous les éléments
      $elementsATtraiter.css('opacity', '1');

      console.log(`✅ ${ongletsTraites} éléments sidebar traités`);
    }, 50);
  }

  // Mise en forme du récap de la sidebar des produits
  function updateSidebarProduitsRecap() {
    const recapData = JSON.parse(localStorage.getItem('soeasyConfig')) || {};
    const adressesData = JSON.parse(localStorage.getItem('soeasyAdresses')) || {};
    const $container = $('#config-recapitulatif');
    $container.empty();

    const engagement = getSelectedEngagement();
    const mode = getSelectedFinancementMode();

    if (Object.keys(recapData).length === 0) {
      $container.append('<p>Aucune sélection pour le moment.</p>');
      return;
    }

    Object.entries(recapData).forEach(([index, config]) => {
      const adresse = adressesData[index]?.adresse || `Adresse #${parseInt(index) + 1}`;
      const abonnements = config.abonnements || [];
      const materiels = config.materiels || [];
      const frais = config.fraisInstallation || [];

      const $accordion = $(`
        <div class="accordion mb-3" id="accordion-${index}">
          <div class="accordion-item">
            <h2 class="accordion-header" id="sidebar-heading-${index}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#sidebar-collapse-${index}" aria-expanded="false" aria-controls="sidebar-collapse-${index}">
                ${adresse}
              </button>
            </h2>
            <div id="sidebar-collapse-${index}" class="accordion-collapse collapse" aria-labelledby="heading-${index}">
              <div class="accordion-body">
  
                <h6 class="mt-2">Abonnements</h6>
                <ul class="list-unstyled small recap-abonnements"></ul>
  
                <h6 class="mt-3">Équipements</h6>
                <ul class="list-unstyled small recap-materiels"></ul>

                <h6 class="mt-3">Frais d'installation</h6>
                <ul class="list-unstyled small recap-frais-installation"></ul>
  
              </div>
            </div>
          </div>
        </div>
      `);

      const $abonnements = $accordion.find('.recap-abonnements');
      abonnements.forEach(item => {
        const total = (item.prixUnitaire || 0) * item.quantite;
        $abonnements.append(`
          <li>${item.nom} × ${item.quantite} <span class="float-end">${total.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</span></li>
        `);
      });

      const $materiels = $accordion.find('.recap-materiels');
      materiels.forEach(item => {
        const total = (item.prixUnitaire || 0) * item.quantite;
        $materiels.append(`
          <li>${item.nom} × ${item.quantite} <span class="float-end">${total.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</span></li>
        `);
      });

      // Frais d'installation (SEUL le total)
      const $fraisList = $accordion.find('.recap-frais-installation');
      let totalFrais = 0;
      if (!Array.isArray(frais)) return;
      frais.forEach(item => {
        const unit = mode === 'leasing'
          ? (item[`prixLeasing${engagement}`] ?? item.prixLeasing0)
          : item.prixComptant;
        totalFrais += unit * item.quantite;
      });
      const suffix = mode === 'leasing' ? ' € / mois' : ' €';
      $fraisList.append(`
      <li>Total frais d'installation <span class="float-end">${totalFrais.toLocaleString('fr-FR', { minimumFractionDigits: 2 })}${suffix}</span></li>
     `);

      $container.append($accordion);

      setTimeout(() => {
        afficherVillesDansSidebar();
      }, 100);

      updateSidebarTotauxRecap()

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
        <div class="fw-bold mb-1">Abonnement + Leasing : ${totalMensuel.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € / mois</div>
        <div class="text-muted small">
        ou abonnements : ${totalAbonnement.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € / mois<br>
        + équipements : ${totalComptant.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
      </div>
      `);
    } else {
      $container.append(`
        <div class="fw-bold mb-1">Abonnements mensuels : ${totalAbonnement.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</div>
        <div class="fw-bold mb-1">Équipements : ${totalComptant.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</div>
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
    <div class="card item-list-product">
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
          <strong class="frais-total" data-index="${index}">0 €</strong>
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
    <li class="list-group-item d-flex justify-content-between align-items-center">
      <div class="form-check">
        <input class="form-check-input frais-checkbox" 
               type="checkbox" 
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
               checked>
        <label class="form-check-label">
          ${escapeHtml(frais.nom || 'Frais d\'installation')}
          ${quantite > 1 ? ` <span class="text-muted">(×${quantite})</span>` : ''}
        </label>
      </div>
      <span class="badge bg-primary fs-6" data-prix-affiche="${prixTotal}">
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
  window.updateSidebarProduitsRecap = updateSidebarProduitsRecap;
  window.updateSidebarTotauxRecap = updateSidebarTotauxRecap;
  window.updateFraisTotal = updateFraisTotal;
  window.getConfigForStep5 = getConfigForStep5;
  window.generateStep5Content = generateStep5Content;
  window.generateAdresseBlock = generateAdresseBlock;
  window.generateFraisItem = generateFraisItem;
  window.syncFraisToSession = syncFraisToSession;
  window.escapeHtml = escapeHtml;
  window.afficherVillesDansOnglets = afficherVillesDansOnglets;
  window.afficherVillesDansSidebar = afficherVillesDansSidebar;

});