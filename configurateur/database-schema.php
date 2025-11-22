<?php
/**
 * Schéma de base de données pour les configurations SoEasy
 * 
 * Ce fichier gère la création et la maintenance de la table wp_soeasy_configurations
 * qui stocke les configurations sauvegardées des utilisateurs.
 * 
 * @package SoEasy
 * @version 1.0
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Crée la table wp_soeasy_configurations
 * 
 * Utilise dbDelta() pour une création/mise à jour propre et compatible
 * avec les standards WordPress.
 * 
 * Structure de la table :
 * - id : Identifiant unique auto-incrémenté
 * - user_id : Lien vers wp_users (propriétaire de la config)
 * - config_name : Nom donné par l'utilisateur
 * - config_data : JSON complet de la configuration
 * - config_hash : MD5 du JSON pour détection rapide de changements
 * - status : Statut de la config (draft/active/archived/completed)
 * - created_at : Date de création
 * - updated_at : Date de dernière modification
 * - completed_at : Date de validation/transformation en commande
 * - order_id : Lien vers commande WooCommerce si validée
 * - notes : Notes administrateur
 * 
 * @global wpdb $wpdb Instance WordPress database
 * @return bool True si création réussie, false sinon
 */
function soeasy_create_configurations_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'soeasy_configurations';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Requête SQL de création de table
    // IMPORTANT : Format spécifique requis par dbDelta()
    // - Deux espaces après PRIMARY KEY
    // - Pas d'espace avant les virgules
    // - Une colonne par ligne
    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        config_name varchar(255) DEFAULT 'Configuration sans nom' NOT NULL,
        config_data longtext NOT NULL,
        config_hash varchar(64) DEFAULT NULL,
        status varchar(20) DEFAULT 'draft' NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        completed_at datetime DEFAULT NULL,
        order_id bigint(20) UNSIGNED DEFAULT NULL,
        notes text DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY idx_user (user_id),
        KEY idx_status (status),
        KEY idx_created (created_at),
        KEY idx_hash (config_hash)
    ) $charset_collate;";
    
    // Charger la fonction dbDelta
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Exécuter la création/mise à jour
    $result = dbDelta($sql);
    
    // Logger le résultat
    if (!empty($result)) {
        error_log('SoEasy: Table configurations créée/mise à jour avec succès');
        error_log('dbDelta result: ' . print_r($result, true));
        return true;
    } else {
        error_log('SoEasy: Erreur lors de la création de la table configurations');
        return false;
    }
}

/**
 * Vérifie si la table wp_soeasy_configurations existe
 * 
 * Utile pour vérifier l'installation avant d'effectuer des opérations
 * ou pour des scripts de maintenance.
 * 
 * @global wpdb $wpdb Instance WordPress database
 * @return bool True si la table existe, false sinon
 */
function soeasy_check_table_exists() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'soeasy_configurations';
    
    // Requête pour vérifier l'existence de la table
    $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table_name);
    $result = $wpdb->get_var($query);
    
    $exists = ($result === $table_name);
    
    if ($exists) {
        error_log("SoEasy: Table $table_name existe");
    } else {
        error_log("SoEasy: Table $table_name n'existe pas");
    }
    
    return $exists;
}

/**
 * Récupère la version actuelle du schéma de base de données
 * 
 * @return string Version du schéma (ex: '1.0')
 */
function soeasy_get_db_version() {
    return get_option('soeasy_db_version', '0');
}

/**
 * Met à jour la version du schéma de base de données
 * 
 * @param string $version Nouvelle version (ex: '1.0', '1.1', etc.)
 * @return bool True si mise à jour réussie
 */
function soeasy_set_db_version($version) {
    return update_option('soeasy_db_version', $version);
}

