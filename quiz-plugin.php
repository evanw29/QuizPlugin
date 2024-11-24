<?php
/**
 * Plugin Name: Quiz Plugin
 * Description: Quiz plugin for LTC
 * Author: Saad Sahi, Evan White
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include the Quiz Handler class
require_once plugin_dir_path(__FILE__) . 'includes/class-quiz-handler.php';

// Only load the admin dashboard code if an admin is present on site
function init_admin_dashboard() {
    if (is_admin()) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-admin-dashboard.php';
        $quiz_admin_dashboard = new Admin_Dashboard();
    }
}
add_action('init', 'init_admin_dashboard');

// Initialize the plugin
function init_quiz_plugin() {
    global $quiz_handler;
    $quiz_handler = new Quiz_Handler();

    // Register shortcode 'quiz_form' for use inside of WP page
    add_shortcode('quiz_form', 'display_quiz_form');

    // Register shortcode 'display_recommendations' for recommendations page
    add_shortcode('display_recommendations', 'display_recommendations_function');
}
add_action('init', 'init_quiz_plugin');

// Enqueue necessary scripts and styles
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

// Handle AJAX request to get personal questions
function handle_get_personal_questions() {
    check_ajax_referer('quiz_ajax_nonce', 'nonce');

    global $quiz_handler;
    $questions = $quiz_handler->get_personal_questions();

    // Display the personal info questions
    ob_start();
    ?>
    <div class="personal-info-section">
        <h3>Please tell us some information about yourself</h3>
        <?php foreach ($questions as $question): ?>
            <div class="question-group" data-question-id="<?php echo esc_attr($question->QuestionID); ?>">
                <label><?php echo esc_html($question->Prompt); ?></label>
                <div class="answers-group">
                <?php
                // Switch case for each question type
                switch($question->question_Type) {
                    case 'ComboBox':
                        ?>
                        <select
                            name="question_<?php echo esc_attr($question->QuestionID); ?>"
                            id="question_<?php echo esc_attr($question->QuestionID); ?>"
                            class="personal-info"
                            required
                        >
                            <option value="">Please select...</option>
                            <?php foreach ($question->answers as $answer): ?>
                                <option value="<?php echo esc_attr($answer->AnswerID); ?>">
                                    <?php echo esc_html($answer->answer_Text); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        break;

                    case 'Checkbox':
                        foreach ($question->answers as $answer): ?>
                            <div class="answer-option">
                                <input
                                    type="checkbox"
                                    name="question_<?php echo esc_attr($question->QuestionID); ?>[]"
                                    value="<?php echo esc_attr($answer->AnswerID); ?>"
                                    id="answer_<?php echo esc_attr($question->QuestionID); ?>_<?php echo esc_attr($answer->AnswerID); ?>"
                                    class="personal-info"
                                >
                                <label for="answer_<?php echo esc_attr($question->QuestionID); ?>_<?php echo esc_attr($answer->AnswerID); ?>">
                                    <?php echo esc_html($answer->answer_Text); ?>
                                </label>
                            </div>
                        <?php endforeach;
                        break;

                    default:
                        // Handle different text input types based on question ID
                        $input_type = 'text';
                        $extra_attrs = 'required';

                        // Date of birth
                        if ($question->QuestionID == 24) {
                            $input_type = 'date';
                            $extra_attrs .= ' max="' . date('Y-m-d') . '"';
                        // Email
                        } elseif ($question->QuestionID == 28) {
                            $input_type = 'email';
                        // Phone
                        } elseif ($question->QuestionID == 29) {
                            $input_type = 'tel';
                            $extra_attrs .= ' pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number"';
                        }
                        ?>
                        <input
                            type="<?php echo $input_type; ?>"
                            name="question_<?php echo esc_attr($question->QuestionID); ?>"
                            id="question_<?php echo esc_attr($question->QuestionID); ?>"
                            class="personal-info"
                            <?php echo $extra_attrs; ?>
                        >
                        <?php
                        break;
                }
                ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php

    wp_send_json_success(array('html' => ob_get_clean()));
}
add_action('wp_ajax_get_personal_questions', 'handle_get_personal_questions');
add_action('wp_ajax_nopriv_get_personal_questions', 'handle_get_personal_questions');

// AJAX handler for form submission
function handle_quiz_submission() {

    // Check if answers were answered by user (HTML data has been sent)
    if (!isset($_POST['responses']) || !is_array($_POST['responses'])) {
        wp_send_json_error('No valid responses received');
        return;
    }

    global $quiz_handler;
    if (!$quiz_handler) {
        wp_send_json_error('System error: Quiz handler not initialized');
        return;
    }

    $responses = $_POST['responses'];
    $personal_info = null;

    try {
        // Question IDs of personal questions in preexisting db
        $personal_fields = [23, 24, 25, 28, 29, 30, 31];
        foreach ($personal_fields as $field) {
            if (isset($responses[$field])) {
                $personal_info[$field] = $responses[$field];
                unset($responses[$field]);
            }
        }

        // Save responses with or without personal questions
        $quiz_id = $quiz_handler->save_responses( $responses, $personal_info);

        // After saving responses, process recommendations and redirect
        if ($quiz_id) {
            wp_send_json_success(array(
                'redirect_url' => site_url('/recommendation/?quiz_id=' . $quiz_id)
            ));
        } else {
            wp_send_json_error('There was an error saving your responses. Please try again.');
        }
    } catch (Exception $e) {
        wp_send_json_error('An error occurred: ' . $e->getMessage());
    }
}
add_action('wp_ajax_handle_quiz_submission', 'handle_quiz_submission');
add_action('wp_ajax_nopriv_handle_quiz_submission', 'handle_quiz_submission');

// Function to display recommendations
function display_recommendations_function($atts) {
    if (!isset($_GET['quiz_id'])) {
        return '<p>No recommendations available. Please take the quiz first.</p>';
    }

    $quiz_id = intval($_GET['quiz_id']);

    global $wpdb;

    // Fetch the tech IDs from wp_Quiz table
    $quiz = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT TechID1, TechID2, TechID3 FROM {$wpdb->prefix}Quiz WHERE QuizID = %d",
            $quiz_id
        )
    );

    if (!$quiz) {
        return '<p>Invalid quiz ID.</p>';
    }

    $tech_ids = array_filter(array($quiz->TechID1, $quiz->TechID2, $quiz->TechID3));

    if (empty($tech_ids)) {
        return '<p>No recommendations found.</p>';
    }

    // Get tech details from db
    $placeholders = implode(',', array_fill(0, count($tech_ids), '%d'));
    $techs = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT TechID, `name`, `description`, `price-range`, `url` FROM {$wpdb->prefix}Tech WHERE TechID IN ($placeholders)",
            $tech_ids
        )
    );

    // Generate HTML for recommendations
    ob_start();
    echo '<div class="recommendations-container">';
    foreach ($techs as $tech) {
        ?>
        <div class="recommendation-card">
            <div class="card-header">
                <div class="placeholder-image"></div>
            </div>
            <div class="card-body">
                <h3 class="recommendation-name"><?php echo esc_html($tech->name); ?></h3>
                <p class="recommendation-description"><?php echo esc_html($tech->description); ?></p>
                <p class="recommendation-price">Price: <?php echo esc_html($tech->{'price-range'}); ?></p>
                <a href="<?php echo esc_url($tech->url); ?>" class="recommendation-link" target="_blank">Learn More</a>
            </div>
        </div>
        <?php
    }
    echo '</div>';
    return ob_get_clean();
}
