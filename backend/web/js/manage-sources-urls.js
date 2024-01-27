$(document).ready(function () {
    $('[name="selection[]"]').on('change', function () {
        let ids = $('#w1').yiiGridView('getSelectedRows');
        $('#js-button-delete-batch').attr('href', '/sources/categories/delete-batch?ids='+ids.toString());
        $('#js-button-enable-batch').attr('href', '/sources/categories/change-status-batch?ids='+ids.toString()+'&enable=1');
        $('#js-button-disable-batch').attr('href', '/sources/categories/change-status-batch?ids='+ids.toString()+'&enable=0');
    });

    $('#js-button-copy-to-buffer').tooltipster();
    $('body').on('click', '#js-button-copy-to-buffer',function () {
        let text = '';
        $('tr.danger').each(function () {
            text += $(this).find('.js-source-url').text().toString()+'\n';
        });

        $('#js-button-copy-to-buffer').tooltipster('destroy');

        if (text === '') {
            $('#js-button-copy-to-buffer').attr('title', 'Выберите строки для копирования');
        }
        else {
            $('#js-copy-data').val(text);
            $('#js-copy-data').select();
            if (document.execCommand('copy')) {
                $('#js-button-copy-to-buffer').attr('title', 'Успешно скопировано');
            } else {
                $('#js-button-copy-to-buffer').attr('title', 'Ошибка копирования');
            }
        }
        initTooltips();
        $('#js-button-copy-to-buffer').mouseenter();
    });


    $('#js-button-import').on('click', function (e) {
        $('#js-modal-import').find('.modal-header').html('Импорт категорий источников');
        $('#js-modal-import').modal('show');
        $('#js-modal-import').find('.modal-body').load("/sources/categories/import");
        return false;
    });
});

