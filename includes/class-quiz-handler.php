<?php
if (!defined('ABSPATH')) {
    exit;
}

//Quiz Object
class Quiz_Handler {
    private $wpdb;
    private $tables;
    
    //Create quiz object
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        //initialize array with all table names as they appear in phpMyAdmin
        $this->tables = array(
            'quiz' => $wpdb->prefix . 'Quiz',
            'questions' => $wpdb->prefix . 'Questions',
            'answers' => $wpdb->prefix . 'Answers',
            'responses' => $wpdb->prefix . 'Responses'
        );
    }
    
    //Pull questions from db 
    public function get_questions_with_answers() {

        //Get all questions in the wp_Questions table, ignore personal category for now
        $questions = $this->wpdb->get_results(
            "SELECT q.QuestionID, q.Prompt, q.question_Type, q.Category,
                    a.AnswerID, a.answer_Text
             FROM {$this->tables['questions']} q
             LEFT JOIN {$this->tables['answers']} a ON q.QuestionID = a.QuestionID
             WHERE q.Category != 'Personal'
             ORDER BY q.QuestionID, a.AnswerID"
        );
        
        $structured_questions = array();
        
        //Go through each question and add it to the new array which containt all question info for each question
        foreach ($questions as $row) {
            //set all previously unset questions into array defined fields
            if (!isset($structured_questions[$row->QuestionID])) {
                $structured_questions[$row->QuestionID] = array(
                    'id' => $row->QuestionID,
                    'prompt' => $row->Prompt,
                    'type' => $row->question_Type,
                    'category' => $row->Category,
                    'answers' => array()
                );
            }

            //Get all answers for each question and put them into similar array
            if ($row->AnswerID) {
                $structured_questions[$row->QuestionID]['answers'][] = array(
                    'id' => $row->AnswerID,
                    'text' => $row->answer_Text
                );
            }
        }
        return $structured_questions;
    }
    
    //Save responses in phpMyAdmin wp_Responses table
    public function save_responses($user_id, $responses) {
        
        //Empty response case
        if (!is_array($responses) || empty($responses)) {
            error_log('Quiz save error: Invalid responses');
            return false;
        }
    
        $this->wpdb->query('START TRANSACTION');
        
        try {
            //Insert quiz entry into wp_Quiz in db, ignore techID for now
            $quiz_insert_result = $this->wpdb->insert(
                $this->tables['quiz'],
                array(
                    'Date' => current_time('mysql'),
                    'user_id' => $user_id,
                    'TechID' => null
                ),
                array('%s', '%d', '%d')
            );
    
            if ($quiz_insert_result === false) {
                throw new Exception('Failed to create quiz entry: ' . $this->wpdb->last_error);
            }
    
            //Pull newly created quiz_id entry from wp_Quiz
            $quiz_id = $this->wpdb->insert_id;
    
            //Parse each response 
            foreach ($responses as $question_id => $answer_ids) {
                //Get real question ID for response
                $question_id = absint($question_id);
                
                //Convert to array to preserve checkbox question types
                if (!is_array($answer_ids)) {
                    $answer_ids = array($answer_ids);
                }
                
                //Go through each response (for each question)
                foreach ($answer_ids as $answer_id) {
                    $answer_id = absint($answer_id);
    
                    //Insert response in wp_Response
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
                    
                    //Could not insert response error case
                    if ($response_insert_result === false) {
                        throw new Exception('Failed to save response: ' . $this->wpdb->last_error);
                    }
                }
            }
            
            //Complete transaction
            $this->wpdb->query('COMMIT');
            error_log('Transaction committed successfully');
            return $quiz_id;
            
        //Do not commit if error occurs
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('Quiz save error: ' . $e->getMessage());
            error_log('Last SQL query: ' . $this->wpdb->last_query);
            error_log('Last DB error: ' . $this->wpdb->last_error);
            throw $e;
        }
    }
}

//Initialize Quiz handler
$quiz_handler = new Quiz_Handler();

function display_quiz_form() {
    global $quiz_handler;
    $questions = $quiz_handler->get_questions_with_answers();

    //Create quiz html container 
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