<?php
defined('MOODLE_INTERNAL') || die();

function local_seguridad_extend_navigation(global_navigation $navigation) {
    global $PAGE, $COURSE, $CFG;

    // Usaremos la URL física del navegador para que nunca falle
    $script = $_SERVER['SCRIPT_NAME'];
    
    // Solo aplicar a la actividad de Exámenes (Quiz)
    if (strpos($script, '/mod/quiz/attempt.php') !== false) {
        
        $config = [
            'courseid' => $COURSE->id,
            'cmid' => isset($PAGE->cm) ? $PAGE->cm->id : 0,
            'str_warning' => '¡Advertencia! Se ha detectado que cambiaste de pestaña o ventana. Esta acción ha sido registrada. Una infracción más y tu examen será anulado.',
            'str_annulled' => 'Infracción grave detectada. Tu examen ha sido anulado y enviado automáticamente por motivos de seguridad.',
            'str_clipboard' => 'La función de copiar y pegar está deshabilitada en esta evaluación por políticas de seguridad.',
            'wwwroot' => $CFG->wwwroot,
            'sesskey' => sesskey()
        ];
        
        $PAGE->requires->js(new moodle_url('/local/seguridad/js/proctoring.js'));
        $PAGE->requires->js_init_call('init_proctoring', array($config));
    }
}
