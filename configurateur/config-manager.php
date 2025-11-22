<?php
/**
 * Gestionnaire CRUD pour les configurations SoEasy
 * 
 * Ce fichier contient toutes les fonctions de manipulation des configurations :
 * - CREATE : Sauvegarder une nouvelle configuration
 * - READ : Récupérer des configurations (une, plusieurs, dernière)
 * - UPDATE : Modifier une configuration existante
 * - DELETE : Supprimer une configuration
 * - DUPLICATE : Dupliquer une configuration
 * 
 * @package SoEasy
 * @version 1.0
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * CREATE - Sauvegarder une nouvelle configuration
 * ============================================================================
 */

/**
 * Sauvegarde une nouvelle configuration en base de données
 * 
 * Cette fonction crée une nouvelle entrée dans wp_soeasy_configurations
 * avec toutes les données de configuration en JSON.
 * 
 * @param int    $user_id      ID de l'utilisateur WordPress (propriétaire)
 * @param mixed  $config_data  Données de configuration (array ou string JSON)
 * @param string $config_name  Nom de la configuration (par défaut : "Configuration sans nom")
 * @param string $status       Statut initial (draft/active/archived/completed)
 * 
 * @return int|WP_Error ID de la configuration créée, ou WP_Error en cas d'erreur
 * 
 * @example
 * $config_data = [
 *     'userId' => 42,
 *     'adresses' => [...],
 *     'config' => [...],
 *     'dureeEngagement' => '36',
 *     'modeFinancement' => 'leasing'
 * ];
 * $config_id = soeasy_save_configuration(42, $config_data, 'Ma super config');
 */
function soeasy_save_configuration($user_id, $config_data, $config_name = 'Configuration sans nom', $status = 'draft') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    // 1. Validation des paramètres requis
    if (empty($user_id) || !is_numeric($user_id)) {
        return new WP_Error('invalid_user_id', 'user_id doit être un nombre valide');
    }
    
    if (empty($config_data)) {
        return new WP_Error('missing_data', 'config_data est requis');
    }
    
    // 2. Encoder en JSON si nécessaire
    if (is_array($config_data) || is_object($config_data)) {
        $json_data = wp_json_encode($config_data, JSON_UNESCAPED_UNICODE);
        
        if ($json_data === false) {
            return new WP_Error('json_encode_error', 'Impossible d\'encoder les données en JSON');
        }
    } else {
        // Vérifier que c'est un JSON valide
        $json_data = $config_data;
        json_decode($json_data);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'config_data doit être un JSON valide : ' . json_last_error_msg());
        }
    }
    
    // 3. Générer le hash MD5 pour détection rapide de changements
    $hash = md5($json_data);
    
    // 4. Valider le statut
    $valid_statuses = ['draft', 'active', 'archived', 'completed'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'draft';
    }
    
    // 5. Sanitiser le nom
    $config_name = sanitize_text_field($config_name);
    if (empty($config_name)) {
        $config_name = 'Configuration sans nom';
    }
    
    // 6. Insertion en base de données
    $result = $wpdb->insert(
        $table,
        [
            'user_id'     => intval($user_id),
            'config_name' => $config_name,
            'config_data' => $json_data,
            'config_hash' => $hash,
            'status'      => $status,
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql')
        ],
        [
            '%d', // user_id
            '%s', // config_name
            '%s', // config_data
            '%s', // config_hash
            '%s', // status
            '%s', // created_at
            '%s'  // updated_at
        ]
    );
    
    // 7. Vérifier le résultat
    if ($result === false) {
        error_log('SoEasy: Erreur insertion configuration - ' . $wpdb->last_error);
        return new WP_Error('db_error', 'Erreur lors de la sauvegarde : ' . $wpdb->last_error);
    }
    
    $config_id = $wpdb->insert_id;
    
    // Log de succès
    error_log("SoEasy: Configuration #$config_id créée pour user #$user_id : $config_name");
    
    return $config_id;
}