/**
 * Fonction de migration/upgrade du schéma
 * 
 * Gère les migrations futures de schéma de base de données.
 * Actuellement, il n'y a qu'une version (1.0), mais cette fonction
 * sera utilisée pour les futures mises à jour.
 * 
 * Exemple d'utilisation future :
 * - Version 1.1 : Ajouter colonne 'shared_with'
 * - Version 1.2 : Ajouter table de logs d'accès
 * 
 * @return bool True si upgrade réussi
 */
function soeasy_upgrade_table() {
    $current_version = soeasy_get_db_version();
    $target_version = '1.0';
    
    // Si déjà à jour, ne rien faire
    if (version_compare($current_version, $target_version, '>=')) {
        error_log("SoEasy: Base de données déjà à jour (version $current_version)");
        return true;
    }
    
    error_log("SoEasy: Début de la migration depuis version $current_version vers $target_version");
    
    // Migration de 0 à 1.0 : création initiale
    if (version_compare($current_version, '1.0', '<')) {
        soeasy_create_configurations_table();
        soeasy_set_db_version('1.0');
        error_log('SoEasy: Migration vers version 1.0 terminée');
    }
    
    // Futures migrations ici
    // if (version_compare($current_version, '1.1', '<')) {
    //     soeasy_upgrade_to_1_1();
    //     soeasy_set_db_version('1.1');
    // }
    
    return true;
}

/**
 * Hook d'activation du thème
 * 
 * Cette fonction est appelée automatiquement lors du changement de thème
 * vers SoEasy. Elle garantit que la table existe.
 * 
 * Note : WordPress n'a pas de hook "activation" pour les thèmes comme
 * pour les plugins, donc on utilise 'after_switch_theme'
 */
function soeasy_activate_theme() {
    error_log('SoEasy: Activation du thème - Vérification de la base de données');
    
    // Vérifier si la table existe
    if (!soeasy_check_table_exists()) {
        error_log('SoEasy: Table absente, création en cours...');
        soeasy_create_configurations_table();
    }
    
    // Exécuter les migrations si nécessaire
    soeasy_upgrade_table();
    
    // Flush rewrite rules pour les endpoints WooCommerce
    flush_rewrite_rules();
    
    error_log('SoEasy: Activation du thème terminée');
}
add_action('after_switch_theme', 'soeasy_activate_theme');

/**
 * Fonction de vérification/réparation de la table
 * 
 * Utile pour les scripts de maintenance ou pour réparer une installation
 * corrompue. Peut être appelée manuellement ou via un cron job.
 * 
 * @return array Résultat de la vérification avec détails
 */
function soeasy_verify_and_repair_table() {
    global $wpdb;
    
    $results = [
        'table_exists' => false,
        'version_correct' => false,
        'columns_ok' => false,
        'indexes_ok' => false,
        'errors' => []
    ];
    
    $table_name = $wpdb->prefix . 'soeasy_configurations';
    
    // 1. Vérifier existence de la table
    $results['table_exists'] = soeasy_check_table_exists();
    
    if (!$results['table_exists']) {
        $results['errors'][] = 'Table absente';
        
        // Tenter de créer la table
        soeasy_create_configurations_table();
        
        // Re-vérifier
        $results['table_exists'] = soeasy_check_table_exists();
        
        if ($results['table_exists']) {
            $results['errors'][] = 'Table créée avec succès';
        } else {
            $results['errors'][] = 'Échec de la création de la table';
            return $results;
        }
    }
    
    // 2. Vérifier version
    $current_version = soeasy_get_db_version();
    $results['version_correct'] = ($current_version === '1.0');
    
    if (!$results['version_correct']) {
        $results['errors'][] = "Version incorrecte: $current_version (attendue: 1.0)";
        soeasy_upgrade_table();
    }
    
    // 3. Vérifier les colonnes
    $columns = $wpdb->get_results("DESCRIBE $table_name");
    $expected_columns = [
        'id', 'user_id', 'config_name', 'config_data', 'config_hash',
        'status', 'created_at', 'updated_at', 'completed_at', 'order_id', 'notes'
    ];
    
    $found_columns = array_map(function($col) {
        return $col->Field;
    }, $columns);
    
    $missing_columns = array_diff($expected_columns, $found_columns);
    
    if (empty($missing_columns)) {
        $results['columns_ok'] = true;
    } else {
        $results['errors'][] = 'Colonnes manquantes: ' . implode(', ', $missing_columns);
        
        // Tenter de recréer la table
        soeasy_create_configurations_table();
    }
    
    // 4. Vérifier les index
    $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
    $expected_indexes = ['PRIMARY', 'idx_user', 'idx_status', 'idx_created', 'idx_hash'];
    
    $found_indexes = array_unique(array_map(function($idx) {
        return $idx->Key_name;
    }, $indexes));
    
    $missing_indexes = array_diff($expected_indexes, $found_indexes);
    
    if (empty($missing_indexes)) {
        $results['indexes_ok'] = true;
    } else {
        $results['errors'][] = 'Index manquants: ' . implode(', ', $missing_indexes);
        
        // Tenter de recréer la table pour ajouter les index
        soeasy_create_configurations_table();
    }
    
    // Log des résultats
    error_log('SoEasy: Vérification table - ' . print_r($results, true));
    
    return $results;
}

