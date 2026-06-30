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
 * Strings for the Guidance activity chooser tool.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Guidance activity chooser';
$string['guidance:view'] = 'Use the guidance activity chooser';
$string['privacy:metadata'] = 'The Guidance activity chooser tool does not store any personal data.';

// Activity chooser ("plus" menu) entry point.
$string['choosebutton'] = 'Help me choose…';

// Chooser shell.
$string['choosertitle'] = 'What do you want students to do next?';
$string['chooserintro'] = 'Answer a few questions and we will suggest an activity, set up for your goal.';
$string['startover'] = 'Start over';
$string['suggestedpresets'] = 'Suggested activities';
$string['usepreset'] = 'Use this template';
$string['usepresetnote'] = 'Creating an activity from a template will be available once the content library is connected.';

// Questions.
$string['q_goal'] = 'What is your main goal for students right now?';
$string['q_assess'] = 'How do you want to assess their understanding?';

// Answers.
$string['a_goal_assess'] = 'Check what they understand';
$string['a_goal_assess_help'] = 'Find out what students already know before you teach something new.';
$string['a_goal_discuss'] = 'Get them discussing with each other';
$string['a_goal_discuss_help'] = 'Encourage students to share ideas, ask questions and learn from one another.';
$string['a_goal_collect'] = 'Collect work they submit';
$string['a_goal_collect_help'] = 'Gather files or text from students so you can review and grade it.';
$string['a_assess_auto'] = 'A quick, auto-graded check';
$string['a_assess_auto_help'] = 'Short questions marked automatically — fast feedback with no manual grading.';
$string['a_assess_open'] = 'An open-ended written answer';
$string['a_assess_open_help'] = 'Students write a longer response that you read and grade yourself.';

// Result headings.
$string['r_quiz_heading'] = 'A quiz is a good fit';
$string['r_assign_heading'] = 'An assignment is a good fit';
$string['r_forum_heading'] = 'A forum is a good fit';

// Preset titles and descriptions.
$string['p_quiz_title'] = 'Diagnostic quiz';
$string['p_quiz_desc'] = 'A short, auto-graded quiz to check what students already know.';
$string['p_assign_title'] = 'Written reflection';
$string['p_assign_desc'] = 'An assignment where students submit an open-ended written response.';
$string['p_forum_title'] = 'Discussion forum';
$string['p_forum_desc'] = 'A standard forum to spark discussion between students.';

// Preset configuration labels.
$string['cfg_questions'] = 'Number of questions';
$string['cfg_attempts'] = 'Attempts allowed';
$string['cfg_grademethod'] = 'Grading method';
$string['cfg_submissiontypes'] = 'Submission types';
$string['cfg_duedate'] = 'Due date';
$string['cfg_grade'] = 'Maximum grade';
$string['cfg_forumtype'] = 'Forum type';
$string['cfg_subscription'] = 'Subscription';
$string['cfg_grading'] = 'Grading';

// Preset configuration sample values.
$string['cfgv_quiz_questions'] = '5';
$string['cfgv_quiz_attempts'] = 'Unlimited';
$string['cfgv_quiz_grademethod'] = 'Highest grade';
$string['cfgv_assign_submission'] = 'Online text';
$string['cfgv_assign_due'] = 'One week after the start date';
$string['cfgv_assign_grade'] = '100 points';
$string['cfgv_forum_type'] = 'Standard forum for general use';
$string['cfgv_forum_sub'] = 'Optional';
$string['cfgv_forum_grading'] = 'None';
