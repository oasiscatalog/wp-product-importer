<div id="content">
    <h2 class="nav-tab-wrapper">
        <a data-tab-id="overview" class="nav-tab<?php oasis_pi_admin_active_tab('overview'); ?>"
           href="<?php echo add_query_arg(array('page' => 'oasis_pi', 'tab' => 'overview'),
               'admin.php'); ?>">Главная</a>
        <a data-tab-id="export" class="nav-tab<?php oasis_pi_admin_active_tab('import'); ?>"
           href="<?php echo add_query_arg(array('page' => 'oasis_pi', 'tab' => 'import'), 'admin.php'); ?>">Импорт
            товаров</a>
    </h2>
    <?php oasis_pi_tab_template($tab); ?>
</div>
