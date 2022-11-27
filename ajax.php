<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Lookup various things for use in Ajax scripts
 * Requires course logon & sesskey
 *
 * @package local_ajax
 * @copyright  2022 tim st. clair (https://github.com/frumbert/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

require_sesskey();

$action = required_param('action', PARAM_ALPHANUM);
$courseid = required_param('id', PARAM_INT);
$contextid = optional_param('context', 0, PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($course->id);

require_login($course, false, null, false, true);

header('content-type: application/json'); // because jquery expects it

if (!isloggedin()) {
    print_error('nopermission');
}

$response = new stdClass();

switch ($action) {

    // for each section, for each module, has the user started or completed?
    case "activitycompletion":
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);
        $completioninfo = new completion_info($course);

        $section  = 0;
        $numsections = (int)max(array_keys($sections));
        while ($section <= $numsections) {

            $thissection = $sections[$section];
            $showsection = true;
            if (!$thissection->visible || !$thissection->available) {
                $showsection = $canviewhidden || !($course->hiddensections == 1);
            }

            // look through visible sections within this course
            if ($showsection) {

                $sectObj = new stdClass();
                $sectObj->name = $thissection->name;
                $sectObj->id = $thissection->id;

                // look through visibile activities within this section
                $usermod = [];
                if (!empty($modinfo->sections[$section])) {
                    foreach ($modinfo->sections[$section] as $modnumber) {
                        $mod = $modinfo->cms[$modnumber];

                        if (!$mod->is_visible_on_course_page()) {
                            continue; // skip
                        }

                        $completiondetails = \core_completion\cm_completion_details::get_instance($mod, $USER->id);
                        if (!$completiondetails->has_completion()) {
                            continue; // skip modules without completion or
                        }

                        $usermod[] = [
                            "name" => $mod->name,
                            "id" => $mod->id,
                            "started" => $completiondetails->is_tracked_user(), // activity has completion
                            "completed" => ($completiondetails->get_overall_completion() === COMPLETION_COMPLETE)
                        ];
                    }
                }
                $sectObj->modules = $usermod;

                $response->results[] = $sectObj;

            }
            $section++;
        }
        break;


    // list sections that the user has participated in
    // (completed at least one activity inside)
    case "participation":
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);
        $completioninfo = new completion_info($course);

        $section  = 0;
        $numsections = (int)max(array_keys($sections));
        while ($section <= $numsections) {

            $thissection = $sections[$section];
            $showsection = true;
            if (!$thissection->visible || !$thissection->available) {
                $showsection = $canviewhidden || !($course->hiddensections == 1);
            }

            if ($showsection) {

                $sectObj = new stdClass();
                $sectObj->name = $thissection->name;
                $sectObj->participated = false;

                if (!empty($modinfo->sections[$section])) {
                    foreach ($modinfo->sections[$section] as $modnumber) {
                        $mod = $modinfo->cms[$modnumber];

                        if (!$mod->is_visible_on_course_page()) {
                            continue; // skip
                        }

                        $completiondetails = \core_completion\cm_completion_details::get_instance($mod, $USER->id);
                        if (!$completiondetails->has_completion()) {
                            continue; // skip modules without completion or
                        }

                        if (($completiondetails->get_overall_completion() === COMPLETION_COMPLETE)) {
                            $sectObj->participated = true;
                            // could break here
                        }
                    }
                }

                $response->results[] = $sectObj;

            }
            $section++;
        }
        break;

    // for a user / questionnaire response, store feedback text to a predefined text field
    // $contextid will be the cmid
    // used for storing a field after a response has finished (e.g. on feedback screen)
    case "savefeedback":
        $response->status = 'error';
        $feedback = required_param('value', PARAM_RAW); // textarea value
        $target = required_param('name', PARAM_ALPHANUM); // questionnaire question name
        if (!empty($feedback)) {
            // $mod = context_module::instance($contextid); // you'd think; 
            $mod = $DB->get_record('course_modules', ['id'=>$contextid]);
            require_once($CFG->dirroot . '/mod/questionnaire/locallib.php');
            $responses = questionnaire_get_user_responses($mod->instance, $USER->id);
            foreach ($responses as $qresponse) {
                // a questionnaire has a sid
                $questionnaire = $DB->get_record('questionnaire', ['id'=>$qresponse->questionnaireid]);
                // a sid is a questionnaire_survey id, which questions are bound to
                $question = $DB->get_record('questionnaire_question', ['surveyid'=>$questionnaire->sid, 'name'=>$target]);
                if ($question) {
                    $record = $DB->get_record('questionnaire_response_text', ['response_id'=>$qresponse->id,'question_id'=>$question->id]);
                    if (!$record) {
                        $record = new stdClass();
                        $record->response_id = $qresponse->id;
                        $record->question_id = $question->id;
                        $record->response = $feedback;
                        $DB->insert_record('questionnaire_response_text', $record);
                    } else {
                        $record->response = $feedback;
                        $DB->update_record('questionnaire_response_text', $record);
                    }
                    $response->status = 'ok';
                }
            }
        }
        break;


}

echo json_encode($response);
die();
