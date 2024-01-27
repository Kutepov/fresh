$('body').on('change', '#adbanner-type', function () {
    $('.additional-field').removeClass('hidden');
    $('.field-adbanner-id_banner').addClass('hidden');
    $('#adbanner-provider').removeAttr('readonly');

    if ($(this).val() === 'category' || $(this).val() === 'feed') {
        $('#ad-banners-additional-fields').removeClass('hidden');
        if ($(this).val() === 'category') {
            $('#ad-banners-categories-field').removeClass('hidden');
        } else {
            $('#ad-banners-categories-field').addClass('hidden');
        }
    }
    else if ($(this).val() === 'article-body' || $(this).val() === 'similar-articles') {
        $('#ad-banners-additional-fields').removeClass('hidden');
        $('.field-adbanner-repeat_factor').addClass('hidden');
        $('.field-adbanner-limit').addClass('hidden');
        $('#ad-banners-categories-field').addClass('hidden');
        $('.field-adbanner-id_banner').removeClass('hidden');
        $('#adbanner-provider').val('googlead');
        $('#adbanner-provider').attr('readonly', true);
    }
    else {
        $('#ad-banners-additional-fields').addClass('hidden');
        $('#ad-banners-categories-field').addClass('hidden');
    }
});