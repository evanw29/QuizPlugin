<?php

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Dashboard {
    private $tables;

    public function __construct() {
        global $wpdb;
        
        $this->tables = array(
            'quiz' => $wpdb->prefix . 'Quiz',
            'questions' => $wpdb->prefix . 'Questions',
            'answers' => $wpdb->prefix . 'Answers',
            'users' => $wpdb->prefix . 'quizUsers',
            'responses' => $wpdb->prefix . 'Responses'
        );

        add_action('admin_menu', array($this, 'add_admin_menu'));
        //add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    public function pull_table($table){
        global $wpdb;

        $table_data = $wpdb->get_results(
            "SELECT *
             FROM {$this->tables[$table]}"
        );
        
        return $table_data;
    }

    public function get_columns($table){

        global $wpdb;
    
        // Get the first row with DESCRIBE to get column info
        $columns = $wpdb->get_results("DESCRIBE $table");
        
        if (!$columns) {
            error_log('No columns found for table: ' . $table);
            return array();
        }
    
    return $columns;
    }

    public function query_creator($table, $column="*", $value=null, $sort_col=null, $sort_mode=null, $join=null, $limit=null) {
        global $wpdb;
    
        // Make sure we use the proper table name with prefix
        $table_name = $this->tables[$table];
    
        // Build the base query
        $query = "SELECT $column FROM $table_name";
    
        // Add joins if they exist
        if ($join !== null && is_array($join)) {
            $query .= " " . implode(" ", $join);
        }
    
        // Add where conditions if they exist
        if ($value !== null && is_array($value)) {
            $query .= " WHERE " . implode(" AND ", $value);
        }
    
        //Add sorting if specified if sort_col is not null
        if ($sort_col !== null) {
            //makes the default sorting mode ASC 
            $sort_mode = $sort_mode ? strtoupper($sort_mode) : 'ASC';
            $query .= " ORDER BY $sort_col $sort_mode";
        }
    
        // Add limit if specified
        if ($limit !== null) {
            $query .= $wpdb->prepare(" LIMIT %d", $limit);
        }
    
        // Get results
        $results = $wpdb->get_results($query);
    
        return $results;
    }

    public function add_admin_menu() {
        add_menu_page(
            'Quiz Statistics',
            'Quiz Stats',
            'manage_options',
            'quiz-statistics',
            array($this, 'render_dashboard'),
            'dashicons-chart-bar',
            30
        );
    }
    public function render_dashboard() {

        //Block non admins from viewing this page
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        //Get some basic statistics
        $recent_quizzes = $this->query_creator(
            'quiz',
            'QuizID, Date, user_id',
            null,
            'Date',
            'DESC',
            null,
            5
        );

        //Get total number of quizzes taken
        $total_quizzes = $this->query_creator(
            'quiz',
            'COUNT(*) as count',
            null,
            null,
            null,
            null,
            null
        );
        
        //Get number of users
        $total_users = $this->query_creator(
            'users',
            'COUNT(DISTINCT email) as count',
            null,
            null,
            null,
            null,
            null
        );

        ?>
        <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <!-- Always show table view form at top -->
        <?php $this->render_table_view(); ?>

        <!-- Default view with recent quizzes -->
        <div class="quiz-stats-cards">
            <div class="stat-card">
                <h3>Recent Quizzes</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Quiz ID</th>
                            <th>Date</th>
                            <th>User ID</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    if ($recent_quizzes && !empty($recent_quizzes)) {
                        foreach ($recent_quizzes as $quiz): ?>
                            <tr>
                                <td><?php echo esc_html($quiz->QuizID); ?></td>
                                <td><?php echo esc_html($quiz->Date); ?></td>
                                <td><?php echo esc_html($quiz->user_id); ?></td>
                            </tr>
                        <?php endforeach;
                    } else {
                        echo '<tr><td colspan="3">No quizzes found</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    }

    //This function handles rendering the table view selector in the quiz stats module
    public function render_table_view() {
        // Get selected table from dropdown
        $selected_table = isset($_GET['table']) ? sanitize_text_field($_GET['table']) : '';
    
        ?>
        <!-- Table Selection Form -->
        <form method="get">
            <input type="hidden" name="page" value="quiz-statistics">
            <select name="table">
                <option value="">Select a table</option>
                <option value="quiz" <?php selected($selected_table, 'quiz'); ?>>Quiz Table</option>
                <option value="questions" <?php selected($selected_table, 'questions'); ?>>Questions Table</option>
                <option value="answers" <?php selected($selected_table, 'answers'); ?>>Answers Table</option>
                <option value="users" <?php selected($selected_table, 'users'); ?>>Users Table</option>
                <option value="responses" <?php selected($selected_table, 'responses'); ?>>Responses Table</option>
            </select>
            <input type="submit" class="button" value="View Table">
        </form>
    
        <?php
        // Display selected table contents only if a table is selected
        if ($selected_table && array_key_exists($selected_table, $this->tables)) {
            // Get columns using your get_columns function
            $columns = $this->get_columns($this->tables[$selected_table]);
            
            // Get table data
            $table_data = $this->pull_table($selected_table);
            
            if ($table_data && !empty($table_data)) {
                // Get column names
                $columns = $this->get_columns($this->tables[$selected_table]);
                if ($columns && !empty($columns)) {
                    ?>
                    <div class="table-container" style="margin-top: 20px;">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <th><?php echo esc_html($column->Field); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($table_data as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <td><?php 
                                                $field = $column->Field;
                                                echo esc_html($row->$field); 
                                            ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
            } else {
                echo '<p>No data found in selected table.</p>';
            }
        }
    }
}
}