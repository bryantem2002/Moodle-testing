<?php
/**
 * Gemini API Client.
 *
 * @package    local_ai_core
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_core;

defined('MOODLE_INTERNAL') || die();

class client {
    
    private $api_key;
    private $model;
    private $base_url = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private $upload_url = 'https://generativelanguage.googleapis.com/upload/v1beta/files';

    public function __construct() {
        $this->api_key = get_config('local_ai_core', 'api_key');
        $this->model = get_config('local_ai_core', 'ai_model');
        
        if (empty($this->model)) {
            $this->model = 'gemini-1.5-flash';
        }
    }

    /**
     * Comprueba si el cliente está configurado.
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Sube un archivo a Gemini File API y retorna el URI del archivo.
     * Útil para PDFs.
     */
    public function upload_file($file_path, $mime_type = 'application/pdf', $display_name = 'Documento') {
        if (!$this->is_configured()) {
            throw new \moodle_exception('error_no_key', 'local_ai_core');
        }

        $file_size = filesize($file_path);
        $url = $this->upload_url . '?key=' . $this->api_key;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Solución para problemas de SSL en localhost bajo Windows
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $mime_type,
            'Content-Length: ' . $file_size
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($file_path));

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception("Error de cURL (Subida): " . $curl_error);
        }

        $result = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300 && isset($result['file']['uri'])) {
            return $result['file']; // Retorna array con 'uri', 'name', etc.
        }

        throw new \Exception($this->get_error_message($http_code, $response));
    }

    /**
     * Genera contenido basado en un prompt, opcionalmente con contexto de sistema o archivos.
     */
    public function generate_content($prompt, $system_instruction = null, $file_uri = null, $mime_type = null, $history = []) {
        if (!$this->is_configured()) {
            throw new \moodle_exception('error_no_key', 'local_ai_core');
        }

        $url = $this->base_url . $this->model . ':generateContent?key=' . $this->api_key;

        $payload = [
            'contents' => []
        ];

        // Añadir historial previo si es para un chat
        if (!empty($history)) {
            foreach ($history as $msg) {
                $payload['contents'][] = $msg;
            }
        }

        // Construir la parte del usuario actual
        $user_parts = [];
        
        if ($file_uri && $mime_type) {
            $user_parts[] = [
                'fileData' => [
                    'mimeType' => $mime_type,
                    'fileUri' => $file_uri
                ]
            ];
        }

        $user_parts[] = [
            'text' => $prompt
        ];

        $payload['contents'][] = [
            'role' => 'user',
            'parts' => $user_parts
        ];

        // Añadir instrucción del sistema si se proporciona
        if (!empty($system_instruction)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $system_instruction]
                ]
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Solución para problemas de SSL en localhost bajo Windows
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception("Error de cURL (Generación): " . $curl_error);
        }

        $result = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300 && isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }

        throw new \Exception($this->get_error_message($http_code, $response));
    }

    /**
     * Mapea los códigos de error HTTP de la API de Gemini a mensajes claros y amigables.
     */
    private function get_error_message($http_code, $response) {
        $error_data = json_decode($response, true);
        $error_msg = "Error de conexión con la IA (HTTP $http_code).";
        
        $code = $http_code;
        if (isset($error_data['error']['code'])) {
            $code = $error_data['error']['code'];
        }
        
        switch ($code) {
            case 400:
                $error_msg = 'La solicitud fue rechazada por la IA (400). Es posible que el archivo PDF o los datos del curso superen la longitud máxima o contengan caracteres no válidos.';
                break;
            case 401:
            case 403:
                $error_msg = 'Error de autenticación (403). Parece que la Clave de API de Gemini configurada en el sistema es inválida o ha expirado. Verifica la configuración del plugin Core IA.';
                break;
            case 429:
                $error_msg = 'El servidor de IA está temporalmente saturado debido al límite de cuota (Error 429). Por favor, espera unos 30 segundos y vuelve a intentarlo.';
                break;
            case 500:
                $error_msg = 'El servidor de Inteligencia Artificial de Google experimentó un error interno grave (Error 500). Por favor intenta de nuevo en unos minutos.';
                break;
            case 503:
                $error_msg = 'El servicio de Inteligencia Artificial se encuentra en mantenimiento o temporalmente fuera de línea (Error 503).';
                break;
            default:
                if (isset($error_data['error']['message'])) {
                    $error_msg = 'Error de la IA: ' . $error_data['error']['message'];
                } else {
                    $error_msg = "Error desconocido de la IA (Código $code).";
                }
                break;
        }
        return $error_msg;
    }
}
