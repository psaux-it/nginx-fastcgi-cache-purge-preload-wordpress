<?php
/**
 * Settings page for FastCGI Cache Purge and Preload for Nginx
 * Description: This file contains settings page functions for FastCGI Cache Purge and Preload for Nginx
 * Version: 2.0.1
 * Author: Hasan ÇALIŞIR
 * Author Email: hasan.calisir@psauxit.com
 * Author URI: https://www.psauxit.com
 * License: GPL-2.0+
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Initializes the Nginx Cache settings by registering settings, adding settings section, and fields
function nppp_nginx_cache_settings_init() {
    // Register settings
    register_setting('nppp_nginx_cache_settings_group', 'nginx_cache_settings', 'nppp_nginx_cache_settings_sanitize');

    // Add settings section and fields
    add_settings_section('nppp_nginx_cache_settings_section', 'FastCGI Cache Purge & Preload Settings', 'nppp_nginx_cache_settings_section_callback', 'nppp_nginx_cache_settings_group');
    add_settings_field('nginx_cache_path', 'Nginx FastCGI Cache Path', 'nppp_nginx_cache_path_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_email', 'Email Address', 'nppp_nginx_cache_email_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_cpu_limit', 'CPU Usage Limit for Cache Preloading (0-100)', 'nppp_nginx_cache_cpu_limit_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_reject_regex', 'Excluded endpoints from cache preloading', 'nppp_nginx_cache_reject_regex_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_send_mail', 'Send Mail', 'nppp_nginx_cache_send_mail_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_logs', 'Logs', 'nppp_nginx_cache_logs_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_limit_rate', 'Limit Rate Definition', 'nppp_nginx_cache_limit_rate_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_auto_preload', 'Auto Preload', 'nppp_nginx_cache_auto_preload_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_api_key', 'API Key', 'nppp_nginx_cache_api_key_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_api', 'API', 'nppp_nginx_cache_api_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_schedule', 'Scheduled Cache', 'nppp_nginx_cache_schedule_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_purge_on_update', 'Purge Cache on Post/Page Update', 'nppp_nginx_cache_purge_on_update_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
    add_settings_field('nginx_cache_wait_request', 'Per Request Wait Time', 'nppp_nginx_cache_wait_request_callback', 'nppp_nginx_cache_settings_group', 'nppp_nginx_cache_settings_section');
}

// Add settings page
function nppp_add_nginx_cache_settings_page() {
    add_submenu_page(
        'options-general.php',
        'Nginx FastCGI Cache',
        'FastCGI Cache Purge and Preload',
        'manage_options',
        'nginx_cache_settings',
        'nppp_nginx_cache_settings_page'
    );
}

// Add the option name to the allowed options list
function nppp_add_nginx_cache_settings_to_allowed_options($options) {
    $options['nginx_cache_settings'] = 'nginx_cache_settings';
    return $options;
}

// Displays the Nginx Cache Settings page in the WordPress admin dashboard
// Handles form submission, settings validation, and updating options
function nppp_nginx_cache_settings_page() {
    if (isset($_GET['status_message']) && isset($_GET['message_type'])) {
        // Sanitize and validate the nonce
        $nonce = isset($_GET['redirect_nonce']) ? sanitize_text_field(wp_unslash($_GET['redirect_nonce'])) : '';

        if (empty($nonce) || !wp_verify_nonce($nonce, 'nppp_redirect_nonce')) {
            wp_die('Nonce verification failed');
        }

        // Sanitize the status message and message type
        $status_message = sanitize_text_field(urldecode($_GET['status_message']));
        $message_type = sanitize_text_field(urldecode($_GET['message_type']));

        // Validate the message type against a set of allowed values
        $allowed_message_types = ['success', 'error', 'info', 'warning'];
        if (!in_array($message_type, $allowed_message_types)) {
            $message_type = 'info';
        }

        // Display the status message as an admin notice
        nppp_display_admin_notice($message_type, $status_message, false);
    }

    // Retrieve the Nginx cache path option
    $options = get_option('nginx_cache_settings');
    $nginx_cache_path = isset($options['nginx_cache_path']) ? $options['nginx_cache_path'] : '';

    // Check if the form has been submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
        // Verify nonce
        if (isset($_POST['nginx_cache_settings_nonce'])) {
            // Sanitize the nonce input
            $nonce = sanitize_text_field(wp_unslash($_POST['nginx_cache_settings_nonce']));

            // Verify the nonce
            if (wp_verify_nonce($nonce, 'nginx_cache_settings_nonce')) {

                // Sanitize and validate the submitted values
                $new_settings = nppp_nginx_cache_settings_sanitize($_POST['nginx_cache_settings']);

                // Check if there are any settings errors
                $errors = get_settings_errors('nppp_nginx_cache_settings_group');

                // If there are no sanitize errors, proceed to update the settings
                if (empty($errors)) {
                    // Update the settings with the new values
                    update_option('nginx_cache_settings', $new_settings);

                    // Show success message
                    echo '<div class="updated"><p>Settings saved successfully!</p></div>';
                } else {
                    // Display settings errors
                    foreach ($errors as $error) {
                        echo '<div class="error"><p>' . esc_html($error['message']) . '</p></div>';
                    }
                }
            } else {
                // Nonce verification failed
                wp_die('Nonce verification failed');
            }
        }
    }

    // Display the settings form
    ?>
    <div class="wrap">
        <div class="nppp-header-content">
            <div class="nppp-img-container">
                <img src="<?php echo esc_url( plugins_url( '../admin/img/logo.png', __FILE__ ) ); ?>">
            </div>
            <div class="nppp-cache-buttons">
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=nppp_purge_cache'), 'purge_cache_nonce')); ?>" class="nppp-button nppp-button-primary" id="nppp-purge-button">Purge All</a>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=nppp_preload_cache'), 'preload_cache_nonce')); ?>" class="nppp-button nppp-button-primary" id="nppp-preload-button">Preload All</a>
            </div>
        </div>
        <h2></h2>
        <div id="nppp-nginx-tabs">
            <div class="tab-header-container">
                <ul>
                    <li><a href="#settings"><?php echo do_shortcode('[nppp_svg_icon icon="settings" class="tab-icon" size="24px"]'); ?> <span class="tab-text">Settings</span></a></li>
                    <li><a href="#status"><?php echo do_shortcode('[nppp_svg_icon icon="status" class="tab-icon" size="24px"]'); ?> <span class="tab-text">Status</span></a></li>
                    <li><a href="#premium"><?php echo do_shortcode('[nppp_svg_icon icon="advanced" class="tab-icon" size="24px"]'); ?> <span class="tab-text">Advanced</span></a></li>
                    <li><a href="#help"><?php echo do_shortcode('[nppp_svg_icon icon="help" class="tab-icon" size="24px"]'); ?> <span class="tab-text">Help</span></a></li>
                </ul>
            </div>
            <div id="settings" class="tab-content active">
                <form method="post" action="">
                    <?php
                    // Add nonce field
                    wp_nonce_field('nginx_cache_settings_nonce', 'nginx_cache_settings_nonce');
                    ?>
                    <table class="form-table">
                        <!-- Start Purge Options Section -->
                        <tr valign="top">
                            <th scope="row" style="padding: 0; padding-top: 15px;"><h3 style="margin: 0; padding: 0;">Purge Options</h3></th>
                            <td style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <td colspan="2" style="padding-left: 0; margin: 0;"><hr class="nppp-separator" style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-admin-site"></span> Nginx Cache Directory</th>
                            <td>
                                <?php nppp_nginx_cache_path_callback(); ?>
                                <p class="description">Please provide the complete NGINX cache directory path required for plugin operations.</p>
                                <p class="description">The cache directory is essential for the proper functioning of cache purge and preload actions.</p>
                                <p class="description">It is crucial that the PHP-FPM process owner has both read and write permissions to this directory.</p>
                                <p class="description">Without these permissions, the plugin will be unable to purge or preload the cache effectively.</p>
                                <p class="cache-path-plugin-note">
                                    <span style="color: red;">NOTE:</span> The plugin author explicitly disclaims any liability for unintended deletions resulting<br>
                                    from incorrect directory entries. Users are solely responsible for verifying the directory's<br>
                                    accuracy prior to deletion. For safety, paths such as <strong>'/home'</strong> and other <strong>critical system paths</strong><br>
                                    are prohibited in default. Best practice using directories like <strong>'/dev/shm/'.</strong><br>
                                    or <strong>'/var/cache/'</strong>. Please refer HELP section for detailed information.
                                </p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-update"></span> Auto Purge</th>
                            <td>
                                <div class="nppp-auto-preload-container">
                                    <div class="nppp-onoffswitch-autopurge">
                                        <?php nppp_nginx_cache_purge_on_update_callback(); ?>
                                    </div>
                                </div>
                                <p class="description">Enabling this feature ensures that whenever you make changes to the content of a <strong>POST/PAGE</strong><br></p>
                                <p class="description">on your website, the cached version of that specific <strong>POST/PAGE</strong> is automatically cleared.</p>
                            </td>
                        </tr>
                        <!-- Start Preload Options Section -->
                        <tr valign="top">
                            <th scope="row" style="padding: 0; padding-top: 15px;"><h3 style="margin: 0; padding: 0;">Preload Options</h3></th>
                            <td style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <td colspan="2" style="padding-left: 0; margin: 0;"><hr class="nppp-separator" style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-dashboard"></span> CPU Usage Limit (%)</th>
                            <td>
                                <?php nppp_nginx_cache_cpu_limit_callback(); ?>
                                <p class="description">Enter the CPU usage limit for preload operation. <br> Please note that Preload action is CPU intensive task and could cause high server loads. <br> You need "cpulimit" installed on your system and higly recommended (10-100%).</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-no"></span> Exclude Endpoints</th>
                            <td>
                                <?php nppp_nginx_cache_reject_regex_callback(); ?>
                                <p class="description">Enter a regex pattern to exclude endpoints from being cached while Preloading. Use | as a delimeter for new rules.</p>
                                <p class="description">Default regex pattern triggers caching only static pages as much as possible to reduce CPU load and fastest Preload times.</p>
                                <button id="nginx-regex-reset-defaults" class="button nginx-reset-regex-button">Reset Default Regex</button>
                                <p class="description">Click the button to reset default regex.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-admin-generic"></span> Limit Rate</th>
                            <td>
                                 <?php nppp_nginx_cache_limit_rate_callback(); ?>
                                 <p class="description">Enter a limit rate for preload action in KB/Sec. <br> Preventing excessive bandwidth usage and avoiding overwhelming the server.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-hourglass"></span> Wait Time</th>
                            <td>
                                 <?php nppp_nginx_cache_wait_request_callback(); ?>
                                 <p class="description">Wait the specified number of seconds between the retrievals. <br></p>
                                 <p class="description">Use of this option is recommended, as it lightens the server load by making the requests less frequent. <br></p>
                                 <p class="description">Higher values dramatically increase cache preload times, while lowering the value can increase server load (CPU, Memory, Network) .<br></p>
                                 <p class="description">Adjust the values to find the optimal balance based on your desired server resource allocation. <br></p>
                                 <p class="description">If you face unexpected permission issues, try incrementally increasing the value, taking small steps each time. <br></p>
                                 <p class="description">Default: 1 second, 0 Disabled <br></p>
                            </td>
                        </tr>
                        <!-- Start Advanced Options Section -->
                        <tr valign="top">
                            <th scope="row" style="padding: 0; padding-top: 15px;"><h3 style="margin: 0; padding: 0;">Advanced Options</h3></th>
                            <td style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <td colspan="2" style="padding-left: 0; margin: 0;"><hr class="nppp-separator" style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-clock"></span> WP Schedule Cache</th>
                            <td>
                                <div class="nppp-auto-preload-container">
                                    <div class="nppp-onoffswitch-preload">
                                        <?php nppp_nginx_cache_schedule_callback(); ?>
                                    </div>
                                </div>
                                <div class="nppp-select-wrapper">
                                    <div class="nppp-cron-event-select-container">
                                        <select name="cron_event" id="nppp_cron_event" class="nppp-cron-event-select">
                                            <option value="" disabled selected>On Every</option>
                                            <option value="daily">Every Day</option>
                                            <option value="weekly">Every Week</option>
                                            <option value="monthly">Every Month</option>
                                        </select>
                                    </div>
                                    <div class="nppp-time-select-container">
                                        <div class="nppp-input-group">
                                            <input id="nppp_datetimepicker1Input" type="text" placeholder="Time"/>
                                            <div class="nppp-input-group-append">
                                                <button id="nginx-cache-schedule-set" class="button nginx-cache-schedule-set-button">
                                                    <span class="nppp-tooltip">SET CRON<span class="nppp-tooltiptext">Click to set <br> cron schedule</span></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <ul class="scheduled-events-list">
                                    <?php nppp_get_active_cron_events(); ?>
                                </ul>
                                <p class="description">Enable this feature to automatically schedule cache preloading task at specified intervals. This ensures that your website's cache is consistently updated, <br> optimizing performance and reducing server load.</p>
                                <p class="description">When enabled, your website will automatically refresh its cache according to the configured schedule, keeping content up-to-date and reducing load times for visitors.</p>
                                <p class="description">This feature is particularly useful for maintaining peak performance on dynamic websites with content that changes periodically. By scheduling caching tasks <br> you can ensure that your site remains fast and responsive, even during peak traffic periods.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-update"></span> Auto Preload</th>
                            <td>
                                <div class="nppp-auto-preload-container">
                                    <div class="nppp-onoffswitch-preload">
                                        <?php nppp_nginx_cache_auto_preload_callback(); ?>
                                    </div>
                                </div>
                                <p class="description">Enable this feature to automatically preload the cache after purging. This ensures fast page load times for visitors by proactively caching content.</p>
                                <p class="description">When enabled, your website's cache will preload with the latest content automatically after purge, ensuring quick loading times even for uncached pages.</p>
                                <p class="description">This feature is particularly useful for dynamic websites with frequently changing content.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-admin-network"></span> REST API</th>
                            <td>
                                <div class="nppp-auto-preload-container">
                                    <div class="nppp-onoffswitch-preload">
                                        <?php nppp_nginx_cache_api_callback(); ?>
                                    </div>
                                </div>
                                <?php nppp_nginx_cache_api_key_callback(); ?>
                                <p class="description">Enable this feature to for remote triggering of Purge and Preload actions. Generate your API Key automatically with single click.</p>
                                <p class="description">This premium functionality streamlines cache management, enhancing website performance and efficiency through seamless integration with external systems.</p>
                                <p class="description">The REST API capability ensures effortless cache control from anywhere, facilitating automated maintenance and optimization.</p>
                                <p class="description">You can copy your API Key and the full REST API URLs for Purge and Preload actions via above buttons with just a click.</p>
                            </td>
                        </tr>
                        <!-- Start Mail Options Section -->
                        <tr valign="top">
                            <th scope="row" style="padding: 0; padding-top: 15px;"><h3 style="margin: 0; padding: 0;">Mail Options</h3></th>
                            <td style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <td colspan="2" style="padding-left: 0; margin: 0;"><hr class="nppp-separator" style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-email-alt"></span> Send Email Notification</th>
                            <td>
                                <div class="nppp-onoffswitch">
                                    <?php nppp_nginx_cache_send_mail_callback(); ?>
                                </div>
                                <p class="description">Enable this feature to receive email notifications about essential plugin activities, ensuring you stay informed about preload actions, <br>cron task statuses, and general plugin updates..</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-email"></span> Email Address</th>
                            <td>
                                <?php nppp_nginx_cache_email_callback(); ?>
                                <p class="description">Enter an email address to get Nginx FastCGI Cache operation's notifications.</p>
                            </td>
                        </tr>
                        <!-- Start Logging Options Section -->
                        <tr valign="top">
                            <th scope="row" style="padding: 0; padding-top: 15px;"><h3 style="margin: 0; padding: 0;">Logging Options</h3></th>
                            <td style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <td colspan="2" style="padding-left: 0; margin: 0;"><hr class="nppp-separator" style="margin: 0; padding: 0;"></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><span class="dashicons dashicons-archive"></span> Logs</th>
                            <td>
                                <?php nppp_nginx_cache_logs_callback(); ?>
                                <button id="clear-logs-button" class="button nginx-clear-logs-button">Clear Logs</button>
                                <p class="description">Click the button to clear logs.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="submit" class="button-primary" value="Update Options">
                    </p>
                </form>
            </div>

            <div id="status" class="tab-content">
                <div id="status-content-placeholder"></div>
            </div>

            <div id="premium" class="tab-content">
                <div id="premium-content-placeholder"></div>
            </div>

            <div id="help" class="tab-content">
                <?php echo do_shortcode('[nppp_my_faq]'); ?>
            </div>
        </div>
    </div>
    <?php
}

// AJAX callback function to clear logs
function nppp_clear_nginx_cache_logs() {
    check_ajax_referer('nppp-clear-nginx-cache-logs', '_wpnonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to access this page.');
    }

    $wp_filesystem = nppp_initialize_wp_filesystem();
    if ($wp_filesystem === false) {
        wp_send_json_error('Failed to initialize WP Filesystem.');
    }

    $log_file_path = NGINX_CACHE_LOG_FILE;
    if ($wp_filesystem->exists($log_file_path)) {
        nppp_perform_file_operation($log_file_path, 'write', '');
        nppp_display_admin_notice('success', 'SUCCESS: Logs cleared successfully.');
    } else {
        nppp_display_admin_notice('error', 'ERROR LOGS: Log file not found.');
    }
    // Exit after AJAX functions to avoid extra output
    wp_die();
}

// Child AJAX callback function to retrieve log content after clear
function nppp_get_nginx_cache_logs() {
    check_ajax_referer('nppp-clear-nginx-cache-logs', '_wpnonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    $wp_filesystem = nppp_initialize_wp_filesystem();
    if ($wp_filesystem === false) {
        wp_send_json_error('Failed to initialize WP Filesystem.');
    }

    $log_file_path = NGINX_CACHE_LOG_FILE;
    if ($wp_filesystem->exists($log_file_path)) {
        // Read and return the content of the log file after clear
        $logs = nppp_perform_file_operation($log_file_path, 'read');
        wp_send_json_success($logs);
    } else {
        wp_send_json_error('ERROR LOGS: Log file not found.');
    }
}

// AJAX callback function to update send mail option
function nppp_update_send_mail_option() {
    // Verify nonce
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'nppp-update-send-mail-option')) {
            wp_send_json_error('Nonce verification failed.');
        }
    } else {
        wp_send_json_error('Nonce is missing.');
    }

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Sanitize the posted option value
    $send_mail = isset($_POST['send_mail']) ? sanitize_text_field(wp_unslash($_POST['send_mail'])) : '';

    // Get the current options
    $current_options = get_option('nginx_cache_settings', array());

    // Update the specific option within the array
    $current_options['nginx_cache_send_mail'] = $send_mail;

    // Save the updated options
    $updated = update_option('nginx_cache_settings', $current_options);

    // Check if option is updated successfully
    if ($updated) {
        wp_send_json_success('Settings saved successfully!');
    } else {
        wp_send_json_error('Error updating option.');
    }
}

// AJAX callback function to update auto preload option
function nppp_update_auto_preload_option() {
    // Verify nonce
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'nppp-update-auto-preload-option')) {
            wp_send_json_error('Nonce verification failed.');
        }
    } else {
        wp_send_json_error('Nonce is missing.');
    }

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Get the posted option value and sanitize it
    $auto_preload = isset($_POST['auto_preload']) ? sanitize_text_field(wp_unslash($_POST['auto_preload'])) : '';

    // Get the current options
    $current_options = get_option('nginx_cache_settings', array());

    // Update the specific option within the array
    $current_options['nginx_cache_auto_preload'] = $auto_preload;

    // Save the updated options
    $updated = update_option('nginx_cache_settings', $current_options);

    // Check if option is updated successfully
    if ($updated) {
        wp_send_json_success('Option updated successfully.');
    } else {
        wp_send_json_error('Error updating option.');
    }
}

// AJAX callback function to update auto purge option
function nppp_update_auto_purge_option() {
    // Verify nonce
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'nppp-update-auto-purge-option')) {
            wp_send_json_error('Nonce verification failed.');
        }
    } else {
        wp_send_json_error('Nonce is missing.');
    }

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Get the posted option value and sanitize it
    $auto_purge = isset($_POST['auto_purge']) ? sanitize_text_field(wp_unslash($_POST['auto_purge'])) : '';

    // Get the current options
    $current_options = get_option('nginx_cache_settings', array());

    // Update the specific option within the array
    $current_options['nginx_cache_purge_on_update'] = $auto_purge;

    // Save the updated options
    $updated = update_option('nginx_cache_settings', $current_options);

    // Check if option is updated successfully
    if ($updated) {
        wp_send_json_success('Option updated successfully.');
    } else {
        wp_send_json_error('Error updating option.');
    }
}

// AJAX callback function to update cache schedule option
function nppp_update_cache_schedule_option() {
    // Verify nonce
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'nppp-update-cache-schedule-option')) {
            wp_send_json_error('Nonce verification failed.');
        }
    } else {
        wp_send_json_error('Nonce is missing.');
    }

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Get the posted option value and sanitize it
    $cache_schedule = isset($_POST['cache_schedule']) ? sanitize_text_field(wp_unslash($_POST['cache_schedule'])) : '';

    // Initialize variables to track the operation status
    $unscheduled_successfully = false;
    $no_existing_event_found = false;

    // Check if the schedule option is set to 'no' or the feature is disabled
    if ($cache_schedule === 'no' || $cache_schedule === '') {
        // Check if there's already a scheduled event with the same hook
        $existing_timestamp = wp_next_scheduled('npp_cache_preload_event');

        // If there's an existing scheduled event, clear it
        if ($existing_timestamp) {
            $cleared = wp_clear_scheduled_hook('npp_cache_preload_event');
            if ($cleared) {
                $unscheduled_successfully = true;
            }
        } else {
            $no_existing_event_found = true;
        }
    }

    // Get the current options
    $current_options = get_option('nginx_cache_settings');

    // Update the specific option within the array
    $current_options['nginx_cache_schedule'] = $cache_schedule;

    // Save the updated options
    $updated = update_option('nginx_cache_settings', $current_options);

    // Check if option is updated successfully
    if ($updated) {
        if ($unscheduled_successfully) {
            wp_send_json_success('Option updated successfully. Unschedule success.');
        } elseif ($no_existing_event_found) {
            wp_send_json_success('Option updated successfully. No event found.');
        } else {
            wp_send_json_success('Option updated successfully.');
        }
    } else {
        wp_send_json_error('Error updating option.');
    }
}

// AJAX callback function to update api key option
function nppp_update_api_key_option() {
    // Verify nonce
    check_ajax_referer('nppp-update-api-key-option', '_wpnonce');

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Generate new API key
    $new_api_key = bin2hex(random_bytes(32));
    // Get the current options
    $current_options = get_option('nginx_cache_settings');
    // Update the specific option within the array
    $current_options['nginx_cache_api_key'] = $new_api_key;
    // Save the updated options
    $updated = update_option('nginx_cache_settings', $current_options);

    // Check if option is updated successfully
    if ($updated) {
        // Return the new key as the AJAX response
        wp_send_json_success($new_api_key);
    } else {
        // Return an error response if updating the option fails
        wp_send_json_error('Error updating option.');
    }
}

// AJAX callback function to copy api key
function nppp_update_api_key_copy_value() {
    // Verify nonce
    check_ajax_referer('nppp-update-api-key-copy-value', '_wpnonce');

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Get the current options
    $options = get_option('nginx_cache_settings');

    // Check if the retrieval was successful
    if ($options === false || !is_array($options)) {
        // Error handling if the option retrieval fails
        wp_send_json_error('Failed to retrieve the API key.');
    }

    // Get the API key option value
    $api_key = isset($options['nginx_cache_api_key']) ? $options['nginx_cache_api_key'] : '';

    // Return the API key in the AJAX response
    wp_send_json_success(array('api_key' => $api_key));
}

// AJAX callback function to update REST API option
function nppp_update_api_option() {
    // Verify nonce
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'nppp-update-api-option')) {
            wp_send_json_error('Nonce verification failed.');
        }
    } else {
        wp_send_json_error('Nonce is missing.');
    }

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Get the posted option value and sanitize it
    $nppp_api = isset($_POST['nppp_api']) ? sanitize_text_field(wp_unslash($_POST['nppp_api'])) : '';

    // Get the current options
    $current_options = get_option('nginx_cache_settings');

    // Update the specific option within the array
    $current_options['nginx_cache_api'] = $nppp_api;

    // Save the updated options
    $updated = update_option('nginx_cache_settings', $current_options);

    // Check if option is updated successfully
    if ($updated) {
        wp_send_json_success('success');
    } else {
        wp_send_json_error('Error updating option.');
    }
}

// AJAX callback function to update api key option
function nppp_update_default_reject_regex_option() {
    // Verify nonce
    check_ajax_referer('nppp-update-default-reject-regex-option', '_wpnonce');

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Get default reject regex and prepeare it
    $default_reject_regex = nppp_fetch_default_reject_regex();
    //$reject_regex = preg_replace('/\\\\+/', '\\', $default_reject_regex);
    // Get the current options
    $current_options = get_option('nginx_cache_settings');
    // Update the specific option within the array
    $current_options['nginx_cache_reject_regex'] = $default_reject_regex;
    // Save the option
    update_option('nginx_cache_settings', $current_options);

    // Return the new key as the AJAX response
    wp_send_json_success($default_reject_regex);
}

// AJAX callback function to copy rest api curl purge url
function nppp_rest_api_purge_url_copy() {
    // Verify nonce
    check_ajax_referer('nppp-rest-api-purge-url-copy', '_wpnonce');

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Get Plugin settings and set default API Key
    $options = get_option('nginx_cache_settings');
    $default_api_key = bin2hex(random_bytes(32));

    // Construct the REST API purge URL
    $fdomain = get_site_url();
    $rest_api_route_purge = 'wp-json/nppp_nginx_cache/v2/purge';
    $rest_api_purge_url = $fdomain . '/' . $rest_api_route_purge;
    // Create the JSON data string with the API key
    $api_key = isset($options['nginx_cache_api_key']) ? $options['nginx_cache_api_key'] : $default_api_key;
    $json_data = '{"api_key": "' . $api_key . '"}';
    // Construct the CURL command string
    $curl_command = 'curl -X POST -H "Content-Type: application/json" -d \'' . $json_data . '\' ' . $rest_api_purge_url;
    // Return the rest api purge curl url the AJAX response
    wp_send_json_success($curl_command);
}

// AJAX callback function to copy rest api curl preload url
function nppp_rest_api_preload_url_copy() {
    // Verify nonce
    check_ajax_referer('nppp-rest-api-preload-url-copy', '_wpnonce');

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to update this option.');
    }

    // Get Plugin settings and set default API Key
    $options = get_option('nginx_cache_settings');
    $default_api_key = bin2hex(random_bytes(32));

    // Construct the REST API preload URL
    $fdomain = get_site_url();
    $rest_api_route_preload = 'wp-json/nppp_nginx_cache/v2/preload';
    $rest_api_preload_url = $fdomain . '/' . $rest_api_route_preload;
    // Create the JSON data string with the API key
    $api_key = isset($options['nginx_cache_api_key']) ? $options['nginx_cache_api_key'] : $default_api_key;
    $json_data = '{"api_key": "' . $api_key . '"}';
    // Construct the CURL command string
    $curl_command = 'curl -X POST -H "Content-Type: application/json" -d \'' . $json_data . '\' ' . $rest_api_preload_url;
    // Return the rest api preload curl url the AJAX response
    wp_send_json_success($curl_command);
}

// Define the AJAX handler function to save the cron expression
function nppp_get_save_cron_expression() {
    // Verify nonce to ensure the request is coming from a trusted source
    if (isset($_POST['_wpnonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'nppp-get-save-cron-expression')) {
            wp_send_json_error('Nonce verification failed.');
        }
    } else {
        wp_send_json_error('Nonce is missing.');
    }

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to access this page.');
    }

    // Get the cron frequency and time from the AJAX request and sanitize them
    $cron_freq = isset($_POST['nppp_cron_freq']) ? sanitize_text_field(wp_unslash($_POST['nppp_cron_freq'])) : '';
    $time = isset($_POST['nppp_time']) ? sanitize_text_field(wp_unslash($_POST['nppp_time'])) : '';

    // Validate the cron frequency value before saving the option
    if (!in_array($cron_freq, array('daily', 'weekly', 'monthly'))) {
        // If the cron frequency is not one of the allowed values, return an error response
        wp_send_json_error('Invalid cron frequency value.');
    }

    // Validate the time format (HH:mm)
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
        // If the time format is invalid, return an error response
        wp_send_json_error('Invalid time format.');
    }

    // Save the cron frequency and time as needed
    $cron_expression = $cron_freq . '|' . $time;
    update_option('nginx_cache_schedule_value', $cron_expression);

    // Check if option update was successful
    $updated_value = get_option('nginx_cache_schedule_value');
    if ($updated_value === $cron_expression) {
        // Get the WordPress timezone string
        $timezone_string = wp_timezone_string();

        // Get all scheduled events
        $events = _get_cron_array();

        // Check if timezone string is set
        if (empty($timezone_string)) {
            wp_send_json_error('Timezone not set in WordPress options!');
        }

        // Check events are empty
        if (empty($events)) {
            wp_send_json_error('No active scheduled events found!');
        }

        // If the option was successfully updated, the timezone is set,
        // and there are no active scheduled events, create a new schedule event
        nppp_create_scheduled_events($cron_expression);

        // If the option was successfully updated and a new WP cron is scheduled
        // without error, then send a success response
        wp_send_json_success('New cron event scheduled successfully.');
    } else {
        // If there was an issue updating the option, send an error response
        wp_send_json_error('Error saving cron expression.');
    }
}

// Callback function to display the settings section description.
function nppp_nginx_cache_settings_section_callback() {
    echo 'Configure the settings for FastCGI Cache.';
}

// Callback function to display the input field for Nginx Cache Path setting
function nppp_nginx_cache_path_callback() {
    $options = get_option('nginx_cache_settings');
    $default_cache_path = '/dev/shm/change-me-now';
    echo "<input type='text' id='nginx_cache_path' name='nginx_cache_settings[nginx_cache_path]' value='" . esc_attr($options['nginx_cache_path'] ?? $default_cache_path) . "' class='regular-text' />";
}

// Callback function to display the input field for Email Address setting
function nppp_nginx_cache_email_callback() {
    $options = get_option('nginx_cache_settings');
    $default_email = 'your-email@example.com';
    echo "<input type='text' id='nginx_cache_email' name='nginx_cache_settings[nginx_cache_email]' value='" . esc_attr($options['nginx_cache_email'] ?? $default_email) . "' class='regular-text' />";
}

// Callback function to display the input field for CPU Usage Limit setting
function nppp_nginx_cache_cpu_limit_callback() {
    $options = get_option('nginx_cache_settings');
    $default_cpu_limit = 50;
    echo "<input type='number' id='nginx_cache_cpu_limit' name='nginx_cache_settings[nginx_cache_cpu_limit]' min='10' max='100' value='" . esc_attr($options['nginx_cache_cpu_limit'] ?? $default_cpu_limit) . "' class='small-text' />";
}

// Callback function to display the input field for Per Request Wait Time setting
function nppp_nginx_cache_wait_request_callback() {
    $options = get_option('nginx_cache_settings');
    $default_wait_time = 1;
    echo "<input type='number' id='nginx_cache_wait_request' name='nginx_cache_settings[nginx_cache_wait_request]' min='0' max='60' value='" . esc_attr($options['nginx_cache_wait_request'] ?? $default_wait_time) . "' class='small-text' />";
}

// Callback function to display the checkbox for Send Email Notification setting
function nppp_nginx_cache_send_mail_callback() {
    $options = get_option('nginx_cache_settings');
    $send_mail_checked = isset($options['nginx_cache_send_mail']) && $options['nginx_cache_send_mail'] === 'yes' ? 'checked="checked"' : '';
    ?>
    <input type="checkbox" name="nginx_cache_settings[nginx_cache_send_mail]" class="nppp-onoffswitch-checkbox" value='yes' id="nginx_cache_send_mail" <?php echo esc_attr($send_mail_checked); ?>>
    <label class="nppp-onoffswitch-label" for="nginx_cache_send_mail">
        <span class="nppp-onoffswitch-inner">
            <span class="nppp-off">OFF</span>
            <span class="nppp-on">ON</span>
        </span>
        <span class="nppp-onoffswitch-switch"></span>
    </label>
    <?php
}

// Callback function for the nginx_cache_auto_preload field
function nppp_nginx_cache_auto_preload_callback() {
    $options = get_option('nginx_cache_settings');
    $auto_preload_checked = isset($options['nginx_cache_auto_preload']) && $options['nginx_cache_auto_preload'] === 'yes' ? 'checked="checked"' : '';

    ?>
    <input type="checkbox" name="nginx_cache_settings[nginx_cache_auto_preload]" class="nppp-onoffswitch-checkbox-preload" value="yes" id="nginx_cache_auto_preload" <?php echo esc_attr($auto_preload_checked); ?>>
    <label class="nppp-onoffswitch-label-preload" for="nginx_cache_auto_preload">
        <span class="nppp-onoffswitch-inner-preload">
            <span class="nppp-off-preload">OFF</span>
            <span class="nppp-on-preload">ON</span>
        </span>
        <span class="nppp-onoffswitch-switch-preload"></span>
    </label>
    <?php
}

// Callback function for the nginx_cache_schedule field
function nppp_nginx_cache_schedule_callback() {
    $options = get_option('nginx_cache_settings');
    $cache_schedule_checked = isset($options['nginx_cache_schedule']) && $options['nginx_cache_schedule'] === 'yes' ? 'checked="checked"' : '';

    ?>
    <input type="checkbox" name="nginx_cache_settings[nginx_cache_schedule]" class="nppp-onoffswitch-checkbox-schedule" value="yes" id="nginx_cache_schedule" <?php echo esc_attr($cache_schedule_checked); ?>>
    <label class="nppp-onoffswitch-label-schedule" for="nginx_cache_schedule">
        <span class="nppp-onoffswitch-inner-schedule">
            <span class="nppp-off-schedule">OFF</span>
            <span class="nppp-on-schedule">ON</span>
        </span>
        <span class="nppp-onoffswitch-switch-schedule"></span>
    </label>
    <?php
}

// Callback function for the nginx_cache_purge_on_update field
function nppp_nginx_cache_purge_on_update_callback() {
    $options = get_option('nginx_cache_settings');
    $auto_purge_checked = isset($options['nginx_cache_purge_on_update']) && $options['nginx_cache_purge_on_update'] === 'yes' ? 'checked="checked"' : '';

    ?>
    <input type="checkbox" name="nginx_cache_settings[nginx_cache_purge_on_update]" class="nppp-onoffswitch-checkbox-autopurge" value="yes" id="nginx_cache_purge_on_update" <?php echo esc_attr($auto_purge_checked); ?>>
    <label class="nppp-onoffswitch-label-autopurge" for="nginx_cache_purge_on_update">
        <span class="nppp-onoffswitch-inner-autopurge">
            <span class="nppp-off-autopurge">OFF</span>
            <span class="nppp-on-autopurge">ON</span>
        </span>
        <span class="nppp-onoffswitch-switch-autopurge"></span>
    </label>
    <?php
}

// Callback function to display the Reject Regex field
function nppp_nginx_cache_reject_regex_callback() {
    $options = get_option('nginx_cache_settings');
    $default_reject_regex = nppp_fetch_default_reject_regex();
    $default_reject_regex = isset($options['nginx_cache_reject_regex']) ? $options['nginx_cache_reject_regex'] : $default_reject_regex;
    $reject_regex = preg_replace('/\\\\+/', '\\', $default_reject_regex);
    echo "<textarea id='nginx_cache_reject_regex' name='nginx_cache_settings[nginx_cache_reject_regex]' rows='3' cols='50' class='large-text'>" . esc_textarea($reject_regex) . "</textarea>";
}

// Callback function to display the Logs field
function nppp_nginx_cache_logs_callback() {
    $log_file_path = NGINX_CACHE_LOG_FILE;
    nppp_perform_file_operation($log_file_path, 'create');
    if (file_exists($log_file_path) && is_readable($log_file_path)) {
        // Read the log file into an array of lines
        $lines = file($log_file_path);
        // Get the latest 5 lines
        if (is_array($lines)) {
            $latest_lines = array_slice($lines, -5);

            // Remove leading tab spaces and spaces from each line
            $cleaned_lines = array_map(function($line) {
                return trim($line);
            }, $latest_lines);
            ?>
            <div class="logs-container">
                <?php
                // Output the latest 5 lines
                foreach ($cleaned_lines as $line) {
                    if (!empty($line)) {
                        // Extract timestamp and message
                        preg_match('/^\[(.*?)\]\s*(.*?)$/', $line, $matches);
                        $timestamp = isset($matches[1]) ? $matches[1] : '';
                        $message = isset($matches[2]) ? $matches[2] : '';

                        // Apply different CSS classes based on whether it's an error line or not
                        $class = strpos($message, 'ERROR') !== false ? 'error-line' : (strpos($message, 'SUCCESS') !== false ? 'success-line' : 'normal-line');

                        // Output the line with the appropriate CSS class
                        echo '<div class="' . esc_attr($class) . '"><span class="timestamp">' . esc_html($timestamp) . '</span> ' . esc_html($message) . '</div>';
                    }
                }
                ?>
                <div class="cursor blink">#</div>
            </div>
            <?php
        } else {
             echo '<p>Unable to read log file. Please check file permissions.</p>';
        }
    } else {
        echo '<p>Log file not found or is not readable.</p>';
    }
}

// Callback function to display the input field for Limit Rate setting.
function nppp_nginx_cache_limit_rate_callback() {
    $options = get_option('nginx_cache_settings');
    $default_limit_rate = 1024;
    echo "<input type='number' id='nginx_cache_limit_rate' name='nginx_cache_settings[nginx_cache_limit_rate]' value='" . esc_attr($options['nginx_cache_limit_rate'] ?? $default_limit_rate) . "' class='small-text' />";
}

// Fetch default Reject Regex
function nppp_fetch_default_reject_regex() {
    $wp_filesystem = nppp_initialize_wp_filesystem();

    if ($wp_filesystem === false) {
        wp_die('Failed to initialize WP Filesystem.');
    }

    $rr_txt_file = plugin_dir_path(__FILE__) . '../includes/reject_regex.txt';
    if ($wp_filesystem->exists($rr_txt_file)) {
        $file_content = nppp_perform_file_operation($rr_txt_file, 'read');
        $regex_match = preg_match('/\$reject_regex\s*=\s*[\'"](.+?)[\'"];/i', $file_content, $matches);
        if ($regex_match && isset($matches[1])) {
            return $matches[1];
        }
    } else {
        wp_die('File does not exist: ' . esc_html($rr_txt_file));
    }
    return '';
}

// Callback function for REST API Key
function nppp_nginx_cache_api_key_callback() {
    $options = get_option('nginx_cache_settings');
    $default_api_key = bin2hex(random_bytes(32));
    $api_key = isset($options['nginx_cache_api_key']) ? $options['nginx_cache_api_key'] : $default_api_key;
    echo "<input type='text' id='nginx_cache_api_key' name='nginx_cache_settings[nginx_cache_api_key]' value='" . esc_attr($api_key) . "' class='regular-text' />";

    echo "<div style='display: block; align-items: baseline;'>";
    echo "<button id='api-key-button' class='button nginx-api-key-button'>Generate API Key</button>";
    echo "<div style='display: flex; align-items: baseline; margin-top: 8px; margin-bottom: 8px;'>";
    echo "<p class='description' id='nppp-api-key' style='margin-right: 10px;'><span class='nppp-tooltip'>API Key<span class='nppp-tooltiptext'>Click to copy API Key</span></span></p>";
    echo "<p class='description' id='nppp-purge-url' style='margin-right: 10px;'><span class='nppp-tooltip'>Purge URL<span class='nppp-tooltiptext'>Click to copy full REST API URL for Purge</span></span></p>";
    echo "<p class='description' id='nppp-preload-url'><span class='nppp-tooltip'>Preload URL<span class='nppp-tooltiptext'>Click to copy full REST API URL for Preload</span></span></p>";
    echo "</div>";
    echo "</div>";
}

// Callback function for REST API
function nppp_nginx_cache_api_callback() {
    $options = get_option('nginx_cache_settings');
    $api_checked = isset($options['nginx_cache_api']) && $options['nginx_cache_api'] === 'yes' ? 'checked="checked"' : '';

    ?>
    <input type="checkbox" name="nginx_cache_settings[nginx_cache_api]" class="nppp-onoffswitch-checkbox-api" value='yes' id="nginx_cache_api" <?php echo esc_attr($api_checked); ?>>
    <label class="nppp-onoffswitch-label-api" for="nginx_cache_api">
        <span class="nppp-onoffswitch-inner-api">
            <span class="nppp-off-api">OFF</span>
            <span class="nppp-on-api">ON</span>
        </span>
        <span class="nppp-onoffswitch-switch-api"></span>
    </label>
    <?php
}

// Sanitize inputs
function nppp_nginx_cache_settings_sanitize($input) {
    $sanitized_input = array();

    // Sanitize and validate cache path
    if (!empty($input['nginx_cache_path'])) {
        // Validate the path
        $validation_result = nppp_validate_path($input['nginx_cache_path'], false);

        // Check the validation result
        if ($validation_result === true) {
            $sanitized_input['nginx_cache_path'] = sanitize_text_field($input['nginx_cache_path']);
        } else {
            // Handle different validation outcomes
            switch ($validation_result) {
                case 'critical_path':
                    $error_message = 'ERROR PATH: The specified Nginx Cache Directory appears to be a critical system directory or a first-level directory, which is not allowed.';
                    break;
                case 'directory_not_exist_or_readable':
                    $error_message = 'ERROR PATH: The specified Nginx Cache Directory does not exist. Please verify the Nginx Cache Directory.';
                    break;
                default:
                    $error_message = 'ERROR PATH: An invalid path was provided for the Nginx Cache Directory. Please provide a valid directory path.';
            }

            // Add settings error
            add_settings_error(
                'nppp_nginx_cache_settings_group',
                'invalid_path',
                $error_message,
                'error'
            );

            // Log error message
            $log_message = $error_message;
            $log_file_path = NGINX_CACHE_LOG_FILE;
            nppp_perform_file_operation($log_file_path, 'create');
            if (!empty($log_file_path)) {
                nppp_perform_file_operation($log_file_path, 'append', '[' . current_time('Y-m-d H:i:s') . '] ' . $log_message);
            }
        }
    }

    // Sanitize and validate email
    if (!empty($input['nginx_cache_email'])) {
        // Validate email format
        $email = sanitize_email($input['nginx_cache_email']);
        if (is_email($email)) {
            $sanitized_input['nginx_cache_email'] = $email;
        } else {
            // Email is not valid, add error message
            add_settings_error(
                'nppp_nginx_cache_settings_group',
                'invalid-email',
                'ERROR OPTION: Please enter a valid email address.',
                'error'
            );
            // Log error message
            $log_message = 'ERROR OPTION: Please enter a valid email address.';
            $log_file_path = NGINX_CACHE_LOG_FILE;
            nppp_perform_file_operation($log_file_path, 'create');
            if (!empty($log_file_path)) {
                nppp_perform_file_operation($log_file_path, 'append', '[' . current_time('Y-m-d H:i:s') . '] ' . $log_message);
            }
        }
    }

    // Sanitize and validate CPU limit
    if (!empty($input['nginx_cache_cpu_limit'])) {
        // Validate CPU limit
        $cpu_limit = intval($input['nginx_cache_cpu_limit']);
        if ($cpu_limit >= 10 && $cpu_limit <= 100) {
            $sanitized_input['nginx_cache_cpu_limit'] = $cpu_limit;
        } else {
            // CPU limit is not within range, add error message
            add_settings_error(
                'nppp_nginx_cache_settings_group',
                'invalid-cpu-limit',
                'Please enter a CPU limit between 10 and 100.',
                'error'
            );
            // Log error message
            $log_message = 'ERROR: Please enter a CPU limit between 10 and 100.';
            $log_file_path = NGINX_CACHE_LOG_FILE;
            nppp_perform_file_operation($log_file_path, 'create');
            if (!empty($log_file_path)) {
                nppp_perform_file_operation($log_file_path, 'append', '[' . current_time('Y-m-d H:i:s') . '] ' . $log_message);
            }
        }
    }

    // Sanitize and validate Per Request Wait Time
    if (isset($input['nginx_cache_wait_request'])) {
        // Validate Wait Time
        $wait_time = intval($input['nginx_cache_wait_request']);
        if ($wait_time >= 0 && $wait_time <= 60) {
            $sanitized_input['nginx_cache_wait_request'] = $wait_time;
        } else {
            // Wait Time is not within range, add error message
            add_settings_error(
                'nppp_nginx_cache_settings_group',
                'invalid-wait-time',
                'Please enter a Per Request Wait Time between 0 and 60 seconds.',
                'error'
            );
            // Log error message
            $log_message = 'ERROR: Please enter a Per Request Wait Time between 0 and 60 seconds.';
            $log_file_path = NGINX_CACHE_LOG_FILE;
            nppp_perform_file_operation($log_file_path, 'create');
            if (!empty($log_file_path)) {
                nppp_perform_file_operation($log_file_path, 'append', '[' . current_time('Y-m-d H:i:s') . '] ' . $log_message);
            }
        }
    }

    // Sanitize Reject Regex field
    if (!empty($input['nginx_cache_reject_regex'])) {
        //$sanitized_input['nginx_cache_reject_regex'] = $input['nginx_cache_reject_regex'];
        $sanitized_input['nginx_cache_reject_regex'] = preg_replace('/\\\\+/', '\\', $input['nginx_cache_reject_regex']);
    }

    // Sanitize Send Mail
    $sanitized_input['nginx_cache_send_mail'] = isset($input['nginx_cache_send_mail']) && $input['nginx_cache_send_mail'] === 'yes' ? 'yes' : 'no';

    // Sanitize Auto Preload
    $sanitized_input['nginx_cache_auto_preload'] = isset($input['nginx_cache_auto_preload']) && $input['nginx_cache_auto_preload'] === 'yes' ? 'yes' : 'no';

    // Sanitize Auto Purge
    $sanitized_input['nginx_cache_purge_on_update'] = isset($input['nginx_cache_purge_on_update']) && $input['nginx_cache_purge_on_update'] === 'yes' ? 'yes' : 'no';

     // Sanitize Cache Schedule
    $sanitized_input['nginx_cache_schedule'] = isset($input['nginx_cache_schedule']) && $input['nginx_cache_schedule'] === 'yes' ? 'yes' : 'no';

    // Sanitize REST API
    $sanitized_input['nginx_cache_api'] = isset($input['nginx_cache_api']) && $input['nginx_cache_api'] === 'yes' ? 'yes' : 'no';

    // Sanitize Limit Rate
    if (!empty($input['nginx_cache_limit_rate'])) {
        $sanitized_input['nginx_cache_limit_rate'] = sanitize_text_field($input['nginx_cache_limit_rate']);
    }

    // Sanitize REST API Key
    if (!empty($input['nginx_cache_api_key'])) {
        $api_key = $input['nginx_cache_api_key'];

        // Validate if the input is a 32-character hexadecimal string
        if (preg_match('/^[0-9a-fA-F]{64}$/', $api_key)) {
            // Input is a valid 32-character hexadecimal string, sanitize and store it
            $sanitized_input['nginx_cache_api_key'] = $api_key;
        } else {
            // API key is not valid, add error message
            add_settings_error(
                'nppp_nginx_cache_settings_group',
                'invalid-api-key',
                'ERROR API: Please enter a valid 64-character hexadecimal string for the API key.',
                'error'
            );
        }
    }

    return $sanitized_input;
}

// Validate the fastcgi cache path to prevent bad inputs as much as possible
function nppp_validate_path($path, $nppp_is_premium_purge = false) {
    // Initialize WP filesystem
    $wp_filesystem = nppp_initialize_wp_filesystem();
    if ($wp_filesystem === false) {
        return false;
    }

    // Define the default path for whitelist
    $default_path = '/dev/shm/change-me-now';

    // Check if the path is the default path and whitelist it
    if ($path === $default_path) {
        return true;
    }

    // Validate the path
    $pattern = '/^\/(?:[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)+)\/?$/';

    // Define critical system directories
    $critical_directories = array('/bin','/boot','/etc','/lib','/lib64','/media','/proc','/root','/sbin','/srv','/sys','/usr','/home','/mnt');

    // Check if the path starts with any critical directory
    foreach ($critical_directories as $dir) {
        if (strpos($path, $dir) === 0) {
            return 'critical_path';
        }
    }

    // Check if the path matches correct format
    if (!preg_match($pattern, $path)) {
        return 'critical_path';
    }

    // Now check if the path points to a file
    // For premium purge validation
    if ($nppp_is_premium_purge) {
        if (!$wp_filesystem->is_file($path)) {
            return 'file_not_found_or_not_readable';
        }
    } else {
        // Now check if the directory exists
        if (!$wp_filesystem->is_dir($path)) {
            return 'directory_not_exist_or_readable';
        }
    }

    return true;
}

// Function to reset plugin settings on deactivation
function nppp_reset_plugin_settings_on_deactivation() {
    delete_option('nginx_cache_settings');
    // Stop preload process status inspector event
    wp_clear_scheduled_hook('npp_cache_preload_status_event');
}

// Automatically update the default options when the plugin is activated or reactivated
function nppp_defaults_on_plugin_activation() {
    $new_api_key = bin2hex(random_bytes(32));

    // Define default options
    $default_options = array(
        'nginx_cache_path' => '/dev/shm/change-me-now',
        'nginx_cache_email' => 'your-email@example.com',
        'nginx_cache_cpu_limit' => 50,
        'nginx_cache_reject_regex' => nppp_fetch_default_reject_regex(),
        'nginx_cache_wait_request' => 1,
        'nginx_cache_limit_rate' => 1024,
    );

    // Update options
    update_option('nginx_cache_settings', $default_options);

    // Create the log file if it doesn't exist
    $log_file_path = NGINX_CACHE_LOG_FILE;
    if (!file_exists($log_file_path)) {
        $log_file_created = nppp_perform_file_operation($log_file_path, 'create');
        if (!$log_file_created) {
            // Log file creation failed, handle error accordingly
            error_log('Failed to create log file: ' . $log_file_path);
        }
    }
}
