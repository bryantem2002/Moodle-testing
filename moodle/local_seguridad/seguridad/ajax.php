<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$courseid = required_param('courseid', PARAM_INT);
$action = required_param('action', PARAM_ALPHAEXT);
$status = required_param('status', PARAM_ALPHAEXT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$record = new stdClass();
$record->userid = $USER->id;
$record->courseid = $courseid;
$record->action = $action;
$record->status = $status;
$record->timecreated = time();

$DB->insert_record('local_seguridad_logs', $record);

if ($status == 'annulled') {
    global $DB, $CFG;
    // Cerramos CUALQUIER intento en progreso del usuario, sin depender del cmid
    $attempts = $DB->get_records('quiz_attempts', array('userid' => $USER->id, 'state' => 'inprogress'));
    foreach ($attempts as $attempt) {
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $DB->update_record('quiz_attempts', $attempt);
        
        // Actualizamos las calificaciones correctamente
        $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
        if ($quiz) {
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
            quiz_save_best_grade(quiz_update_grades($quiz, $USER->id));
        }
    }
}

echo json_encode(['success' => true]);
