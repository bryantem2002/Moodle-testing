<?php
/**
 * Formulario de configuración para aiquiz.
 *
 * @package    mod_aiquiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_aiquiz_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        // Configuración general
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        // Configuración específica de AI Quiz
        $mform->addElement('header', 'aisettings', get_string('pluginadministration', 'mod_aiquiz'));

        $mform->addElement('filemanager', 'pdf_file', get_string('pdf_file', 'mod_aiquiz'), null,
                array('subdirs' => 0, 'maxbytes' => 10485760, 'maxfiles' => 1, 'accepted_types' => array('.pdf')));
        $mform->addRule('pdf_file', null, 'required', null, 'client');

        $mform->addElement('text', 'num_questions', get_string('num_questions', 'mod_aiquiz'), array('size'=>'5'));
        $mform->setDefault('num_questions', 5);
        $mform->setType('num_questions', PARAM_INT);
        $mform->addRule('num_questions', null, 'required', null, 'client');

        $difficulty_options = [
            'facil' => 'Fácil',
            'media' => 'Media',
            'dificil' => 'Difícil'
        ];
        $mform->addElement('select', 'difficulty', get_string('difficulty', 'mod_aiquiz'), $difficulty_options);
        $mform->setDefault('difficulty', 'media');

        $type_options = [
            'cerrada' => 'Cerradas (Opción Múltiple)',
            'abierta' => 'Abiertas (Desarrollo)'
        ];
        $mform->addElement('select', 'q_type', get_string('q_type', 'mod_aiquiz'), $type_options);
        $mform->setDefault('q_type', 'cerrada');

        $mform->addElement('textarea', 'custom_prompt', get_string('custom_prompt', 'mod_aiquiz'), 'wrap="virtual" rows="4" cols="50"');
        $mform->setType('custom_prompt', PARAM_TEXT);
        
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$default_values) {
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('pdf_file');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_aiquiz', 'pdf_base', 0,
                ['subdirs' => 0, 'maxbytes' => 10485760, 'maxfiles' => 1]
            );
            $default_values['pdf_file'] = $draftitemid;
        }
    }
}
