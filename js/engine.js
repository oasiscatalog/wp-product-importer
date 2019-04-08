var $j = jQuery.noConflict();
var i = 0;
var isImporting = false;
var importSettings = false;
var productImportInterval = false;
var errors = false;
var progress = 0;
var totalProgress = 0;

function beginImport() {

    var data = {
        'action': 'product_importer',
        'step': ajaxImport.step,
        'log': ajaxImport.settings.log,
        'advanced_log': ajaxImport.settings.advanced_log,
        'import_method': ajaxImport.settings.import_method,
        'cancel_import': ajaxImport.settings.cancel_import,
        'failed_import': ajaxImport.settings.failed_import
    }

    $j.post(ajaxImport.ajaxurl, data, function (r) {

        importSettings = r;
        checkForErrors();
        if (errors) return;
        updateLog(importSettings.log);
        totalProgress = importSettings.rows + 3;
        incrementProgress();

        var data = {
            'action': 'product_importer',
            'step': 'generate_categories',
            'settings': importSettings
        }

        $j.post(ajaxImport.ajaxurl, data, function (r) {

            importSettings = r;
            checkForErrors();
            if (errors) return;
            updateLog(importSettings.log);
            incrementProgress();

            var data = {
                'action': 'product_importer',
                'step': 'prepare_product_import',
                'settings': importSettings
            }

            $j.post(ajaxImport.ajaxurl, data, function (r) {

                importSettings = r;
                checkForErrors();
                if (errors) return;
                incrementProgress();
                i = r.i;

                productImportInterval = setInterval('importProduct()', 500);
            });
        });
    });
}

function importProduct() {

    if (!isImporting) {

        isImporting = true;

        var data = {
            'action': 'product_importer',
            'step': 'save_product',
            'settings': importSettings,
            'i': i
        }

        $j.post(ajaxImport.ajaxurl, data, function (r) {

            importSettings = r;
            checkForErrors();
            if (errors) return;
            updateLog(importSettings.log);
            incrementProgress();

            i++;
            if (i == importSettings.rows) {
                clearInterval(productImportInterval);
                finishImport();
            }
            isImporting = false;

        });
    }

}

function finishImport() {

    $j("#progress-bar").removeClass('blue').addClass('warning');

    var data = {
        'action': 'product_importer',
        'step': 'clean_up',
        'settings': importSettings
    }

    $j.post(ajaxImport.ajaxurl, data, function (r) {

        importSettings = r;
        checkForErrors();
        if (errors) return;
        updateLog(importSettings.log);
        $j("#pause-import").fadeOut(200);
        $j("#progress-bar").removeClass('warning').addClass('success');
        $j('#import-progress .finished-notice').fadeIn(200);
        $j('#import-progress .finished').fadeIn(200);

    });

}

function updateLog(data) {

    if (data) {
        data = data.replace(/\<br(\s*\/|)\>/g, '\r\n');
    }
    var log = $j('#installation_log');

    var scroll = false;
    if (log[0].scrollHeight - log.scrollTop() == log.innerHeight()) scroll = true;

    log.val(log.val() + data);
    $j(".ui-progress .ui-label").text(importSettings.loading_text);

    if (scroll) {
        log.animate({
            scrollTop: log[0].scrollHeight
        }, 500);
    }

    importSettings.log = '';

}

function checkForErrors() {

    var errorMessage = '';

    if (typeof(importSettings) != 'object') {
        errors = true;
        errorMessage = importSettings;
    }

    if (importSettings.cancel_import == 'true' || importSettings.cancel_import == true) {
        errors = true;
        errorMessage = importSettings.failed_import;
    }

    if (errors) {
        updateLog(importSettings.log);
        updateLog('<br /><br />' + errorMessage);
        $j("#pause-import").fadeOut(200);
        $j("#progress-bar").removeClass("warning").removeClass("blue").addClass("red");
        if ($j("#toggle_log").is(':checked') == false)
            $j('#toggle_log').trigger('click');
        $j("#reload-resume").slideDown(500);
        if (importSettings.step == 'save_product') {
            $j('#refresh-btn').hide();
            $j('#reload-btn').show();
        }
        $j("#reload_refresh_step").val(importSettings.step);
        $j("#reload_progress").val(progress);
        $j("#reload_total_progress").val(totalProgress + 1);
        if (i > 0)
            $j("#reload_restart_from").val(i - 1);

        $j(".ui-progress .ui-label").text(importSettings.loading_text);
    }

}

function incrementProgress() {

    if (progress == 0) {
        $j(".ui-progress").css('width', '2%');
        $j("#progress-bar").removeClass('warning').addClass('blue');
    }

    progress++;

    var percent = progress / totalProgress * 100;
    if (percent < 2) percent = 2;
    if (percent > 98 && percent < 100) percent = 98;

    $j(".ui-progress").animateProgress(percent);

}

$j(function () {

    $j("#progress-bar").addClass('warning');
    $j("#pause-import").slideDown(500);

    if (ajaxImport.step == 'save_product') {
        i = ajaxImport.settings.restart_from;
        progress = ajaxImport.settings.progress;
        totalProgress = ajaxImport.settings.total_progress;
        $j("#progress-bar").removeClass('warning').addClass('blue');
        // Adjust this to speed up/slow down imports
        productImportInterval = setInterval('importProduct()', 500);
    } else if (ajaxImport.step == 'clean_up') {
        finishImport();
    } else {
        beginImport();
    }

    $j(document).ajaxError(function (e, xhr, settings, exception) {
        importSettings.cancel_import = true;
        importSettings.failed_import = 'AJAX Error';

        if (xhr.responseText != '') importSettings.failed_import = importSettings.failed_import + ': ' + xhr.responseText;

        checkForErrors();
        if (errors) clearInterval(productImportInterval);
    });

    $j('#toggle_log').change(function () {

        if ($j(this).is(':checked')) {
            $j('#toggle_installation').fadeIn(200, function () {
                $j('#installation_log').scrollTop($j('#installation_log')[0].scrollHeight);
            });
        } else {
            $j('#toggle_installation').fadeOut(200);

        }
    });

});