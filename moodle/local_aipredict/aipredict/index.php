<?php
/**
 * Tablero global para Panel Predictivo IA.
 *
 * @package    local_aipredict
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();
$context = context_system::instance();

$PAGE->set_url('/local/aipredict/index.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_aipredict'));
$PAGE->set_heading(get_string('pluginname', 'local_aipredict'));

echo $OUTPUT->header();

// Obtener los cursos donde el usuario está inscrito y tiene capacidad de gestionar actividades
$courses = enrol_get_my_courses('*');
$managed_courses = [];

foreach ($courses as $c) {
    $coursecontext = context_course::instance($c->id);
    if (has_capability('moodle/course:manageactivities', $coursecontext)) {
        $managed_courses[] = $c;
    }
}

echo '<div class="container mt-4">';
echo '<h2>'.get_string('selectcourse', 'local_aipredict').'</h2>';

if (empty($managed_courses)) {
    echo $OUTPUT->notification(get_string('nocourses', 'local_aipredict'), 'warning');
} else {
    echo '<div class="row mt-4">';
    foreach ($managed_courses as $c) {
        $url = new moodle_url('/local/aipredict/view.php', ['courseid' => $c->id]);
        echo '<div class="col-md-4 mb-4">';
        echo '<div class="card shadow-sm">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title text-truncate" title="'.s($c->fullname).'">'.s($c->fullname).'</h5>';
        echo '<p class="card-text text-muted small">'.s($c->shortname).'</p>';
        echo '<a href="'.$url.'" class="btn btn-primary btn-block">'.get_string('analyze', 'local_aipredict').'</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div>';
echo $OUTPUT->footer();
