<?php
/**
 * Controlador AJAX para generar preguntas con IA.
 *
 * @package    mod_aiquiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

try {
    $cmid = required_param('cmid', PARAM_INT);
    require_sesskey();
    
    $cm = get_coursemodule_from_id('aiquiz', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $aiquiz = $DB->get_record('aiquiz', array('id' => $cm->instance), '*', MUST_EXIST);

    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);
    require_capability('mod/aiquiz:manage', $context);

    // Inicializar cliente core de IA
    if (!class_exists('\local_ai_core\client')) {
        throw new Exception('El plugin local_ai_core no está instalado o habilitado.');
    }
    
    $ai_client = new \local_ai_core\client();
    
    // Obtener el archivo PDF adjunto
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_aiquiz', 'pdf_base', 0, 'itemid', false);
    
    if (empty($files)) {
        throw new Exception('No hay ningún archivo PDF adjunto a esta actividad.');
    }
    
    // Filtrar archivos reales (Moodle a veces guarda un archivo "." oculto como primer elemento)
    $file = null;
    foreach ($files as $f) {
        if ($f->get_filename() !== '.') {
            $file = $f;
            break;
        }
    }
    
    if (!$file) {
        throw new Exception('No se encontró el archivo PDF adjunto.');
    }
    
    // Extraerlo a un archivo temporal para enviarlo a Gemini
    $temp_dir = make_request_directory();
    $temp_file = $temp_dir . '/' . time() . '_' . $file->get_filename();
    $file->copy_content_to($temp_file);
    
    // Subir el archivo a Gemini
    $file_data = $ai_client->upload_file($temp_file, 'application/pdf', 'Base Quiz');
    
    if (!isset($file_data['uri'])) {
        throw new Exception('No se pudo procesar el archivo en la nube de IA.');
    }

    // Construir el prompt estructurado
    $tipo = ($aiquiz->q_type == 'abierta') ? 'PREGUNTAS ABIERTAS (de desarrollo o ensayo)' : 'PREGUNTAS CERRADAS (de opción múltiple)';
    
    $prompt = "Actúa como un profesor experto. Basándote en el documento proporcionado, genera exactamente {$aiquiz->num_questions} preguntas de dificultad '{$aiquiz->difficulty}'.\n";
    $prompt .= "El tipo de preguntas debe ser: {$tipo}.\n";
    
    if (!empty($aiquiz->custom_prompt)) {
        $prompt .= "\nINSTRUCCIONES EXTRA DEL PROFESOR:\n" . $aiquiz->custom_prompt . "\n\n";
    }
    
    $prompt .= "INSTRUCCIÓN CRÍTICA: Debes responder ÚNICA y EXCLUSIVAMENTE con un arreglo JSON válido. No añadas saludos, ni bloques de código (```json), ni ningún otro texto.\n";
    $prompt .= "El formato JSON debe ser exactamente este:\n";
    if ($aiquiz->q_type == 'abierta') {
        $prompt .= "[\n  {\n    \"pregunta\": \"Texto de la pregunta\",\n    \"respuesta_esperada\": \"Texto que describe lo que se espera como respuesta correcta\"\n  }\n]";
    } else {
        $prompt .= "[\n  {\n    \"pregunta\": \"Texto de la pregunta\",\n    \"opciones\": [\"Opción A\", \"Opción B\", \"Opción C\", \"Opción D\"],\n    \"respuesta_correcta\": \"Texto exacto de la opción correcta\"\n  }\n]";
    }

    // Generar contenido
    $content = $ai_client->generate_content($prompt, null, $file_data['uri'], $file_data['mimeType']);
    
    // Limpieza agresiva de Markdown en caso de que Gemini ignore la instrucción de no usar ```json
    $content = trim($content);
    if (strpos($content, '```') === 0) {
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
    }

    // Validar JSON
    $json_decoded = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('La respuesta de la IA no es un formato válido de JSON. Por favor intenta de nuevo.');
    }

    // Guardar en la BD local del plugin
    $aiquiz->questions_json = json_encode($json_decoded);
    $DB->update_record('aiquiz', $aiquiz);

    // --- INTEROPERABILIDAD: INYECTAR EN EL BANCO DE PREGUNTAS NATIVO ---
    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot . '/question/format.php');
    require_once($CFG->dirroot . '/question/format/xml/format.php');

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n";

    // Crear la categoría estructurada para este cuestionario
    $cat_name = "IA - " . format_string($aiquiz->name);
    $xml .= "  <question type=\"category\">\n";
    $xml .= "    <category>\n";
    $xml .= "      <text>\$course\$/" . htmlspecialchars($cat_name) . "</text>\n";
    $xml .= "    </category>\n";
    $xml .= "    <info format=\"html\">\n";
    $xml .= "      <text></text>\n";
    $xml .= "    </info>\n";
    $xml .= "  </question>\n";

    foreach ($json_decoded as $idx => $q) {
        if ($aiquiz->q_type == 'abierta') {
            $xml .= "  <question type=\"essay\">\n";
            $xml .= "    <name><text>" . htmlspecialchars("P" . ($idx+1) . " - " . substr($q['pregunta'], 0, 50)) . "</text></name>\n";
            $xml .= "    <questiontext format=\"html\">\n";
            $xml .= "      <text><![CDATA[<p>" . $q['pregunta'] . "</p>]]></text>\n";
            $xml .= "    </questiontext>\n";
            $xml .= "    <defaultgrade>1.0</defaultgrade>\n";
            $xml .= "    <penalty>0.0</penalty>\n";
            $xml .= "    <hidden>0</hidden>\n";
            $xml .= "    <responseformat>editor</responseformat>\n";
            $xml .= "    <responserequired>1</responserequired>\n";
            $xml .= "    <responsefieldlines>15</responsefieldlines>\n";
            $xml .= "    <graderinfo format=\"html\">\n";
            $xml .= "      <text><![CDATA[<p><strong>Respuesta Esperada:</strong><br/>" . $q['respuesta_esperada'] . "</p>]]></text>\n";
            $xml .= "    </graderinfo>\n";
            $xml .= "  </question>\n";
        } else {
            $xml .= "  <question type=\"multichoice\">\n";
            $xml .= "    <name><text>" . htmlspecialchars("P" . ($idx+1) . " - " . substr($q['pregunta'], 0, 50)) . "</text></name>\n";
            $xml .= "    <questiontext format=\"html\">\n";
            $xml .= "      <text><![CDATA[<p>" . $q['pregunta'] . "</p>]]></text>\n";
            $xml .= "    </questiontext>\n";
            $xml .= "    <defaultgrade>1.0</defaultgrade>\n";
            $xml .= "    <penalty>0.3333333</penalty>\n";
            $xml .= "    <hidden>0</hidden>\n";
            $xml .= "    <single>true</single>\n";
            $xml .= "    <shuffleanswers>true</shuffleanswers>\n";
            $xml .= "    <answernumbering>abc</answernumbering>\n";

            foreach ($q['opciones'] as $opt) {
                $fraction = ($opt === $q['respuesta_correcta']) ? "100" : "0";
                $xml .= "    <answer fraction=\"{$fraction}\" format=\"html\">\n";
                $xml .= "      <text><![CDATA[<p>" . $opt . "</p>]]></text>\n";
                $xml .= "    </answer>\n";
            }
            $xml .= "  </question>\n";
        }
    }
    $xml .= "</quiz>\n";

    $xml_file = $temp_dir . '/questions_export.xml';
    file_put_contents($xml_file, $xml);

    $qformat = new \qformat_xml();
    $qformat->setContexts([context_course::instance($course->id)]);
    $qformat->setCourse($course);
    $qformat->setFilename($xml_file);
    $qformat->setMatchgrades('error');
    $qformat->setCatfromfile(true);
    $qformat->setContextfromfile(true);
    $qformat->setStoponerror(true);
    $qformat->setRealfilename('questions_export.xml');
    
    // Ejecutar la importación silenciosamente ocultando cualquier HTML que Moodle imprima por defecto
    ob_start();
    $qformat->importprocess();
    ob_end_clean();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
