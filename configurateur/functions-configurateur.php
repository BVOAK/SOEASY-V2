<?php
/**
 * Fonctions utilitaires pour le configurateur SoEasy
 */


/**
 * Gestion des sessions WooCommerce
 */
function soeasy_start_session_if_needed()
{
    if (!class_exists('WooCommerce'))
        return;
    if (!WC()->session || !WC()->session->has_session()) {
        WC()->session = WC()->session ?: WC_Session_Handler::instance();
        WC()->session->init();
        WC()->session->set_customer_session_cookie(true);
    }
}

function soeasy_session_set($key, $value)
{
    soeasy_start_session_if_needed();
    if (WC()->session)
        WC()->session->set($key, $value);
}

function soeasy_session_get($key, $default = null)
{
    soeasy_start_session_if_needed();
    return WC()->session ? WC()->session->get($key, $default) : $default;
}

function soeasy_session_delete($key)
{
    soeasy_start_session_if_needed();
    if (WC()->session)
        WC()->session->__unset($key);
}

/**
 * Fonctions générales
 */
function soeasy_get_adresses_configurateur() {
    $adresses = soeasy_session_get('soeasy_config_adresses', []);
    
    // ✅ NOUVEAU : Auto-enrichissement si pas déjà fait
    $enriched = [];
    foreach ($adresses as $adresse) {
        if (is_array($adresse) && isset($adresse['ville_courte'])) {
            // Déjà enrichie
            $enriched[] = $adresse;
        } else {
            // À enrichir
            $adresse_text = is_array($adresse) ? $adresse['adresse'] : $adresse;
            $enriched[] = [
                'adresse' => $adresse_text,
                'services' => is_array($adresse) ? ($adresse['services'] ?? []) : [],
                'ville_courte' => soeasy_get_ville_courte($adresse_text),
                'ville_longue' => soeasy_get_ville_longue($adresse_text)
            ];
        }
    }
    
    return $enriched;
}

function soeasy_add_adresse_configurateur($adresse, $services = []) {
    $adresses = soeasy_session_get('soeasy_config_adresses', []);
    
    // ✅ NOUVEAU : Enrichir directement lors de l'ajout
    $nouvelle_adresse = [
        'adresse' => $adresse,
        'services' => $services,
        'ville_courte' => soeasy_get_ville_courte($adresse),
        'ville_longue' => soeasy_get_ville_longue($adresse)
    ];
    
    $adresses[] = $nouvelle_adresse;
    soeasy_session_set('soeasy_config_adresses', $adresses);
    
    return $adresses;
}

// Helper pour page-configurateur.php
function soeasy_get_adresses_json_enriched() {
    $adresses = soeasy_get_adresses_configurateur();
    return json_encode($adresses);
}

function soeasy_get_selected_services($index)
{
    $adresses = soeasy_get_adresses_configurateur();
    return $adresses[$index]['services'] ?? [];
}

function soeasy_get_selected_duree_engagement()
{
    return soeasy_session_get('soeasy_duree_engagement');
}

function soeasy_get_selected_financement()
{
    return soeasy_session_get('soeasy_mode_financement', 'comptant');
}

function soeasy_get_leasing_price($product_id, $duree)
{
    return get_field('prix_leasing_' . $duree, $product_id);
}

function soeasy_add_product_to_cart($product_id, $quantity = 1, $meta = [])
{
    WC()->cart->add_to_cart($product_id, $quantity, 0, [], $meta);
}

function soeasy_get_config_produits()
{
    $config = WC()->session->get('soeasy_configurateur', []);
    $resultat = [];

    foreach ($config as $index => $data) {
        $resultat[$index] = [];

        foreach (['abonnements', 'materiels', 'fraisInstallation'] as $cle) {
            if (!isset($data[$cle]))
                continue;

            foreach ($data[$cle] as $item) {
                $id = isset($item['id']) ? $item['id'] : null;
                if (!$id)
                    continue;

                $type = $item['type'] ?? 'inconnu';

                if (!isset($resultat[$index][$type])) {
                    $resultat[$index][$type] = [];
                }

                $resultat[$index][$type][] = [
                    'id' => $id,
                    'type' => $type
                ];
            }
        }
    }

    return $resultat;
}

function soeasy_set_session_items($key, $index, $items)
{
    if ($index < 0 || !is_array($items))
        wp_send_json_error('Paramètres invalides');
    $session_data = soeasy_session_get($key, []);
    $session_data[$index] = array_map(fn($item) => ['id' => intval($item['id']), 'qty' => intval($item['qty'])], $items);
    soeasy_session_set($key, $session_data);
    wp_send_json_success('Données enregistrées');
}


/**
 * Fonction utilitaire de vérification des nonces
 */
function soeasy_verify_nonce($nonce_value, $nonce_action)
{
    if (!wp_verify_nonce($nonce_value, $nonce_action)) {
        wp_send_json_error('Sécurité : nonce invalide');
        exit;
    }
}