/**
 * Fonction de nettoyage (pour désinstallation)
 * 
 * ATTENTION : Cette fonction supprime DÉFINITIVEMENT toutes les données.
 * Elle ne devrait être appelée que lors d'une désinstallation complète.
 * 
 * @return bool True si suppression réussie
 */
function soeasy_drop_configurations_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'soeasy_configurations';
    
    // Suppression de la table
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Suppression de l'option de version
    delete_option('soeasy_db_version');
    
    error_log("SoEasy: Table $table_name supprimée");
    
    return true;
}

/**
 * Hook pour afficher un message admin si la table est manquante
 * 
 * Utile pour alerter l'administrateur en cas de problème
 */
function soeasy_admin_notice_missing_table() {
    if (!soeasy_check_table_exists()) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>SoEasy :</strong> La table de configurations est manquante. 
                <a href="<?php echo admin_url('themes.php?page=soeasy-repair-db'); ?>">
                    Cliquez ici pour réparer
                </a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'soeasy_admin_notice_missing_table');

/**
 * Ajouter une page de réparation dans l'admin (sous Apparence)
 */
function soeasy_add_repair_page() {
    add_theme_page(
        'Réparation BDD SoEasy',
        'Réparer BDD',
        'manage_options',
        'soeasy-repair-db',
        'soeasy_repair_page_content'
    );
}
add_action('admin_menu', 'soeasy_add_repair_page');

/**
 * Contenu de la page de réparation
 */
function soeasy_repair_page_content() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Si formulaire soumis
    if (isset($_POST['soeasy_repair']) && check_admin_referer('soeasy_repair_db')) {
        $results = soeasy_verify_and_repair_table();
        
        echo '<div class="notice notice-success"><p>Vérification et réparation terminées.</p></div>';
        echo '<pre>' . print_r($results, true) . '</pre>';
    }
    
    ?>
    <div class="wrap">
        <h1>Réparation de la base de données SoEasy</h1>
        
        <p>
            Cet outil vérifie et répare la table <code>wp_soeasy_configurations</code>.
        </p>
        
        <form method="post">
            <?php wp_nonce_field('soeasy_repair_db'); ?>
            <input type="submit" name="soeasy_repair" class="button button-primary" value="Vérifier et réparer">
        </form>
        
        <hr>
        
        <h2>Informations actuelles</h2>
        <table class="widefat">
            <tr>
                <th>Table existe</th>
                <td><?php echo soeasy_check_table_exists() ? '✅ Oui' : '❌ Non'; ?></td>
            </tr>
            <tr>
                <th>Version du schéma</th>
                <td><?php echo soeasy_get_db_version(); ?></td>
            </tr>
            <tr>
                <th>Nombre de configurations</th>
                <td>
                    <?php 
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'soeasy_configurations';
                    if (soeasy_check_table_exists()) {
                        echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
