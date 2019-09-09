<div class="inside">
    <?php oasis_pi_finish_message(); ?>
    <p>Теперь Вы можете управлять своими продуктами, посетив раздел Продукты.</p>
    <div class="buttons">
        <a href="<?php echo add_query_arg('post_type', 'product', 'edit.php'); ?>"
           class="button-primary button-separator">Управление товарами</a>
        <a href="<?php echo add_query_arg('page', 'oasis_pi', 'admin.php'); ?>" class="button">Импорт товаров</a>
    </div>
</div>