// ============================================================================
// CONFIGURATION GÉNÉRALE
// ============================================================================

function soeasy_set_engagement()
{
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');

    // LOGIQUE EXISTANTE INCHANGÉE
    soeasy_session_set('soeasy_duree_engagement', intval($_POST['duree'] ?? 0));
    wp_send_json_success('Engagement enregistré');
}
add_action('wp_ajax_soeasy_set_engagement', 'soeasy_set_engagement');
add_action('wp_ajax_nopriv_soeasy_set_engagement', 'soeasy_set_engagement');
function soeasy_set_financement()
{
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');

    // LOGIQUE EXISTANTE INCHANGÉE
    soeasy_session_set('soeasy_mode_financement', sanitize_text_field($_POST['mode'] ?? ''));
    wp_send_json_success('Mode financement enregistré');
}
add_action('wp_ajax_soeasy_set_financement', 'soeasy_set_financement');
add_action('wp_ajax_nopriv_soeasy_set_financement', 'soeasy_set_financement');


// ============================================================================
// CATÉGORIE 1 - GESTION DES ADRESSES (SÉCURISÉES)
// ============================================================================

function ajax_soeasy_add_adresse_configurateur()
{

    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_address_action');

    $adresse = $_POST['adresse'] ?? '';
    $services = $_POST['services'] ?? [];
    if (!$adresse /* || empty($services) */)
        wp_send_json_error('Adresse ou services manquants');
    $updated = soeasy_add_adresse_configurateur($adresse, $services);

    $enriched_addresses = [];
    foreach ($updated as $i => $adr) {
        $enriched_addresses[] = [
            'adresse' => $adr['adresse'],
            'services' => $adr['services'],
            'ville_courte' => soeasy_get_ville_courte($adr['adresse']),
            'ville_longue' => soeasy_get_ville_longue($adr['adresse'])
        ];
    }

    ob_start();
    foreach ($updated as $i => $adr) {
        echo '<li class="list-group-item d-flex justify-content-between align-items-center p-3 mb-1">';
        //echo '<span>' . esc_html($adr['adresse']) . ' — <em>Services : ' . implode(', ', $adr['services']) . '</em></span>';
        echo '<span>' . esc_html($adr['adresse']) . '</span>';
        echo '<button class="btn btn-sm btn-remove-adresse" data-index="' . $i . '"><i class="fa-solid fa-circle-xmark"></i></button>';
        echo '</li>';
    }

    wp_send_json_success([
        'html' => ob_get_clean(),
        'addresses_enriched' => $enriched_addresses // ✅ NOUVEAU : Pour le localStorage
    ]);
}

function ajax_soeasy_remove_adresse_configurateur()
{

    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_address_action');

    $index = intval($_POST['index'] ?? -1);
    $adresses = soeasy_get_adresses_configurateur();
    if ($index >= 0 && isset($adresses[$index])) {
        unset($adresses[$index]);
        soeasy_session_set('soeasy_config_adresses', array_values($adresses));
        wp_send_json_success($adresses);
    }
    wp_send_json_error('Adresse non trouvée');
}

function soeasy_extraire_ville($adresse_complete, $max_length = 25) {
    if (empty($adresse_complete)) {
        return 'Adresse';
    }

    // Nettoyer l'adresse
    $adresse_complete = trim($adresse_complete);
    
    // Séparer par les virgules
    $parties = array_map('trim', explode(',', $adresse_complete));
    
    if (count($parties) >= 3) {
        // Format "Rue, Ville, Pays" → prendre la ville (avant-dernière partie)
        $ville = $parties[count($parties) - 2];
    } elseif (count($parties) === 2) {
        // Format "Rue, Ville" → prendre la ville (dernière partie)
        $ville = $parties[count($parties) - 1];
    } else {
        // Pas de virgule, essayer d'extraire intelligemment
        $ville = soeasy_extraire_ville_intelligente($adresse_complete);
    }
    
    // Nettoyer le résultat
    $ville = trim($ville);
    
    // Si trop long, tronquer avec des points de suspension
    if (strlen($ville) > $max_length) {
        $ville = substr($ville, 0, $max_length - 3) . '...';
    }
    
    // Si vide après nettoyage, fallback
    if (empty($ville)) {
        $ville = strlen($adresse_complete) > $max_length 
            ? substr($adresse_complete, 0, $max_length - 3) . '...'
            : $adresse_complete;
    }
    
    return $ville;
}

/**
 * Extraction intelligente de ville sans virgules
 * Cherche des patterns typiques d'adresses françaises
 */
