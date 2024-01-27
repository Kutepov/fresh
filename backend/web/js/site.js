if (typeof $.pjax !== 'undefined') {
    $.pjax.defaults.timeout = 100000;
}

initTooltips = function () {
    if (typeof $().tooltipster !== 'undefined') {
        $('[title][title!=""]').not('.tooltipstered').tooltipster({
            theme: 'tooltipster-shadow',
            delay: 100,
            interactive: true,
            contentAsHTML: true
        });
    }
};

$(document).ready(function () {
    initTooltips();
});

NProgress.configure({"minimum": 0.08, "showSpinner": false});

$(document).off('pjax:start');
jQuery(document).on('pjax:start', function () {
    NProgress.start();
});

$(document).off('ajaxStart');
jQuery(document).on('ajaxStart', function () {
    NProgress.start();
});

$(document).off('pjax:end');
$(document).on('pjax:end', function () {
    initTooltips();
    NProgress.done();
});

$(document).off('ajaxComplete');
$(document).on('ajaxComplete', function () {
    initTooltips();
    NProgress.done();
});

function loadArticleTopLog(id)
{
    $.post('/statistics/article-top-log', {
        'id': id
    }, function (data) {
        $('#top-log-modal .modal-body').html(data);
        $('#top-log-modal').modal('show');
    })
}