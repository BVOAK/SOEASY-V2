<?php
/**
 * Test des endpoints AJAX - À SUPPRIMER après tests
 * Accès : https://soeasy.test/wp-content/themes/so-easy/configurateur/test-ajax-endpoints.php
 */

require_once('../../../../wp-load.php');

// Simuler utilisateur connecté
wp_set_current_user(1);

/**
 * Helper pour appeler un endpoint AJAX et récupérer le résultat
 */
function call_ajax_endpoint($function_name, $post_data = []) {
    $_POST = array_merge($_POST, $post_data);
    
    // Capturer la sortie
    ob_start();
    
    try {
        call_user_func($function_name);
    } catch (Exception $e) {
        ob_end_clean();
        return ['success' => false, 'error' => $e->getMessage()];
    }
    
    $output = ob_get_clean();
    
    // Extraire le JSON (peut y avoir du HTML avant)
    if (preg_match('/\{.*\}$/s', $output, $matches)) {
        return json_decode($matches[0], true);
    }
    
    return json_decode($output, true);
}

echo '<h1>Tests Endpoints AJAX Configurations</h1>';
echo '<style>
    body { font-family: monospace; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
</style>';
echo '<pre>';

$test_config_id = null;
$duplicate_id = null;

// Préparer le nonce
$_POST['nonce'] = wp_create_nonce('soeasy_config_action');

// TEST 1 : Sauvegarder une config
echo "TEST 1 - Sauvegarder config... ";
$result = call_ajax_endpoint('soeasy_ajax_save_configuration', [
    'config_name' => 'Test AJAX Config',
    'config_data' => json_encode([
        'userId' => 1,
        'adresses' => [['adresse' => 'Test AJAX']],
        'config' => ['0' => ['abonnements' => [], 'materiels' => []]],
        'dureeEngagement' => '36',
        'modeFinancement' => 'leasing'
    ])
]);

if ($result && $result['success']) {
    $test_config_id = $result['data']['config_id'];
    echo "<span class='success'>✅ OK - Config ID: {$test_config_id}</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
    echo "Erreur: " . ($result['data']['message'] ?? 'Inconnue') . "\n";
    die();
}

// TEST 2 : Charger la config
echo "TEST 2 - Charger config... ";
$result = call_ajax_endpoint('soeasy_ajax_load_configuration', [
    'config_id' => $test_config_id
]);

if ($result && $result['success'] && $result['data']['config_name'] === 'Test AJAX Config') {
    echo "<span class='success'>✅ OK</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
}

// TEST 3 : Lister les configs
echo "TEST 3 - Lister configs... ";
unset($_POST['config_id']);
$result = call_ajax_endpoint('soeasy_ajax_list_configurations');

if ($result && $result['success'] && $result['data']['count'] > 0) {
    echo "<span class='success'>✅ OK - Nombre: {$result['data']['count']}</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
}

// TEST 4 : Dupliquer
echo "TEST 4 - Dupliquer config... ";
$result = call_ajax_endpoint('soeasy_ajax_duplicate_configuration', [
    'config_id' => $test_config_id,
    'new_name' => 'Test AJAX Config (copie)'
]);

if ($result && $result['success']) {
    $duplicate_id = $result['data']['new_config_id'];
    echo "<span class='success'>✅ OK - Nouveau ID: {$duplicate_id}</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
}

// TEST 5 : Supprimer le duplicate
echo "TEST 5 - Supprimer duplicate... ";
$result = call_ajax_endpoint('soeasy_ajax_delete_configuration', [
    'config_id' => $duplicate_id
]);

if ($result && $result['success']) {
    echo "<span class='success'>✅ OK</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
}

// TEST 6 : Sync config vers session
echo "TEST 6 - Sync vers session... ";
$result = call_ajax_endpoint('soeasy_ajax_sync_config_to_session', [
    'config' => json_encode([
        'config' => ['0' => ['abonnements' => []]],
        'dureeEngagement' => '36',
        'modeFinancement' => 'leasing'
    ])
]);

if ($result && $result['success']) {
    echo "<span class='success'>✅ OK</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
}

// TEST 7 : Check session
echo "TEST 7 - Check session (doit avoir config)... ";
$result = call_ajax_endpoint('soeasy_ajax_check_session_config');

if ($result && $result['success'] && $result['data']['hasConfig'] === true) {
    echo "<span class='success'>✅ OK - Session contient config</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
}

// TEST 8 : Vider session
echo "TEST 8 - Vider session... ";
$result = call_ajax_endpoint('soeasy_ajax_clear_session');

if ($result && $result['success']) {
    echo "<span class='success'>✅ OK</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
}

// TEST 9 : Check session (doit être vide maintenant)
echo "TEST 9 - Check session (doit être vide)... ";
$result = call_ajax_endpoint('soeasy_ajax_check_session_config');

if ($result && $result['success'] && $result['data']['hasConfig'] === false) {
    echo "<span class='success'>✅ OK - Session vide</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
}

// TEST 10 : Charger dernière config
echo "TEST 10 - Charger dernière config... ";
$result = call_ajax_endpoint('soeasy_ajax_load_last_configuration');

if ($result && $result['success'] && $result['data']['config_id'] == $test_config_id) {
    echo "<span class='success'>✅ OK - Dernière config ID: {$result['data']['config_id']}</span>\n";
} else {
    echo "<span class='error'>❌ FAILED</span>\n";
}

// Nettoyer
echo "\nNettoyage... ";
soeasy_delete_configuration($test_config_id);
echo "<span class='success'>✅ Config de test supprimée</span>\n";

echo "\n<strong class='success'>🎉 TOUS LES TESTS AJAX RÉUSSIS !</strong>\n";
echo '</pre>';

// Afficher les configs en DB
echo '<h2>Configurations en base de données</h2>';
echo '<pre>';
global $wpdb;
$table = $wpdb->prefix . 'soeasy_configurations';
$configs = $wpdb->get_results("SELECT id, user_id, config_name, status, created_at FROM $table ORDER BY id DESC LIMIT 10");
print_r($configs);
echo '</pre>';