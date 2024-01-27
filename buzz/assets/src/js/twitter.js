var twitter = function () {
    this.loadPost = function (id) {
        twttr.widgets.createTweet(
            id,
            document.getElementById('twitter-widget-' + id)
        );
    };
};

var Twitter = new twitter();