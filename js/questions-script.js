jQuery(document).ready(function($) {
    
    console.log('Quiz script loaded');

    let currentCategory = 0;
    const categories = ['Health Status', 'Technology Comfort', 'Preferences', 'Financial', 'Caregiver', 'Matching'];
    
    // Add navigation controls if they don't exist
    if ($('.quiz-navigation').length == 0) {
        const navigationContainer = $('<div>', {
            class: 'quiz-navigation'
        });

        const prevButton = $('<button>', {
            type: 'button',
            class: 'nav-button prev-button',
            text: 'Previous'
        });

        const nextButton = $('<button>', {
            type: 'button',
            class: 'nav-button next-button',
            text: 'Next'
        });

        const submitButton = $('<button>', {
            type: 'submit',
            class: 'submit-button',
            text: 'Submit Quiz'
        });

        const progressIndicator = $('<div>', {
            class: 'progress-indicator'
        });

        navigationContainer.append(prevButton, progressIndicator, nextButton, submitButton);
        $('#quiz-form').append(navigationContainer);

        // Initially hide the submit button and prev button
        submitButton.hide();
        prevButton.hide();
    }

    // Debug log to check if questions exist
    console.log('Questions found:', $('.question-group').length);
    console.log('Categories:', categories);

    function updateCurrentCategory() {
        console.log('Showing category:', categories[currentCategory]);
        
        // Hide all question groups
        $('.question-group').hide();
        
        if (currentCategory >= categories.length) {
            // Show save data question and personal info if needed
            $('.save-data-question').show();
            if ($('input[name="save_data"]:checked').val() === 'yes') {
                $('#personal-info-section').show();
            }
            $('.next-button').hide();
            $('.submit-button').show();
        } else {
            // Show questions for current category
            $(`.question-group[data-category="${categories[currentCategory]}"]`).show();
            console.log('Showing questions for category:', categories[currentCategory]);
            console.log('Questions found:', $(`.question-group[data-category="${categories[currentCategory]}"]`).length);
            
            $('.submit-button').hide();
            $('.next-button').show();
        }

        // Update navigation buttons
        $('.prev-button').toggle(currentCategory > 0);
        
        // Update progress
        const progress = ((currentCategory) / (categories.length + 1)) * 100;
        $('.progress-indicator').html(`
            <span>Section ${currentCategory + 1} of ${categories.length + 1}</span>
            <div class="progress-bar">
                <div class="progress" style="width: ${progress}%"></div>
            </div>
        `);
    }

    // Initialize first category
    updateCurrentCategory();

    // Navigation button handlers
    $('.prev-button').on('click', function() {
        if (currentCategory > 0) {
            currentCategory--;
            updateCurrentCategory();
        }
    });

    $('.next-button').on('click', function() {
        if (checkCurrentCategory()) {
            currentCategory++;
            updateCurrentCategory();
        }
    });

    function checkCurrentCategory() {
        let isValid = true;
        const currentQuestions = $(`.question-group[data-category="${categories[currentCategory]}"]:visible`);
        
        currentQuestions.each(function() {
            const $group = $(this);
            const questionType = $group.find('input, select').first().attr('type') || 'select';
            
            if (questionType === 'radio' || questionType === 'select-one') {
                if (!$group.find('input:checked, select').val()) {
                    isValid = false;
                    $group.addClass('error');
                } else {
                    $group.removeClass('error');
                }
            } else if (questionType === 'checkbox') {
                if (!$group.find('input:checked').length) {
                    isValid = false;
                    $group.addClass('error');
                } else {
                    $group.removeClass('error');
                }
            }
        });

        if (!isValid) {
            $('#form-message').html('<div class="error">Please answer all questions in this section.</div>');
        } else {
            $('#form-message').empty();
        }
        
        return isValid;
    }

    // Handle save data question visibility and personal info loading
    const saveDataRadios = $('input[name="save_data"]');
    saveDataRadios.on('change', function() {
        const saveData = $(this).val() === 'yes';
        const personalInfoSection = $('#personal-info-section');
        
        if (saveData) {
            $.ajax({
                url: quizAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_personal_questions',
                    nonce: quizAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        personalInfoSection.html(response.data.html).slideDown();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    $('#form-message').html('<div class="error">Failed to load personal information form. Please try again.</div>');
                }
            });
        } else {
            personalInfoSection.slideUp().find('input').val('');
        }
    });

    $('#quiz-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const saveData = $('input[name="save_data"]:checked').val() === 'yes';
        const submitButton = form.find('button[type="submit"]');
        const formMessage = $('#form-message');

        submitButton.prop('disabled', true);

        // Create responses array
        let responses = {};

        // Gather regular question responses
        $('.question-group:not(.save-data-question)').each(function() {
            const questionId = $(this).data('question-id');
            const inputs = $(this).find('input:checked, select, input[type="text"], input[type="email"], input[type="date"], input[type="tel"]');

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
                if (!$input.val() && $input.prop('required')) {
                    isValid = false;
                    $input.closest('.question-group').addClass('error');
                } else {
                    $input.closest('.question-group').removeClass('error');
                }
            });

            console.log('Personal info validation:', isValid);

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

        // Submit form data to database
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
                    // Redirect to recommendations page
                    window.location.href = response.data.redirect_url;
                } else {
                    formMessage.html('<div class="error">' + (response.data || 'An error occurred.') + '</div>');
                    submitButton.prop('disabled', false);
                }
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