function soeasy_extraire_ville_intelligente($adresse) {
    // Patterns typiques à exclure (début d'adresse)
    $patterns_rue = [
        '/^\d+[a-z]?\s+/', // Numéro de rue
        '/^(rue|avenue|boulevard|place|square|impasse|allée|chemin|route)\s+/i',
        '/^(bis|ter|quater)\s+/i'
    ];
    
    $adresse_sans_rue = $adresse;
    
    // Supprimer les patterns de rue du début
    foreach ($patterns_rue as $pattern) {
        $adresse_sans_rue = preg_replace($pattern, '', $adresse_sans_rue);
    }
    
    $adresse_sans_rue = trim($adresse_sans_rue);
    
    // Si on a réussi à isoler quelque chose, le retourner
    if (!empty($adresse_sans_rue) && $adresse_sans_rue !== $adresse) {
        return $adresse_sans_rue;
    }
    
    // Fallback : prendre les derniers mots (supposés être la ville)
    $mots = explode(' ', $adresse);
    if (count($mots) > 2) {
        // Prendre les 2 derniers mots maximum pour la ville
        return implode(' ', array_slice($mots, -2));
    }
    
    return $adresse;
}

/**
 * Fonction helper pour obtenir la ville d'une adresse par son index
 * 
 * @param int $index Index de l'adresse dans le tableau des adresses
 * @return string Le nom de la ville
 */
function soeasy_get_ville_par_index($index) {
    $adresses = soeasy_get_adresses_configurateur();
    
    if (isset($adresses[$index]['adresse'])) {
        return soeasy_extraire_ville($adresses[$index]['adresse']);
    }
    
    return "Adresse " . ($index + 1);
}

/**
 * Version courte pour les onglets (max 20 caractères)
 */
function soeasy_get_ville_courte($adresse_complete) {
    return soeasy_extraire_ville($adresse_complete, 20);
}

/**
 * Version longue pour les accordéons (max 35 caractères)
 */
function soeasy_get_ville_longue($adresse_complete) {
    return soeasy_extraire_ville($adresse_complete, 35);
}

// Tests unitaires (à supprimer en production)
function soeasy_test_extraction_ville() {
    $tests = [
        "12 rue Voltaire, Paris, France" => "Paris",
        "Avenue des Champs-Élysées, Paris" => "Paris",
        "Lyon" => "Lyon",
        "123 Boulevard Saint-Germain, 75006 Paris, France" => "75006 Paris",
        "Marseille, France" => "Marseille",
        "Une très très longue adresse qui dépasse la limite" => "Une très très long...",
        "" => "Adresse"
    ];
    
    foreach ($tests as $input => $expected) {
        $result = soeasy_extraire_ville($input);
        echo "Input: '$input' => Result: '$result' (Expected: '$expected')\n";
    }
}

/**
 * Génère les options d'adresses pour un select
 */
function soeasy_get_adresses_options() {
    $adresses = soeasy_get_adresses_configurateur();
    $options = [];
    
    foreach ($adresses as $i => $adresse) {
        $ville = soeasy_get_ville_courte($adresse['adresse']);
        $options[] = [
            'value' => $i,
            'text' => $ville,
            'title' => $adresse['adresse'] // Pour tooltip
        ];
    }
    
    return $options;
}

// Pour les meta données (génération JSON côté PHP)
function soeasy_get_adresses_json_for_js() {
    $adresses = soeasy_get_adresses_configurateur();
    $adresses_js = [];
    
    foreach ($adresses as $i => $adresse) {
        $adresses_js[] = [
            'index' => $i,
            'adresse_complete' => $adresse['adresse'],
            'ville_courte' => soeasy_get_ville_courte($adresse['adresse']),
            'ville_longue' => soeasy_get_ville_longue($adresse['adresse'])
        ];
    }
    
    return json_encode($adresses_js);
}

/**
 * Enregistrement des hooks AJAX
 */
add_action('wp_ajax_soeasy_add_adresse_configurateur', 'ajax_soeasy_add_adresse_configurateur');
add_action('wp_ajax_nopriv_soeasy_add_adresse_configurateur', 'ajax_soeasy_add_adresse_configurateur');
add_action('wp_ajax_soeasy_remove_adresse_configurateur', 'ajax_soeasy_remove_adresse_configurateur');
add_action('wp_ajax_nopriv_soeasy_remove_adresse_configurateur', 'ajax_soeasy_remove_adresse_configurateur');


// ============================================================================
// CATÉGORIE 2 - ÉTAPE 2 INTERNET (SÉCURISÉES)
// ============================================================================
function soeasy_set_forfait_internet()
{

    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');

    $index = intval($_POST['index'] ?? -1);
    $product_id = intval($_POST['product_id'] ?? 0);
    if ($index < 0 || $product_id <= 0) {
        wp_send_json_error('Paramètres invalides');
    }

    $session_key = 'config_internet_' . $index;
    WC()->session->set($session_key, $product_id);

    wp_send_json_success("Forfait Internet $product_id enregistré pour index $index.");
}
add_action('wp_ajax_soeasy_set_forfait_internet', 'soeasy_set_forfait_internet');
add_action('wp_ajax_nopriv_soeasy_set_forfait_internet', 'soeasy_set_forfait_internet');

