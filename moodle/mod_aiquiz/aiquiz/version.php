<?php
/**
 * Detalles de versión del plugin.
 *
 * @package    mod_aiquiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_aiquiz';
$plugin->version   = 2026061502;
$plugin->requires  = 2022041900; // Moodle 4.0
$plugin->dependencies = [
    'local_ai_core' => 2026061500
];