/**
 * ============================================================================
 * READ - Récupérer des configurations
 * ============================================================================
 */

/**
 * Récupère toutes les configurations d'un utilisateur
 * 
 * @param int         $user_id  ID de l'utilisateur
 * @param int         $limit    Nombre maximum de résultats (défaut: 50)
 * @param string|null $status   Filtrer par statut (optionnel)
 * 
 * @return array Liste d'objets de configurations
 * 
 * @example
 * // Toutes les configs de l'utilisateur 42
 * $configs = soeasy_get_user_configurations(42);
 * 
 * // Seulement les brouillons
 * $drafts = soeasy_get_user_configurations(42, 50, 'draft');
 */
function soeasy_get_user_configurations($user_id, $limit = 50, $status = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    // Validation
    if (empty($user_id) || !is_numeric($user_id)) {
        return [];
    }
    
    $limit = intval($limit);
    if ($limit < 1) {
        $limit = 50;
    }
    
    // Construction de la requête
    $sql = $wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        intval($user_id)
    );
    
    // Filtre par statut si fourni
    if ($status && in_array($status, ['draft', 'active', 'archived', 'completed'])) {
        $sql .= $wpdb->prepare(" AND status = %s", $status);
    }
    
    // Tri par date de modification (plus récent en premier)
    $sql .= " ORDER BY updated_at DESC";
    
    // Limite
    $sql .= $wpdb->prepare(" LIMIT %d", $limit);
    
    // Exécution
    $results = $wpdb->get_results($sql);
    
    if ($wpdb->last_error) {
        error_log('SoEasy: Erreur récupération configurations - ' . $wpdb->last_error);
        return [];
    }
    
    return $results;
}

/**
 * Récupère une configuration spécifique par son ID
 * 
 * @param int $config_id ID de la configuration
 * 
 * @return object|null Objet de configuration ou null si introuvable
 * 
 * @example
 * $config = soeasy_get_configuration(123);
 * if ($config) {
 *     echo $config->config_name;
 *     $data = json_decode($config->config_data, true);
 * }
 */
function soeasy_get_configuration($config_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    // Validation
    if (empty($config_id) || !is_numeric($config_id)) {
        return null;
    }
    
    // Requête préparée
    $sql = $wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        intval($config_id)
    );
    
    $result = $wpdb->get_row($sql);
    
    if ($wpdb->last_error) {
        error_log('SoEasy: Erreur récupération configuration #' . $config_id . ' - ' . $wpdb->last_error);
        return null;
    }
    
    return $result;
}

/**
 * Récupère la dernière configuration modifiée d'un utilisateur
 * 
 * Utile pour restaurer automatiquement la config après déconnexion.
 * 
 * @param int $user_id ID de l'utilisateur
 * 
 * @return object|null Objet de configuration ou null si aucune
 * 
 * @example
 * $last_config = soeasy_get_last_user_configuration(42);
 */
function soeasy_get_last_user_configuration($user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    // Validation
    if (empty($user_id) || !is_numeric($user_id)) {
        return null;
    }
    
    // Requête : dernière config modifiée
    $sql = $wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY updated_at DESC LIMIT 1",
        intval($user_id)
    );
    
    $result = $wpdb->get_row($sql);
    
    if ($wpdb->last_error) {
        error_log('SoEasy: Erreur récupération dernière config user #' . $user_id . ' - ' . $wpdb->last_error);
        return null;
    }
    
    return $result;
}

/**
 * Récupère toutes les configurations (admin uniquement)
 * 
 * @param int    $limit   Nombre de résultats (défaut: 100)
 * @param int    $offset  Décalage pour pagination (défaut: 0)
 * @param string $orderby Colonne de tri (défaut: 'created_at')
 * @param string $order   Ordre (ASC ou DESC, défaut: 'DESC')
 * 
 * @return array Liste d'objets de configurations
 */
