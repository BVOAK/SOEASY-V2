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

?>