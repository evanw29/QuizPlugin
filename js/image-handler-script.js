jQuery(document).ready(function($) {

    console.log('Image Carousel script loaded');

    wait();

    initImageCarousel();

    //On refresh or content update, reinitialize the carousel.
    $(document).on('contentChanged', function() {
        console.log('Content changed, reinitializing image carousel');
        setTimeout(initImageCarousel, 500);
    });

    function getImageLink(title, $img) {

        console.log('AJAX request:', {
            url: imageAjax.ajaxurl,
            nonce: imageAjax.nonce,
            data: {title}
        });

        $.ajax({
            url: imageAjax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'handle_image_name_matching',
                nonce: imageAjax.nonce,
                title: title
            },
            success: function(response) {
                console.log('Image link response:', title, " for: ",response);
                
                //If the response is successful, create a link for the image
                if (response.success && response.data.url) {
                    createImageLink($img, response.data.url, '_blank');
                } else {
                    console.error('No image found for title in file system. Ensure filename is EXACTLY how the name appears in DB:', title);
                }
            },
            error: function(xhr, status, error) {   
                console.error('Error fetching image link: ', {
                    title: title,
                    error: error
                });
            }
        });
    }
   
   //Gets image title for use of matching to database entry. Assumes image 'Title' attribute is named 
   //as business name.
    function extractImageTitle(image){
        
        //Get alt attribute from Image in carousel. Check that its not empty.
        //This corresponds to the 'Title' attribute in Wordpress media library.
        title = image.attr('alt');
        if (title && title.trim()) {
            console.log('Image title found:', title);
            return title.toLowerCase();
        }
    }

    function createImageLink(img, url, target='_blank') {
        console.log('Creating link for image:', img, 'with URL:', url);
        //Create the link element
        const $link = $('<a>', {
            href: url,
            target: target,
            class: 'carousel-image-link'
        });

        //Add hover effects
        $link.css({
            'display': 'inline-block',
            'cursor': 'pointer',
            'transition': 'opacity 0.3s ease'
        });

        $link.hover(
            function() {
                $(this).css('opacity', '0.8');
            },
            function() {
                $(this).css('opacity', '1');
            }
        );

        //Wrap the image with the link
        img.wrap($link);

        console.log('image link created:', img, 'with URL:', url);
        
    }

    function wait() {
        if (typeof imageAjax === 'undefined' || typeof imageAjax.ajaxurl === 'undefined') {
            console.log('WordPress AJAX not ready yet, waiting...');
            setTimeout(wait, 100); //Check again in 100ms
            return;
        }
    }

    function initImageCarousel() {
        console.log('Initializing image carousel');

        //Select all images in the carousel
        //FIX: change to make all applicable images linkable
        const $images = $('.product-carousel img');

        if ($images.length === 0) {
            console.log('No images found in the carousel.');
            return;
        }

        //Loop through each image
        $images.each(function() {
            const $img = $(this);
            const title = extractImageTitle($img);
            //console.log('Processing image:', $img, 'with title:', title);

            if (title) {
                console.log('Extracted title:', title);
                getImageLink(title, $img);

            } else {
                console.warn('No title found for image:', $img);
            }
        });
    }
});