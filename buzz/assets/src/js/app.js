var fresh = function () {
    var self = this;

    this.showPopup = function (content, onClose) {
        $('[data-popup]').remove();
        $(content).appendTo('body');
        $('[data-popup]').addClass('is-opened');
        if (onClose) {
            $('[data-popup] [data-popup-close]').on('click', function () {
                onClose();
            });
        }
    };

    this.openLinkInApp = function (scheme, route, fallbackUrl) {
        window.open(scheme + route, '_self');
        setTimeout(function () {
            window.open(fallbackUrl, '_self');
        }, 2000);
    };

    this.loadPopup = function (url) {
        $.get(url, function (content) {
            Fresh.showPopup(content);
        });
    };

    $(function () {
        $(document).on('click', '[data-ios-deeplink]', function (event) {
            event.preventDefault();
            Fresh.openLinkInApp('myfresh://', $(this).data('ios-deeplink'), $(this).attr('href'));
        });

        $(document).on('click', '[data-android-deeplink]', function (event) {
            event.preventDefault();
            Fresh.openLinkInApp('fresh://', $(this).data('android-deeplink'), $(this).attr('href'));
        });

        $(document).on('click', '[data-rating-widget] [data-rating-positive], [data-rating-widget] [data-rating-negative]', function (e) {
            var id = $(this).closest('[data-rating-widget]').data('id');
            var entityType = $(this).closest('[data-rating-widget]').data('rating-widget');
            var isPositive = typeof $(e.target).attr('data-rating-positive') !== 'undefined';
            var action = isPositive ? 'up' : 'down';
            var url = '/' + entityType + 's/rating-' + action;
            var selector = '[data-rating-widget="' + entityType + '"][data-id="' + id + '"]';

            $.post(url + '?id=' + id, function (data) {
                $(selector).find('[data-rating-value]').text(data);

                var currentRating = parseInt(data);
                $(selector).find('[data-rating-value]').removeClass('is-positive');
                $(selector).find('[data-rating-value]').removeClass('is-negative');
                if (currentRating > 0) {
                    $(selector).find('[data-rating-value]').addClass('is-positive');
                } else if (currentRating < 0) {
                    $(selector).find('[data-rating-value]').addClass('is-negative');
                }

                if ($(selector).find('.is-active').length) {
                    $(selector).find('.is-active').removeClass('is-active');
                } else {
                    $(selector).find('[data-rating-' + (isPositive ? 'positive' : 'negative') + ']').addClass('is-active');
                }
            });

        });

        new ClipboardJS('[data-clipboard-text]');

        $(function () {
            $(document).on('click', '[data-popup-url]', function () {
                Fresh.loadPopup($(this).data('popup-url'));
            });

            $(document).on('click', '[data-popup-close]', function () {
                $(this).closest('[data-popup]').remove();
            });
        });

    });
};

var Fresh = new fresh();