<?php
/**
 * Funciones principales para aiquiz.
 *
 * @package    mod_aiquiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function aiquiz_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:         return true;
        case FEATURE_SHOW_DESCRIPTION:  return true;
        case FEATURE_BACKUP_MOODLE2:    return true;
        default:                        return null;
    }
}

function aiquiz_add_instance($aiquiz, $mform) {
    global $DB;

    $aiquiz->timecreated = time();
    $aiquiz->timemodified = time();
    $aiquiz->status = 'draft';

    $aiquiz->id = $DB->insert_record('aiquiz', $aiquiz);

    // Guardar el archivo PDF adjunto
    if ($mform) {
        $context = context_module::instance($aiquiz->coursemodule);
        file_save_draft_area_files($aiquiz->pdf_file, $context->id, 'mod_aiquiz', 'pdf_base', 0, ['subdirs' => 0, 'maxfiles' => 1]);
    }

    return $aiquiz->id;
}

function aiquiz_update_instance($aiquiz, $mform) {
    global $DB;

    $aiquiz->timemodified = time();
    $aiquiz->id = $aiquiz->instance;

    $DB->update_record('aiquiz', $aiquiz);

    if ($mform) {
        $context = context_module::instance($aiquiz->coursemodule);
        file_save_draft_area_files($aiquiz->pdf_file, $context->id, 'mod_aiquiz', 'pdf_base', 0, ['subdirs' => 0, 'maxfiles' => 1]);
    }

    return true;
}

function aiquiz_delete_instance($id) {
    global $DB;

    if (!$aiquiz = $DB->get_record('aiquiz', array('id' => $id))) {
        return false;
    }

    $DB->delete_records('aiquiz_attempts', array('aiquizid' => $aiquiz->id));
    $DB->delete_records('aiquiz', array('id' => $aiquiz->id));

    return true;
}
