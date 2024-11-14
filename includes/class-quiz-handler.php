<?php
if (!defined('ABSPATH')) {
    exit;
}

class Quiz_Handler {
    private $wpdb;
    private $tables;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->tables = array(
            'quiz' => $wpdb->prefix . 'Quiz',
            'questions' => $wpdb->prefix . 'Questions',
            'answers' => $wpdb->prefix . 'Answers',
            'responses' => $wpdb->prefix . 'Responses'
        );
    }
    
    public function get_questions_with_answers() {
        $questions = $this->wpdb->get_results(
            "SELECT q.QuestionID, q.Prompt, q.question_Type, q.Category,
                    a.AnswerID, a.answer_Text
             FROM {$this->tables['questions']} q
             LEFT JOIN {$this->tables['answers']} a ON q.QuestionID = a.QuestionID
             WHERE q.Category != 'Personal'
             ORDER BY q.QuestionID, a.AnswerID"
        );
        
        $structured_questions = array();
        foreach ($questions as $row) {
            if (!isset($structured_questions[$row->QuestionID])) {
                $structured_questions[$row->QuestionID] = array(
                    'id' => $row->QuestionID,
                    'prompt' => $row->Prompt,
                    'type' => $row->question_Type,
                    'category' => $row->Category,
                    'answers' => array()
                );
            }
            if ($row->AnswerID) {
                $structured_questions[$row->QuestionID]['answers'][] = array(
                    'id' => $row->AnswerID,
                    'text' => $row->answer_Text
                );
            }
        }
        return $structured_questions;
    }
    
    public function save_responses($user_id, $responses) {
        error_log('Starting save_responses method');
        error_log('User ID: ' . $user_id);
        error_log('Responses: ' . print_r($responses, true));
    
        if (!is_array($responses) || empty($responses)) {
            error_log('Quiz save error: Invalid responses');
            return false;
        }
    
        // Log table names for debugging
        error_log('Table names: ' . print_r($this->tables, true));
    
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Verify quiz table exists
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->tables['quiz']}'");
            if (!$table_exists) {
                throw new Exception("Quiz table does not exist: {$this->tables['quiz']}");
            }
    
            // Insert quiz entry with debug logging
            error_log('Attempting to insert quiz entry');
            $quiz_insert_result = $this->wpdb->insert(
                $this->tables['quiz'],
                array(
                    'Date' => current_time('mysql'),
                    'user_id' => $user_id,
                    'TechID' => null
                ),
                array('%s', '%d', '%d')
            );
    
            error_log('Quiz insert result: ' . var_export($quiz_insert_result, true));
            error_log('Last DB error: ' . $this->wpdb->last_error);
    
            if ($quiz_insert_result === false) {
                throw new Exception('Failed to create quiz entry: ' . $this->wpdb->last_error);
            }
    
            $quiz_id = $this->wpdb->insert_id;
            error_log('Quiz ID generated: ' . $quiz_id);
    
            // Process each response
            foreach ($responses as $question_id => $answer_ids) {
                $question_id = absint($question_id);
                error_log("Processing question ID: {$question_id}");
    
                if (!is_array($answer_ids)) {
                    $answer_ids = array($answer_ids);
                }
    
                foreach ($answer_ids as $answer_id) {
                    $answer_id = absint($answer_id);
                    error_log("Processing answer ID: {$answer_id}");
    
                    // Insert response with debug logging
                    $response_insert_result = $this->wpdb->insert(
                        $this->tables['responses'],
                        array(
                            'user_id' => $user_id,
                            'QuizID' => $quiz_id,
                            'QuestionID' => $question_id,
                            'AnswerID' => $answer_id
                        ),
                        array('%d', '%d', '%d', '%d')
                    );
    
                    error_log('Response insert result: ' . var_export($response_insert_result, true));
                    error_log('Last DB error: ' . $this->wpdb->last_error);
    
                    if ($response_insert_result === false) {
                        throw new Exception('Failed to save response: ' . $this->wpdb->last_error);
                    }
                }
            }
    
            $this->wpdb->query('COMMIT');
            error_log('Transaction committed successfully');
            return $quiz_id;
    
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('Quiz save error: ' . $e->getMessage());
            error_log('Last SQL query: ' . $this->wpdb->last_query);
            error_log('Last DB error: ' . $this->wpdb->last_error);
            throw $e;
        }
    }
}

$quiz_handler = new Quiz_Handler();

function display_quiz_form() {
    global $quiz_handler;
    $questions = $quiz_handler->get_questions_with_answers();

    ob_start();
    ?>
    <div class="quiz-form-container">
        <form id="quiz-form" class="quiz-form">
            <?php wp_nonce_field('quiz_ajax_nonce', 'quiz_nonce'); ?>
            
            <?php foreach ($questions as $question): ?>
                <div class="question-group" data-question-id="<?php echo esc_attr($question['id']); ?>">
                    <h3 class="question-prompt"><?php echo esc_html($question['prompt']); ?></h3>
                    
                    <div class="answers-group">
                        <?php 
                        switch($question['type']) {
                            case 'ComboBox':
                                ?>
                                <select 
                                    name="question_<?php echo esc_attr($question['id']); ?>"
                                    id="question_<?php echo esc_attr($question['id']); ?>"
                                    class="combobox-input"
                                    required
                                >
                                    <option value="">Please select...</option>
                                    <?php foreach ($question['answers'] as $answer): ?>
                                        <option value="<?php echo esc_attr($answer['id']); ?>">
                                            <?php echo esc_html($answer['text']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php
                                break;
                                
                            case 'Checkbox':
                                foreach ($question['answers'] as $answer): ?>
                                    <div class="answer-option">
                                        <input 
                                            type="checkbox"
                                            name="question_<?php echo esc_attr($question['id']); ?>[]"
                                            value="<?php echo esc_attr($answer['id']); ?>"
                                            id="answer_<?php echo esc_attr($question['id']); ?>_<?php echo esc_attr($answer['id']); ?>"
                                        >
                                        <label for="answer_<?php echo esc_attr($question['id']); ?>_<?php echo esc_attr($answer['id']); ?>">
                                            <?php echo esc_html($answer['text']); ?>
                                        </label>
                                    </div>
                                <?php endforeach;
                                break;
                                
                            case 'Text':
                                ?>
                                <input 
                                    type="text"
                                    name="question_<?php echo esc_attr($question['id']); ?>"
                                    id="question_<?php echo esc_attr($question['id']); ?>"
                                    class="text-input"
                                    required
                                >
                                <?php
                                break;
                                
                            default: // Multiple Choice
                                foreach ($question['answers'] as $answer): ?>
                                    <div class="answer-option">
                                        <input 
                                            type="radio"
                                            name="question_<?php echo esc_attr($question['id']); ?>"
                                            value="<?php echo esc_attr($answer['id']); ?>"
                                            id="answer_<?php echo esc_attr($question['id']); ?>_<?php echo esc_attr($answer['id']); ?>"
                                            required
                                        >
                                        <label for="answer_<?php echo esc_attr($question['id']); ?>_<?php echo esc_attr($answer['id']); ?>">
                                            <?php echo esc_html($answer['text']); ?>
                                        </label>
                                    </div>
                                <?php endforeach;
                                break;
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="submit-button">Submit Quiz</button>
        </form>
        <div id="form-message"></div>
    </div>
    <?php
    return ob_get_clean();
}