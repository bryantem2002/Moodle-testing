<?php
/**
 * Página de visualización para aiquiz.
 *
 * @package    mod_aiquiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // ID del Módulo de Curso
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('aiquiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$aiquiz = $DB->get_record('aiquiz', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/aiquiz/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($aiquiz->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/mod/aiquiz/styles.css');

// Manejar publicación y guardado de borrador del profesor
if ($action == 'publish' && has_capability('mod/aiquiz:manage', $context)) {
    require_sesskey();
    require_once($CFG->dirroot.'/course/lib.php');
    
    $edited_json = required_param('questions_json', PARAM_RAW);
    $action_type = optional_param('action_type', 'publish', PARAM_ALPHA); // publish o draft
    
    $aiquiz->questions_json = $edited_json;
    if ($action_type == 'publish') {
        $aiquiz->status = 'published';
        set_coursemodule_visible($cm->id, 1);
        $msg = 'Cuestionario finalizado. La visibilidad dependerá de las fechas que hayas programado.';
    } else {
        $aiquiz->status = 'draft';
        set_coursemodule_visible($cm->id, 0);
        $msg = 'Progreso guardado. El cuestionario se ha ocultado por completo a los estudiantes.';
    }
    
    $DB->update_record('aiquiz', $aiquiz);
    redirect($PAGE->url, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Manejar envío del alumno
if ($action == 'submit' && has_capability('mod/aiquiz:submit', $context)) {
    require_sesskey();
    $answers = $_POST['answers'] ?? []; // Arreglo de respuestas ingresadas
    
    $attempt = new stdClass();
    $attempt->aiquizid = $aiquiz->id;
    $attempt->userid = $USER->id;
    $attempt->answers_json = json_encode($answers);
    $attempt->timecreated = time();
    $DB->insert_record('aiquiz_attempts', $attempt);
    
    redirect($PAGE->url, 'Tus respuestas han sido enviadas.', null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// --- CSS para Overlay de Carga ---
echo '
<style>
#ai-loading-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(255,255,255,0.9);
    z-index: 9999;
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}
.ai-spinner {
    width: 4rem; height: 4rem;
    border: 0.5em solid #f3f3f3;
    border-top: 0.5em solid #0f6cbf;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.q-card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 15px; background: #fff; }
.q-options .input-group { margin-bottom: 5px; }
</style>
<div id="ai-loading-overlay">
    <div class="ai-spinner"></div>
    <h3 id="ai-loading-text">La Inteligencia Artificial está analizando tu PDF...</h3>
    <p>Esto puede tardar hasta un minuto dependiendo del tamaño del archivo.</p>
</div>
';

echo $OUTPUT->heading(format_string($aiquiz->name));

if ($aiquiz->intro) {
    echo $OUTPUT->box(format_module_intro('aiquiz', $aiquiz, $cm->id), 'generalbox mod_introbox', 'intro');
}

// Vista Profesor
if (has_capability('mod/aiquiz:manage', $context)) {
    
    // Comprobar si ya hay intentos de estudiantes
    $has_attempts = $DB->record_exists('aiquiz_attempts', ['aiquizid' => $aiquiz->id]);
    
    if ($aiquiz->status == 'draft') {
        echo $OUTPUT->notification('Estado: BORRADOR. Oculto para los estudiantes.', 'warning');
    } else {
        echo $OUTPUT->notification('Estado: FINALIZADO. (Visible según las fechas programadas en Moodle)', 'success');
    }
    
    if (empty($aiquiz->questions_json)) {
        // Botón para generar
        echo '<div class="text-center mt-5 mb-5">
                <button id="btn-generate-ai" class="btn btn-primary btn-lg" style="padding: 15px 30px; font-size: 1.2rem;">
                    <i class="fa fa-magic"></i> Generar Preguntas con IA
                </button>
              </div>';
        
        // Script para llamar a ajax
        echo "
        <script>
        document.getElementById('btn-generate-ai').addEventListener('click', function() {
            document.getElementById('ai-loading-overlay').style.display = 'flex';
            
            fetch('ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'cmid={$cm->id}&sesskey=' + M.cfg.sesskey
            })
            .then(r => r.json())
            .then(data => {
                if(data.error) {
                    alert(data.error);
                    document.getElementById('ai-loading-overlay').style.display = 'none';
                } else {
                    document.getElementById('ai-loading-text').innerText = '¡Listo! Preparando editor...';
                    setTimeout(() => window.location.reload(), 1000);
                }
            })
            .catch(e => {
                alert('Error de red al contactar con el servidor.');
                document.getElementById('ai-loading-overlay').style.display = 'none';
            });
        });
        </script>";
    } else if (!$has_attempts) {
        // Mostrar formulario para editar el JSON y Publicar
        $q_type = $aiquiz->q_type;
        echo '<h3>Editor de Preguntas</h3>';
        echo '<p>Como ningún estudiante ha resuelto aún el cuestionario, puedes seguir editando las preguntas con total libertad.</p>';
        echo '<form method="POST" action="view.php?id='.$cm->id.'&action=publish" id="publishForm">';
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'">';
        echo '<input type="hidden" name="questions_json" id="final_questions_json">';
        
        echo '<input type="hidden" name="action_type" id="form-action-type" value="publish">';
        
        echo '<div id="editor-container"></div>';
        
        echo '<hr>';
        echo '<div class="d-flex justify-content-between">';
        echo '  <button type="button" class="btn btn-secondary mt-3" id="btn-add-q"><i class="fa fa-plus"></i> Añadir otra pregunta</button>';
        echo '  <div>';
        echo '    <button type="submit" class="btn btn-outline-primary mt-3 mr-2" onclick="document.getElementById(\'form-action-type\').value=\'draft\'"><i class="fa fa-eye-slash"></i> Guardar Oculto (Nadie lo verá)</button>';
        
        // Comprobar si realmente hay restricciones configuradas en Moodle
        $is_scheduled = false;
        if (!empty($cm->availability)) {
            $avail_obj = json_decode($cm->availability);
            if ($avail_obj && isset($avail_obj->c) && count($avail_obj->c) > 0) {
                $is_scheduled = true;
            }
        }
        
        if ($is_scheduled) {
            echo '    <button type="submit" class="btn btn-success mt-3" onclick="document.getElementById(\'form-action-type\').value=\'publish\'"><i class="fa fa-calendar-check-o"></i> Finalizar y Dejar Programado</button>';
        } else {
            echo '    <button type="submit" class="btn btn-success mt-3" onclick="document.getElementById(\'form-action-type\').value=\'publish\'"><i class="fa fa-check"></i> Finalizar y Publicar Ahora</button>';
        }
        
        echo '  </div>';
        echo '</div>';
        echo '</form>';
        
        // Lógica de renderizado en JavaScript
        echo "
        <script>
        let questions = " . ($aiquiz->questions_json ? $aiquiz->questions_json : '[]') . ";
        let qType = '{$q_type}';
        
        function renderEditor() {
            const container = document.getElementById('editor-container');
            container.innerHTML = '';
            
            questions.forEach((q, index) => {
                let card = document.createElement('div');
                card.className = 'q-card';
                
                let html = `<h5>Pregunta \${index + 1} 
                    <button type=\"button\" class=\"btn btn-sm btn-danger float-right\" onclick=\"removeQuestion(\${index})\">Eliminar</button>
                </h5>`;
                
                html += `<div class=\"form-group\">
                            <label>Enunciado:</label>
                            <textarea class=\"form-control q-enunciado\" data-idx=\"\${index}\" rows=\"2\">\${q.pregunta || ''}</textarea>
                         </div>`;
                         
                if (qType === 'abierta') {
                    html += `<div class=\"form-group\">
                                <label>Respuesta Esperada / Rúbrica:</label>
                                <textarea class=\"form-control q-respuesta\" data-idx=\"\${index}\" rows=\"3\">\${q.respuesta_esperada || ''}</textarea>
                             </div>`;
                } else {
                    html += `<label>Opciones y Respuesta Correcta (Selecciona la correcta):</label>`;
                    html += `<div class=\"q-options\" id=\"options-container-\${index}\">`;
                    
                    let options = q.opciones || [];
                    let correctAnswer = q.respuesta_correcta || '';
                    
                    options.forEach((opt, optIndex) => {
                        let isChecked = (opt === correctAnswer && opt !== '') ? 'checked' : '';
                        html += `
                        <div class=\"input-group\">
                            <div class=\"input-group-prepend\">
                                <div class=\"input-group-text\">
                                    <input type=\"radio\" name=\"correct_\${index}\" value=\"\${optIndex}\" \${isChecked}>
                                </div>
                            </div>
                            <input type=\"text\" class=\"form-control q-opt-text\" data-qidx=\"\${index}\" data-optidx=\"\${optIndex}\" value=\"\${opt}\">
                            <div class=\"input-group-append\">
                                <button class=\"btn btn-outline-danger\" type=\"button\" onclick=\"removeOption(\${index}, \${optIndex})\">X</button>
                            </div>
                        </div>`;
                    });
                    html += `</div>`;
                    html += `<button type=\"button\" class=\"btn btn-sm btn-info mt-2\" onclick=\"addOption(\${index})\">+ Añadir Opción</button>`;
                }
                
                card.innerHTML = html;
                container.appendChild(card);
            });
        }
        
        window.removeQuestion = function(idx) {
            questions.splice(idx, 1);
            renderEditor();
        };
        
        window.addOption = function(qIdx) {
            if(!questions[qIdx].opciones) questions[qIdx].opciones = [];
            questions[qIdx].opciones.push('Nueva opción');
            renderEditor();
        };
        
        window.removeOption = function(qIdx, optIdx) {
            questions[qIdx].opciones.splice(optIdx, 1);
            renderEditor();
        };
        
        document.getElementById('btn-add-q').addEventListener('click', () => {
            if(qType === 'abierta') {
                questions.push({pregunta: 'Nueva pregunta', respuesta_esperada: ''});
            } else {
                questions.push({pregunta: 'Nueva pregunta', opciones: ['Opción 1', 'Opción 2'], respuesta_correcta: 'Opción 1'});
            }
            renderEditor();
        });
        
        document.getElementById('publishForm').addEventListener('submit', function(e) {
            // Recolectar datos del DOM y actualizar JSON
            let updatedQuestions = [];
            const cards = document.querySelectorAll('.q-card');
            
            cards.forEach((card, idx) => {
                let q = { pregunta: card.querySelector('.q-enunciado').value };
                if(qType === 'abierta') {
                    q.respuesta_esperada = card.querySelector('.q-respuesta').value;
                } else {
                    q.opciones = [];
                    let optInputs = card.querySelectorAll('.q-opt-text');
                    optInputs.forEach(inp => q.opciones.push(inp.value));
                    
                    let checkedRadio = card.querySelector('input[name=\"correct_'+idx+'\"]:checked');
                    if(checkedRadio) {
                        let selectedOptIdx = checkedRadio.value;
                        q.respuesta_correcta = q.opciones[selectedOptIdx];
                    } else {
                        q.respuesta_correcta = q.opciones[0] || '';
                    }
                }
                updatedQuestions.push(q);
            });
            
            document.getElementById('final_questions_json').value = JSON.stringify(updatedQuestions);
        });
        
        // Inicializar vista
        renderEditor();
        </script>
        ";
    } else {
        echo $OUTPUT->notification('No puedes editar las preguntas porque ya hay estudiantes que han resuelto el cuestionario.', 'info');
        
        $questions = json_decode($aiquiz->questions_json, true);
        if (is_array($questions)) {
            foreach ($questions as $i => $q) {
                echo $OUTPUT->box_start('generalbox mt-3');
                echo '<p><strong>'.($i+1).'. ' . htmlspecialchars($q['pregunta'] ?? '') . '</strong></p>';
                if ($aiquiz->q_type == 'abierta') {
                    echo '<p class="text-muted"><em>Respuesta esperada: ' . htmlspecialchars($q['respuesta_esperada'] ?? 'N/A') . '</em></p>';
                } else {
                    echo '<ul>';
                    foreach ($q['opciones'] ?? [] as $opt) {
                        $is_correct = ($opt === ($q['respuesta_correcta'] ?? ''));
                        echo '<li>' . htmlspecialchars($opt) . ($is_correct ? ' <span class="badge badge-success">Correcta</span>' : '') . '</li>';
                    }
                    echo '</ul>';
                }
                echo $OUTPUT->box_end();
            }
        }
    }
} 
// Vista Estudiante
else if (has_capability('mod/aiquiz:submit', $context)) {
    if ($aiquiz->status == 'draft') {
        echo $OUTPUT->notification('Este cuestionario aún se está preparando.', 'info');
    } else {
        // Comprobar si ya envió
        $has_attempt = $DB->record_exists('aiquiz_attempts', ['aiquizid' => $aiquiz->id, 'userid' => $USER->id]);
        if ($has_attempt) {
            echo $OUTPUT->notification('Ya has enviado tus respuestas para este cuestionario.', 'success');
        } else {
            // Mostrar formulario de resolución
            $questions = json_decode($aiquiz->questions_json, true);
            if (is_array($questions)) {
                echo '<form method="POST" action="view.php?id='.$cm->id.'&action=submit">';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'">';
                foreach ($questions as $i => $q) {
                    echo $OUTPUT->box_start('generalbox mt-3');
                    echo '<p><strong>'.($i+1).'. ' . htmlspecialchars($q['pregunta'] ?? '') . '</strong></p>';
                    
                    if ($aiquiz->q_type == 'abierta') {
                        echo '<textarea name="answers['.$i.']" class="form-control" rows="4" required></textarea>';
                    } else {
                        foreach ($q['opciones'] ?? [] as $optIdx => $opt) {
                            echo '<div class="form-check">';
                            echo '<input class="form-check-input" type="radio" name="answers['.$i.']" value="'.htmlspecialchars($opt).'" id="q'.$i.'opt'.$optIdx.'" required>';
                            echo '<label class="form-check-label" for="q'.$i.'opt'.$optIdx.'">' . htmlspecialchars($opt) . '</label>';
                            echo '</div>';
                        }
                    }
                    echo $OUTPUT->box_end();
                }
                echo '<button type="submit" class="btn btn-primary mt-3 btn-lg">Enviar Cuestionario</button>';
                echo '</form>';
            } else {
                echo '<p>Error: El cuestionario no tiene un formato válido.</p>';
            }
        }
    }
}

echo $OUTPUT->footer();
