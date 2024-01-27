var comments = function () {
    var self = this;

    this.loadAnswers = function (articleId, commentId) {
        $.get('/comments/answers-list', {
            articleId: articleId,
            parentCommentId: commentId
        }, function (response) {
            $('[data-comment-answers="' + commentId + '"]').append(response);
            $('[data-comment-expand="'+commentId+'"]').remove();
        });
    };

    $(document).on('click', '[data-comment-expand] button', function() {
        self.loadAnswers($(this).parent().data('article-id'), $(this).parent().data('comment-expand'));
    });
};

var Comments = new comments();