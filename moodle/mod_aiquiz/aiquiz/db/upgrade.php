<?php
/**
 * Upgrade script for aiquiz.
 *
 * @package    mod_aiquiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_aiquiz_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061502) {

        // Define field custom_prompt to be added to aiquiz.
        $table = new xmldb_table('aiquiz');
        $field = new xmldb_field('custom_prompt', XMLDB_TYPE_TEXT, null, null, null, null, null, 'q_type');

        // Conditionally launch add field custom_prompt.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // aiquiz savepoint reached.
        upgrade_mod_savepoint(true, 2026061502, 'aiquiz');
    }

    return true;
}
