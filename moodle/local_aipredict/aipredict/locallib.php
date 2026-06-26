<?php
/**
 * Biblioteca local para el bloque aipredict.
 *
 * @package    block_aipredict
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function aipredict_gather_course_data($courseid) {
    global $DB, $CFG;
    
    require_once($CFG->dirroot.'/course/lib.php');
    
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    
    // Obtener todos los estudiantes inscritos en el curso
    $all_enrolled = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname');
    $students = [];
    foreach ($all_enrolled as $u) {
        if (!has_capability('moodle/course:update', $context, $u->id)) {
            $students[] = $u;
        }
    }
    
    $dataset = [];
    
    // Contar total de módulos/actividades en el curso
    $modinfo = get_fast_modinfo($course);
    $total_activities = 0;
    foreach ($modinfo->cms as $cm) {
        if ($cm->completion != COMPLETION_TRACKING_NONE) {
            $total_activities++;
        }
    }

    foreach ($students as $student) {
        $student_data = [
            'id' => $student->id,
            'name' => fullname($student),
            'grade' => 'N/A',
            'last_access_days_ago' => 'Never',
            'total_actions' => 0,
            'activities_completed' => 0,
            'total_activities' => $total_activities
        ];
        
        // 1. Calificaciones (Usando consulta directa para evitar errores con clases grade_item)
        $course_item = $DB->get_record('grade_items', ['courseid' => $courseid, 'itemtype' => 'course']);
        if ($course_item) {
            $grade = $DB->get_record('grade_grades', ['itemid' => $course_item->id, 'userid' => $student->id]);
            if ($grade && !is_null($grade->finalgrade)) {
                $student_data['grade'] = round($grade->finalgrade, 2);
            }
        }
        
        // 2. Último acceso
        $lastaccess = $DB->get_record('user_lastaccess', ['userid' => $student->id, 'courseid' => $courseid]);
        if ($lastaccess) {
            $days = floor((time() - $lastaccess->timeaccess) / (60 * 60 * 24));
            $student_data['last_access_days_ago'] = $days;
        }
        
        // 3. Acciones totales (Registros / Logs)
        if ($DB->get_manager()->table_exists('logstore_standard_log')) {
            $actions = $DB->count_records('logstore_standard_log', ['userid' => $student->id, 'courseid' => $courseid]);
            $student_data['total_actions'] = $actions;
        }
        
        // 4. Finalización de actividades
        $completion_count = $DB->count_records_sql(
            "SELECT COUNT(cmc.id) 
             FROM {course_modules_completion} cmc
             JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
             WHERE cmc.userid = ? AND cm.course = ? AND cmc.completionstate IN (1, 2)",
            [$student->id, $courseid]
        );
        $student_data['activities_completed'] = $completion_count;
        
        // --- FILTRO DE EFICIENCIA (HU-2.04) ---
        // Si el estudiante nunca ha entrado y no tiene acciones, lo excluimos
        // del array final que se envía a la IA, ahorrando así Tokens de Entrada reales.
        if ($student_data['last_access_days_ago'] === 'Never' && $student_data['total_actions'] == 0) {
            continue; // Saltamos a este estudiante, no entra al dataset
        }
        
        $dataset[] = $student_data;
    }
    
    return $dataset;
}
