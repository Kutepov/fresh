$(function () {
    $(document).on('change', '[data-country-dropdown]', function () {
        var country = $(this).val();
        document.location.href = $(this).data('country-dropdown') + '?country=' + country;
    });
});