// Enregistre les équipements Internet sélectionnés
function soeasy_set_equipements_internet()
{

    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');

    $index = intval($_POST['index'] ?? -1);
    $product_ids = array_map('intval', $_POST['product_ids'] ?? []);
    if ($index < 0) {
        wp_send_json_error('Index invalide');
    }

    $session_key = 'equipements_internet_' . $index;
    WC()->session->set($session_key, $product_ids);

    wp_send_json_success("Équipements enregistrés pour index $index.");
}
add_action('wp_ajax_soeasy_set_equipements_internet', 'soeasy_set_equipements_internet');
add_action('wp_ajax_nopriv_soeasy_set_equipements_internet', 'soeasy_set_equipements_internet');


// ============================================================================
// CATÉGORIE 3 - ÉTAPE 3 MOBILE (SÉCURISÉES)
// ============================================================================

function soeasy_set_forfaits_mobile()
{
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    soeasy_set_session_items('soeasy_forfaits_mobile', intval($_POST['index'] ?? -1), $_POST['items'] ?? []);
}
add_action('wp_ajax_soeasy_set_forfaits_mobile', 'soeasy_set_forfaits_mobile');
add_action('wp_ajax_nopriv_soeasy_set_forfaits_mobile', 'soeasy_set_forfaits_mobile');

function soeasy_set_forfaits_data()
{
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    soeasy_set_session_items('soeasy_forfaits_data', intval($_POST['index'] ?? -1), $_POST['items'] ?? []);
}
add_action('wp_ajax_soeasy_set_forfaits_data', 'soeasy_set_forfaits_data');
add_action('wp_ajax_nopriv_soeasy_set_forfaits_data', 'soeasy_set_forfaits_data');

function soeasy_set_equipements_mobile()
{
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    soeasy_set_session_items('soeasy_equipements_mobile', intval($_POST['index'] ?? -1), $_POST['items'] ?? []);
}
add_action('wp_ajax_soeasy_set_equipements_mobile', 'soeasy_set_equipements_mobile');
add_action('wp_ajax_nopriv_soeasy_set_equipements_mobile', 'soeasy_set_equipements_mobile');



// ============================================================================
// CATÉGORIE 4 - ÉTAPE 4 CENTREX (SÉCURISÉES)
// ============================================================================

