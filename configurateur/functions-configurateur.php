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
 * FONCTION DE TEST : Forcer une adresse fictive en session
 * À SUPPRIMER après les tests
 */
function soeasy_force_test_address_in_session() {
    error_log('🧪 FORCING TEST ADDRESS IN SESSION');
    
    $test_address = [
        [
            'adresse' => 'TEST ADDRESS - 123 Rue de Test, Paris, France',
            'services' => [],
            'ville_courte' => 'TEST Paris',
            'ville_longue' => 'TEST Paris France'
        ]
    ];
    
    WC()->session->set('soeasy_config_adresses', $test_address);
    
    if (method_exists(WC()->session, 'save_data')) {
        WC()->session->save_data();
    }
    
    error_log('🧪 Test address forced, verifying...');
    $check = WC()->session->get('soeasy_config_adresses', []);
    error_log('🧪 Verification: ' . count($check) . ' address(es) in session');
}

// Hook au chargement de n'importe quelle page du configurateur
add_action('template_redirect', function() {
    // Seulement sur la page du configurateur
    if (is_page() && get_post_field('post_name') == 'configurateur') {
        if (function_exists('WC') && WC()->session && is_user_logged_in()) {
            soeasy_force_test_address_in_session();
        }
    }
});


/**
 * Fonctions générales
 */
function soeasy_get_adresses_configurateur() {
    error_log('=== GET ADRESSES CONFIGURATEUR ===');
    
    // Forcer l'initialisation de la session
    soeasy_start_session_if_needed();
    
    $adresses = soeasy_session_get('soeasy_config_adresses', []);
    error_log('Adresses lues depuis session: ' . print_r($adresses, true));
    error_log('Nombre d\'adresses: ' . count($adresses));
    
    // ✅ SI TEST ADDRESS présente, la retourner telle quelle
    if (count($adresses) > 0 && isset($adresses[0]['adresse']) && strpos($adresses[0]['adresse'], 'TEST ADDRESS') !== false) {
        error_log('🧪 TEST ADDRESS détectée, retour direct sans enrichissement');
        error_log('=== FIN GET ADRESSES (TEST MODE) ===');
        return $adresses;
    }
    
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
    
    error_log('Adresses enrichies retournées: ' . count($enriched));
    error_log('=== FIN GET ADRESSES ===');
    
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
 * Vérifier que les adresses sont bien présentes en session PHP
 * CRITIQUE : Résout le bug d'accès étape 2 en mode connecté
 */
function ajax_soeasy_verify_adresses_in_session() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_address_action');
    
    $adresses = soeasy_get_adresses_configurateur();
    $count = count($adresses);
    
    if ($count > 0) {
        wp_send_json_success([
            'has_addresses' => true,
            'count' => $count,
            'addresses' => $adresses
        ]);
    } else {
        wp_send_json_error([
            'has_addresses' => false,
            'count' => 0,
            'message' => 'Aucune adresse trouvée en session'
        ]);
    }
}

/**
 * Enregistrement des hooks AJAX
 */
add_action('wp_ajax_soeasy_add_adresse_configurateur', 'ajax_soeasy_add_adresse_configurateur');
add_action('wp_ajax_nopriv_soeasy_add_adresse_configurateur', 'ajax_soeasy_add_adresse_configurateur');
add_action('wp_ajax_soeasy_remove_adresse_configurateur', 'ajax_soeasy_remove_adresse_configurateur');
add_action('wp_ajax_nopriv_soeasy_remove_adresse_configurateur', 'ajax_soeasy_remove_adresse_configurateur');
add_action('wp_ajax_soeasy_verify_adresses_in_session', 'ajax_soeasy_verify_adresses_in_session');
add_action('wp_ajax_nopriv_soeasy_verify_adresses_in_session', 'ajax_soeasy_verify_adresses_in_session');


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


/**
 * AJAX : Sauvegarder une nouvelle configuration
 * 
 * Reçoit les données du localStorage et les sauvegarde en base de données.
 * 
 * POST params:
 * - config_name : Nom de la configuration
 * - config_data : JSON complet (string)
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { config_id: 123, message: '...' }
 * - error: { message: '...' }
 */
