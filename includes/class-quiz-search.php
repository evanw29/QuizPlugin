<?php

if (!defined('ABSPATH')) {
    exit;
}


//This class is the quiz search functionality, where users can search for their previous quizzes using information they 
//provided if chosen to do so. 
class Quiz_Search {
    private $wpdb;
    private $tables;

    //This function constructs the quiz search class.
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        //Grab tables necessary to return to user
        $this->tables = array(
            'quiz' => $wpdb->prefix . 'Quiz',
            'users' => $wpdb->prefix . 'quizUsers',
            'tech' => $wpdb->prefix . 'Tech'
        );
    }

    //Using given identifying info, this function finds all matching quiz rows
    public function search_quizzes($last_name, $email, $phone_number, $password) {

        //Convert to upper to negate case sensitivity
        $last_name = strtoupper($last_name);
        $email = strtoupper($email);

        //Find the user using provided info
        $user = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT user_id 
             FROM {$this->tables['users']} 
             WHERE UPPER(last_name) = %s 
             AND UPPER(email) = %s
             AND phone_number = %s
             AND password_hash = %s
             AND user_type = 'senior'",
            $last_name,
            $email,
            $phone_number,
            password_hash($password, PASSWORD_DEFAULT)
        ));

        //No user with matching info found
        //Fix: flesh out error messages
        if (!$user) {
            return false;
        }

        //find all quizzes with matching user_id
        $quizzes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT q.QuizID, q.Date, q.TechID1, q.TechID2, q.TechID3
             FROM {$this->tables['quiz']} q
             WHERE q.user_id = %d
             ORDER BY q.Date DESC",
            $user->user_id
        ));
        
        //No quizzes found
        if (!$quizzes) {
            return false;
        }

        foreach ($quizzes as $quiz) {
            $tech_ids = array_filter([$quiz->TechID1, $quiz->TechID2, $quiz->TechID3]);
            if (!empty($tech_ids)) {
                $placeholders = implode(',', array_fill(0, 3, '%d'));
                $recommendations = $this->wpdb->get_col($this->wpdb->prepare(
                    "SELECT name 
                     FROM {$this->tables['tech']} 
                    WHERE TechID IN ($placeholders)",
                    $tech_ids
                ));
                $quiz->recommendations = $recommendations;
            }
            
            // Clean up the object
            unset($quiz->TechID1);
            unset($quiz->TechID2);
            unset($quiz->TechID3);
        }

        return $quizzes;
    }
}

//This function handles the HTML classes for the data entry of the user search.
function display_quiz_search() {
    ob_start();
    ?>
    <div class="quiz-form-container">
        <div id="quiz-search-form">
            <div class="search-group">
                <h3 class="question-prompt">Find Your Previous Quizzes</h3>
                <div class="answers-group">
                    <div class="answer-option">
                        <label for="search_email">Email:</label>
                        <input type="email" id="search_email" name="search_email" class="text-input" required>
                    </div>
                    <div class="answer-option">
                        <label for="search_last_name">Last Name:</label>
                        <input type="text" id="search_last_name" name="search_last_name" class="text-input" required>
                    </div>
                    <div class="answer-option">
                        <label for="search_phone">Phone Number:</label>
                        <input type="tel" id="search_phone" name="search_phone" class="text-input" 
                                pattern="[0-9]{10}" title="Please enter a 10-digit phone number" required>
                    </div>
                    <div class="answer-option">
                        <label for="search_password">Password:</label>
                        <input type="text" id="search_password" name="search_password" class="text-input" required>
                    </div>
                    <button type="button" id="search-quizzes-btn" class="search-button">Search Quizzes</button>
                </div>
            </div>
            <div id="search-results"></div>
            <div id="form-message"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}