jQuery(document).ready(function($) {
    console.log('Quiz search script loaded');

    //Search button click event
    $('#search-quizzes-btn').on('click', function(e) {
        //Prevent any default form submission
        e.preventDefault();
        
        console.log('Search button clicked');
        
        const email = $('#search_email').val().trim();
        const lastName = $('#search_last_name').val().trim();
        const phoneNumber = $('#search_phone').val().trim();
        const password = $('#search_password').val().trim();
        const formMessage = $('#form-message');
        const searchResults = $('#search-results');

        //Check for input
        if (!email || !lastName || !phoneNumber) {
            formMessage.html('<div class="error">Please fill in all fields.</div>');
            return;
        }

        //Display loading message
        formMessage.html('<div>Now Searching...</div>');

        console.log('Searching for Quiz');

        console.log('AJAX request:', {
            url: quizAjax.ajaxurl,
            nonce: quizAjax.nonce,
            data: {email, lastName, phoneNumber, password}
        });

        //AJAX request
        $.ajax({
            url: quizAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'handle_quiz_search',
                nonce: quizAjax.nonce,
                email: email,
                lastName: lastName,
                phoneNumber: phoneNumber,
                password: password
            },
            success: function(response) {
                console.log('Search response:', response);
                
                //Successful query
                if (response.success) {
                    //Display the results
                    let html = '<h3 >Your Previous Results:</h3>';
                    html += '<table class="quiz-results-table">';
                    //Fix: uncapitalize headers?
                    html += '<thead><tr><th>Date</th><th>Recommendations</th><th>Quiz Results</th></tr></thead>';
                    html += '<tbody>';
                    
                    response.data.forEach(function(quiz) {
                        const date = new Date(quiz.Date);
                        
                        //Format date
                        const formattedDate = date.toLocaleDateString();
                        
                        //Insert date info into HTML table for display
                        html += '<tr>';
                        html += '<td>' + formattedDate + '</td>';
                        html += '<td>';
                        
                        if (quiz.recommendations && quiz.recommendations.length > 0) {
                            html += '<ul>';
                            quiz.recommendations.forEach(function(rec) {
                                html += '<li>' + rec + '</li>';
                            });
                            html += '</ul>';
                        } else {
                            html += 'No recommendations available';
                        }
                        
                        html += '</td>';
                        html += '<td><a href="/recommendation/?quiz_id=' + quiz.QuizID + '" class="view-quiz-btn">View Details</a></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    searchResults.html(html);
                    formMessage.html('');
                } else {
                    //Show error message
                    searchResults.html('');
                    formMessage.html('<div class="error">' + response.data + '</div>');
                    console.log(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.log('Search error:', {
                    status: status,
                    error: error,
                    xhr: xhr
                });
                searchResults.html('');
                formMessage.html('<div class="error">An error occurred while searching. Please try again.</div>');
            }
        });
    });

});