function soeasy_ajax_save_configuration() {
    // Vérification du nonce
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    // Vérifier que l'utilisateur est connecté
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'Vous devez être connecté pour sauvegarder une configuration']);
    }
    
    // Récupérer les paramètres
    $config_name = sanitize_text_field($_POST['config_name'] ?? 'Configuration sans nom');
    $config_data = $_POST['config_data'] ?? '';
    
    // Validation
    if (empty($config_data)) {
        wp_send_json_error(['message' => 'Données de configuration manquantes']);
    }
    
    // Vérifier que c'est un JSON valide
    $decoded = json_decode($config_data);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'Format JSON invalide : ' . json_last_error_msg()]);
    }
    
    // Sauvegarder via la fonction CRUD
    $config_id = soeasy_save_configuration($user_id, $config_data, $config_name, 'draft');
    
    if (is_wp_error($config_id)) {
        wp_send_json_error(['message' => $config_id->get_error_message()]);
    }
    
    // Succès
    wp_send_json_success([
        'message' => 'Configuration enregistrée avec succès',
        'config_id' => $config_id,
        'config_name' => $config_name
    ]);
}
add_action('wp_ajax_soeasy_ajax_save_configuration', 'soeasy_ajax_save_configuration');

/**
 * AJAX : Charger une configuration par ID
 * 
 * POST params:
 * - config_id : ID de la configuration à charger
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { config: {...}, config_name: '...', config_id: 123 }
 * - error: { message: '...' }
 */
function soeasy_ajax_load_configuration() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    $config_id = intval($_POST['config_id'] ?? 0);
    $user_id = get_current_user_id();
    
    if (!$config_id) {
        wp_send_json_error(['message' => 'ID de configuration manquant']);
    }
    
    // Récupérer la configuration
    $config = soeasy_get_configuration($config_id);
    
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration introuvable']);
    }
    
    // Vérifier les droits d'accès
    // L'utilisateur doit être propriétaire OU admin
    if ($config->user_id != $user_id && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Vous n\'avez pas accès à cette configuration']);
    }
    
    // Décoder le JSON
    $config_decoded = json_decode($config->config_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'Erreur de décodage JSON']);
    }
    
    // Succès
    wp_send_json_success([
        'config' => $config_decoded,
        'config_name' => $config->config_name,
        'config_id' => $config->id,
        'status' => $config->status,
        'created_at' => $config->created_at,
        'updated_at' => $config->updated_at
    ]);
}
add_action('wp_ajax_soeasy_ajax_load_configuration', 'soeasy_ajax_load_configuration');

/**
 * AJAX : Lister toutes les configurations de l'utilisateur
 * 
 * POST params:
 * - nonce : soeasy_config_action
 * - limit (optionnel) : Nombre max de résultats (défaut: 50)
 * - status (optionnel) : Filtrer par statut
 * 
 * Response:
 * - success: { configurations: [...], count: 10 }
 */
function soeasy_ajax_list_configurations() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error(['message' => 'Utilisateur non connecté']);
    }
    
    $limit = intval($_POST['limit'] ?? 50);
    $status = sanitize_text_field($_POST['status'] ?? '');
    
    // Récupérer les configurations
    $configs = soeasy_get_user_configurations($user_id, $limit, $status ?: null);
    
    // Formatter les données (sans inclure le JSON complet pour économiser bande passante)
    $formatted_configs = array_map(function($config) {
        return [
            'id' => $config->id,
            'config_name' => $config->config_name,
            'status' => $config->status,
            'created_at' => $config->created_at,
            'updated_at' => $config->updated_at,
            'completed_at' => $config->completed_at,
            'order_id' => $config->order_id
        ];
    }, $configs);
    
    wp_send_json_success([
        'configurations' => $formatted_configs,
        'count' => count($formatted_configs)
    ]);
}
add_action('wp_ajax_soeasy_ajax_list_configurations', 'soeasy_ajax_list_configurations');

