<?php
/**
 * Plugin Name: Quiz Plugin
 * Description: Quiz plugin for LTC
 * Author: Evan White
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

//Include the Quiz Handler class
require_once plugin_dir_path(__FILE__) . 'includes/class-quiz-handler.php';

//Initialize the plugin
function init_quiz_plugin() {
    global $quiz_handler;
    $quiz_handler = new Quiz_Handler();
    
    //Register shortcode 'quiz_form' for use inside of WP page
    add_shortcode('quiz_form', 'display_quiz_form');
}
add_action('init', 'init_quiz_plugin');

//Enqueue necessary scripts
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
//prepare actions for displaying and saving quizzes 
add_action('wp_enqueue_scripts', 'enqueue_quiz_scripts');
add_action('wp_ajax_get_personal_questions', 'handle_get_personal_questions');
add_action('wp_ajax_nopriv_get_personal_questions', 'handle_get_personal_questions');

function handle_get_personal_questions() {
    check_ajax_referer('quiz_ajax_nonce', 'nonce');
    
    global $quiz_handler;
    $questions = $quiz_handler->get_personal_questions();
    
    //Display the personal info questions, divided by question type.
    ob_start();
    ?>
    <div class="personal-info-section">
        <h3>Personal Information</h3>
        <?php foreach ($questions as $question): ?>
            <div class="question-group" data-question-id="<?php echo esc_attr($question->QuestionID); ?>">
                <label><?php echo esc_html($question->Prompt); ?></label>
                <div class="answers-group">
                <?php
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
                        
                        if ($question->QuestionID == 24) { // Date of birth
                            $input_type = 'date';
                            $extra_attrs .= ' max="' . date('Y-m-d') . '"';
                        } elseif ($question->QuestionID == 28) { // Email
                            $input_type = 'email';
                        } elseif ($question->QuestionID == 29) { // Phone
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

//AJAX handler for form submission
function handle_quiz_submission() {
    
    //Check if answers were answered by user
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
        
        $personal_fields = [23, 24, 25, 28, 29, 30, 31];
        foreach ($personal_fields as $field) {
            if (isset($responses[$field])) {
                $personal_info[$field] = $responses[$field];
                unset($responses[$field]);
            }
        }

        $quiz_id = $quiz_handler->save_responses( $responses, $personal_info);
        
        //Message display on WP page of successful quiz completion and save
        if ($quiz_id) {
            wp_send_json_success(array(
                'message' => 'Thank you! Your responses have been saved.',
                'quiz_id' => $quiz_id
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