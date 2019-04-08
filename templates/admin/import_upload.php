<div id="content" class="woo_pi_page_options">
    <form method="post" action="<?php echo add_query_arg('action', null); ?>" class="options">
        <div id="poststuff">
            <div class="postbox">
                <h3 class="hndle">Настройки импорта</h3>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <td>
                                <label for="import_method"><strong>Тип импорта</strong></label>
                                <div>
                                    <p>
                                        <label>
                                            <input type="radio" name="import_method"
                                                   value="new" <?php checked($import->import_method, 'new'); ?> />
                                            Импорт только новых товаров
                                        </label>
                                    </p>
                                    <p>
                                        <label>
                                            <input type="radio" name="import_method"
                                                   value="merge" <?php checked($import->import_method, 'merge'); ?> />
                                            Импорт новых товаров и обновление измененных
                                        </label>
                                    </p>
                                    <p>
                                        <label>
                                            <input type="radio" name="import_method"
                                                   value="update" <?php checked($import->import_method, 'update'); ?> />
                                            Обновление измененных товаров
                                        </label>
                                    </p>
                                </div>
                                <p class="description">Выберите метод импорта который Вам необходим. Обновление товаров
                                    происходит по артикулу.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <?php wp_nonce_field('update-options'); ?>
        <input type="hidden" name="action" value="save"/>
        <input type="hidden" name="json_file" value="<?php echo $import->json_file; ?>"/>
        <p class="submit">
            <input type="submit" value="Загрузить и импортировать данные" class="button-primary"/>
        </p>
    </form>
</div>