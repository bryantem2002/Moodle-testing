<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_seguridad_category', get_string('pluginname', 'local_seguridad')));
    
    $settings = new admin_settingpage('local_seguridad', get_string('settings', 'local_seguridad'));
    $ADMIN->add('local_seguridad_category', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_seguridad/enable_clipboard_block',
        get_string('enable_clipboard_block', 'local_seguridad'),
        get_string('enable_clipboard_block_desc', 'local_seguridad'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_seguridad/enable_tab_monitoring',
        get_string('enable_tab_monitoring', 'local_seguridad'),
        get_string('enable_tab_monitoring_desc', 'local_seguridad'),
        1
    ));
}
