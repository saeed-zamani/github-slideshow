<?php
/**
 * Plugin Name:       Gemini AI Chat Widget
 * Plugin URI:        https://example.com/gemini-chat-widget
 * Description:       Adds a floating chat widget powered by Google's Gemini AI for logged-in users.
 * Version:           1.0.0
 * Author:            AI Assistant
 * Author URI:        https://example.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gemini-chat-widget
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Include the Gemini API functions
require_once plugin_dir_path(__FILE__) . 'includes/gemini-api.php';

// Hook to add the admin menu
add_action('admin_menu', 'gemini_chat_add_admin_menu');

// Hook to register settings
add_action('admin_init', 'gemini_chat_settings_init');

// Hook to load the text domain
add_action('plugins_loaded', 'gemini_chat_load_textdomain');

function gemini_chat_add_admin_menu() {
    add_menu_page(
        'Gemini Chat Settings',      // Page title
        'Gemini Chat',               // Menu title
        'manage_options',            // Capability
        'gemini-chat-settings',      // Menu slug
        'gemini_chat_settings_page_html', // Callback function to display page content
        'dashicons-format-chat',     // Icon (optional)
        80                           // Position (optional)
    );
}

function gemini_chat_settings_init() {
    register_setting('gemini_chat_settings_group', 'gemini_chat_api_key', 'sanitize_text_field');

    add_settings_section(
        'gemini_chat_api_key_section',
        'API Key Configuration', // Title for the section
        null, // Callback for section description (optional)
        'gemini-chat-settings' // Page slug where this section will be shown
    );

    add_settings_field(
        'gemini_chat_api_key_field',
        'Google Gemini API Key', // Title for the field
        'gemini_chat_api_key_field_html', // Callback function to render the field
        'gemini-chat-settings', // Page slug
        'gemini_chat_api_key_section' // Section ID
    );
}

function gemini_chat_api_key_field_html() {
    $api_key = get_option('gemini_chat_api_key');
    ?>
    <input type="password" name="gemini_chat_api_key" id="gemini_chat_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
    <p class="description"><?php _e('Enter your Google Gemini API key.', 'gemini-chat-widget'); ?></p>
    <?php
}

function gemini_chat_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('gemini_chat_settings_group');
            do_settings_sections('gemini-chat-settings');
            submit_button('Save API Key');
            ?>
        </form>
    </div>
    <?php
}

function gemini_chat_load_textdomain() {
    load_plugin_textdomain('gemini-chat-widget', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Hook to output frontend elements
add_action('wp_footer', 'gemini_chat_output_frontend_elements');

// Hook to enqueue frontend assets
add_action('wp_enqueue_scripts', 'gemini_chat_enqueue_frontend_assets');

function gemini_chat_output_frontend_elements() {
    if (!is_user_logged_in()) {
        return;
    }
    ?>
    <div id="gemini-chat-floating-icon">Chat</div>
    <div id="gemini-chat-box-container">
        <div id="gemini-chat-box-header">
            <h3><?php _e('Gemini AI Chat', 'gemini-chat-widget'); ?></h3>
            <span id="gemini-chat-close-btn">&times;</span>
        </div>
        <div id="gemini-chat-messages-area">
            <!-- Messages will appear here -->
        </div>
        <div id="gemini-chat-input-area">
            <input type="text" id="gemini-chat-message-input" placeholder="<?php _e('Type your message...', 'gemini-chat-widget'); ?>">
            <button id="gemini-chat-send-btn"><?php _e('Send', 'gemini-chat-widget'); ?></button>
        </div>
    </div>
    <?php
}

function gemini_chat_enqueue_frontend_assets() {
    if (!is_user_logged_in()) {
        return;
    }

    wp_enqueue_style(
        'gemini-chat-style',
        plugin_dir_url(__FILE__) . 'css/gemini-chat-style.css',
        [], // Dependencies
        '1.0.0' // Version
    );

    wp_enqueue_script(
        'gemini-chat-frontend-js',
        plugin_dir_url(__FILE__) . 'js/gemini-chat-frontend.js',
        [], // Dependencies
        '1.0.0', // Version
        true // Load in footer
    );

    // Localize script for AJAX
    wp_localize_script('gemini-chat-frontend-js', 'geminiChatAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('gemini_chat_nonce_action')
    ]);
}

// AJAX Handler for sending messages
add_action('wp_ajax_send_gemini_message', 'gemini_chat_ajax_handle_send_message');

function gemini_chat_ajax_handle_send_message() {
    // 1. Security: Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gemini_chat_nonce_action')) {
        wp_send_json_error(['message' => __('Invalid security token.', 'gemini-chat-widget')]);
        return;
    }

    // 2. Security: Ensure user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in to chat.', 'gemini-chat-widget')]);
        return;
    }

    // 3. Retrieve and sanitize user message
    if (!isset($_POST['message']) || empty(trim($_POST['message']))) {
        wp_send_json_error(['message' => __('Message cannot be empty.', 'gemini-chat-widget')]);
        return;
    }
    $user_message = sanitize_textarea_field(stripslashes($_POST['message']));

    // 4. Retrieve and decode conversation history
    $conversation_history = [];
    if (isset($_POST['history'])) {
        $history_json = stripslashes($_POST['history']);
        $decoded_history = json_decode($history_json, true);
        // Basic validation: ensure $decoded_history is an array.
        // The gemini_chat_send_message_to_gemini function expects a specific format.
        // For now, we pass it as is if it's an array.
        if (is_array($decoded_history)) {
            $conversation_history = $decoded_history;
        } else {
            // Optionally handle malformed history, e.g., log or send an error
            // For now, we'll proceed with empty history if decoding fails or not an array
        }
    }
    
    // 5. Call the API function
    $gemini_response = gemini_chat_send_message_to_gemini($user_message, $conversation_history);

    // 6. Process the response
    if (is_wp_error($gemini_response)) {
        wp_send_json_error(['message' => $gemini_response->get_error_message()]);
    } else {
        wp_send_json_success(['reply' => $gemini_response]);
    }
    // wp_die(); // Not needed as wp_send_json_success/error call it.
}
?>
