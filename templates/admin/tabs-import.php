<form id="upload_form" method="post">
    <div id="poststuff">

        <?php do_action('oasis_pi_before_upload'); ?>

        <div id="upload-csv" class="postbox">
            <h3 class="hndle">Загрузка товаров</h3>
            <div class="inside">
                <p>Вставьте ссылку на загрузку товаров из Личного кабинета <a
                            href="https://www.oasiscatalog.com/cabinet/integrations"
                            target="_blank">www.oasiscatlog.com</a></p>

                <div id="import-products-filters-upload" class="upload-method">
                    <label for="file_upload">
                        <strong>Укажите выгрузку товаров в формате JSON</strong>:</label>
                    <input type="text" class="regular-text code" id="json_file" name="json_file"
                           value="<?= $json_file; ?>" size="25"/>
                </div>

                <p class="submit">
                    <input type="submit" value="Загрузить и импортировать товары" class="button-primary"/>
                    <input type="reset" value="Сбросить" class="button"/>
                </p>
            </div>
        </div>

        <div id="upload-csv" class="postbox">
            <h3 class="hndle">Обновление товара</h3>
            <div class="inside">

                <?php if (!empty($_SESSION['import_result'])): ?>
                    <p style="color: red"><?= $_SESSION['import_result']; ?></p>
                    <?php unset($_SESSION['import_result']); ?>
                <?php endif; ?>
                <div id="import-products-filters-upload" class="upload-method">
                    <label for="article">
                        <strong>Укажите артикул</strong>:</label>
                    <input type="text" class="regular-text code" id="article" name="article"
                           value="" size="25"/>
                </div>

                <p class="submit">
                    <input type="submit" value="Обновить товар" class="button-primary"/>
                    <input type="reset" value="Сбросить" class="button"/>
                </p>
            </div>
        </div>

        <?php do_action('oasis_pi_after_upload'); ?>
    </div>

    <input type="hidden" name="action" value="upload"/>
    <input type="hidden" name="page_options" value="json_file"/>
    <?php wp_nonce_field('update-options'); ?>
</form>