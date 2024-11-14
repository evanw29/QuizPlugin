<?php
/**
 * Plugin Name: Quiz Plugin
 * Description: Quiz plugin
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include the Quiz Handler class
require_once plugin_dir_path(__FILE__) . 'includes/class-quiz-handler.php';

// Initialize the plugin
function init_quiz_plugin() {
    global $quiz_handler;
    $quiz_handler = new Quiz_Handler();
    
    // Register shortcode
    add_shortcode('quiz_form', 'display_quiz_form');
}
add_action('init', 'init_quiz_plugin');

// Enqueue necessary scripts
function enqueue_quiz_scripts() {
    wp_enqueue_style(
        'quiz-style',
        plugins_url('css/style.css', __FILE__)
    );
    
    wp_enqueue_script(
        'quiz-script',
        plugins_url('js/questions-script.js', __FILE__),
        array('jquery'),
        '1.0',
        true
    );
    
    wp_localize_script('quiz-script', 'quizAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('quiz_ajax_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_quiz_scripts');

// AJAX handler for form submission
function handle_quiz_submission() {
    error_log('Quiz submission received'); // Debug log
    
    // Check if nonce is present and valid
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quiz_ajax_nonce')) {
        error_log('Quiz error: Invalid nonce');
        wp_send_json_error('Security check failed');
        return;
    }
    
    if (!isset($_POST['responses']) || !is_array($_POST['responses'])) {
        error_log('Quiz error: No responses or invalid format. POST data: ' . print_r($_POST, true));
        wp_send_json_error('No valid responses received');
        return;
    }
    
    error_log('Quiz responses received: ' . print_r($_POST['responses'], true));
    
    global $quiz_handler;
    if (!$quiz_handler) {
        error_log('Quiz error: Quiz handler not initialized');
        wp_send_json_error('System error: Quiz handler not initialized');
        return;
    }
    
    // Hardcode user_id to 1
    $user_id = 1;
    $responses = $_POST['responses'];
    
    try {
        // Log attempt to save
        error_log('Attempting to save quiz for user ' . $user_id);
        
        $quiz_id = $quiz_handler->save_responses($user_id, $responses);
        
        if ($quiz_id) {
            error_log('Quiz saved successfully with ID: ' . $quiz_id);
            wp_send_json_success(array(
                'message' => 'Thank you! Your responses have been saved.',
                'quiz_id' => $quiz_id
            ));
        } else {
            error_log('Quiz error: Failed to get quiz ID after save');
            wp_send_json_error('There was an error saving your responses. Please try again.');
        }
    } catch (Exception $e) {
        error_log('Quiz error: Exception during save: ' . $e->getMessage());
        wp_send_json_error('An error occurred: ' . $e->getMessage());
    }
}

add_action('wp_ajax_handle_quiz_submission', 'handle_quiz_submission');
add_action('wp_ajax_nopriv_handle_quiz_submission', 'handle_quiz_submission');