jQuery(document).ready(function ($) {
    var isPaused = false;
    var imageIds = [];
    var totalImages = 0;
    var processedCount = 0;

    $('#wpio-start-bulk').on('click', function () {
        if ($(this).hasClass('disabled')) return;

        $(this).hide();
        $('#wpio-pause-bulk').show();
        $('#wpio-progress-container').show();

        log('Starting optimization...');

        // Step 1: Get the list of IDs
        $.ajax({
            url: wpio_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpio_get_unoptimized_images',
                nonce: wpio_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    imageIds = response.data.ids;
                    totalImages = response.data.total;
                    log('Found ' + totalImages + ' images to process.');
                    processNext();
                } else {
                    log('Error finding images: ' + response.data, 'error');
                }
            },
            error: function () {
                log('AJAX Error.', 'error');
            }
        });
    });

    $('#wpio-pause-bulk').on('click', function () {
        isPaused = !isPaused;
        $(this).text(isPaused ? 'Resume' : 'Pause');
        if (!isPaused) processNext();
    });

    function processNext() {
        if (isPaused) return;
        if (imageIds.length === 0) {
            log('Bulk optimization complete!');
            $('#wpio-pause-bulk').hide();
            $('#wpio-start-bulk').show().text('Bulk Optimization Finished').addClass('disabled');
            return;
        }

        var currentId = imageIds.shift();
        processedCount++;

        updateProgress();

        $.ajax({
            url: wpio_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpio_process_image',
                attachment_id: currentId,
                nonce: wpio_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    log('Optimized ID ' + currentId + ': success.');
                } else {
                    log('Failed to optimize ID ' + currentId + ': ' + response.data, 'error');
                }
                processNext();
            },
            error: function () {
                log('AJAX Error processing ID ' + currentId, 'error');
                processNext();
            }
        });
    }

    function log(message, type) {
        var $log = $('#wpio-log');
        if ($log.find('em').length) $log.empty();

        var color = type === 'error' ? 'red' : 'inherit';
        $log.append('<div style="color:' + color + ';">[' + new Date().toLocaleTimeString() + '] ' + message + '</div>');
        $log.scrollTop($log[0].scrollHeight);
    }

    function updateProgress() {
        var percent = Math.round((processedCount / totalImages) * 100);
        $('#wpio-progress-inner').css('width', percent + '%');
        $('#wpio-progress-text').text('Processing ' + processedCount + ' of ' + totalImages + ' (' + percent + '%)');
    }
});
