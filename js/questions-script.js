jQuery(document).ready(function($) {
    
    console.log('Quiz script loaded');

    let currentCategory = 0;
    const categories = ['Health Status', 'Technology Comfort', 'Preferences', 'Financial', 'Caregiver', 'Matching'];
    
    //Add navigation controls
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
            class: 'quiz-submit-button',
            text: 'Submit Quiz'
        });

        const progressIndicator = $('<div>', {
            class: 'progress-indicator'
        });

        navigationContainer.append(prevButton, progressIndicator, nextButton, submitButton);
        $('#quiz-form').append(navigationContainer);

        //Initially hide the submit button and prev button
        submitButton.hide();
        prevButton.hide();
    }

    //Debug log to check if questions exist
    console.log('Questions found:', $('.question-group').length);
    console.log('Categories:', categories);

    function updateCurrentCategory() {
        console.log('Showing category:', categories[currentCategory]);
        
        //Hide all question groups temporarily to load following category.
        $('.question-group').hide();
        
        if (currentCategory >= categories.length) {
            //Show save data question and personal info if needed
            $('.save-data-question').show();
            if ($('input[name="save_data"]:checked').val() === 'yes') {
                $('#personal-info-section').show();
            }
            $('.next-button').hide();
            $('.quiz-submit-button').show();
        } else {
            //Show questions for current category
            $(`.question-group[data-category="${categories[currentCategory]}"]`).show();
            console.log('Showing questions for category:', categories[currentCategory]);
            console.log('Questions found:', $(`.question-group[data-category="${categories[currentCategory]}"]`).length);
            
            $('.quiz-submit-button').hide();
            $('.next-button').show();
        }

        //Scroll to top of page on each category refresh
        window.scrollTo(0, 0);

        //Update navigation buttons
        $('.prev-button').toggle(currentCategory > 0);
        
        //Update progress
        const progress = ((currentCategory) / (categories.length + 1)) * 100;
        $('.progress-indicator').html(`
            <span>Section ${currentCategory + 1} of ${categories.length + 1}</span>
            <div class="progress-bar">
                <div class="progress" style="width: ${progress}%"></div>
            </div>
        `);
    }

    //Initialize first category in quiz
    updateCurrentCategory();

    //Navigation button handlers
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

    //This function checks if all questions in a page have been answered, and asks the user to do so if they have not already.
    function checkCurrentCategory() {
        let isValid = true;
        const currentQuestions = $(`.question-group[data-category="${categories[currentCategory]}"]:visible`);
        
        currentQuestions.each(function() {
            const $group = $(this);
            const questionType = $group.find('input, select').first().attr('type') || 'select';
            //$comboBox = $group.find('input, select')
            
            if (questionType === 'radio' || questionType === 'select') {
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

    //Handle save data question visibility and personal info loading
    const saveDataRadios = $('input[name="save_data"]');
    // Load the personal info section as soon as possible
    const personalInfoSection = $('#personal-info-section');
    let cachedPersonalSection = null;

    if (!cachedPersonalSection) {
        $.ajax({
            url: quizAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_personal_questions',
                nonce: quizAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    cachedPersonalSection = response.data.html;
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                $('#form-message').html('<div class="error">Failed to load personal information form. Please try again.</div>');
            }
        });
    }

    saveDataRadios.on('change', function() {
        const saveData = $(this).val() === 'yes';
        if (saveData && cachedPersonalSection) {
            personalInfoSection.html(cachedPersonalSection).slideDown();
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

        //User feedback
        formMessage.html('<div>Submitting...</div>');

        //Create responses array
        let responses = {};

        //Gather regular question responses
        $('.question-group:not(.save-data-question)').each(function() {
            const questionId = $(this).data('question-id');
            const inputs = $(this).find('input:checked, select, input[type="text"], input[type="email"], input[type="date"], input[type="tel"], input[type="password"]');

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

        //Log request info
        console.log('Data being sent to server:', {
            action: 'handle_quiz_submission',
            nonce: quizAjax.nonce,
            responses: responses
        });

        //Submit form data to database
        $.ajax({
            url: quizAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'handle_quiz_submission',
                nonce: quizAjax.nonce,
                responses: responses
            },
            //On success write to console
            success: function(response) {
                console.log('Submission response received:', response);
                if (response.success) {
                    //Redirect to recommendations page
                    window.location.href = response.data.redirect_url;
                } else {
                    formMessage.html('<div class="error">' + (response.data || 'An error occurred.') + '</div>');
                    submitButton.prop('disabled', false);
                }
            },
            //fail case
            error: function(xhr, status, error) {
                console.log('Submission error:', {
                    status: status,
                    error: error,
                    xhr: xhr.responseText
                });
                
                formMessage.html('<div class="error">An error occurred. Please try again.</div>');
                submitButton.prop('disabled', false);
            }
        });
    });
});
