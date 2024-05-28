$(document).ready(function() {

    $(document).on('change', '.addChildCollection', function(event) {

        if ($(this).val() !== '' && !$(this).next().hasClass('addChildCollection'))
        {
            $(this).after($(this).clone());
        }

    })

    $(document).on('click', '.contact-list-button', function(event) {

        $(this).parent().parent().next().toggleClass('d-none');

    })

    $(document).on('change', '.tag-filter', function(event) {

        if ($(this).val() == '')
        {
            $("[data-tags]").show();
        }
        else
        {
            $("[data-tags]").hide();
            $("[data-tags*='"+$(this).val()+"']").show();
        }
        
    })

});

