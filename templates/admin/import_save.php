<div id="import-progress" class="postbox">
    <div class="inside">
        <div class="finished-notice" style="display:none;">
            <div class="updated settings-error below-h2">
                <p>Импорт завершен. Нажмите кнопку чтобы просмотреть результат.</p>
            </div>
        </div>
        <!-- .finished-notice -->
        <div id="progress-bar" class="ui-progress-bar warning transition">
            <div class="ui-progress">
                <span class="ui-label">Загрузка данных...</span>
            </div>
        </div>
        <!-- #progress-bar -->
        <table id="installation-controls">
            <tr>
                <td>
                    <label>
                        <input type="checkbox" id="toggle_log" name="log" class="checkbox" value="0"/>
                        Отображать сообщения
                    </label>
                </td>
            </tr>
            <tr>
                <td id="toggle_installation" style="display:none;">
                    <textarea id="installation_log" rows="30" readonly="readonly"
                              tabindex="2">Загрузка данных...</textarea>
                </td>
            </tr>
        </table>
        <!-- #installation-controls -->
        <div class="finished" style="display:none;">
            <input type="button" class="button" value="Вернуться к настройкам"
                   onclick="history.go(-1); return true;"/>
            <input type="button" class="button-primary" value="Результаты"/>
            <img src="<?php echo OASIS_PI_PLUGINPATH; ?>/templates/admin/images/loading.gif" class="pi-loading"
                 style="display:none;"/>
        </div>
        <!-- .finished -->
        <form id="reload-resume" action="" method="post" style="display:none; margin-bottom:10px;">
            <input type="hidden" name="action" value="save"/>
            <input type="hidden" id="reload_refresh_step" name="refresh_step" value="prepare_data"/>
            <input type="hidden" id="reload_progress" name="progress" value="0"/>
            <input type="hidden" id="reload_total_progress" name="total_progress" value="0"/>
            <input type="hidden" id="reload_restart_from" name="restart_from" value="0"/>
            <input type="hidden" name="import_method" value="<?php echo $import->import_method; ?>"/>
            <input type="button" class="button-primary" value="Вернуться к настройкам"
                   onclick="history.go(-1); return true;"/>
        </form>
    </div>
</div>

<div id="finish-import" class="postbox" style="display:none;"></div>