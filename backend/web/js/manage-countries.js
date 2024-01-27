$('body').on('change', '.js-default-language', function () {
    $('.js-default-language').each(function () {
        $(this).prop('checked', false);
    });
    $(this).prop('checked', true);
});

$('body').on('change', 'select.js-name-language', function () {
    let idField = $(this).attr('id').replace(/\D/g, '');
    let idLang = $(this).val();

    $.ajax({
        type: "get",
        data: {
            'id': idLang
        },
        url: "/countries/get-language-data",
        contentType: "application/json",
        success: function (data) {
            $('input[name="Country[languages][' + idField + '][code]"]').val(data.code);
            $('input[name="Country[languages][' + idField + '][short_name]"]').val(data.shortName);
        },
        error: function () {
            $('input[name="Country[languages][' + idField + '][code]"]').val('').prop('disabled', false);
            $('input[name="Country[languages][' + idField + '][short_name]"]').val('').prop('disabled', false);
        },
    });
});