/**
 * AJAX : Supprimer une configuration
 * 
 * POST params:
 * - config_id : ID de la configuration à supprimer
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { message: '...' }
 * - error: { message: '...' }
 */
function soeasy_ajax_delete_configuration() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    $config_id = intval($_POST['config_id'] ?? 0);
    $user_id = get_current_user_id();
    
    if (!$config_id) {
        wp_send_json_error(['message' => 'ID de configuration manquant']);
    }
    
    // Récupérer la config pour vérifier les droits
    $config = soeasy_get_configuration($config_id);
    
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration introuvable']);
    }
    
    // Vérifier propriété (ou admin)
    if ($config->user_id != $user_id && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Vous n\'avez pas le droit de supprimer cette configuration']);
    }
    
    // Supprimer
    $deleted = soeasy_delete_configuration($config_id);
    
    if (!$deleted) {
        wp_send_json_error(['message' => 'Erreur lors de la suppression']);
    }
    
    wp_send_json_success(['message' => 'Configuration supprimée avec succès']);
}
add_action('wp_ajax_soeasy_ajax_delete_configuration', 'soeasy_ajax_delete_configuration');

/**
 * AJAX : Dupliquer une configuration
 * 
 * POST params:
 * - config_id : ID de la configuration à dupliquer
 * - new_name (optionnel) : Nouveau nom
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { new_config_id: 456, message: '...' }
 */
function soeasy_ajax_duplicate_configuration() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    $config_id = intval($_POST['config_id'] ?? 0);
    $new_name = sanitize_text_field($_POST['new_name'] ?? '');
    $user_id = get_current_user_id();
    
    if (!$config_id) {
        wp_send_json_error(['message' => 'ID de configuration manquant']);
    }
    
    // Vérifier propriété
    $config = soeasy_get_configuration($config_id);
    
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration introuvable']);
    }
    
    if ($config->user_id != $user_id && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Vous n\'avez pas accès à cette configuration']);
    }
    
    // Dupliquer
    $new_id = soeasy_duplicate_configuration($config_id, $new_name ?: null);
    
    if (is_wp_error($new_id)) {
        wp_send_json_error(['message' => $new_id->get_error_message()]);
    }
    
    wp_send_json_success([
        'message' => 'Configuration dupliquée avec succès',
        'new_config_id' => $new_id
    ]);
}
add_action('wp_ajax_soeasy_ajax_duplicate_configuration', 'soeasy_ajax_duplicate_configuration');

/**
 * AJAX : Vider la session WooCommerce
 * 
 * Utilisé lors de la réconciliation pour nettoyer les données obsolètes.
 * 
 * POST params:
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { message: '...' }
 */
function soeasy_ajax_clear_session() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    // Vider la session WooCommerce
    if (function_exists('WC') && WC()->session) {
        WC()->session->set('soeasy_configurateur', []);
        WC()->session->set('soeasy_duree_engagement', 0);
        WC()->session->set('soeasy_mode_financement', '');
        
        // Vider aussi le panier
        WC()->cart->empty_cart();
    }
    
    wp_send_json_success(['message' => 'Session vidée avec succès']);
}
add_action('wp_ajax_soeasy_ajax_clear_session', 'soeasy_ajax_clear_session');
add_action('wp_ajax_nopriv_soeasy_ajax_clear_session', 'soeasy_ajax_clear_session');

/**
 * AJAX : Synchroniser localStorage vers session PHP
 * 
 * Envoie toute la config localStorage en session WooCommerce.
 * 
 * POST params:
 * - config : Objet configuration complet (JSON string)
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { message: '...' }
 */
