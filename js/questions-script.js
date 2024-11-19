jQuery(document).ready(function($) {
    console.log('Quiz script loaded');

    // Handle save data question visibility and personal info loading
    const saveDataRadios = $('input[name="save_data"]');
    console.log('Save data radio buttons found:', saveDataRadios.length);

    saveDataRadios.on('change', function() {
        console.log('Radio button changed');
        const saveData = $(this).val() === 'yes';
        console.log('Save data value:', saveData);
        const personalInfoSection = $('#personal-info-section');
        console.log('Personal info section found:', personalInfoSection.length);
        
        if (saveData) {
            console.log('Attempting to fetch personal questions');
            $.ajax({
                url: quizAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_personal_questions',
                    nonce: quizAjax.nonce
                },
                success: function(response) {
                    console.log('Ajax response received:', response);
                    if (response.success) {
                        personalInfoSection.html(response.data.html).slideDown();
                        console.log('Personal info section populated');
                    } else {
                        console.log('Ajax response indicated failure');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Ajax error:', {
                        status: status,
                        error: error,
                        xhr: xhr
                    });
                }
            });
        } else {
            personalInfoSection.slideUp();
            personalInfoSection.find('input').val('');
        }
    });

    $('#quiz-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const saveData = $('input[name="save_data"]:checked').val() === 'yes';
        const submitButton = form.find('button[type="submit"]');
        const formMessage = $('#form-message');
        
        submitButton.prop('disabled', true);
        
        //create responses array
        let responses = {};
        
        //gather regular question responses
        $('.question-group:not(.save-data-question)').each(function() {
            const questionId = $(this).data('question-id');
            const inputs = $(this).find('input:checked, select, input[type="text"]');
            
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

        console.log('Collected regular responses:', responses);

        //Add personal info if saving data to user
        if (saveData) {
            console.log('Processing personal info fields');
            const personalInfoFields = $('.personal-info');
            let isValid = true;
            
            console.log('Found personal info fields:', personalInfoFields.length);
            
            personalInfoFields.each(function() {
                const $input = $(this);
                const questionId = $input.closest('.question-group').data('question-id');
                
                console.log('Processing field:', {
                    questionId: questionId,
                    type: $input.attr('type') || $input.prop('tagName').toLowerCase(),
                    value: $input.val(),
                    isCheckbox: $input.is(':checkbox'),
                    isSelect: $input.is('select')
                });

                if ($input.is('select')) {
                    if (!$input.val()) {
                        isValid = false;
                        $input.closest('.question-group').addClass('error');
                    } else {
                        $input.closest('.question-group').removeClass('error');
                        responses[questionId] = $input.val();
                        console.log('Select value saved:', {
                            questionId: questionId,
                            value: $input.val()
                        });
                    }
                } else if ($input.is(':checkbox')) {
                    if (!responses[questionId]) responses[questionId] = [];
                    if ($input.is(':checked')) {
                        responses[questionId].push($input.val());
                        console.log('Checkbox value added:', {
                            questionId: questionId,
                            value: $input.val()
                        });
                    }
                } else {
                    if (!$input.val()) {
                        isValid = false;
                        $input.closest('.question-group').addClass('error');
                    } else {
                        $input.closest('.question-group').removeClass('error');
                        responses[questionId] = $input.val();
                        console.log('Input value saved:', {
                            questionId: questionId,
                            value: $input.val()
                        });
                    }
                }
            });
            
            console.log('Personal info validation:', isValid);
            console.log('Final responses with personal info:', responses);
            
            if (!isValid) {
                formMessage.html('<div class="error">Please fill in all required fields.</div>');
                submitButton.prop('disabled', false);
                return;
            }
        }

        console.log('Data being sent to server:', {
            action: 'handle_quiz_submission',
            nonce: quizAjax.nonce,
            responses: responses
        });

        // Submit form data
        $.ajax({
            url: quizAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'handle_quiz_submission',
                nonce: quizAjax.nonce,
                responses: responses
            },
            success: function(response) {
                console.log('Submission response received:', response);
                if (response.success) {
                    form.hide();
                    formMessage.html('<div class="success">' + response.data.message + '</div>');
                } else {
                    formMessage.html('<div class="error">' + (response.data || 'An error occurred.') + '</div>');
                }
                submitButton.prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.log('Submission error:', {
                    status: status,
                    error: error,
                    xhr: xhr
                });
                formMessage.html('<div class="error">An error occurred. Please try again.</div>');
                submitButton.prop('disabled', false);
            }
        });
    });
});