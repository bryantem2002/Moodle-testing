<?php
/**
 * Detalles de versión para local_aipredict
 *
 * @package    local_aipredict
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_aipredict';
$plugin->version   = 2026061600;
$plugin->requires  = 2022041900; // Moodle 4.0
$plugin->dependencies = [
    'local_ai_core' => 2026061500
];