foreach (['licences', 'services', 'postes', 'switchs', 'accessoires'] as $type) {
    $function_name = "soeasy_set_{$type}_centrex";

    if (!function_exists($function_name)) {
        eval ("
        function {$function_name}() {
            soeasy_verify_nonce(\$_POST['nonce'] ?? '', 'soeasy_config_action');
            soeasy_set_session_items('soeasy_{$type}_centrex', intval(\$_POST['index'] ?? -1), \$_POST['items'] ?? []);
        }
        ");
    }

    add_action("wp_ajax_{$function_name}", $function_name);
    add_action("wp_ajax_nopriv_{$function_name}", $function_name);
}


// ============================================================================
// CATÉGORIE 5 - ÉTAPE 5 FRAIS D'INSTALLATION (SÉCURISÉES)
// ============================================================================

function soeasy_set_frais_installation()
{

    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');

    if (!isset($_POST['index']) || !isset($_POST['items']) || !is_array($_POST['items'])) {
        wp_send_json_error('Paramètres manquants.');
        return;
    }

    $index = sanitize_text_field($_POST['index']);
    $items = $_POST['items'];

    $config = WC()->session->get('soeasy_configurateur', []);

    // Initialiser l'index si besoin
    if (!isset($config[$index])) {
        $config[$index] = [];
    }

    // Injecter les frais avec validation
    $config[$index]['fraisInstallation'] = array_map(function ($item) {
        return [
            'id' => intval($item['id']),
            'nom' => sanitize_text_field($item['nom'] ?? ''),
            'quantite' => intval($item['quantite'] ?? 1),
            'type' => sanitize_text_field($item['type'] ?? 'internet'),
            'prixComptant' => floatval($item['prixComptant'] ?? 0),
            'prixLeasing24' => floatval($item['prixLeasing24'] ?? 0),
            'prixLeasing36' => floatval($item['prixLeasing36'] ?? 0),
            'prixLeasing48' => floatval($item['prixLeasing48'] ?? 0),
            'prixLeasing63' => floatval($item['prixLeasing63'] ?? 0),
        ];
    }, $items);

    // Sauvegarder en session
    WC()->session->set('soeasy_configurateur', $config);

    // Log pour debug
    error_log("Frais installation sauvegardés pour index {$index}: " . print_r($config[$index]['fraisInstallation'], true));

    wp_send_json_success([
        'message' => 'Frais d\'installation mis à jour avec succès',
        'config' => $config[$index],
        'count' => count($config[$index]['fraisInstallation'])
    ]);
}
add_action('wp_ajax_soeasy_set_frais_installation', 'soeasy_set_frais_installation');
add_action('wp_ajax_nopriv_soeasy_set_frais_installation', 'soeasy_set_frais_installation');

function soeasy_set_config_part()
{

    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');

    $index = sanitize_text_field($_POST['index']);
    $key = sanitize_text_field($_POST['key']);
    $items = $_POST['items'];

    if ($index === '' || $key === '') {
        wp_send_json_error('Paramètres manquants');
    }

    $config = WC()->session->get('soeasy_configurateur', []);
    if (!isset($config[$index])) {
        $config[$index] = [];
    }

    $config[$index][$key] = $items;
    WC()->session->set('soeasy_configurateur', $config);
    wp_send_json_success('Mise à jour OK');
}

add_action('wp_ajax_soeasy_set_config_part', 'soeasy_set_config_part');
add_action('wp_ajax_nopriv_soeasy_set_config_part', 'soeasy_set_config_part');



// ============================================================================
// CATÉGORIE 6 - PANIER ET COMMANDE (SÉCURISÉES) - CRITIQUES
// ============================================================================

function soeasy_resolve_product($input)
{
    if ($input instanceof WC_Product) {
        return $input;
    }
    if ($input instanceof WP_Post) {
        return wc_get_product($input->ID);
    }
    if (is_numeric($input)) {
        return wc_get_product(intval($input));
    }
    return null;
}

// Ajout au panier
function soeasy_ajouter_au_panier()
{

    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_cart_action');

    if (!function_exists('WC')) {
        wp_send_json_error(['message' => 'WooCommerce non disponible']);
    }

    WC()->cart->empty_cart();

    $adresse_index = intval($_POST['index'] ?? 0);

    // Mapping clair des types par étape
    $config_map = [
        'step2' => [
            'soeasy_forfaits_internet',
            'soeasy_equipements_internet',
        ],
        'step3' => [
            'soeasy_forfaits_mobile',
            'soeasy_forfaits_data',
            'soeasy_equipements_mobile',
        ],
        'step4' => [
            'soeasy_licences_centrex',
            'soeasy_services_centrex',
            'soeasy_postes_centrex',
            'soeasy_switchs_centrex',
            'soeasy_accessoires_centrex',
        ]
    ];

    // Ajout au panier pour chaque type de produit
    foreach ($config_map as $step => $types) {
        foreach ($types as $type) {
            $produits = soeasy_session_get($type, []);
            foreach ($produits[$adresse_index] ?? [] as $item) {
                if (!empty($item['id']) && !empty($item['qty'])) {
                    WC()->cart->add_to_cart($item['id'], $item['qty']);
                }
            }
        }
    }

    $fake_product_id = wc_get_product_id_by_sku('soeasy-config-preview');
    if ($fake_product_id) {
        WC()->cart->add_to_cart($fake_product_id);
    }

    wp_send_json_success(['redirect' => wc_get_checkout_url()]);
    wp_send_json_error(['message' => 'Votre configuration est vide.']);


    error_log(print_r([
        'internet' => soeasy_session_get('soeasy_forfaits_internet'),
        'centrex' => soeasy_session_get('soeasy_licences_centrex'),
        'mobile' => soeasy_session_get('soeasy_forfaits_mobile'),
        'frais' => soeasy_session_get('soeasy_frais_installation'),
    ], true));
}

add_action('wp_ajax_soeasy_ajouter_au_panier', 'soeasy_ajouter_au_panier');
add_action('wp_ajax_nopriv_soeasy_ajouter_au_panier', 'soeasy_ajouter_au_panier');

/**
 * Ajout au panier pour configuration multi-adresses
 */
function soeasy_ajouter_au_panier_multi()
{

    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_cart_action');

    if (!function_exists('WC')) {
        wp_send_json_error(['message' => 'WooCommerce non disponible']);
        return;
    }

    // 1. Récupération des données
    $config = $_POST['config'] ?? [];
    $adresses = $_POST['adresses'] ?? [];

    if (empty($config)) {
        wp_send_json_error(['message' => 'Configuration vide']);
        return;
    }

    // 2. Vider le panier existant
    WC()->cart->empty_cart();

    // 3. Compteurs pour debug
    $produits_ajoutes = 0;
    $erreurs = [];

    // 4. Traitement de chaque adresse
    foreach ($config as $adresse_index => $data_adresse) {

        $nom_adresse = $adresses[$adresse_index]['adresse'] ?? "Adresse #" . ($adresse_index + 1);

        // 4a. Abonnements
        if (!empty($data_adresse['abonnements'])) {
            foreach ($data_adresse['abonnements'] as $produit) {
                $resultat = ajouter_produit_au_panier($produit, $nom_adresse, 'Abonnement');
                if ($resultat['success']) {
                    $produits_ajoutes++;
                } else {
                    $erreurs[] = $resultat['error'];
                }
            }
        }

        // 4b. Matériels
        if (!empty($data_adresse['materiels'])) {
            foreach ($data_adresse['materiels'] as $produit) {
                $resultat = ajouter_produit_au_panier($produit, $nom_adresse, 'Matériel');
                if ($resultat['success']) {
                    $produits_ajoutes++;
                } else {
                    $erreurs[] = $resultat['error'];
                }
            }
        }

        // 4c. Frais d'installation
        if (!empty($data_adresse['fraisInstallation'])) {
            foreach ($data_adresse['fraisInstallation'] as $frais) {
                $resultat = ajouter_produit_au_panier($frais, $nom_adresse, 'Frais d\'installation');
                if ($resultat['success']) {
                    $produits_ajoutes++;
                } else {
                    $erreurs[] = $resultat['error'];
                }
            }
        }
    }

    // 5. Résultat final
    if ($produits_ajoutes > 0) {

        WC()->cart->calculate_totals();

        // Sauvegarder la config dans la session pour le checkout
        WC()->session->set('soeasy_configuration_complete', [
            'config' => $config,
            'adresses' => $adresses,
            'timestamp' => current_time('timestamp')
        ]);

        $message = sprintf(
            '%d produit%s ajouté%s au panier',
            $produits_ajoutes,
            $produits_ajoutes > 1 ? 's' : '',
            $produits_ajoutes > 1 ? 's' : ''
        );

        if (!empty($erreurs)) {
            $message .= '. Quelques erreurs : ' . implode(', ', array_slice($erreurs, 0, 3));
        }

        wp_send_json_success([
            'message' => $message,
            'produits_ajoutes' => $produits_ajoutes,
            'erreurs' => $erreurs,
            'redirect_url' => wc_get_cart_url()
        ]);

    } else {
        wp_send_json_error([
            'message' => 'Aucun produit n\'a pu être ajouté au panier.',
            'erreurs' => $erreurs
        ]);
    }
}

add_action('wp_ajax_soeasy_ajouter_au_panier_multi', 'soeasy_ajouter_au_panier_multi');
add_action('wp_ajax_nopriv_soeasy_ajouter_au_panier_multi', 'soeasy_ajouter_au_panier_multi');


/**
 * Fonction helper pour ajouter un produit au panier WC
 */
function ajouter_produit_au_panier($produit_data, $nom_adresse, $categorie)
{

    if (empty($produit_data['id']) || empty($produit_data['quantite'])) {
        return [
            'success' => false,
            'error' => "Données produit invalides (ID: {$produit_data['id']}, Qty: {$produit_data['quantite']})"
        ];
    }

    $product_id = intval($produit_data['id']);
    $quantity = intval($produit_data['quantite']);

    // Vérifier que le produit existe
    $product = wc_get_product($product_id);
    if (!$product) {
        return [
            'success' => false,
            'error' => "Produit #{$product_id} introuvable"
        ];
    }

    // Métadonnées pour identifier la configuration dans le panier
    $cart_item_data = [
        'soeasy_adresse' => $nom_adresse,
        'soeasy_categorie' => $categorie,
        'soeasy_config_id' => uniqid('soeasy_')
    ];

    // Si prix custom (pour gestion engagement/leasing)
    if (!empty($produit_data['prixUnitaire'])) {
        $cart_item_data['soeasy_prix_custom'] = floatval($produit_data['prixUnitaire']);
        error_log("💰 Prix custom défini: {$cart_item_data['soeasy_prix_custom']}€ pour produit #{$product_id}");
    } else {
        error_log("⚠️ Aucun prix custom pour produit #{$product_id}");
    }

    // === GESTION DES PRODUITS VARIABLES ===
    $variation_id = 0;
    $variation_attributes = array();

    // ✅ VÉRIFICATION : Le produit est-il variable ?
    if ($product->is_type('variable')) {

        // ✅ RÉCUPÉRATION UNIQUE des variations disponibles
        /** @var WC_Product_Variable $product */
        $available_variations = $product->get_available_variations('array');

        if (empty($available_variations)) {
            error_log("SoEasy: ⚠️ Produit #{$product_id} est variable mais n'a aucune variation disponible");
            return [
                'success' => false,
                'error' => "Produit #{$product_id} : aucune variation disponible"
            ];
        }

        error_log("SoEasy: 🔍 Produit #{$product_id} - " . count($available_variations) . " variations disponibles");

        // PRIORITÉ 1 : Utiliser les attributs envoyés par le JS
        if (!empty($produit_data['attributes']) && is_array($produit_data['attributes'])) {
            $sent_attributes = $produit_data['attributes'];

            error_log("SoEasy: 🎯 Recherche avec attributes JS: " . print_r($sent_attributes, true));

            foreach ($available_variations as $variation_data) {
                $matches = true;

                error_log("  🔄 Test variation #{$variation_data['variation_id']}");

                // Vérifier si tous les attributs correspondent
                foreach ($sent_attributes as $attr_key => $attr_value) {
                    // Normaliser le nom de l'attribut (ajouter 'attribute_' si absent)
                    $full_attr_name = (strpos($attr_key, 'attribute_') === 0) ? $attr_key : 'attribute_' . $attr_key;

                    error_log("    - Comparaison: JS envoie '{$attr_key}' => '{$attr_value}'");
                    error_log("    - Normalisé en: '{$full_attr_name}'");
                    error_log("    - Attributs dispo dans variation: " . print_r(array_keys($variation_data['attributes']), true));

                    if (isset($variation_data['attributes'][$full_attr_name])) {
                        $variation_attr_value = $variation_data['attributes'][$full_attr_name];

                        error_log("    - Valeur WC: '{$variation_attr_value}' vs JS: '{$attr_value}'");

                        // Comparaison flexible : valeur vide = "any"
                        if ($variation_attr_value !== '' && $variation_attr_value != $attr_value) {
                            $matches = false;
                            error_log("    ❌ Pas de match !");
                            break;
                        } else {
                            error_log("    ✅ Match OK");
                        }
                    } else {
                        error_log("    ⚠️ Attribut '{$full_attr_name}' pas trouvé dans cette variation");
                    }

                }

                if ($matches) {
                    $variation_id = $variation_data['variation_id'];

                    // Construire les attributs pour WooCommerce
                    foreach ($sent_attributes as $attr_key => $attr_value) {
                        $full_attr_name = (strpos($attr_key, 'attribute_') === 0) ? $attr_key : 'attribute_' . $attr_key;
                        $variation_attributes[$full_attr_name] = $attr_value;
                    }

                    error_log("SoEasy: ✅ Variation trouvée via JS attributes : #{$variation_id}");
                    break;
                }
            }
        } else {
            error_log("SoEasy: ⚠️ Aucun attributes JS fourni pour produit #{$product_id}");
        }

        // PRIORITÉ 2 : Fallback sur les paramètres globaux en session
        if (!$variation_id) {
            error_log("SoEasy: 🔄 Fallback sur paramètres session");

            $duree_engagement = soeasy_get_selected_duree_engagement() ?: '24';
            $mode_financement = soeasy_get_selected_financement() ?: 'comptant';

            error_log("SoEasy: Session - engagement={$duree_engagement}, financement={$mode_financement}");

            foreach ($available_variations as $variation_data) {
                $variation_attributes_to_test = array();

                foreach ($variation_data['attributes'] as $attr_name => $attr_value) {
                    $clean_attr_name = str_replace('attribute_', '', $attr_name);

                    switch ($clean_attr_name) {
                        case 'pa_duree-engagement':
                        case 'pa_engagement':
                        case 'duree_engagement':
                            $variation_attributes_to_test[$attr_name] = $duree_engagement;
                            break;

                        case 'pa_financement':
                        case 'pa_mode-financement':
                        case 'mode_financement':
                            $variation_attributes_to_test[$attr_name] = $mode_financement;
                            break;

                        default:
                            if (!empty($attr_value)) {
                                $variation_attributes_to_test[$attr_name] = $attr_value;
                            }
                            break;
                    }
                }

                $matches = true;
                foreach ($variation_attributes_to_test as $attr_name => $attr_value) {
                    if (
                        isset($variation_data['attributes'][$attr_name]) &&
                        $variation_data['attributes'][$attr_name] !== '' &&
                        $variation_data['attributes'][$attr_name] !== $attr_value
                    ) {
                        $matches = false;
                        break;
                    }
                }

                if ($matches) {
                    $variation_id = $variation_data['variation_id'];
                    $variation_attributes = $variation_attributes_to_test;

                    // Récupérer le prix de la variation si pas de prix custom
                    if (empty($produit_data['prixUnitaire'])) {
                        $variation = wc_get_product($variation_id);
                        if ($variation) {
                            $variation_price = $variation->get_price();
                            if ($variation_price) {
                                $cart_item_data['soeasy_prix_custom'] = floatval($variation_price);
                            }
                        }
                    }

                    error_log("SoEasy: ✅ Variation trouvée via session : #{$variation_id}");
                    break;
                }
            }
        }

        // PRIORITÉ 3 : Dernière chance - première variation disponible
        if (!$variation_id) {
            $first_variation = reset($available_variations);
            $variation_id = $first_variation['variation_id'];
            $variation_attributes = $first_variation['attributes'];

            error_log("SoEasy: ⚠️ Aucune variation exacte trouvée, utilisation de la première disponible #{$variation_id}");
        }
    }
    // Si produit simple, pas besoin de variation
    else {
        error_log("SoEasy: 📦 Produit #{$product_id} est un produit simple");
    }

    // === AJOUT AU PANIER ===
    try {
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            $quantity,
            $variation_id,
            $variation_attributes,
            $cart_item_data
        );

        if ($cart_item_key) {

            // ✅ CORRECTION : Utiliser une référence pour modifier directement
            if (!empty($cart_item_data['soeasy_prix_custom'])) {
                $cart = WC()->cart->get_cart();

                // Utiliser une référence avec &
                foreach ($cart as $key => &$cart_item) {
                    if ($key === $cart_item_key) {
                        $custom_price = floatval($cart_item_data['soeasy_prix_custom']);
                        $cart_item['data']->set_price($custom_price);
                        error_log("✅ Prix appliqué immédiatement: {$custom_price}€ pour produit #{$product_id}");
                        break;
                    }
                }
                unset($cart_item); // Important : détruire la référence après la boucle
            }

            $log_msg = "SoEasy: ✅ Ajouté au panier - Produit:{$product_id}";
            if ($variation_id) {
                $log_msg .= ", Variation:{$variation_id}";
            }
            error_log($log_msg);

            return [
                'success' => true,
                'cart_item_key' => $cart_item_key,
                'variation_id' => $variation_id
            ];
        } else {
            error_log("SoEasy: ❌ Échec ajout panier - Produit #{$product_id}");
            return [
                'success' => false,
                'error' => "Impossible d'ajouter le produit au panier"
            ];
        }

    } catch (Exception $e) {
        error_log("SoEasy: 💥 Exception - " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Hook pour afficher les métadonnées SoEasy dans le panier
 */
add_filter('woocommerce_get_item_data', 'soeasy_display_cart_item_data', 10, 2);

function soeasy_display_cart_item_data($cart_item_data, $cart_item)
{

    if (isset($cart_item['soeasy_adresse'])) {
        $cart_item_data[] = [
            'name' => 'Adresse de service',
            'value' => esc_html($cart_item['soeasy_adresse'])
        ];
    }

    if (isset($cart_item['soeasy_categorie'])) {
        $cart_item_data[] = [
            'name' => 'Type',
            'value' => esc_html($cart_item['soeasy_categorie'])
        ];
    }

    return $cart_item_data;
}

/**
 * Hook pour appliquer les prix custom dans le panier
 */
add_action('woocommerce_before_calculate_totals', 'soeasy_apply_custom_prices');

function soeasy_apply_custom_prices($cart)
{

    if (is_admin() && !defined('DOING_AJAX'))
        return;
    if (did_action('woocommerce_before_calculate_totals') >= 2)
        return;

    error_log("🔄 soeasy_apply_custom_prices() appelé");

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['soeasy_prix_custom'])) {
            $custom_price = floatval($cart_item['soeasy_prix_custom']);
            if ($custom_price > 0) {
                $cart_item['data']->set_price($custom_price);
                error_log("✅ Prix appliqué: {$custom_price}€ pour produit #{$cart_item['product_id']}");
            } else {
                error_log("⚠️ Prix custom = 0 pour produit #{$cart_item['product_id']}");
            }
        } else {
            error_log("ℹ️ Pas de prix custom pour produit #{$cart_item['product_id']}");
        }
    }
}


