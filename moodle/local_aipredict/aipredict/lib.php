<?php
/**
 * Funciones de biblioteca para local_aipredict
 *
 * @package    local_aipredict
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extender la navegación para añadir un enlace al menú principal de navegación.
 */
function local_aipredict_extend_navigation(global_navigation $navigation) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $syscontext = context_system::instance();
    // Verificamos de forma general si el usuario ha iniciado sesión.
    // Los permisos específicos se comprueban directamente en la página.
    
    $url = new moodle_url('/local/aipredict/index.php');
    $node = $navigation->add(
        get_string('pluginname', 'local_aipredict'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'aipredict'
    );
    
    $node->showinflatnavigation = true; // Mostrar en la barra de navegación lateral si el tema lo soporta.
}
