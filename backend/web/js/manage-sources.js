$('body').on('change', '#source-type', function () {
    if ($(this).val() === 'webview') {
        $('.webview-wrapper-js').removeClass('hidden');
    } else {
        $('.webview-wrapper-js').addClass('hidden');
    }
});