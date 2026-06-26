<?php
/**
 * Funciones de biblioteca para local_aichat
 *
 * @package    local_aichat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extender la navegación para inyectar el JS del chatbot en las páginas de los cursos.
 */
function local_aichat_extend_navigation(global_navigation $navigation) {
    global $PAGE, $COURSE;

    // Solo inyectar si estamos dentro de un curso específico (id > 1)
    if (!empty($COURSE->id) && $COURSE->id > 1) {
        // Pasar variables PHP a JS (ID del curso, sesskey, wwwroot)
        $PAGE->requires->js_call_amd('local_aichat/chatbot', 'init', [
            'courseid' => $COURSE->id,
            'sesskey' => sesskey(),
            'wwwroot' => $PAGE->theme->setting_file_url('','') // o usar M.cfg.wwwroot global
        ]);
        
        // Incluir script JS estándar para el widget flotante
        $url = new moodle_url('/local/aichat/js/aichat.js');
        $PAGE->requires->js($url);
        
        // Pasar configuración al widget JS
        $config = [
            'courseid' => $COURSE->id,
            'sesskey' => sesskey(),
            'wwwroot' => (new moodle_url('/'))->out(false)
        ];
        $PAGE->requires->js_init_call('window.init_aichat_widget', [$config]);
    }
}