function soeasy_ajax_sync_config_to_session() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    $config = $_POST['config'] ?? [];
    
    // Si config est un string JSON, le décoder
    if (is_string($config)) {
        $config = json_decode($config, true);
    }
    
    if (!function_exists('WC') || !WC()->session) {
        wp_send_json_error(['message' => 'Session WooCommerce non disponible']);
    }
    
    // Sauvegarder en session
    if (isset($config['config'])) {
        WC()->session->set('soeasy_configurateur', $config['config']);
    }
    
    if (isset($config['dureeEngagement'])) {
        WC()->session->set('soeasy_duree_engagement', intval($config['dureeEngagement']));
    }
    
    if (isset($config['modeFinancement'])) {
        WC()->session->set('soeasy_mode_financement', $config['modeFinancement']);
    }
    
    wp_send_json_success(['message' => 'Configuration synchronisée en session']);
}
add_action('wp_ajax_soeasy_ajax_sync_config_to_session', 'soeasy_ajax_sync_config_to_session');
add_action('wp_ajax_nopriv_soeasy_ajax_sync_config_to_session', 'soeasy_ajax_sync_config_to_session');

/**
 * AJAX : Vérifier si la session contient une configuration
 * 
 * Utilisé par la réconciliation pour détecter les incohérences.
 * 
 * POST params:
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { hasConfig: true/false }
 */
function soeasy_ajax_check_session_config() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    $hasConfig = false;
    
    if (function_exists('WC') && WC()->session) {
        $session_config = WC()->session->get('soeasy_configurateur', []);
        $hasConfig = !empty($session_config);
    }
    
    wp_send_json_success(['hasConfig' => $hasConfig]);
}
add_action('wp_ajax_soeasy_ajax_check_session_config', 'soeasy_ajax_check_session_config');
add_action('wp_ajax_nopriv_soeasy_ajax_check_session_config', 'soeasy_ajax_check_session_config');

/**
 * AJAX : Mettre à jour le statut d'une configuration (admin)
 * 
 * POST params:
 * - config_id : ID de la configuration
 * - status : Nouveau statut (draft/active/archived/completed)
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { message: '...' }
 */
function soeasy_ajax_update_config_status() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    // Vérifier droits admin
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Accès non autorisé']);
    }
    
    $config_id = intval($_POST['config_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    
    if (!$config_id || !$status) {
        wp_send_json_error(['message' => 'Paramètres manquants']);
    }
    
    // Valider le statut
    $valid_statuses = ['draft', 'active', 'archived', 'completed'];
    if (!in_array($status, $valid_statuses)) {
        wp_send_json_error(['message' => 'Statut invalide']);
    }
    
    // Mettre à jour
    $updated = soeasy_update_configuration($config_id, null, null, $status);
    
    if (!$updated) {
        wp_send_json_error(['message' => 'Erreur lors de la mise à jour']);
    }
    
    wp_send_json_success(['message' => 'Statut mis à jour avec succès']);
}
add_action('wp_ajax_soeasy_ajax_update_config_status', 'soeasy_ajax_update_config_status');

/**
 * AJAX : Ajouter/modifier les notes admin d'une configuration
 * 
 * POST params:
 * - config_id : ID de la configuration
 * - notes : Texte des notes
 * - nonce : soeasy_config_action
 */
function soeasy_ajax_update_config_notes() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Accès non autorisé']);
    }
    
    global $wpdb;
    
    $config_id = intval($_POST['config_id'] ?? 0);
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if (!$config_id) {
        wp_send_json_error(['message' => 'ID manquant']);
    }
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    $result = $wpdb->update(
        $table,
        ['notes' => $notes, 'updated_at' => current_time('mysql')],
        ['id' => $config_id],
        ['%s', '%s'],
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error(['message' => 'Erreur lors de la mise à jour']);
    }
    
    wp_send_json_success(['message' => 'Notes enregistrées']);
}
add_action('wp_ajax_soeasy_ajax_update_config_notes', 'soeasy_ajax_update_config_notes');

/**
 * AJAX : Synchroniser les adresses vers la session PHP
 * 
 * POST params:
 * - adresses : Array d'adresses (JSON string)
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { message: '...' }
 */
