<?php
/**
 * Controlador AJAX para aichat.
 *
 * @package    local_aichat
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
ini_set('display_errors', '0');
error_reporting(0);

$courseid = required_param('courseid', PARAM_INT);
$sectionnum = optional_param('section', 0, PARAM_INT);
$message = required_param('message', PARAM_RAW);

require_login($courseid);
$context = context_course::instance($courseid);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

try {
    
    $modinfo = get_fast_modinfo($course);
    $section_context_text = "Estás asistiendo en el curso '{$course->fullname}'. El usuario tiene una duda sobre la sección/tema {$sectionnum}.\n\n";
    $section_context_text .= "Aquí tienes el contenido y los recursos que están en esa sección. Basa tu respuesta en esta información:\n\n";
    
    $inline_files = []; // Almacenar PDFs adjuntos
    
    // Determinar qué secciones incluir
    $sections_to_include = [];
    if ($sectionnum == 0) {
        // Si está en la página principal, incluir todas las secciones
        $sections_to_include = array_keys($modinfo->sections);
    } else {
        // Si está en una sección específica, incluir la sección 0 (contexto general) y la sección actual
        $sections_to_include = [0, $sectionnum];
    }
    
    $fs = get_file_storage();
    
    foreach ($sections_to_include as $sec) {
        if (!isset($modinfo->sections[$sec])) continue;
        
        $section_info = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $sec]);
        $sec_name = $section_info && !empty($section_info->name) ? $section_info->name : "Sección {$sec}";
        
        $section_context_text .= "\n=== {$sec_name} ===\n";
        
        $cmids = $modinfo->sections[$sec];
        
        foreach ($cmids as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) continue;
            
            $section_context_text .= "--- Recurso: {$cm->name} ({$cm->modname}) ---\n";
            
            if ($cm->modname === 'page') {
                $page = $DB->get_record('page', ['id' => $cm->instance]);
                if ($page) {
                    $section_context_text .= strip_tags($page->content) . "\n\n";
                }
            } else if ($cm->modname === 'label') {
                $label = $DB->get_record('label', ['id' => $cm->instance]);
                if ($label) {
                    $section_context_text .= strip_tags($label->intro) . "\n\n";
                }
            } else if ($cm->modname === 'resource') {
                $resource_context = context_module::instance($cm->id);
                $files = $fs->get_area_files($resource_context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
                foreach ($files as $f) {
                    if ($f->get_mimetype() === 'application/pdf') {
                        $section_context_text .= "[Archivo PDF adjunto: {$f->get_filename()}]\n\n";
                        $inline_files[] = [
                            'mime_type' => 'application/pdf',
                            'data' => base64_encode($f->get_content())
                        ];
                    } else if ($f->get_mimetype() === 'text/plain') {
                        $section_context_text .= "[Archivo de texto: {$f->get_filename()}]\n";
                        $section_context_text .= $f->get_content() . "\n\n";
                    }
                }
            }
        }
    }
    
    if (empty($inline_files) && trim($section_context_text) == "") {
        $section_context_text .= "No hay recursos en esta sección.\n";
    }
    
    
    $api_key = get_config('local_ai_core', 'gemini_api_key');
    if (empty($api_key)) {
        throw new Exception('API Key no configurada en local_ai_core.');
    }
    
    $parts = [];
    $parts[] = ['text' => $section_context_text . "\n\nPregunta del estudiante: " . $message];
    
    foreach ($inline_files as $file) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $file['mime_type'],
                'data' => $file['data']
            ]
        ];
    }
    
    $payload = [
        'contents' => [
            ['parts' => $parts]
        ],
        'systemInstruction' => [
            'parts' => [
                ['text' => 'Eres un tutor amigable de Moodle. Responde las dudas del estudiante usando SOLO la información de los recursos del curso proporcionados. Si la respuesta no está en los recursos, dile amablemente que consulte con su profesor.']
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2
        ]
    ];
    
    $model = get_config('local_ai_core', 'gemini_model');
    if (empty($model)) {
        $model = 'gemini-1.5-flash';
    }
    
    
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evitar problemas de certificado SSL en XAMPP Windows
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            echo json_encode(['success' => true, 'response' => $data['candidates'][0]['content']['parts'][0]['text']]);
        } else {
            echo json_encode(['error' => 'Respuesta inesperada de la IA.']);
        }
    } else {
        $error_data = json_decode($response, true);
        $error_msg = 'Error de conexión con la IA.';
        
        if (isset($error_data['error']['code'])) {
            $code = $error_data['error']['code'];
            switch ($code) {
                case 400:
                    $error_msg = 'La solicitud fue rechazada por la IA. Es posible que el mensaje sea demasiado largo o contenga caracteres inválidos.';
                    break;
                case 401:
                case 403:
                    $error_msg = 'Error de autenticación. Parece que la Clave de API configurada en el sistema es inválida o ha expirado. Contacta al soporte.';
                    break;
                case 429:
                    $error_msg = 'El servidor de IA está saturado en este momento debido al límite de uso. Por favor, espera unos 30 segundos y vuelve a intentarlo.';
                    break;
                case 500:
                    $error_msg = 'El servidor de Inteligencia Artificial experimentó un error interno grave. Intenta de nuevo más tarde.';
                    break;
                case 503:
                    $error_msg = 'El servicio de Inteligencia Artificial se encuentra en mantenimiento o temporalmente fuera de línea.';
                    break;
                default:
                    $error_msg = 'Error desconocido de la IA (Código '.$code.')';
                    break;
            }
        } else if (isset($error_data['error']['message'])) {
            $error_msg = 'Error de la IA: ' . $error_data['error']['message'];
        }
        
        echo json_encode(['error' => $error_msg]);
    }

} catch (Exception $e) {
    
    echo json_encode(['error' => $e->getMessage()]);
}
