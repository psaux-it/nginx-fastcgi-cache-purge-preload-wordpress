<?php
/**
 * Status page for FastCGI Cache Purge and Preload for Nginx
 * Description: This file contains functions which shows information about FastCGI Cache Purge and Preload for Nginx
 * Version: 2.0.9
 * Author: Hasan CALISIR
 * Author Email: hasan.calisir@psauxit.com
 * Author URI: https://www.psauxit.com
 * License: GPL-2.0+
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// To optimize performance and prevent redundancy, we use cached recursive permission checks.
// This technique stores the results of time-consuming (expensive) permission verifications for reuse.
// The results are cached for to reduce performance overhead, especially useful when the Nginx cache path is extensive.
function nppp_check_permissions_recursive_with_cache() {
    $nginx_cache_settings = get_option('nginx_cache_settings');
    $default_cache_path = '/dev/shm/change-me-now';
    $nginx_cache_path = isset($nginx_cache_settings['nginx_cache_path']) ? $nginx_cache_settings['nginx_cache_path'] : $default_cache_path;

    $wp_filesystem = nppp_initialize_wp_filesystem();

    if ($wp_filesystem === false) {
        nppp_display_admin_notice(
            'error',
            'Failed to initialize the WordPress filesystem. Please file a bug on the plugin support page.'
        );
        return;
    }

    // Define a static key-based transient
    $static_key_base = 'nppp';
    $transient_key = 'nppp_permissions_check_' . md5($static_key_base);

    // Check for cached result
    $result = get_transient($transient_key);
    if ($result === false) {
        // Perform the expensive recursive permission check
        $result = nppp_check_permissions_recursive($nginx_cache_path);

        // Convert boolean result to string
        $result = $result ? 'true' : 'false';

        // Cache the result for 1 hour
        set_transient($transient_key, $result, MONTH_IN_SECONDS);
    }

    return $result;
}

// Function to clear all transients related to the plugin
function nppp_clear_plugin_cache() {
    // Static key base
    $static_key_base = 'nppp';

    // Transients to clear
    $transients = array(
        'nppp_cache_keys_wpfilesystem_error',
        'nppp_nginx_conf_not_found',
        'nppp_cache_keys_not_found',
        'nppp_cache_path_not_found',
        'nppp_fuse_path_not_found',
        'nppp_cache_keys_' . md5($static_key_base),
        'nppp_bindfs_version_' . md5($static_key_base),
        'nppp_libfuse_version_' . md5($static_key_base),
        'nppp_permissions_check_' . md5($static_key_base),
        'nppp_cache_paths_' . md5($static_key_base),
        'nppp_fuse_paths_' . md5($static_key_base),
        'nppp_webserver_user_' . md5($static_key_base),
    );

    // Category-related transients based on the URL cache
    $url_cache_pattern = 'nppp_category_';

    // Rate limit transients
    $rate_limit_pattern = 'nppp_rate_limit_';

    // Get all transients
    $all_transients = wp_cache_get('alloptions', 'options');
    foreach ($all_transients as $transient_key => $value) {
        // Match the category-based transients
        if (strpos($transient_key, $url_cache_pattern) !== false) {
            $transients[] = $transient_key;
        }

        // Match the rate limit-related transients
        if (strpos($transient_key, $rate_limit_pattern) !== false) {
            $transients[] = $transient_key;
        }
    }

    // Attempt to delete all transients
    foreach ($transients as $transient) {
        // Delete the transient
        delete_transient($transient);

        // Check if the transient still exists
        if (get_transient($transient) !== false) {
            return 'An error occurred while clearing the plugin cache.';
        }
    }

    // Notify the user if all transients were cleared successfully
    return 'Plugin cache cleared successfully. Refreshing the Status..';
}

// Check server side action need for cache path permissions.
function nppp_check_perm_in_cache($check_path = false, $check_perm = false, $check_fpm = false) {
    // Define a static key-based transient
    $static_key_base = 'nppp';
    $transient_key = 'nppp_permissions_check_' . md5($static_key_base);

    // Get the cached result and path status
    $result = get_transient($transient_key);

    if ($check_path) {
        $path_status = nppp_check_path();

        if ($path_status !== 'Found') {
            return 'false';
        }
    }

    if ($check_perm) {
        $path_status = nppp_check_path();

        if ($path_status !== 'Found') {
            return 'Not Found';
        }
    }

    if ($check_fpm) {
        $path_status = nppp_check_path();

        if ($path_status !== 'Found') {
            return 'Not Found';
        }
    }

    // Return the permission status from cache
    return $result;
}

// Check required command statuses
function nppp_check_command_status($command) {
    $output = shell_exec("command -v $command");
    return !empty($output) ? 'Installed' : 'Not Installed';
}

// Check preload action status
function nppp_check_preload_status() {
    $wp_filesystem = nppp_initialize_wp_filesystem();

    if ($wp_filesystem === false) {
        nppp_display_admin_notice(
            'error',
            'Failed to initialize the WordPress filesystem. Please file a bug on the plugin support page.'
        );
        return;
    }

    $this_script_path = dirname(plugin_dir_path(__FILE__));
    $PIDFILE = rtrim($this_script_path, '/') . '/cache_preload.pid';

    if ($wp_filesystem->exists($PIDFILE)) {
        $pid = intval(nppp_perform_file_operation($PIDFILE, 'read'));

        if ($pid > 0 && nppp_is_process_alive($pid)) {
            return 'progress';;
        }
    }

    // Check permission status, wget command status and cache path existence
    $cached_result = nppp_check_perm_in_cache();
    $wget_status = nppp_check_command_status('wget');
    $path_status = nppp_check_path();

    if ($cached_result === 'false' || $wget_status !== 'Installed' || $path_status !== 'Found') {
        return 'false';
    }

    return 'true';
}

// Check Nginx Cache Path status
function nppp_check_path() {
    $wp_filesystem = nppp_initialize_wp_filesystem();

    if ($wp_filesystem === false) {
        nppp_display_admin_notice(
            'error',
            'Failed to initialize the WordPress filesystem. Please file a bug on the plugin support page.'
        );
        return;
    }

    $nginx_cache_settings = get_option('nginx_cache_settings');
    $default_cache_path = '/dev/shm/change-me-now';
    $nginx_cache_path = isset($nginx_cache_settings['nginx_cache_path']) ? $nginx_cache_settings['nginx_cache_path'] : $default_cache_path;

     // Check if directory exists
    if (!$wp_filesystem->is_dir($nginx_cache_path)) {
        // Cache Directory does not exist
        return 'Not Found';
    } else {
        return 'Found';
    }
}

// Check if shell_exec is allowed or not, required for plugin
function nppp_shell_exec() {
    // Check if shell_exec is enabled
    if (function_exists('shell_exec')) {
        // Attempt to execute a harmless command
        $output = shell_exec('echo "Test"');

        // Check if the command executed successfully
        // Trim the output to handle any extra whitespace or newlines
        if (trim($output) === "Test") {
            return 'Ok';
        }
    }

    return 'Not Ok';
}

// Function to get the PHP process owner (website-user)
function nppp_get_website_user() {
    $php_process_owner = '';

    // Check if the POSIX extension is available
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        // Get the user ID of the PHP process owner
        $php_process_uid = posix_geteuid();
        $userInfo = posix_getpwuid($php_process_uid);

        // Get the user NAME of the PHP process owner
        if ($userInfo) {
            $php_process_uid = $userInfo['name'];
        } else {
            $php_process_uid = 'Not Determined';
        }

        $php_process_owner = $php_process_uid;
    }

    // If POSIX functions are not available or user information is 'Not Determined',
    // try again to find PHP process owner more directly with help of shell

    if (empty($php_process_owner) || $php_process_owner === 'Not Determined') {
        if (defined('ABSPATH')) {
            $wordpressRoot = ABSPATH;
        } else {
            $wordpressRoot = __DIR__;
        }

        // Get the PHP process owner
        $command = "ls -ld " . escapeshellarg($wordpressRoot . '/index.php') . " | awk '{print $3}'";

        // Execute the shell command
        $process_owner = shell_exec($command);

        // Check the PHP process owner if not empty
        if (!empty($process_owner)) {
            $php_process_owner = trim($process_owner);
        } else {
            $php_process_owner = "Not Determined";
        }
    }

    // Return the PHP process owner
    return $php_process_owner;
}

// Function to get webserver user
function nppp_get_webserver_user() {
    // Ask result in cache first
    $static_key_base = 'nppp';
    $transient_key = 'nppp_webserver_user_' . md5($static_key_base);
    $cached_result = get_transient($transient_key);

    // Return cached result if available
    if ($cached_result !== false) {
        return $cached_result;
    }

    // Initialize wp_filesystem
    $wp_filesystem = nppp_initialize_wp_filesystem();
    if ($wp_filesystem === false) {
        nppp_display_admin_notice(
            'error',
            'Failed to initialize the WordPress filesystem. Please file a bug on the plugin support page.'
        );
        return;
    }

    // Find nginx.conf
    $conf_paths = nppp_get_nginx_conf_paths($wp_filesystem);
    $config_file = !empty($conf_paths) ? $conf_paths[0] : '/etc/nginx/nginx.conf';

    // Check if the config file exists
    if (!$wp_filesystem->exists($config_file)) {
        set_transient($transient_key, "Not Determined", MONTH_IN_SECONDS);
        return "Not Determined";
    }

    // Check the running processes for Nginx
    $nginx_user_process = shell_exec("ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v 'root' | awk '{print $1}' | sort | uniq");
    // Convert the process output to an array and filter out empty values
    $process_users = array_filter(array_unique(array_map('trim', explode("\n", $nginx_user_process))));
    // Try to get the user from the Nginx configuration file
    $nginx_user_conf = shell_exec("grep -i '^\s*user\s\+' $config_file | grep -v '^\s*#' | awk '{print $2}' | sed 's/;.*//;s/\s*$//'");

    // If both sources provide a user, check for consistency
    if (!empty($nginx_user_conf) && !empty($process_users)) {
        // Check if the configuration user is among the process users
        if (in_array($nginx_user_conf, $process_users)) {
            set_transient($transient_key, $nginx_user_conf, MONTH_IN_SECONDS);
            return $nginx_user_conf;
        }
    }

    // If only the configuration user is found, return it
    if (!empty($nginx_user_conf)) {
        set_transient($transient_key, $nginx_user_conf, MONTH_IN_SECONDS);
        return $nginx_user_conf;
    }

    // If only the process user is found, return it
    if (!empty($process_users)) {
        $user = reset($process_users);
        set_transient($transient_key, $user, MONTH_IN_SECONDS);
        return $user;
    }

    // If no user is found, return "Not Determined"
    set_transient($transient_key, "Not Determined", MONTH_IN_SECONDS);
    return "Not Determined";
}