function soeasy_ajax_sync_adresses_to_session() {
    soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
    
    error_log('=== SYNC ADRESSES TO SESSION ===');
    
    $adresses = $_POST['adresses'] ?? '[]';
    error_log('Adresses reçues (raw): ' . print_r($adresses, true));
    error_log('Type: ' . gettype($adresses));
    
    // Gérer les deux formats possibles
    if (is_string($adresses)) {
        // Format string JSON : décoder
        $decoded = json_decode($adresses, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $adresses = $decoded;
            error_log('✅ Décodage JSON réussi');
        } else {
            error_log('❌ Échec décodage JSON: ' . json_last_error_msg());
            wp_send_json_error(['message' => 'Format JSON invalide']);
        }
    } elseif (!is_array($adresses)) {
        // Ni string ni array : erreur
        error_log('❌ Type invalide: ' . gettype($adresses));
        wp_send_json_error(['message' => 'Format d\'adresses invalide (type: ' . gettype($adresses) . ')']);
    }
    
    // À ce stade, $adresses DOIT être un array
    if (!is_array($adresses)) {
        error_log('❌ Toujours pas un array après traitement');
        wp_send_json_error(['message' => 'Impossible de convertir les adresses en array']);
    }
    
    error_log('Adresses après traitement: ' . print_r($adresses, true));
    error_log('Nombre d\'adresses: ' . count($adresses));
    
    // Vérifier que WooCommerce est chargé
    if (!function_exists('WC') || !WC()->session) {
        error_log('❌ WooCommerce session non disponible');
        wp_send_json_error(['message' => 'Session WooCommerce non disponible']);
    }
    
    // Enrichir les adresses si nécessaire
    $enriched_addresses = [];
    foreach ($adresses as $adr) {
        $adresse_text = is_array($adr) ? ($adr['adresse'] ?? '') : $adr;
        
        if (empty($adresse_text)) {
            error_log('⚠️ Adresse vide, skip');
            continue; // Skip les adresses vides
        }
        
        error_log('Processing adresse: ' . $adresse_text);
        
        $enriched_addresses[] = [
            'adresse' => $adresse_text,
            'services' => is_array($adr) ? ($adr['services'] ?? []) : [],
            'ville_courte' => soeasy_get_ville_courte($adresse_text),
            'ville_longue' => soeasy_get_ville_longue($adresse_text)
        ];
    }
    
    error_log('Adresses enrichies: ' . print_r($enriched_addresses, true));
    error_log('Nombre d\'adresses enrichies: ' . count($enriched_addresses));
    
    if (count($enriched_addresses) === 0) {
        error_log('❌ Aucune adresse valide à sauvegarder');
        wp_send_json_error(['message' => 'Aucune adresse valide à sauvegarder']);
    }
    
    // Sauvegarder en session
    WC()->session->set('soeasy_config_adresses', $enriched_addresses);
    error_log('✅ WC()->session->set() appelé');
    
    // ✅ CRITIQUE : Forcer le commit en base de données
    if (method_exists(WC()->session, 'save_data')) {
        WC()->session->save_data();
        error_log('✅ Session save_data() appelé');
    }
    
    // Vérification immédiate
    $check = WC()->session->get('soeasy_config_adresses', []);
    error_log('Vérification immédiate après save: ' . count($check) . ' adresse(s)');
    
    if (count($check) === 0) {
        error_log('❌ PROBLÈME : Session vide après save !');
        wp_send_json_error([
            'message' => 'Erreur : Les adresses n\'ont pas pu être sauvegardées en session',
            'debug' => [
                'sent_count' => count($enriched_addresses),
                'saved_count' => count($check)
            ]
        ]);
    }
    
    error_log('✅ Synchronisation réussie');
    error_log('=== FIN SYNC ADRESSES ===');
    
    wp_send_json_success([
        'message' => 'Adresses synchronisées en session',
        'count' => count($enriched_addresses),
        'addresses' => $enriched_addresses
    ]);
}
add_action('wp_ajax_soeasy_ajax_sync_adresses_to_session', 'soeasy_ajax_sync_adresses_to_session');
add_action('wp_ajax_nopriv_soeasy_ajax_sync_adresses_to_session', 'soeasy_ajax_sync_adresses_to_session');

/**
 * ============================================================================
 * FIN DES ENDPOINTS AJAX CONFIGURATIONS
 * ============================================================================
 */


?>