/**
 * Filtre pour afficher le prix custom dans le panier
 */
add_filter('woocommerce_cart_item_price', 'soeasy_display_custom_cart_price', 10, 3);

function soeasy_display_custom_cart_price($price_html, $cart_item, $cart_item_key) {
    
    // Si un prix custom existe, l'afficher
    if (isset($cart_item['soeasy_prix_custom'])) {
        $custom_price = floatval($cart_item['soeasy_prix_custom']);
        if ($custom_price > 0) {
            return wc_price($custom_price);
        }
    }
    
    // Sinon, retourner le prix normal
    return $price_html;
}

/**
 * Filtre pour afficher le sous-total custom dans le panier (prix × quantité)
 */
add_filter('woocommerce_cart_item_subtotal', 'soeasy_display_custom_cart_subtotal', 10, 3);

function soeasy_display_custom_cart_subtotal($subtotal_html, $cart_item, $cart_item_key) {
    
    // Si un prix custom existe, calculer le sous-total custom
    if (isset($cart_item['soeasy_prix_custom'])) {
        $custom_price = floatval($cart_item['soeasy_prix_custom']);
        $quantity = intval($cart_item['quantity']);
        
        if ($custom_price > 0) {
            $custom_subtotal = $custom_price * $quantity;
            return wc_price($custom_subtotal);
        }
    }
    
    // Sinon, retourner le sous-total normal
    return $subtotal_html;
}

?>