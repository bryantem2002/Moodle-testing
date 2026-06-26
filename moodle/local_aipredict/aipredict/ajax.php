<?php
/**
 * Controlador AJAX para aipredict.
 *
 * @package    local_aipredict
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once('locallib.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

try {
    $courseid = required_param('courseid', PARAM_INT);
    require_sesskey();
    
    $context = context_course::instance($courseid);
    require_capability('moodle/course:manageactivities', $context);

    // Inicializar cliente core de IA
    if (!class_exists('\local_ai_core\client')) {
        throw new Exception('El plugin local_ai_core no está instalado o habilitado.');
    }
    
    $ai_client = new \local_ai_core\client();
    
    // Recopilar datos del curso
    $dataset = aipredict_gather_course_data($courseid);
    if (empty($dataset)) {
        throw new Exception('No hay estudiantes inscritos en el curso para analizar.');
    }
    
    $dataset_json = json_encode($dataset);

    // Construir el prompt
    $prompt = "Actúa como un Científico de Datos Educativo. A continuación, te proporcionaré un JSON con el registro de actividad de los estudiantes de un curso.\n\n";
    $prompt .= "Datos de los estudiantes:\n";
    $prompt .= $dataset_json . "\n\n";
    $prompt .= "Tu tarea es analizar estos datos e identificar qué estudiantes están en riesgo de reprobar o abandonar el curso. Toma en cuenta sus notas ('grade'), hace cuántos días no entran ('last_access_days_ago'), cantidad de clics/interacciones ('total_actions') y cuántas actividades han completado vs el total ('activities_completed' vs 'total_activities').\n\n";
    $prompt .= "INSTRUCCIÓN CRÍTICA: Debes responder ÚNICA y EXCLUSIVAMENTE con un arreglo JSON válido. No añadas saludos, ni bloques de código (```json), ni ningún otro texto.\n";
    $prompt .= "El formato JSON debe ser exactamente este:\n";
    $prompt .= "[\n  {\n    \"student_id\": 123,\n    \"student_name\": \"Nombre del estudiante\",\n    \"risk_level\": \"Alto\" (puede ser Alto, Medio o Bajo),\n    \"risk_percentage\": 85 (un nmero entero del 0 al 100 que representa el porcentaje de riesgo exacto),\n    \"reason\": \"Explicacin breve de por quǸ se le asign ese nivel de riesgo, basada en los datos\",\n    \"suggested_intervention\": \"Una sugerencia clara para el profesor de cmo intervenir y ayudar a este estudiante\"\n  }\n]";

    // Generar contenido
    $content = $ai_client->generate_content($prompt, null, null, null);
    
    // Limpieza agresiva de Markdown
    $content = trim($content);
    if (strpos($content, '```') === 0) {
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
    }

    // Validar JSON
    $json_decoded = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('La respuesta de la IA no es un formato válido de JSON.');
    }

    // Guardar el reporte en caché
    $report = new stdClass();
    $report->courseid = $courseid;
    $report->report_json = json_encode($json_decoded);
    $report->timecreated = time();
    
    global $DB;
    // Eliminar reportes anteriores de este curso
    $DB->delete_records('local_aipredict_reports', ['courseid' => $courseid]);
    $DB->insert_record('local_aipredict_reports', $report);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
