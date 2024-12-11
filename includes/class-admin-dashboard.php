<?php

if (!defined('ABSPATH')) {
    exit;
}

//Admin dashboard class. This class Uses the existing PHPMyAdmin database to create a tabel within the wordpress dashboard, visible to 
//Administrators only. Displays dashboard rows and is customizable for filtering, sorting, and limiting.
class Admin_Dashboard {
    private $tables;

    //Construct the class, create a table array with the tables we will use
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

    //This function returns a unfiltered view of an entire given table and a given limit length
    public function pull_table($table, $limit=10){
        global $wpdb;

        $table_data = $wpdb->get_results(
            "SELECT *
             FROM {$this->tables[$table]}
             LIMIT $limit"
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
    
        //Make sure we use the proper table name with prefix
        $table_name = $this->tables[$table];
    
        //Build the base query
        $query = "SELECT $column FROM $table_name";
    
        //Add joins if they exist
        if ($join !== null && is_array($join)) {
            $query .= " " . implode(" ", $join);
        }
    
        //Add where conditions if they exist
        if ($value !== null && is_array($value)) {
            $query .= " WHERE " . implode(" AND ", $value);
        }
    
        //Add sorting if specified if sort_col is not null
        if ($sort_col !== null) {
            //makes the default sorting mode ASC 
            $sort_mode = $sort_mode ? strtoupper($sort_mode) : 'ASC';
            $query .= " ORDER BY $sort_col $sort_mode";
        }
    
        //Add limit if specified
        if ($limit !== null) {
            $query .= " LIMIT " . intval($limit);
        }
    
        //Get results
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

    //Get the most recent quizzes for a special table viewer in the dashboard. default is 10
    public function get_recent_quizzes($limit=10){

        $recent_quizzes = $this->query_creator(
            'quiz',
            '*',
            null,
            'Date',
            'DESC',
            null,
            $limit
        );

        return $recent_quizzes;
    }

    //Render the dahsboard itself and call helper functions
    public function render_dashboard() {

        //Block non admins from viewing this page
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        //Get total number of quizzes taken NOT CURRENTLY USED
        $total_quizzes = $this->query_creator(
            'quiz',
            'COUNT(*) as count',
            null,
            null,
            null,
            null,
            null
        );
        
        //Get number of users NOT CURRENTLY USED
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

        <?php 
        $this->render_table_view(); 
    
    }
    

    //This function handles rendering the table view selector in the quiz stats module
    public function render_table_view() {
        //Get selected table from dropdown
        $selected_table = isset($_GET['table']) ? sanitize_text_field($_GET['table']) : '';
        
        //Add these lines for filter values
        $filter_column = isset($_GET['filter_column']) ? sanitize_text_field($_GET['filter_column']) : '';
        $filter_value = isset($_GET['filter_value']) ? sanitize_text_field($_GET['filter_value']) : '';
        //This is for deciding on equals or contains filter types
        $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'contains';

        $limit = isset($_GET['limit-tbox']) ? absint($_GET['limit-tbox']) :10;

        //store combobox selection in selected_table
        ?>
        <!-- Table Selection Combobox -->
        <form method="get">
            <input type="hidden" name="page" value="quiz-statistics">
            <select name="table">
                <option value="recent" <?php selected($selected_table, 'recent'); ?>>Recent Quizzes</option>
                <option value="quiz" <?php selected($selected_table, 'quiz'); ?>>Quiz Table</option>
                <option value="questions" <?php selected($selected_table, 'questions'); ?>>Questions Table</option>
                <option value="answers" <?php selected($selected_table, 'answers'); ?>>Answers Table</option>
                <option value="users" <?php selected($selected_table, 'users'); ?>>Users Table</option>
                <option value="responses",  <?php selected($selected_table, 'responses'); ?>>Responses Table</option>
            </select>
            <input type="submit" class="button" value="View">
            <p>Show: <input type="text" name="limit-tbox" value="<?php echo $limit; ?>"></p>
            
        </form>

        <?php
        //Display selected table contents only if a table is selected
        if (array_key_exists($selected_table, $this->tables)) {

            //Get columns names
            $columns = $this->get_columns($this->tables[$selected_table]);
            
            //If the columns were found, allow table filtering.
            if ($columns) {
                ?>
                <div class="table-filter" style="margin-top: 20px;">
                    <form method="get">
                        <input type="hidden" name="page" value="quiz-statistics">
                        <input type="hidden" name="table" value="<?php echo esc_attr($selected_table); ?>">
                        <input type="hidden" name="limit-tbox" value="<?php echo esc_attr($limit); ?>">
                        
                        <!--Filter by search -->
                        <select name="filter_column">
                            <option value="">Select Column to Filter</option>
                            <?php foreach ($columns as $column): ?>
                                <option value="<?php echo esc_attr($column->Field); ?>" 
                                        <?php selected($filter_column, $column->Field); ?>>
                                    <?php echo esc_html($column->Field); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!--Add the filter type modifier element-->
                        <select name="filter_type">
                            <option value="contains" <?php selected($filter_type, 'contains'); ?>>Contains</option>
                            <option value="equals" <?php selected($filter_type, 'equals'); ?>>Equals</option>
                        </select>
                        
                        <!-- Filter textbox-->
                        <input type="text" name="filter_value" 
                               value="<?php echo esc_attr($filter_value); ?>" 
                               placeholder="Enter search term">
                        
                        <!--Search Button-->
                        <input type="submit" class="button" value="Filter">
                        
                        <!-- Add clear filter button when a value is entered into filter textbox-->
                        <?php if ($filter_column && $filter_value): ?>
                            <a href="?page=quiz-statistics&table=<?php echo esc_attr($selected_table); ?>" 
                               class="button">Clear Filter</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php

                $limit = isset($_GET['limit-tbox']) ? absint($_GET['limit-tbox']) :10;
            }

            //Get queried table data
            if ($filter_column && $filter_value) {

                //Find filter type and adjust query accordingly (contains or equals search)
                if ($filter_type == "contains")
                {
                    $filter = array("$filter_column LIKE '%$filter_value%'");
                } else if ($filter_type == "equals"){
                    $filter = array("LOWER($filter_column) = LOWER('$filter_value')");
                }

                /*?>
                    <input type="hidden" name="limit-tbox" value="<?php echo esc_attr($limit); ?>">
                <?php
                **/
                //create query
                $table_data = $this->query_creator($selected_table, '*', $filter, null, null, null, $limit);
            } else {
                $table_data = $this->pull_table($selected_table, $limit);
            }

        } elseif ($selected_table == "recent"){
            $table_data = $this->get_recent_quizzes($limit);
            $columns = $this->get_columns($this->tables['quiz']);
        }
        
        //Gets the number of results generated by the query
        if ($selected_table) {
            $shown_count = count($table_data);
            
            //Records element
            ?>
            <div class="results">
                <span class="records-count">
                    <?php echo $shown_count; ?> Results.
                </span>
            </div>
            <?php        
        }
            
        //check for valid data exist
        if ($table_data && !empty($table_data)) {
            $this->render_data($table_data, $columns);
        } else {
            echo '<p>No data found in selected table.</p>';
        }
    }

    //This function renders the table element itself, and populates it with the queried data.
    public function render_data($data, $columns){
        
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
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <!--Populate table rows-->
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
    }
  
}