// Function to get pages in cache count
function nppp_get_in_cache_page_count() {
    $nginx_cache_settings = get_option('nginx_cache_settings');
    $default_cache_path = '/dev/shm/change-me-now';
    $nginx_cache_path = isset($nginx_cache_settings['nginx_cache_path']) ? $nginx_cache_settings['nginx_cache_path'] : $default_cache_path;

    // Retrieve and decode user-defined cache key regex from the database, with a hardcoded fallback
    $regex = isset($nginx_cache_settings['nginx_cache_key_custom_regex'])
             ? base64_decode($nginx_cache_settings['nginx_cache_key_custom_regex'])
             : nppp_fetch_default_regex_for_cache_key();

    $urls_count = 0;

    // Initialize WordPress filesystem
    $wp_filesystem = nppp_initialize_wp_filesystem();

    if ($wp_filesystem === false) {
        nppp_display_admin_notice(
            'error',
            'Failed to initialize the WordPress filesystem. Please file a bug on the plugin support page.'
        );
        return;
    }

    // Check permission issue in cache
    // Cache path existence to prevent expensive directory traversal
    $cached_result = nppp_check_perm_in_cache(false, false, false);
    $path_status = nppp_check_path();

    // Return 'Not Found' if the cache path not found
    if ($path_status !== 'Found') {
        return 'Not Found';
    }

    // Return 'Undetermined' if the perm in cache returns 'false'
    if ($cached_result === 'false') {
        return 'Undetermined';
    }

    try {
        $cache_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($nginx_cache_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $regex_tested = false;
        foreach ($cache_iterator as $file) {
            if ($wp_filesystem->is_file($file->getPathname())) {
                // Check if the file is readable
                if (!$wp_filesystem->is_readable($file->getPathname())) {
                    return 'Undetermined';
                }

                // Read file contents
                $content = $wp_filesystem->get_contents($file->getPathname());

                // Exclude URLs with status 301 or 302
                if (strpos($content, 'Status: 301 Moved Permanently') !== false ||
                    strpos($content, 'Status: 302 Found') !== false) {
                    continue;
                }

                // Skip all request methods except GET
                if (!preg_match('/KEY:\s.*GET/', $content)) {
                    continue;
                }

                // Test regex only once
                // Regex operations can be computationally expensive,
                // especially when iterating over multiple files.
                // So here we test cache key regex only once
                if (!$regex_tested) {
                    if (preg_match($regex, $content, $matches) && isset($matches[1], $matches[2])) {
                        // Build the URL
                        $host = trim($matches[1]);
                        $request_uri = trim($matches[2]);
                        $constructed_url = $host . $request_uri;

                        // Test parsed URL via regex with FILTER_VALIDATE_URL
                        // We need to add prefix here
                        $constructed_url_test = 'https://' . $constructed_url;

                        // Test if the URL is in the expected format
                        if ($constructed_url !== '' && filter_var($constructed_url_test, FILTER_VALIDATE_URL)) {
                            $regex_tested = true;
                        } else {
                            return 'RegexError';
                        }
                    } else {
                        return 'RegexError';
                    }
                }

                // Extract URLs using regex
                if (preg_match($regex, $content, $matches)) {
                    $urls_count++;
                }
            }
        }
    } catch (Exception $e) {
        // Return 'Undetermined' if a permission issue occurs
        return 'Undetermined';
    }

    // Return the count of URLs, if no URLs found, return 0
    return $urls_count > 0 ? $urls_count : 0;
}

// Generate HTML for status tab
function nppp_my_status_html() {
    // Initialize wp_filesystem
    $wp_filesystem = nppp_initialize_wp_filesystem();
    if ($wp_filesystem === false) {
        nppp_display_admin_notice(
            'error',
            'Failed to initialize the WordPress filesystem. Please file a bug on the plugin support page.'
        );
        return;
    }

    // Status tab metrics heavily depends nginx.conf file
    // Try to get it first
    $conf_paths = nppp_get_nginx_conf_paths($wp_filesystem);

    // Exit early if unable to find or read the nginx.conf file
    if (empty($conf_paths)) {
        return '<div class="nppp-status-wrap">
                    <p class="nppp-advanced-error-message">ERROR CONF: Unable to read or locate the <span style="color: #f0c36d;">nginx.conf</span> configuration file!</p>
                </div>
                <div style="background-color: #f9edbe; border-left: 6px solid red; padding: 10px; margin-bottom: 15px; max-width: max-content;">
                    <p style="margin: 0; align-items: center;">
                        <span class="dashicons dashicons-warning" style="font-size: 22px; color: #721c24; margin-right: 8px;"></span>
                        The <strong>nginx.conf</strong> file was not found in the <strong>default paths</strong>. This may indicate a <strong>custom Nginx setup</strong> with a non-standard configuration file location or permission issue. If you still encounter this error, please get help from plugin support forum!
                    </p>
                </div>';
    }

    $perm_in_cache_status_purge = nppp_check_perm_in_cache(true, false, false);
    $perm_in_cache_status_fpm = nppp_check_perm_in_cache(false, false, true);
    $perm_in_cache_status_perm = nppp_check_perm_in_cache(false, true, false);
    $php_process_owner = nppp_get_website_user();
    $web_server_user = nppp_get_webserver_user();

    // Compare the two users and set the status
    if ($php_process_owner === $web_server_user) {
        $nppp_isolation_status = 'Not Isolated';
    } else {
        $nppp_isolation_status = 'Isolated';
    }

    // Check NGINX FastCGI Cache Key
    $config_data = nppp_parse_nginx_cache_key();

    // Warn about not found fastcgi cache keys
    if (isset($config_data['cache_keys']) && $config_data['cache_keys'] === ['Not Found']) {
        echo '<div class="nppp-status-wrap">
                  <p class="nppp-advanced-error-message">WARNING SETUP: No <span style="color: #f0c36d;">fastcgi_cache_key</span> directive was found.</p>
              </div>
              <div style="background-color: #f9edbe; border-left: 6px solid #f0c36d; padding: 10px; margin-bottom: 15px; max-width: max-content;">
                  <p style="margin: 0; align-items: center;">
                      <span class="dashicons dashicons-warning" style="font-size: 22px; color: #ffba00; margin-right: 8px;"></span>
                      Please review your <strong>Nginx FastCGI cache setup</strong> to ensure that the <strong>fastcgi_cache_key</strong> is correctly defined. If you continue to encounter this error, this may indicate a <strong>parsing error</strong> and can be safely ignored.
                  </p>
              </div>';
    // Warn about the unsupported fastcgi cache keys
    } elseif (isset($config_data['cache_keys']) && !empty($config_data['cache_keys'])) {
        echo '<div class="nppp-status-wrap">
                  <p class="nppp-advanced-error-message">INFO: <span style="color: #f0c36d;">Unsupported</span> FastCGI cache keys found!</p>
              </div>
              <div style="background-color: #f9edbe; border-left: 6px solid #f0c36d; padding: 10px; margin-bottom: 15px; max-width: max-content;">
                  <p style="margin: 0; align-items: center;">
                      <span class="dashicons dashicons-warning" style="font-size: 22px; color: #ffba00; margin-right: 8px;"></span>
                      If <strong>Pages In Cache Count</strong> indicates <strong>Regex Error</strong>, please check the <strong>Cache Key Regex</strong> option in plugin <strong>Advanced options</strong> section and try again.
                  </p>
              </div>';
    }

    // Format the status string
    $perm_status_message = $perm_in_cache_status_perm === 'true'
    ? 'Granted'
    : ($perm_in_cache_status_perm === 'Not Found' ? 'Not Determined' : 'Need Action (Check Help)');
    $perm_status_message .= ' (' . esc_html($php_process_owner) . ')';

    ob_start();
    ?>
    <div class="status-and-nginx-info-container">
        <div id="nppp-status-tab" class="container">
            <header></header>
            <main>
                <section class="clear-plugin-cache" style="background-color: mistyrose;">
                    <h2>Clear Plugin Cache</h2>
                    <p style="padding-left: 10px; font-weight: 500;">To ensure the accuracy of the displayed statuses, please clear the plugin cache. This plugin caches expensive status metrics to enhance performance. However, If you're in the testing stage and making frequent changes and re-checking Status tab, clearing the cache is necessary to view the most up-to-date and accurate status.</p>
                    <button id="nppp-clear-plugin-cache-btn" class="button button-primary" style="margin-left: 10px; margin-bottom: 15px;">Clear Plugin Cache</button>
                </section>
                <section class="status-summary">
                    <h2>Status Summary</h2>
                    <table>
                        <thead>
                            <tr>
                                <th class="check-header"><span class="dashicons dashicons-admin-generic"></span> Check</th>
                                <th class="status-header"><span class="dashicons dashicons-info"></span> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="action">
                                    <div class="action-wrapper">Server Side Action (Use One-liner)</div>
                                    <div class="action-wrapper" style="font-size: 12px; color: white; background-color: #ff9900; width: max-content; margin-top: 5px; padding-right: 5px; padding-left: 5px;">
                                        bash <(curl -Ss https://psaux-it.github.io/install.sh)
                                    </div>
                                </td>
                                <td class="status" id="npppphpFpmStatus">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html($perm_in_cache_status_fpm); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="height: 20px;"></div>
                    <table>
                        <thead>
                            <tr>
                                <th class="action-header"><span class="dashicons dashicons-admin-generic"></span> Action</th>
                                <th class="status-header"><span class="dashicons dashicons-info"></span> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="action">Purge Action</td>
                                <td class="status" id="nppppurgeStatus">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html($perm_in_cache_status_purge); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="action">Preload Action</td>
                                <td class="status" id="nppppreloadStatus">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html(nppp_check_preload_status()); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section id="nppp-system-checks" class="system-checks">
                    <h2>System Checks</h2>
                    <table>
                        <thead>
                            <tr>
                                <th class="check-header"><span class="dashicons dashicons-admin-generic"></span> Check</th>
                                <th class="status-header"><span class="dashicons dashicons-info"></span> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="check">PHP Process Owner (Website User)</td>
                                <td class="status" id="npppphpProcessOwner">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html($php_process_owner); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="check">Web Server User (nginx | www-data)</td>
                                <td class="status" id="npppphpWebServer">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html($web_server_user); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="check">Shell Execution (Required)</td>
                                <td class="status" id="npppshellExec">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html(nppp_shell_exec()); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="check">Nginx Cache Path (Required)</td>
                                <td class="status" id="npppcachePath">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html(nppp_check_path()); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="check">Cache Path Permission (Required)</td>
                                <td class="status" id="npppaclStatus">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html($perm_status_message); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="check">Permission Isolation (Optional)</td>
                                <td class="status" id="nppppermIsolation">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html($nppp_isolation_status); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="check">wget (Required command)</td>
                                <td class="status" id="npppwgetStatus">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html(nppp_check_command_status('wget')); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="check">cpulimit (Optional command)</td>
                                <td class="status" id="npppcpulimitStatus">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html(nppp_check_command_status('cpulimit')); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section class="cache-status">
                    <h2>Cache Status</h2>
                    <table>
                        <thead>
                            <tr>
                                <th class="check-header"><span class="dashicons dashicons-admin-generic"></span> Check</th>
                                <th class="status-header"><span class="dashicons dashicons-info"></span> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="check">Pages In Cache Count</td>
                                <td class="status" id="npppphpPagesInCache">
                                    <span class="dashicons"></span>
                                    <span><?php echo esc_html(nppp_get_in_cache_page_count()); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </main>
        </div>
        <div id="nppp-nginx-info" class="container">
            <?php echo do_shortcode('[nppp_nginx_config]'); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// AJAX handler to clear the plugin cache
function nppp_clear_plugin_cache_callback() {
    // Check nonce
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'nppp-clear-plugin-cache-action')) {
            wp_die('Nonce verification failed.');
        }
    } else {
        wp_die('Nonce is missing.');
    }

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }

     // Clear the plugin cache
    $message = nppp_clear_plugin_cache();

    // Return success response
    wp_send_json_success($message);
}

// AJAX handler to fetch shortcode content
function nppp_cache_status_callback() {
    // Check nonce
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'cache-status')) {
            wp_die('Nonce verification failed.');
        }
    } else {
        wp_die('Nonce is missing.');
    }

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }

    // Call the shortcode function to get HTML content
    $shortcode_content = nppp_my_status_shortcode();

    // Return the generated HTML to AJAX
    if (!empty($shortcode_content)) {
        echo wp_kses_post($shortcode_content);
    } else {
        // Send empty string to AJAX to trigger proper error
        echo '';
    }

    // Properly exit to avoid extra output
    wp_die();
}

// Shortcode to display the Status HTML
function nppp_my_status_shortcode() {
    return nppp_my_status_html();
}
