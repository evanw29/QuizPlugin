<?php
//This file contains the quiz-handler class and its required functions with functionality to create, save, and pull from the wpdb
if (!defined('ABSPATH')) {
    exit;
}

//Quiz Object. Handles all functions of the quiz including: Display, reading and writing to db, and results generation
class Quiz_Handler {
    private $wpdb;
    private $tables;

    //Create quiz object
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        //Initialize array with all table names as they appear in phpMyAdmin
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

        //Create a structured array for easier parsing later
        $structured_questions = array();

        //Go through each question and add it to the new array which contains all question info for each question
        foreach ($questions as $row) {
            //Set all previously unset questions into array defined fields
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

    //This is for pulling specifically the "personal" questions from the database. These
    //questions are only to be asked if the user selects they want to save their data 
    public function get_personal_questions() {

        //Pull personal questions
        $questions = $this->wpdb->get_results(
            "SELECT q.QuestionID, q.Prompt, q.question_Type,
                    a.AnswerID, a.answer_Text
             FROM {$this->tables['questions']} q
             LEFT JOIN {$this->tables['answers']} a ON q.QuestionID = a.QuestionID
             WHERE q.QuestionID IN (23, 24, 25, 28, 29, 30, 31, 40)
             ORDER BY FIELD(q.QuestionID, 23, 30, 24, 25, 28, 29, 31, 40)"
        );

        //Structure the data to group answers with their questions
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
            //Get answers for each question
            if ($row->AnswerID) {
                $structured_questions[$row->QuestionID]->answers[] = (object)[
                    'AnswerID' => $row->AnswerID,
                    'answer_Text' => $row->answer_Text
                ];
            }
        }

        return array_values($structured_questions);
    }

    //Save responses in wp_Responses table
    public function save_responses($responses, $personal_info = null) {

        //Empty response case
        if (!is_array($responses) || empty($responses)) {
            error_log('Quiz save error: Invalid responses');
            return false;
        }

        $this->wpdb->query('START TRANSACTION');

        //Default, Anonymous user case where no data is saved
        $user_id = 1;

        try {

            //If personal info is provided, save user data
            if ($personal_info !== null) {
                $user_data = array(
                    'first_name' => $this->quiz_encrypt_data((string)sanitize_text_field($personal_info[23])),
                    'last_name' => $this->quiz_encrypt_data((string)sanitize_text_field($personal_info[30])),
                    'email' => $this->quiz_encrypt_data((string)sanitize_email($personal_info[28])),
                    'dob' => $personal_info[24],
                    'province' => $this->quiz_encrypt_data((string)$personal_info[25]),
                    'phone_number' => $this->quiz_encrypt_data((string)sanitize_text_field($personal_info[29])),
                    'gender' => $this->quiz_encrypt_data((string)$personal_info[31]),
                    'password_hash' => password_hash((string)$personal_info[40], PASSWORD_DEFAULT) ?? "",
                    'user_type' => 'senior',
                    'blind_index' => $this->quiz_generate_blind_index((string)sanitize_email($personal_info[28]), (string)sanitize_text_field($personal_info[30]), (string)sanitize_text_field($personal_info[29]))
                );

                //Check if user already exists in database, existing_user will be Null if no user with important fields is found
                // With encrypted data, users are found using the blind index (hash of the three search inputs)
                $existing_user = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT user_id 
                     FROM {$this->tables['users']} 
                     WHERE blind_index = %s
                     AND user_type = 'senior'",
                    $user_data['blind_index']
                ));
                
                //Previous user found
                if ($existing_user) {
                    //Use found id
                    $user_id = $existing_user->user_id;
                
                //New User creation required
                } else {
                    //Insert personal user data
                    $user_insert_result = $this->wpdb->insert(
                        $this->tables['users'],
                        $user_data,
                        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                    );

                    //User creation in database failed
                    if ($user_insert_result === false) {
                        throw new Exception('Failed to create user' . $this->wpdb->last_error);
                    }

                    //Get auto-created user_id from db for later use
                    $user_id = $this->wpdb->insert_id;
                }
            }

            //Insert quiz entry into wp_Quiz in db, ignore tech IDs for now
            $quiz_insert_result = $this->wpdb->insert(
                $this->tables['quiz'],
                array(
                    'Date' => current_time('mysql'),
                    'user_id' => $user_id
                ),
                array('%s', '%d')
            );

            //Quiz insertion in db failed
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

                    //Insert response in wp_Responses
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

            //Commit transaction
            $this->wpdb->query('COMMIT');

            //After saving responses, process recommendations
            $top_tech_ids = $this->process_recommendations($quiz_id);

            //Store top tech IDs in wp_Quiz table
            $this->store_recommendations($quiz_id, $top_tech_ids);

