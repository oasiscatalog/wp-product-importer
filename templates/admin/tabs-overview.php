<div class="overview-left">

    <h3>
        <div class="dashicons dashicons-migrate"></div>&nbsp;<a href="<?php echo add_query_arg('tab', 'import'); ?>">Импорт
            товаров</a>
    </h3>
    <p>Импорт товаров в WooCommerce из API oasiscatlog.com.</p>

    <p>Для включения автоматического обновления каталога необходимо в панели управления Хостингом добавить crontab
        задачу:<br/>
        <br/>

        <code style="border: dashed 1px #333; border-radius: 4px; padding: 10px 20px;">php <?= OASIS_PI_PATH . 'cron.php'; ?></code>

    </p>

</div>