function soeasy_get_all_configurations($limit = 100, $offset = 0, $orderby = 'created_at', $order = 'DESC') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    // Validation des paramètres
    $limit = intval($limit);
    $offset = intval($offset);
    
    $valid_orderby = ['id', 'user_id', 'config_name', 'status', 'created_at', 'updated_at'];
    if (!in_array($orderby, $valid_orderby)) {
        $orderby = 'created_at';
    }
    
    $order = strtoupper($order);
    if (!in_array($order, ['ASC', 'DESC'])) {
        $order = 'DESC';
    }
    
    // Requête
    $sql = "SELECT * FROM $table ORDER BY $orderby $order LIMIT $limit OFFSET $offset";
    
    $results = $wpdb->get_results($sql);
    
    if ($wpdb->last_error) {
        error_log('SoEasy: Erreur récupération toutes configs - ' . $wpdb->last_error);
        return [];
    }
    
    return $results;
}

/**
 * Compte le nombre total de configurations
 * 
 * @param int|null    $user_id Filtrer par utilisateur (optionnel)
 * @param string|null $status  Filtrer par statut (optionnel)
 * 
 * @return int Nombre de configurations
 */
function soeasy_count_configurations($user_id = null, $status = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    $where = [];
    $values = [];
    
    if ($user_id) {
        $where[] = 'user_id = %d';
        $values[] = intval($user_id);
    }
    
    if ($status && in_array($status, ['draft', 'active', 'archived', 'completed'])) {
        $where[] = 'status = %s';
        $values[] = $status;
    }
    
    $sql = "SELECT COUNT(*) FROM $table";
    
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql = $wpdb->prepare($sql, $values);
    }
    
    $count = $wpdb->get_var($sql);
    
    return intval($count);
}

/**
 * ============================================================================
 * UPDATE - Modifier une configuration
 * ============================================================================
 */

/**
 * Met à jour une configuration existante
 * 
 * Seuls les champs fournis (non null) sont mis à jour.
 * Le champ updated_at est toujours mis à jour automatiquement.
 * 
 * @param int         $config_id   ID de la configuration
 * @param mixed|null  $config_data Nouvelles données (optionnel)
 * @param string|null $config_name Nouveau nom (optionnel)
 * @param string|null $status      Nouveau statut (optionnel)
 * 
 * @return bool True si succès, false si erreur
 * 
 * @example
 * // Modifier uniquement le nom
 * soeasy_update_configuration(123, null, 'Nouveau nom');
 * 
 * // Modifier les données et le statut
 * soeasy_update_configuration(123, $new_data, null, 'active');
 */
function soeasy_update_configuration($config_id, $config_data = null, $config_name = null, $status = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    // Validation
    if (empty($config_id) || !is_numeric($config_id)) {
        return false;
    }
    
    // Vérifier que la config existe
    $existing = soeasy_get_configuration($config_id);
    if (!$existing) {
        error_log("SoEasy: Configuration #$config_id introuvable pour mise à jour");
        return false;
    }
    
    // Préparer les données à mettre à jour
    $update_data = [
        'updated_at' => current_time('mysql')
    ];
    
    $update_format = ['%s']; // updated_at
    
    // Config data
    if ($config_data !== null) {
        // Encoder en JSON si nécessaire
        if (is_array($config_data) || is_object($config_data)) {
            $json_data = wp_json_encode($config_data, JSON_UNESCAPED_UNICODE);
        } else {
            $json_data = $config_data;
        }
        
        $update_data['config_data'] = $json_data;
        $update_data['config_hash'] = md5($json_data);
        
        $update_format[] = '%s'; // config_data
        $update_format[] = '%s'; // config_hash
    }
    
    // Config name
    if ($config_name !== null) {
        $update_data['config_name'] = sanitize_text_field($config_name);
        $update_format[] = '%s';
    }
    
    // Status
    if ($status !== null) {
        if (in_array($status, ['draft', 'active', 'archived', 'completed'])) {
            $update_data['status'] = $status;
            $update_format[] = '%s';
            
            // Si statut devient 'completed', mettre completed_at
            if ($status === 'completed' && empty($existing->completed_at)) {
                $update_data['completed_at'] = current_time('mysql');
                $update_format[] = '%s';
            }
        }
    }
    
    // Exécution de la mise à jour
    $result = $wpdb->update(
        $table,
        $update_data,
        ['id' => intval($config_id)],
        $update_format,
        ['%d']
    );
    
    // Vérifier le résultat
    if ($result === false) {
        error_log("SoEasy: Erreur mise à jour configuration #$config_id - " . $wpdb->last_error);
        return false;
    }
    
    error_log("SoEasy: Configuration #$config_id mise à jour avec succès");
    
    return true;
}

