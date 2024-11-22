<?php
// This file contains the quiz-handler class and its required functions with functionality to create, save, and pull from the wpdb
if (!defined('ABSPATH')) {
    exit;
}

// Quiz Object
class Quiz_Handler {
    private $wpdb;
    private $tables;

    // Create quiz object
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Initialize array with all table names as they appear in phpMyAdmin
        $this->tables = array(
            'quiz' => $wpdb->prefix . 'Quiz',
            'questions' => $wpdb->prefix . 'Questions',
            'answers' => $wpdb->prefix . 'Answers',
            'responses' => $wpdb->prefix . 'Responses',
            'users' => $wpdb->prefix . 'quizUsers',
            'keyword_answers' => $wpdb->prefix . 'KeywordAnswer',
            'tech_keyword_relationship' => $wpdb->prefix . 'TechKeywordRelationship',
            'tech' => $wpdb->prefix . 'Tech'
        );
    }

    // Pull questions from db
    public function get_questions_with_answers() {

        // Get all questions in the wp_Questions table, ignore personal category for now
        $questions = $this->wpdb->get_results(
            "SELECT q.QuestionID, q.Prompt, q.question_Type, q.Category,
                    a.AnswerID, a.answer_Text
             FROM {$this->tables['questions']} q
             LEFT JOIN {$this->tables['answers']} a ON q.QuestionID = a.QuestionID
             WHERE q.Category != 'Personal'
             ORDER BY q.QuestionID, a.AnswerID"
        );

        // Create a structured array for easier parsing later
        $structured_questions = array();

        // Go through each question and add it to the new array which contains all question info for each question
        foreach ($questions as $row) {
            // Set all previously unset questions into array defined fields
            if (!isset($structured_questions[$row->QuestionID])) {
                $structured_questions[$row->QuestionID] = array(
                    'id' => $row->QuestionID,
                    'prompt' => $row->Prompt,
                    'type' => $row->question_Type,
                    'category' => $row->Category,
                    'answers' => array()
                );
            }

            // Get all answers for each question and put them into similar array
            if ($row->AnswerID) {
                $structured_questions[$row->QuestionID]['answers'][] = array(
                    'id' => $row->AnswerID,
                    'text' => $row->answer_Text
                );
            }
        }
        return $structured_questions;
    }

    // This is for pulling specifically the "personal" questions from the database.
    public function get_personal_questions() {

        // Pull personal questions
        $questions = $this->wpdb->get_results(
            "SELECT q.QuestionID, q.Prompt, q.question_Type,
                    a.AnswerID, a.answer_Text
             FROM {$this->tables['questions']} q
             LEFT JOIN {$this->tables['answers']} a ON q.QuestionID = a.QuestionID
             WHERE q.Category = 'Personal'
             ORDER BY q.QuestionID"
        );

        // Structure the data to group answers with their questions
        $structured_questions = array();
        foreach ($questions as $row) {
            if (!isset($structured_questions[$row->QuestionID])) {
                $structured_questions[$row->QuestionID] = (object)[
                    'QuestionID' => $row->QuestionID,
                    'Prompt' => $row->Prompt,
                    'question_Type' => $row->question_Type,
                    'answers' => array()
                ];
            }
            // Get answers for each question
            if ($row->AnswerID) {
                $structured_questions[$row->QuestionID]->answers[] = (object)[
                    'AnswerID' => $row->AnswerID,
                    'answer_Text' => $row->answer_Text
                ];
            }
        }

        return array_values($structured_questions);
    }

    // Save responses in wp_Responses table
    public function save_responses($responses, $personal_info = null) {

        // Empty response case
        if (!is_array($responses) || empty($responses)) {
            error_log('Quiz save error: Invalid responses');
            return false;
        }

        $this->wpdb->query('START TRANSACTION');

        // Default, Anonymous user case where no data is saved
        $user_id = 1;

        try {

            // If personal info is provided, save user data
            if ($personal_info !== null) {
                $user_data = array(
                    'first_name' => sanitize_text_field($personal_info[23]),
                    'last_name' => sanitize_text_field($personal_info[30]),
                    'email' => sanitize_email($personal_info[28]),
                    'dob' => $personal_info[24],
                    'province' => $personal_info[25],
                    'phone_number' => sanitize_text_field($personal_info[29]),
                    'gender' => $personal_info[31],
                    'user_type' => 'senior'
                );

                // Insert personal user data
                $user_insert_result = $this->wpdb->insert(
                    $this->tables['users'],
                    $user_data,
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );

                // User creation in database failed
                if ($user_insert_result == false) {
                    throw new Exception('Failed to create user');
                }

                // Get auto-created user_id from db for later use
                $user_id = $this->wpdb->insert_id;
            }

            // Insert quiz entry into wp_Quiz in db, ignore tech IDs for now
            $quiz_insert_result = $this->wpdb->insert(
                $this->tables['quiz'],
                array(
                    'Date' => current_time('mysql'),
                    'user_id' => $user_id
                ),
                array('%s', '%d')
            );

            // Quiz insertion in db failed
            if ($quiz_insert_result === false) {
                throw new Exception('Failed to create quiz entry: ' . $this->wpdb->last_error);
            }

            // Pull newly created quiz_id entry from wp_Quiz
            $quiz_id = $this->wpdb->insert_id;

            // Parse each response
            foreach ($responses as $question_id => $answer_ids) {
                // Get real question ID for response
                $question_id = absint($question_id);

                // Convert to array to preserve checkbox question types
                if (!is_array($answer_ids)) {
                    $answer_ids = array($answer_ids);
                }

                // Go through each response (for each question)
                foreach ($answer_ids as $answer_id) {
                    $answer_id = absint($answer_id);

                    // Insert response in wp_Responses
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

                    // Could not insert response error case
                    if ($response_insert_result === false) {
                        throw new Exception('Failed to save response: ' . $this->wpdb->last_error);
                    }
                }
            }

            // Commit transaction
            $this->wpdb->query('COMMIT');

            // After saving responses, process recommendations
            $top_tech_ids = $this->process_recommendations($quiz_id);

            // Store top tech IDs in wp_Quiz table
            $this->store_recommendations($quiz_id, $top_tech_ids);

            return $quiz_id;

        // Do not commit if error occurs
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    // Process recommendations based on user's responses
    public function process_recommendations($quiz_id) {
        // Fetch user's responses for this quiz
        $responses = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT QuestionID, AnswerID FROM {$this->tables['responses']} WHERE QuizID = %d",
                $quiz_id
            )
        );

        $triggered_keywords = array();

        // Map answers to keywords
        foreach ($responses as $response) {
            $question_id = $response->QuestionID;
            $answer_id = $response->AnswerID;

            // Get keywords associated with this answer
            $keywords = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT KeywordID FROM {$this->tables['keyword_answers']} WHERE QuestionID = %d AND AnswerID = %d AND association = 'positive'",
                    $question_id,
                    $answer_id
                )
            );

            foreach ($keywords as $keyword) {
                $triggered_keywords[] = $keyword->KeywordID;
            }
        }

        // Remove duplicate keywords
        $triggered_keywords = array_unique($triggered_keywords);

        // Map keywords to tech IDs
        $tech_counts = array();
        foreach ($triggered_keywords as $keyword_id) {
            $techs = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT TechID FROM {$this->tables['tech_keyword_relationship']} WHERE KeywordID = %d AND association = 'positive'",
                    $keyword_id
                )
            );

            foreach ($techs as $tech) {
                $tech_id = $tech->TechID;
                if (!isset($tech_counts[$tech_id])) {
                    $tech_counts[$tech_id] = 0;
                }
                $tech_counts[$tech_id]++;
            }
        }

        // Sort tech IDs by count (descending)
        arsort($tech_counts);

        // Get top 3 tech IDs
        $top_tech_ids = array_slice(array_keys($tech_counts), 0, 3);

        return $top_tech_ids;
    }

    // Store recommendations in wp_Quiz table
    public function store_recommendations($quiz_id, $tech_ids) {
        // Prepare data for update
        $data = array(
            'TechID1' => isset($tech_ids[0]) ? $tech_ids[0] : null,
            'TechID2' => isset($tech_ids[1]) ? $tech_ids[1] : null,
            'TechID3' => isset($tech_ids[2]) ? $tech_ids[2] : null,
        );

        // Update the wp_Quiz table
        $this->wpdb->update(
            $this->tables['quiz'],
            $data,
            array('QuizID' => $quiz_id),
            array('%d', '%d', '%d'),
            array('%d')
        );
    }
}

// Initialize Quiz handler
$quiz_handler = new Quiz_Handler();

function display_quiz_form() {
    global $quiz_handler;
    $questions = $quiz_handler->get_questions_with_answers();

    // Create quiz HTML container
    ob_start();
    ?>
    <div class="quiz-form-container">
        <form id="quiz-form" class="quiz-form">
            <?php wp_nonce_field('quiz_ajax_nonce', 'quiz_nonce'); ?>

            <!-- Regular Quiz Questions -->
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
                            // Multiple Choice
                            default:
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

            <div class="question-group save-data-question">
                <h3 class="question-prompt">Would you like to save your information for future reference?</h3>
                <div class="answers-group">
                    <div class="answer-option">
                        <input type="radio" name="save_data" value="yes" id="save_data_yes" required>
                        <label for="save_data_yes">Yes</label>
                    </div>
                    <div class="answer-option">
                        <input type="radio" name="save_data" value="no" id="save_data_no">
                        <label for="save_data_no">No</label>
                    </div>
                </div>
            </div>

            <div id="personal-info-section" style="display: none">
            </div>

            <button type="submit" class="submit-button">Submit Quiz</button>
        </form>
        <div id="form-message"></div>
    </div>
    <?php
    return ob_get_clean();
}
