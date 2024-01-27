var Comments = function () {
    this.translate = function (id) {
        var button = $('[data-comment-translate-button="' + id + '"]');
        var translatedText = $('[data-comment-translated-text="' + id + '"]');
        var originalText = $('[data-comment-original-text="' + id + '"]');

        if (translatedText.is(':hidden')) {
            $.get('/comments/translate', {
                'id': id
            }, function (data) {
                translatedText.html(data);
                translatedText.show();
                originalText.hide();
                button.text('Показать оригинал');
            });
        } else {
            translatedText.hide();
            originalText.show()
            button.text('Перевести');
        }
    };
}

var comments = new Comments();