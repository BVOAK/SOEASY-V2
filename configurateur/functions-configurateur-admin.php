<?php

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

?>