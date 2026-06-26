<?php
require_once('../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/seguridad/report.php', array('id' => $courseid));
$PAGE->set_title(get_string('report', 'local_seguridad'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'local_seguridad'));

$logs = $DB->get_records('local_seguridad_logs', array('courseid' => $courseid), 'timecreated DESC');

if (empty($logs)) {
    echo $OUTPUT->notification('No se han registrado infracciones.', 'info');
} else {
    $table = new html_table();
    $table->head = array(
        get_string('student', 'local_seguridad'),
        get_string('action', 'local_seguridad'),
        get_string('status', 'local_seguridad'),
        get_string('time', 'local_seguridad')
    );
    
    foreach ($logs as $log) {
        $user = $DB->get_record('user', array('id' => $log->userid));
        $status_class = ($log->status == 'annulled') ? 'badge badge-danger text-white p-1' : 'badge badge-warning text-dark p-1';
        
        $table->data[] = array(
            fullname($user),
            $log->action,
            html_writer::span($log->status, $status_class),
            userdate($log->timecreated)
        );
    }
    
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
