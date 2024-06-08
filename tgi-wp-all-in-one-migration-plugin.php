<?php
/*
Plugin Name: TGI WP All-in-One Migration Plugin
Plugin URI: https://tabsgi.com
Description: A plugin to export and import WordPress site including database and files.
Version: 1.5
Author: Zeeshan Ahmad
Author URI: https://www.linkedin.com/in/zeeshan-ahmad-10a84b18/
License: GPL2
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
include_once ABSPATH . 'wp-admin/includes/file.php';
include_once ABSPATH . 'wp-admin/includes/plugin.php';
include_once ABSPATH . 'wp-admin/includes/misc.php';
include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
include_once ABSPATH . 'wp-admin/includes/update.php';
include_once ABSPATH . 'wp-admin/includes/schema.php';
include_once ABSPATH . 'wp-admin/includes/export.php';
include_once ABSPATH . 'wp-admin/includes/import.php';

// Create backup directory if it doesn't exist
function tgi_create_backup_directory() {
    $backup_dir = plugin_dir_path(__FILE__) . 'backups';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
}
register_activation_hook(__FILE__, 'tgi_create_backup_directory');

// Function to keep the script alive during long execution
function tgi_keep_alive() {
    if (function_exists('set_time_limit')) {
        set_time_limit(0);
    }
    if (function_exists('ini_set')) {
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 0);
        ini_set('memory_limit', '512M');
    }
}
add_action('init', 'tgi_keep_alive');

// Function to log messages and flush output
function tgi_log($message) {
    $log_file = plugin_dir_path(__FILE__) . 'debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message<br>";
    file_put_contents($log_file, $log_message, FILE_APPEND);

    echo $log_message;
    echo '<script>document.getElementById("export-log").scrollTop = document.getElementById("export-log").scrollHeight;</script>';
    ob_flush();
    flush();
}

// Function to clear the log file
function tgi_clear_log() {
    $log_file = plugin_dir_path(__FILE__) . 'debug.log';
    file_put_contents($log_file, '');
}

// Function to create a zip file of the entire WordPress site
function tgi_export_site() {
    tgi_keep_alive();
    tgi_clear_log();
    tgi_log('Export site initiated.');

    // Check for nonce security
    if (!check_admin_referer('tgi_export_site')) {
        tgi_log('Nonce verification failed.');
        wp_die(__('Nonce verification failed', 'tgi-wp-all-in-one-migration-plugin'));
    }

    // Define the file path and name
    $backup_dir = plugin_dir_path(__FILE__) . 'backups';
    $export_file = $backup_dir . '/tgi-wp-export-' . date('Y-m-d_H-i-s') . '.zip';

    // Initialize archive object
    $zip = new ZipArchive();
    if ($zip->open($export_file, ZipArchive::CREATE) !== TRUE) {
        tgi_log('Could not create zip file.');
        wp_die(__('Could not create zip file.', 'tgi-wp-all-in-one-migration-plugin'));
    }

    tgi_log('Zip file created: ' . $export_file);

    // Add WordPress files to the zip, excluding this plugin directory
    $plugin_dir = plugin_dir_path(__FILE__);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ABSPATH), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) {
        try {
            if (!$file->isDir() && $file->getFilename() != 'wp-config.php' && strpos($file->getPathname(), $plugin_dir) === false) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen(ABSPATH));

                // Skip the problematic file for diagnosis
                if ($relative_path === 'wp-content/uploads/2021/10/final-urdu-60x60.jpg') {
                    tgi_log('Skipped problematic file: ' . $relative_path);
                    continue;
                }

                $zip->addFile($file_path, $relative_path);
                tgi_log('Added file to zip: ' . $relative_path);
            }
        } catch (Exception $e) {
            tgi_log('Exception while adding file: ' . $file->getRealPath() . ' - ' . $e->getMessage());
            continue;
        }
    }

    // Add the database export to the zip, excluding the options table
    global $wpdb;
    $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
    $sql_file = 'tgi-wp-export-' . date('Y-m-d_H-i-s') . '.sql';
    $sql_content = '';
    foreach ($tables as $table) {
        $table_name = $table[0];
        if ($table_name != $wpdb->prefix . 'options') {
            $create_table = $wpdb->get_row('SHOW CREATE TABLE ' . $table_name, ARRAY_N);
            $sql_content .= $create_table[1] . ";\n\n";
            $table_data = $wpdb->get_results('SELECT * FROM ' . $table_name, ARRAY_A);
            foreach ($table_data as $row) {
                $sql_content .= 'INSERT INTO ' . $table_name . ' VALUES(';
                $row_values = array();
                foreach ($row as $value) {
                    $row_values[] = is_null($value) ? 'NULL' : '"' . esc_sql($value) . '"';
                }
                $sql_content .= implode(',', $row_values) . ");\n";
            }
            $sql_content .= "\n\n";
            tgi_log('Added table to SQL: ' . $table_name);
        }
    }
    $zip->addFromString($sql_file, $sql_content);

    // Get siteurl and home from the options table and add to a text file
    $siteurl = get_option('siteurl');
    $home = get_option('home');
    $url_file_content = "siteurl=$siteurl\nhome=$home";
    $zip->addFromString('url_data.txt', $url_file_content);

    tgi_log('Added URL data to zip.');

    // Close the zip file
    $zip->close();

    // JavaScript to handle redirection
    echo '<script>
            setTimeout(function() {
                window.location.href = "' . admin_url('admin.php?page=tgi-wp-export&export=success') . '";
            }, 1000);
          </script>';
    tgi_log('Export completed successfully.');
    exit;
}
add_action('admin_post_tgi_export_site', 'tgi_export_site');

// Add the export and import functionality to the main menu
function tgi_migration_admin_menu() {
    add_menu_page('TGI WP Migration', 'TGI WP Migration', 'manage_options', 'tgi-wp-migration', 'tgi_export_page', 'dashicons-migrate', 6);
    add_submenu_page('tgi-wp-migration', 'Export Site', 'Export Site', 'manage_options', 'tgi-wp-export', 'tgi_export_page');
    add_submenu_page('tgi-wp-migration', 'Import Site', 'Import Site', 'manage_options', 'tgi-wp-import', 'tgi_import_page');
}
add_action('admin_menu', 'tgi_migration_admin_menu');

// Create the admin page for exporting
function tgi_export_page() {
    ?>
    <style>
        .tgi-page-wrapper {
            font-family: Arial, sans-serif;
        }
        .tgi-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tgi-button {
            background-color: #ff6600;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
        }
        .tgi-button.secondary {
            background-color: #777;
        }
        .tgi-button:hover {
            background-color: #e65c00;
        }
        .tgi-section {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #ff6600;
            border-radius: 4px;
            background-color: #fff6f0;
        }
        .tgi-section h2 {
            margin-top: 0;
            color: #ff6600;
        }
        .tgi-log {
            height: 400px;
            overflow-y: scroll;
            border: 1px solid #ff6600;
            padding: 10px;
            background-color: #fff;
        }
        .tgi-notice {
            background-color: #fff6f0;
            border-color: #ff6600;
            color: #ff6600;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .tgi-page-wrapper p {
            color: #333;
        }
        .tgi-page-wrapper ul {
            color: #333;
        }
    </style>
    <div class="wrap tgi-page-wrapper">
        <div class="tgi-page-header">
            <h1><?php _e('TGI WP Export', 'tgi-wp-all-in-one-migration-plugin'); ?></h1>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <?php wp_nonce_field('tgi_clear_log'); ?>
                <input type="hidden" name="action" value="tgi_clear_log">
                <?php submit_button(__('Clear Log', 'tgi-wp-all-in-one-migration-plugin'), 'secondary', 'submit', false, array('class' => 'tgi-button secondary')); ?>
            </form>
        </div>
        <?php if (isset($_GET['export']) && $_GET['export'] == 'success') : ?>
            <div class="tgi-notice">
                <p><?php _e('Site exported successfully.', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['delete']) && $_GET['delete'] == 'success') : ?>
            <div class="tgi-notice">
                <p><?php _e('Backup deleted successfully.', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            </div>
        <?php endif; ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="tgi-section">
            <?php wp_nonce_field('tgi_export_site'); ?>
            <input type="hidden" name="action" value="tgi_export_site">
            <p><?php _e('Click the button below to export your site. This will create a backup of your site including all files and the database, except the options table.', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            <?php submit_button(__('Export Site', 'tgi-wp-all-in-one-migration-plugin'), 'primary', 'submit', true, array('class' => 'tgi-button', 'onclick' => 'return confirm("Are you sure you want to export the site?");')); ?>
        </form>
        <div class="tgi-section">
            <h2><?php _e('Existing Backups', 'tgi-wp-all-in-one-migration-plugin'); ?></h2>
            <p><?php _e('Here you can find the existing backups. Click on the file name to download, or click "Delete" to remove the backup.', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            <ul>
            <?php
            $backup_dir = plugin_dir_path(__FILE__) . 'backups';
            $backups = array_diff(scandir($backup_dir), array('.', '..'));
            foreach ($backups as $backup) {
                $backup_url = plugin_dir_url(__FILE__) . 'backups/' . $backup;
                echo '<li style="margin-bottom: 10px;"><a href="' . esc_url($backup_url) . '">' . esc_html($backup) . '</a> <a href="' . esc_url(admin_url('admin-post.php?action=tgi_delete_backup&file=' . urlencode($backup))) . '" onclick="return confirm(\'Are you sure you want to delete this backup?\');" style="color: red; margin-left: 10px;">Delete</a></li>';
            }
            ?>
            </ul>
        </div>
        <div class="tgi-section">
            <h2><?php _e('PHP Configuration', 'tgi-wp-all-in-one-migration-plugin'); ?></h2>
            <p><?php _e('The following PHP settings affect the import and export process:', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            <ul>
                <li><?php _e('max_execution_time:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('max_execution_time'); ?></li>
                <li><?php _e('max_input_time:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('max_input_time'); ?></li>
                <li><?php _e('memory_limit:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('memory_limit'); ?></li>
                <li><?php _e('upload_max_filesize:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('upload_max_filesize'); ?></li>
                <li><?php _e('post_max_size:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('post_max_size'); ?></li>
            </ul>
            <p><?php _e('Please ensure these settings are configured to handle large files and long execution times to avoid interruptions during the backup and restore processes.', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
        </div>
        <div id="export-log" class="tgi-log">
            <?php
            $log_file = plugin_dir_path(__FILE__) . 'debug.log';
            if (file_exists($log_file)) {
                $logs = array_reverse(file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
                echo implode("<br>", $logs);
            } else {
                _e('No logs available.', 'tgi-wp-all-in-one-migration-plugin');
            }
            ?>
        </div>
    </div>
    <?php
}

// Function to handle deleting backups
function tgi_delete_backup() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['file'])) {
        wp_die(__('No file specified.', 'tgi-wp-all-in-one-migration-plugin'));
    }

    $backup_dir = plugin_dir_path(__FILE__) . 'backups';
    $file = $backup_dir . '/' . basename($_GET['file']);

    if (file_exists($file)) {
        unlink($file);
        tgi_log('Backup deleted: ' . $file);
    }

    echo '<script>
            setTimeout(function() {
                window.location.href = "' . admin_url('admin.php?page=tgi-wp-export&delete=success') . '";
            }, 1000);
          </script>';
    exit;
}
add_action('admin_post_tgi_delete_backup', 'tgi_delete_backup');

// Function to handle clearing the log
function tgi_clear_log_action() {
    if (!current_user_can('manage_options')) {
        return;
    }

    tgi_clear_log();
    echo '<script>
            setTimeout(function() {
                window.location.href = "' . admin_url('admin.php?page=tgi-wp-export&log=cleared') . '";
            }, 1000);
          </script>';
    exit;
}
add_action('admin_post_tgi_clear_log', 'tgi_clear_log_action');

// Function to import the WordPress site from a zip file
function tgi_import_site() {
    tgi_keep_alive();
    tgi_clear_log();
    tgi_log('Import site initiated.');

    // Check for nonce security
    if (!check_admin_referer('tgi_import_site')) {
        tgi_log('Nonce verification failed.');
        wp_die(__('Nonce verification failed', 'tgi-wp-all-in-one-migration-plugin'));
    }

    // Check if a file has been uploaded
    if (empty($_FILES['import_file']['name']) && empty($_POST['backup_file'])) {
        tgi_log('No file uploaded or selected.');
        wp_die(__('No file uploaded or selected.', 'tgi-wp-all-in-one-migration-plugin'));
    }

    // Move the uploaded file to the uploads directory
    $backup_dir = plugin_dir_path(__FILE__) . 'backups';
    if (!empty($_FILES['import_file']['name'])) {
        $uploaded_file = $_FILES['import_file'];
        $file_path = $backup_dir . '/' . basename($uploaded_file['name']);
        if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
            tgi_log('Error moving uploaded file.');
            wp_die(__('Error moving uploaded file.', 'tgi-wp-all-in-one-migration-plugin'));
        }
        tgi_log('Uploaded file moved: ' . $file_path);
    } else {
        $file_path = $backup_dir . '/' . basename($_POST['backup_file']);
        tgi_log('Selected backup file: ' . $file_path);
    }

    // Initialize archive object
    $zip = new ZipArchive();
    if ($zip->open($file_path) !== TRUE) {
        tgi_log('Could not open zip file.');
        wp_die(__('Could not open zip file.', 'tgi-wp-all-in-one-migration-plugin'));
    }

    // Extract the zip file contents to the WordPress root directory, excluding wp-config.php
    $output = array();
    $extracted_sql_file = '';
    $url_file_path = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        try {
            if ($filename != 'wp-config.php') {
                $fileinfo = pathinfo($filename);
                $extract_to = ABSPATH . $fileinfo['dirname'];
                if (!is_dir($extract_to)) {
                    mkdir($extract_to, 0755, true);
                }
                copy("zip://".$file_path."#".$filename, ABSPATH.$filename);
                $output[] = "Extracted: " . $filename;
                tgi_log('Extracted: ' . $filename);
                if (pathinfo($filename, PATHINFO_EXTENSION) == 'sql') {
                    $extracted_sql_file = ABSPATH . $filename;
                } elseif ($filename == 'url_data.txt') {
                    $url_file_path = ABSPATH . $filename;
                }
            }
        } catch (Exception $e) {
            tgi_log('Exception while extracting file: ' . $filename . ' - ' . $e->getMessage());
            continue;
        }
    }
    $zip->close();

    // Read the URLs from the text file
    if ($url_file_path && file_exists($url_file_path)) {
        $url_data = file($url_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $source_urls = [];
        foreach ($url_data as $line) {
            list($key, $value) = explode('=', $line);
            $source_urls[$key] = $value;
        }
        unlink($url_file_path);
        tgi_log('Read URL data from text file.');
    }

    // Import the database from the extracted SQL file
    global $wpdb;
    if ($extracted_sql_file && file_exists($extracted_sql_file)) {
        $sql_content = file_get_contents($extracted_sql_file);

        // Get destination URLs
        $destination_siteurl = get_option('siteurl');

        // Replace source URLs with destination URLs in the SQL content
        if (isset($source_urls['siteurl'])) {
            $sql_content = str_replace($source_urls['siteurl'], $destination_siteurl, $sql_content);
        }

        $queries = explode(";\n", $sql_content);

        // Drop existing tables
        $existing_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        foreach ($existing_tables as $table) {
            if ($table[0] != $wpdb->prefix . 'options') {
                $wpdb->query("DROP TABLE IF EXISTS " . $table[0]);
                $output[] = "Dropped table: " . $table[0];
                tgi_log('Dropped table: ' . $table[0]);
            }
        }

        foreach ($queries as $query) {
            if (!empty(trim($query))) {
                $wpdb->query('SET foreign_key_checks = 0'); // Disable foreign key checks
                $result = $wpdb->query($query);
                $wpdb->query('SET foreign_key_checks = 1'); // Enable foreign key checks
                if ($result === false) {
                    $output[] = "Error executing query: " . htmlspecialchars($query) . " - " . $wpdb->last_error;
                    tgi_log('Error executing query: ' . htmlspecialchars($query) . ' - ' . $wpdb->last_error);
                } else {
                    $output[] = "Executed query: " . htmlspecialchars($query);
                    tgi_log('Executed query: ' . htmlspecialchars($query));
                }
            }
        }
        unlink($extracted_sql_file);
        tgi_log('SQL file imported successfully.');
    } else {
        $output[] = "SQL file not found: " . $extracted_sql_file;
        tgi_log('SQL file not found: ' . $extracted_sql_file);
    }

    // Reverse the output array to show the latest actions first
    $output = array_reverse($output);

    // Write the output to a temporary file
    $output_file = $backup_dir . '/import_output.html';
    file_put_contents($output_file, implode("<br>", $output));

    // Redirect to the import page with success message
    echo '<script>
            setTimeout(function() {
                window.location.href = "' . admin_url('admin.php?page=tgi-wp-import&import=success&output_file=' . urlencode($output_file)) . '";
            }, 1000);
          </script>';
    tgi_log('Import completed successfully.');
    exit;
}
add_action('admin_post_tgi_import_site', 'tgi_import_site');

// Create the admin page for importing
function tgi_import_page() {
    ?>
    <style>
        .tgi-page-wrapper {
            font-family: Arial, sans-serif;
        }
        .tgi-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tgi-button {
            background-color: #ff6600;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
        }
        .tgi-button.secondary {
            background-color: #777;
        }
        .tgi-button:hover {
            background-color: #e65c00;
        }
        .tgi-section {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #ff6600;
            border-radius: 4px;
            background-color: #fff6f0;
        }
        .tgi-section h2 {
            margin-top: 0;
            color: #ff6600;
        }
        .tgi-log {
            height: 400px;
            overflow-y: scroll;
            border: 1px solid #ff6600;
            padding: 10px;
            background-color: #fff;
        }
        .tgi-notice {
            background-color: #fff6f0;
            border-color: #ff6600;
            color: #ff6600;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .tgi-page-wrapper p {
            color: #333;
        }
        .tgi-page-wrapper ul {
            color: #333;
        }
    </style>
    <div class="wrap tgi-page-wrapper">
        <div class="tgi-page-header">
            <h1><?php _e('TGI WP Import', 'tgi-wp-all-in-one-migration-plugin'); ?></h1>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <?php wp_nonce_field('tgi_clear_log'); ?>
                <input type="hidden" name="action" value="tgi_clear_log">
                <?php submit_button(__('Clear Log', 'tgi-wp-all-in-one-migration-plugin'), 'secondary', 'submit', false, array('class' => 'tgi-button secondary')); ?>
            </form>
        </div>
        <?php if (isset($_GET['import']) && $_GET['import'] == 'success') : ?>
            <div class="tgi-notice">
                <p><?php _e('Site imported successfully.', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            </div>
        <?php endif; ?>
        <form id="tgi-import-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data" class="tgi-section">
            <?php wp_nonce_field('tgi_import_site'); ?>
            <input type="hidden" name="action" value="tgi_import_site">
            <p><?php _e('Upload a backup file to import:', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            <input type="file" name="import_file" class="tgi-button secondary">
            <h2><?php _e('Or Import from Existing Backups', 'tgi-wp-all-in-one-migration-plugin'); ?></h2>
            <p><?php _e('Select an existing backup file to import:', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            <select name="backup_file" class="tgi-button secondary">
                <option value=""><?php _e('Select a backup file', 'tgi-wp-all-in-one-migration-plugin'); ?></option>
                <?php
                $backup_dir = plugin_dir_path(__FILE__) . 'backups';
                $backups = array_diff(scandir($backup_dir), array('.', '..'));
                foreach ($backups as $backup) {
                    if (pathinfo($backup, PATHINFO_EXTENSION) == 'zip') {
                        echo '<option value="' . esc_attr($backup) . '">' . esc_html($backup) . '</option>';
                    }
                }
                ?>
            </select>
            <?php submit_button(__('Import Site', 'tgi-wp-all-in-one-migration-plugin'), 'primary', 'submit', true, array('class' => 'tgi-button', 'onclick' => 'return confirm("This will overwrite your current site. Please make sure you have a backup of your current site. Are you sure you want to proceed?");')); ?>
        </form>
        <div class="tgi-section">
            <h2><?php _e('PHP Configuration', 'tgi-wp-all-in-one-migration-plugin'); ?></h2>
            <p><?php _e('The following PHP settings affect the import and export process:', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            <ul>
                <li><?php _e('max_execution_time:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('max_execution_time'); ?></li>
                <li><?php _e('max_input_time:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('max_input_time'); ?></li>
                <li><?php _e('memory_limit:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('memory_limit'); ?></li>
                <li><?php _e('upload_max_filesize:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('upload_max_filesize'); ?></li>
                <li><?php _e('post_max_size:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('post_max_size'); ?></li>
            </ul>
            <p><?php _e('Please ensure these settings are configured to handle large files and long execution times to avoid interruptions during the backup and restore processes.', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
        </div>
        <div id="import-log" class="tgi-log">
            <?php
            $log_file = plugin_dir_path(__FILE__) . 'debug.log';
            if (file_exists($log_file)) {
                $logs = array_reverse(file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
                echo implode("<br>", $logs);
            } else {
                _e('No logs available.', 'tgi-wp-all-in-one-migration-plugin');
            }
            ?>
        </div>
    </div>
    <?php
}

// Detailed usage instructions
function tgi_add_usage_instructions() {
    ?>
    <style>
        .tgi-page-wrapper {
            font-family: Arial, sans-serif;
        }
        .tgi-section {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #ff6600;
            border-radius: 4px;
            background-color: #fff6f0;
        }
        .tgi-section h2 {
            margin-top: 0;
            color: #ff6600;
        }
        .tgi-log {
            height: 400px;
            overflow-y: scroll;
            border: 1px solid #ff6600;
            padding: 10px;
            background-color: #fff;
        }
        .tgi-notice {
            background-color: #fff6f0;
            border-color: #ff6600;
            color: #ff6600;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
    <div class="wrap tgi-page-wrapper">
        <h1><?php _e('TGI WP All-in-One Migration Plugin Usage Instructions', 'tgi-wp-all-in-one-migration-plugin'); ?></h1>
        <div class="tgi-section">
            <h2><?php _e('Exporting Your Site', 'tgi-wp-all-in-one-migration-plugin'); ?></h2>
            <p><?php _e('To export your site, go to "TGI WP Migration" > "Export Site" and click the "Export Site" button. This will create a backup of your site including all files and the database, except the options table.', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
        </div>
        <div class="tgi-section">
            <h2><?php _e('Importing Your Site', 'tgi-wp-all-in-one-migration-plugin'); ?></h2>
            <p><?php _e('To import your site, go to "TGI WP Migration" > "Import Site". You can either upload a backup file or select an existing backup file from the list. Please note that importing will overwrite your current site.', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
        </div>
        <div class="tgi-section">
            <h2><?php _e('PHP Configuration', 'tgi-wp-all-in-one-migration-plugin'); ?></h2>
            <p><?php _e('Ensure that your PHP settings are configured to handle large files and long execution times. Adjust the following settings if necessary:', 'tgi-wp-all-in-one-migration-plugin'); ?></p>
            <ul>
                <li><?php _e('max_execution_time:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('max_execution_time'); ?></li>
                <li><?php _e('max_input_time:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('max_input_time'); ?></li>
                <li><?php _e('memory_limit:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('memory_limit'); ?></li>
                <li><?php _e('upload_max_filesize:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('upload_max_filesize'); ?></li>
                <li><?php _e('post_max_size:', 'tgi-wp-all-in-one-migration-plugin'); ?> <?php echo ini_get('post_max_size'); ?></li>
            </ul>
        </div>
    </div>
    <?php
}
add_action('admin_menu', function() {
    add_submenu_page('tgi-wp-migration', 'Usage Instructions', 'Usage Instructions', 'manage_options', 'tgi-wp-usage-instructions', 'tgi_add_usage_instructions');
});
