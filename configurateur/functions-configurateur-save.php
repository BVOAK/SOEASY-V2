<?php

/**
 * AJAX : Auto-sauvegarde automatique (status = draft)
 * 
 * Sauvegarde automatique pendant que l'utilisateur configure.
 * Différence avec save manuel : status = 'draft' au lieu de 'active'
 * 
 * POST params:
 * - nonce : soeasy_config_action
 * 
 * Lit les données depuis usermeta (déjà synchronisé)
 * 
 * Response:
 * - success: { config_id, last_saved, is_new }
 * - error: { message }
 */
function soeasy_ajax_auto_save_configuration() {
    error_log('=== DÉBUT AUTO-SAVE ===');
    
    try {
        // Vérifier nonce
        soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(['message' => 'Utilisateur non connecté']);
        }
        
        // Lire les données depuis usermeta
        $config = soeasy_session_get('soeasy_configurateur', []);
        $adresses = soeasy_session_get('soeasy_config_adresses', []);
        $duree_engagement = soeasy_session_get('soeasy_duree_engagement', 0);
        $mode_financement = soeasy_session_get('soeasy_mode_financement', 'comptant');
        
        // Vérifier qu'il y a des données à sauvegarder
        if (empty($adresses)) {
            wp_send_json_error(['message' => 'Aucune adresse configurée']);
        }
        
        // Construire config_data
        $config_data = [
            'userId' => $user_id,
            'adresses' => $adresses,
            'config' => $config,
            'dureeEngagement' => $duree_engagement,
            'modeFinancement' => $mode_financement,
            'timestamp' => current_time('mysql')
        ];
        
        $config_data_json = json_encode($config_data);
        
        // Chercher un draft existant pour cet utilisateur
        global $wpdb;
        $table = $wpdb->prefix . 'soeasy_configurations';
        
        $existing_draft = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table 
             WHERE user_id = %d 
             AND status = 'draft'
             ORDER BY updated_at DESC 
             LIMIT 1",
            $user_id
        ));
        
        $is_new = false;
        
        if ($existing_draft) {
            // Mettre à jour le draft existant
            $config_id = $existing_draft->id;
            $result = soeasy_update_configuration(
                $config_id,
                $config_data_json,
                'Auto-save ' . date('d/m/Y H:i'),
                'draft'
            );
            
            error_log("✅ Draft mis à jour (ID: $config_id)");
            
        } else {
            // Créer un nouveau draft
            $config_id = soeasy_save_configuration(
                $user_id,
                $config_data_json,
                'Auto-save ' . date('d/m/Y H:i'),
                'draft'
            );
            
            $is_new = true;
            error_log("✅ Nouveau draft créé (ID: $config_id)");
        }
        
        if (is_wp_error($config_id) || !$config_id) {
            wp_send_json_error(['message' => 'Erreur lors de la sauvegarde']);
        }
        
        wp_send_json_success([
            'config_id' => $config_id,
            'last_saved' => current_time('timestamp'),
            'is_new' => $is_new,
            'message' => 'Configuration sauvegardée automatiquement'
        ]);
        
    } catch (Exception $e) {
        error_log('💥 Exception dans auto_save: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Erreur serveur']);
    }
}
add_action('wp_ajax_soeasy_ajax_auto_save_configuration', 'soeasy_ajax_auto_save_configuration');

?>