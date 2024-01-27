yii.multilingual = (function ($) {
    var pub = {
        buttonSelector: '[data-toggle="pill"]',
        switcherSelector: '[name="language-switcher"]',
        fieldSelector: '[data-toggle="multilingual-field"]',
        init: function () {
            $(document).on('change', pub.switcherSelector, switchLanguage);
            $(document).on('afterValidate', $(pub.switcherSelector).closest('form'), afterValidateAction);
        }
    };

    function switchLanguage(event) {
        var language = $(this).find('option:selected').val();
        $(pub.fieldSelector).hide().filter('[data-lang="' + language + '"]').show();
    }

    function afterValidateAction(event) {
        var language = getLanguageWithErrors();
        if (language !== false) {
            $(pub.switcherSelector).val(language).change();
            $(pub.fieldSelector).hide().filter('[data-lang="' + language + '"]').show();
        }
    }

    function getLanguageWithErrors() {
        var language = false;

        $(pub.switcherSelector).find('option').each(function (index, button) {
            var lang = $(button).attr('value');
            var errors = $(pub.fieldSelector).filter('[data-lang="' + lang + '"]').filter('.has-error');

            if (errors.length > 0) {
                language = lang;
                return false;
            }
        });

        return language;
    }

    return pub;
})(jQuery);