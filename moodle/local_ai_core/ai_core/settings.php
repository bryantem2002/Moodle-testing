<?php
/**
 * Ajustes de configuración para local_ai_core.
 *
 * @package    local_ai_core
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ai_core', get_string('pluginname', 'local_ai_core'));

    $settings->add(new admin_setting_configtext(
        'local_ai_core/api_key',
        get_string('api_key', 'local_ai_core'),
        get_string('api_key_desc', 'local_ai_core'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_ai_core/ai_model',
        get_string('ai_model', 'local_ai_core'),
        get_string('ai_model_desc', 'local_ai_core'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $ADMIN->add('localplugins', $settings);
}
