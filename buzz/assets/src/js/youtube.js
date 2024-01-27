var youtube = function () {
    this.videos = [];
    this.autostart = false;
    this.ready = false;

    this.enqueueVideo = function (videoId, autostart) {
        this.autostart = typeof autostart === 'undefined' ? false : autostart;
        this.videos.push(videoId);

        if (this.ready) {
            window.onYouTubeIframeAPIReady();
        }
    };

    this.loadVideo = function (videoId) {
        new YT.Player('youtube-player-' + videoId, {
            height: '360',
            width: '640',
            videoId: videoId
        });
    };
};

var YouTube = new youtube();

window.onYTPlayerReady = function (event) {
    if (YouTube.autostart) {
        // event.target.mute();
        event.target.playVideo();
    }
};

window.onYouTubeIframeAPIReady = function () {
    YouTube.ready = true;
    YouTube.videos.forEach(function (id) {
        YouTube.loadVideo(id);
    });
    YouTube.videos = [];
};