            return $quiz_id;

        //Do not commit if error occurs
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('Quiz save error: ' . $e->getMessage());
            throw $e;
        }
    }

    //Process recommendations based on user's responses
    public function process_recommendations($quiz_id) {
        global $wpdb;

        //Fetch user's responses for this quiz
        $responses = $wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT QuestionID, AnswerID FROM {$this->tables['responses']} WHERE QuizID = %d",
                $quiz_id
            )
        );
        
        //Could not find responses
        if (empty($responses)) {
            error_log("No responses found for QuizID: $quiz_id");
            return [];
        }

        //Create an array for the keywords that is associated to each answer given by the user
        $triggered_keywords = array();

        //Map answers to keywords
        foreach ($responses as $response) {
            $question_id = $response->QuestionID;
            $answer_id = $response->AnswerID;

            //Get keywords associated with this answer and their weight (positive/negative association)
            $keywords = $this->wpdb->get_results(
                $wpdb->prepare(
                    "SELECT KeywordID, weight FROM {$this->tables['keyword_answers']} 
                    WHERE QuestionID = %d AND AnswerID = %d",
                    $question_id,
                    $answer_id
                )
            );

            foreach ($keywords as $keyword) {
                $triggered_keywords[$keyword->KeywordID] = ($triggered_keywords[$keyword->KeywordID] ?? 0) + $keyword->weight;
            }
        }

        $tech_scores = [];
        foreach ($triggered_keywords as $keyword_id => $keyword_score) {
            $techs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT TechID, association FROM {$this->tables['tech_keyword_relationship']} 
                    WHERE KeywordID = %d",
                    $keyword_id
                )
            );

            foreach ($techs as $tech) {
                $tech_id = $tech->TechID;

                //Positive association adds weight, negative subtracts weight
                $tech_scores[$tech_id] = ($tech_scores[$tech_id] ?? 0) + ($tech->association === 'positive' ? $keyword_score : -$keyword_score);
            }
        }

        if (empty($tech_scores)) {
            error_log("No technologies matched for QuizID: $quiz_id");
            return [];
        }

        arsort($tech_scores);

        $top_tech_ids = [];
        foreach ($tech_scores as $tech_id => $score) {
            if (count($top_tech_ids) >= 3) {
                break;
            }
            //Prevent duplicates and add the tech ID
            $top_tech_ids[] = $tech_id;
        }

        //If not enough techs, fill with random entries from the Tech table
        if (count($top_tech_ids) < 3) {
            $remaining_techs = $wpdb->get_results(
                "SELECT TechID FROM {$this->tables['tech']} WHERE TechID NOT IN (" . implode(',', $top_tech_ids) . ") ORDER BY RAND() LIMIT " . (3 - count($top_tech_ids))
            );

            foreach ($remaining_techs as $tech) {
                $top_tech_ids[] = $tech->TechID;
            }
        }

        return $top_tech_ids;
    }

    //Store recommendations in wp_Quiz table
    public function store_recommendations($quiz_id, $tech_ids) {
        //Prepare data for update
        $data = array(
            'TechID1' => isset($tech_ids[0]) ? $tech_ids[0] : null,
            'TechID2' => isset($tech_ids[1]) ? $tech_ids[1] : null,
            'TechID3' => isset($tech_ids[2]) ? $tech_ids[2] : null,
        );

        //Update the wp_Quiz table
        $this->wpdb->update(
            $this->tables['quiz'],
            $data,
            array('QuizID' => $quiz_id),
            array('%d', '%d', '%d'),
            array('%d')
        );
    }

    public function quiz_encrypt_data(string $field): string|false {
        if (!file_exists(PRIVATE_KEY_FILE)) {
            error_log("Encryption key file not found");
            return false;
        }
        $key_b64 = trim(file_get_contents(PRIVATE_KEY_FILE));
        $key = sodium_base642bin($key_b64, SODIUM_BASE64_VARIANT_ORIGINAL); // Decode the key
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); // Generate a different 24-bit nonce for every message
        $encrypted = sodium_crypto_secretbox($field, $nonce, $key); // The actual encryption
        $encrypted_b64 = sodium_bin2base64($nonce . $encrypted, SODIUM_BASE64_VARIANT_ORIGINAL); // Base64 of nonce + encrypted message

        // Clear out the memory
        sodium_memzero($field);
        sodium_memzero($nonce);
        sodium_memzero($key_b64);
        sodium_memzero($key);

        return $encrypted_b64;
    }

    // Not used anywhere yet
    public function quiz_decrypt_data(string $encrypted_field): string|false {
        if (!file_exists(PRIVATE_KEY_FILE)) {
            error_log("Encryption key file not found");
            return false;
        }
        $key_b64 = trim(file_get_contents(PRIVATE_KEY_FILE));
        $key = sodium_base642bin($key_b64, SODIUM_BASE64_VARIANT_ORIGINAL);
        $decoded_field = sodium_base642bin($encrypted_field, SODIUM_BASE64_VARIANT_ORIGINAL);
        $nonce = mb_substr($decoded_field, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $encrypted_text = mb_substr($decoded_field, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        $field_text = sodium_crypto_secretbox_open($encrypted_text, $nonce, $key);

        sodium_memzero($nonce);
        sodium_memzero($key_b64);
        sodium_memzero($key);

        return $field_text;
    }

    public function quiz_generate_blind_index(string $email, string $last_name, string $phone_number): string|false {
        $combined = $email . $last_name . $phone_number;
        if (!file_exists(BLIND_INDEX_KEY_FILE)) {
            error_log("Blind index key file not found");
            return false;
        }
        $key_b64 = trim(file_get_contents(BLIND_INDEX_KEY_FILE));
        $key = sodium_base642bin($key_b64, SODIUM_BASE64_VARIANT_ORIGINAL);
        $blind_index = hash_hmac('sha256', $combined, $key, true);
        $blind_index_b64 = sodium_bin2base64($blind_index, SODIUM_BASE64_VARIANT_ORIGINAL);

        sodium_memzero($key_b64);
        sodium_memzero($key);
        sodium_memzero($email);
        sodium_memzero($last_name);
        sodium_memzero($phone_number);
        sodium_memzero($combined);
        return $blind_index_b64;
    }
}

//Initialize Quiz handler
$quiz_handler = new Quiz_Handler();

function display_quiz_form() {
    global $quiz_handler;
    $questions = $quiz_handler->get_questions_with_answers();

    //List of question categories, used for creating a page for each
    $categories = array('Health Status', 'Technology Comfort', 'Preferences', 'Financial', 'Caregiver', 'Matching');

    //Create quiz HTML container
    ob_start();
    ?>
    <div class="quiz-form-container">
        <form id="quiz-form" class="quiz-form">
            <?php wp_nonce_field('quiz_ajax_nonce', 'quiz_nonce');
            
            foreach ($categories as $category):
                
                //Go through each question from the database, and only keep the ones with matching category for this section
                //and store them in category_questions. 
                $category_questions = array_filter($questions, function($c) use ($category){
                    return $c['category'] == $category;
                });
                
                //Skip a category if nothing is found within it. 
                if (empty($category_questions)) {
                    continue;
                }
                
                foreach ($category_questions as $question):
                //Create container for questions
                ?>
                <div class="question-group" 
                     data-question-id="<?php echo esc_attr($question['id']); ?>"
                     data-category="<?php echo esc_attr($category); ?>">
                    <h3 class="question-prompt"><?php echo esc_html($question['prompt']); ?></h3>
                    <div class="answers-group">
                        <?php
                        //question type = Combobox case. each question type is handeled differently
                        switch($question['type']) {
                            case 'ComboBox':
                                ?>
                                <!--Question element creation-->
                                <select
                                    name="question_<?php echo esc_attr($question['id']); ?>"
                                    id="question_<?php echo esc_attr($question['id']); ?>"
                                    class="combobox-input"
                                    required
                                >
                                    <option value="">Please select...</option>
                                    <?php //Load answers
                                        foreach ($question['answers'] as $answer): ?>
                                        <option value="<?php echo esc_attr($answer['id']); ?>">
                                            <?php echo esc_html($answer['text']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php break;

                            //Checkbox question type
                            case 'Checkbox':
                                foreach ($question['answers'] as $answer): ?>

                                    <!--Create chechbox question type container-->
                                    <div class="answer-option">
                                        <input
                                            type="checkbox"
                                            name="question_<?php echo esc_attr($question['id']); ?>[]"
                                            value="<?php echo esc_attr($answer['id']); ?>"
                                            id="answer_<?php echo esc_attr($question['id']); ?>_<?php echo esc_attr($answer['id']); ?>"
                                        >

                                        <!--Answers for question-->
                                        <label for="answer_<?php echo esc_attr($question['id']); ?>_<?php echo esc_attr($answer['id']); ?>">
                                            <?php echo esc_html($answer['text']); ?>
                                        </label>
                                    </div>
                                <?php endforeach;
                                break;
                            //Multiple choice question type
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
        <?php 
            endforeach;
        endforeach;

        ?>
         <!--Save Data Question, this question is not in the db nor does it need to be saved so create it here -->
         <div class="question-group save-data-question" style="display: none;">
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

            <!-- Personal Info Section -->
            <div id="personal-info-section" style="display: none">
            </div>

            <div id="form-message"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}