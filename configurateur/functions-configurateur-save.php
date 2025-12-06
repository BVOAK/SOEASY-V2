<?php

/**
 * AJAX : Sauvegarder configuration manuellement en BDD
 * 
 * Si config_id existe : UPDATE
 * Sinon : INSERT nouvelle config
 * 
 * POST params:
 * - config_id : ID config existante (optionnel)
 * - config_name : Nom de la config
 * - nonce : soeasy_config_action
 * 
 * Response:
 * - success: { message, config_id, is_new }
 */
function soeasy_ajax_save_configuration() {
    error_log('=== DÉBUT SAVE CONFIGURATION ===');
    
    try {
        // Vérifier nonce
        soeasy_verify_nonce($_POST['nonce'] ?? '', 'soeasy_config_action');
        
        $user_id = get_current_user_id();
        
        if ($user_id === 0) {
            wp_send_json_error(['message' => 'Vous devez être connecté pour sauvegarder']);
        }
        
        // Récupérer les données depuis usermeta (source de vérité)
        $config = soeasy_session_get('soeasy_configurateur', []);
        $adresses = soeasy_session_get('soeasy_config_adresses', []);
        $duree_engagement = soeasy_session_get('soeasy_duree_engagement', '0');
        $mode_financement = soeasy_session_get('soeasy_mode_financement', 'comptant');
        
        error_log('Données depuis backend:');
        error_log('  Config: ' . (is_array($config) ? count($config) : 'INVALID') . ' items');
        error_log('  Adresses: ' . (is_array($adresses) ? count($adresses) : 'INVALID') . ' items');
        
        // Vérifier qu'il y a des données
        if (empty($adresses) || count($adresses) === 0) {
            wp_send_json_error(['message' => 'Aucune configuration à sauvegarder (aucune adresse)']);
        }
        
        // Construire config_data
        $config_data = [
            'userId' => $user_id,
            'adresses' => $adresses,
            'config' => $config,
            'dureeEngagement' => $duree_engagement,
            'modeFinancement' => $mode_financement
        ];
        
        // Nom de la config
        $config_name = sanitize_text_field($_POST['config_name'] ?? '');
        if (empty($config_name)) {
            $config_name = 'Configuration du ' . date('d/m/Y à H:i');
        }
        
        // ID config existante (pour UPDATE)
        $config_id = intval($_POST['config_id'] ?? 0);
        
        $is_new = false;
        
        if ($config_id > 0) {
            // ========================================
            // UPDATE config existante
            // ========================================
            error_log('UPDATE config existante ID=' . $config_id);
            
            // Vérifier que la config appartient bien à l'user
            $existing = soeasy_get_configuration($config_id);
            
            if (!$existing || $existing->user_id != $user_id) {
                wp_send_json_error(['message' => 'Configuration introuvable ou accès refusé']);
            }
            
            // ✅ UTILISER LA BONNE FONCTION (celle qui existe dans config-manager.php)
            $result = soeasy_update_configuration(
                $config_id,
                $config_data,  // config_data
                $config_name,  // config_name
                'active'       // status
            );
            
            if ($result === false) {
                wp_send_json_error(['message' => 'Erreur mise à jour']);
            }
            
            error_log('✅ Config mise à jour : ' . $config_name . ' (ID=' . $config_id . ')');
            
        } else {
            // ========================================
            // INSERT nouvelle config
            // ========================================
            error_log('INSERT nouvelle config');
            
            // ✅ UTILISER LA BONNE FONCTION (celle qui existe dans config-manager.php)
            $config_id = soeasy_save_configuration(
                $user_id,      // user_id
                $config_data,  // config_data
                $config_name,  // config_name
                'active'       // status
            );
            
            if (is_wp_error($config_id)) {
                wp_send_json_error(['message' => 'Erreur création: ' . $config_id->get_error_message()]);
            }
            
            $is_new = true;
            
            error_log('✅ Nouvelle config créée : ' . $config_name . ' (ID=' . $config_id . ')');
        }
        
        error_log('=== FIN SAVE CONFIGURATION - SUCCESS ===');
        
        wp_send_json_success([
            'message' => $is_new ? 'Configuration sauvegardée avec succès' : 'Configuration mise à jour avec succès',
            'config_id' => $config_id,
            'config_name' => $config_name,
            'is_new' => $is_new
        ]);
        
    } catch (Exception $e) {
        error_log('💥 Exception dans save_configuration: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
}
add_action('wp_ajax_soeasy_ajax_save_configuration', 'soeasy_ajax_save_configuration');

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