jQuery(document).ready(function($) {

    //When the submit button is pressed, collect and send data to db
    $('#quiz-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const formMessage = $('#form-message');
        const submitButton = form.find('button[type="submit"]');
        
        //Disable submit button to prevent double submission
        submitButton.prop('disabled', true);
        
        //Collect all responses
        let responses = {};
        
        //Process each question group
        $('.question-group').each(function() {
            const questionId = $(this).data('question-id');
            const inputs = $(this).find('input:checked, select, input[type="text"]');
            
            //Get multiple answers in case of checkbox question type
            if (inputs.length) {
                if (inputs.is(':checkbox')) {
                    responses[questionId] = [];
                    inputs.each(function() {
                        responses[questionId].push($(this).val());
                    });
                } else {
                    responses[questionId] = inputs.val();
                }
            }
        });

        console.log('Responses collected:', responses); // Debug log

        //Send AJAX request
        $.ajax({
            url: quizAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'handle_quiz_submission',
                nonce: quizAjax.nonce,
                responses: responses
            },
            success: function(response) {
                console.log('Server response:', response); // Debug log
                submitButton.prop('disabled', false);
                
                if (response.success) {
                    formMessage.html('<div class="success">' + response.data.message + '</div>');
                    form.hide();
                } else {
                    formMessage.html('<div class="error">Error: ' + (response.data || 'Unknown error occurred') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('Error details:', {xhr: xhr, status: status, error: error}); // Debug log
                submitButton.prop('disabled', false);
                formMessage.html('<div class="error">Error submitting form. Please try again.</div>');
            }
        });
    });
});