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
    }

    //This function returns a unfiltered view of an entire given table
    public function pull_table($table){
        global $wpdb;

        $table_data = $wpdb->get_results(
            "SELECT *
             FROM {$this->tables[$table]}"
        );
        
        return $table_data;
    }

    //This function returns an array of all columns for a given table
    public function get_columns($table){

        global $wpdb;
    
        //Get column names using query
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

    //This function is responsible for adding the Quiz Stats menu in the Wordpress Admin sidebar
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
        
        // Add these lines for filter values
        $filter_column = isset($_GET['filter_column']) ? sanitize_text_field($_GET['filter_column']) : '';
        $filter_value = isset($_GET['filter_value']) ? sanitize_text_field($_GET['filter_value']) : '';

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

            // Get columns using get_columns function
            $columns = $this->get_columns($this->tables[$selected_table]);
            
            //If the columns were found, allow table filtering.
            if ($columns) {
                ?>
                <div class="table-filter" style="margin-top: 20px;">
                    <form method="get">
                        <input type="hidden" name="page" value="quiz-statistics">
                        <input type="hidden" name="table" value="<?php echo esc_attr($selected_table); ?>">
                        
                        <select name="filter_column">
                            <option value="">Select Column to Filter</option>
                            <?php foreach ($columns as $column): ?>
                                <option value="<?php echo esc_attr($column->Field); ?>" 
                                        <?php selected($filter_column, $column->Field); ?>>
                                    <?php echo esc_html($column->Field); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="text" name="filter_value" 
                               value="<?php echo esc_attr($filter_value); ?>" 
                               placeholder="Enter search term">
                        
                        <input type="submit" class="button" value="Filter">
                        
                        <?php if ($filter_column && $filter_value): ?>
                            <a href="?page=quiz-statistics&table=<?php echo esc_attr($selected_table); ?>" 
                               class="button">Clear Filter</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php
            }

            // Get table data
            if ($filter_column && $filter_value) {
                $where = array("$filter_column LIKE '%$filter_value%'");
                $table_data = $this->query_creator($selected_table, '*', $where);
            } else {
                $table_data = $this->pull_table($selected_table);
            }
            
            //check for valid data exist
            if ($table_data && !empty($table_data)) {
                // Get column names for display
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