/**
 * Lie une configuration à une commande WooCommerce
 * 
 * @param int $config_id ID de la configuration
 * @param int $order_id  ID de la commande WooCommerce
 * 
 * @return bool True si succès
 */
function soeasy_link_configuration_to_order($config_id, $order_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    $result = $wpdb->update(
        $table,
        [
            'order_id'      => intval($order_id),
            'status'        => 'completed',
            'completed_at'  => current_time('mysql'),
            'updated_at'    => current_time('mysql')
        ],
        ['id' => intval($config_id)],
        ['%d', '%s', '%s', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        error_log("SoEasy: Configuration #$config_id liée à la commande #$order_id");
        return true;
    }
    
    return false;
}

/**
 * ============================================================================
 * DELETE - Supprimer une configuration
 * ============================================================================
 */

/**
 * Supprime une configuration
 * 
 * ATTENTION : Action irréversible !
 * 
 * @param int $config_id ID de la configuration à supprimer
 * 
 * @return bool True si suppression réussie, false sinon
 */
function soeasy_delete_configuration($config_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    // Validation
    if (empty($config_id) || !is_numeric($config_id)) {
        return false;
    }
    
    // Récupérer info avant suppression (pour logs)
    $config = soeasy_get_configuration($config_id);
    
    if (!$config) {
        error_log("SoEasy: Tentative de suppression d'une config inexistante #$config_id");
        return false;
    }
    
    // Suppression
    $result = $wpdb->delete(
        $table,
        ['id' => intval($config_id)],
        ['%d']
    );
    
    if ($result === false) {
        error_log("SoEasy: Erreur suppression configuration #$config_id - " . $wpdb->last_error);
        return false;
    }
    
    error_log("SoEasy: Configuration #$config_id supprimée (était : {$config->config_name}, user #{$config->user_id})");
    
    return true;
}

/**
 * Supprime toutes les configurations d'un utilisateur
 * 
 * Utile lors de la suppression d'un compte utilisateur.
 * 
 * @param int $user_id ID de l'utilisateur
 * 
 * @return int Nombre de configurations supprimées
 */
function soeasy_delete_user_configurations($user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    if (empty($user_id) || !is_numeric($user_id)) {
        return 0;
    }
    
    $count = soeasy_count_configurations($user_id);
    
    $result = $wpdb->delete(
        $table,
        ['user_id' => intval($user_id)],
        ['%d']
    );
    
    if ($result !== false) {
        error_log("SoEasy: $count configuration(s) de l'utilisateur #$user_id supprimée(s)");
        return $count;
    }
    
    return 0;
}

/**
 * Hook pour supprimer les configs quand un utilisateur est supprimé
 */
function soeasy_delete_configs_on_user_delete($user_id) {
    soeasy_delete_user_configurations($user_id);
}
add_action('delete_user', 'soeasy_delete_configs_on_user_delete');

/**
 * ============================================================================
 * DUPLICATE - Dupliquer une configuration
 * ============================================================================
 */

/**
 * Duplique une configuration existante
 * 
 * Crée une copie exacte avec un nouveau nom.
 * Le statut de la copie est toujours 'draft'.
 * 
 * @param int         $config_id ID de la configuration source
 * @param string|null $new_name  Nom de la copie (par défaut : "Nom (copie)")
 * 
 * @return int|WP_Error ID de la nouvelle configuration ou WP_Error
 * 
 * @example
 * $new_id = soeasy_duplicate_configuration(123);
 * // Crée une copie nommée "Nom original (copie)"
 * 
 * $new_id = soeasy_duplicate_configuration(123, 'Ma variante');
 * // Crée une copie nommée "Ma variante"
 */
function soeasy_duplicate_configuration($config_id, $new_name = null) {
    // Récupérer la config source
    $config = soeasy_get_configuration($config_id);
    
    if (!$config) {
        return new WP_Error('not_found', 'Configuration source introuvable');
    }
    
    // Générer le nouveau nom
    if ($new_name === null) {
        $new_name = $config->config_name . ' (copie)';
    } else {
        $new_name = sanitize_text_field($new_name);
    }
    
    // Créer la copie
    $new_id = soeasy_save_configuration(
        $config->user_id,
        $config->config_data,
        $new_name,
        'draft' // Toujours en brouillon
    );
    
    if (is_wp_error($new_id)) {
        return $new_id;
    }
    
    error_log("SoEasy: Configuration #$config_id dupliquée → nouvelle config #$new_id");
    
    return $new_id;
}

/**
 * ============================================================================
 * HELPERS - Fonctions utilitaires
 * ============================================================================
 */

/**
 * Récupère une configuration et décode directement le JSON
 * 
 * @param int $config_id ID de la configuration
 * 
 * @return array|null Données décodées ou null
 */
function soeasy_get_configuration_data($config_id) {
    $config = soeasy_get_configuration($config_id);
    
    if (!$config) {
        return null;
    }
    
    return json_decode($config->config_data, true);
}

/**
 * Vérifie si un utilisateur possède une configuration
 * 
 * @param int $config_id ID de la configuration
 * @param int $user_id   ID de l'utilisateur
 * 
 * @return bool True si l'utilisateur possède cette config
 */
function soeasy_user_owns_configuration($config_id, $user_id) {
    $config = soeasy_get_configuration($config_id);
    
    if (!$config) {
        return false;
    }
    
    return (intval($config->user_id) === intval($user_id));
}

/**
 * Génère un badge HTML pour le statut
 * 
 * @param string $status Statut (draft/active/archived/completed)
 * 
 * @return string HTML du badge
 */
function soeasy_get_status_badge($status) {
    $badges = [
        'draft'     => '<span class="badge badge-secondary" style="background:#6c757d;color:#fff;padding:4px 8px;border-radius:4px;">Brouillon</span>',
        'active'    => '<span class="badge badge-primary" style="background:#007bff;color:#fff;padding:4px 8px;border-radius:4px;">Active</span>',
        'archived'  => '<span class="badge badge-warning" style="background:#ffc107;color:#000;padding:4px 8px;border-radius:4px;">Archivée</span>',
        'completed' => '<span class="badge badge-success" style="background:#28a745;color:#fff;padding:4px 8px;border-radius:4px;">Validée</span>'
    ];
    
    return isset($badges[$status]) ? $badges[$status] : $badges['draft'];
}

/**
 * Recherche des configurations par terme (nom, notes)
 * 
 * @param string   $search_term Terme de recherche
 * @param int|null $user_id     Filtrer par utilisateur (optionnel)
 * @param int      $limit       Limite de résultats
 * 
 * @return array Liste de configurations
 */
function soeasy_search_configurations($search_term, $user_id = null, $limit = 50) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'soeasy_configurations';
    
    $search_term = sanitize_text_field($search_term);
    $limit = intval($limit);
    
    $where = "WHERE (config_name LIKE %s OR notes LIKE %s)";
    $params = ["%$search_term%", "%$search_term%"];
    
    if ($user_id) {
        $where .= " AND user_id = %d";
        $params[] = intval($user_id);
    }
    
    $sql = "SELECT * FROM $table $where ORDER BY updated_at DESC LIMIT $limit";
    $sql = $wpdb->prepare($sql, $params);
    
    return $wpdb->get_results($sql);
}
