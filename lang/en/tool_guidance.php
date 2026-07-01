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
 * Strings for the Guidance activity chooser tool (chooser + suggestion engine).
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Guidance activity chooser';
$string['guidance:view'] = 'Use the guidance activity chooser';
$string['guidance:managerules'] = 'Manage activity suggestion rules';

// Activity chooser ("plus" menu) entry point.
$string['choosebutton'] = 'Help me choose…';

// Chooser shell.
$string['choosertitle'] = 'Help me choose';
$string['chooserintro'] = 'Answer a few questions and we will suggest an activity, set up for your goal.';
$string['startover'] = 'Start over';
$string['suggestedpresets'] = 'Suggested activities';
$string['usepreset'] = 'Use this template';
$string['showconfig'] = 'Show configuration';
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

// Preset titles and descriptions. Each result offers a few templates to pick from.
$string['p_quiz_title'] = 'Diagnostic quiz';
$string['p_quiz_desc'] = 'A short, auto-graded quiz to check what students already know.';
$string['p_quiz2_title'] = 'Practice quiz';
$string['p_quiz2_desc'] = 'A low-stakes quiz students can retake as often as they like to practise.';
$string['p_quiz3_title'] = 'Graded test';
$string['p_quiz3_desc'] = 'A single-attempt test that counts towards the final grade.';
$string['p_assign_title'] = 'Written reflection';
$string['p_assign_desc'] = 'An assignment where students submit an open-ended written response.';
$string['p_assign2_title'] = 'File submission';
$string['p_assign2_desc'] = 'An assignment where students upload one or more files for grading.';
$string['p_forum_title'] = 'Discussion forum';
$string['p_forum_desc'] = 'A standard forum to spark discussion between students.';
$string['p_forum2_title'] = 'Q&A forum';
$string['p_forum2_desc'] = 'Students must post their own answer before they can see their classmates\' replies.';
$string['p_forum3_title'] = 'Introduction';
$string['p_forum3_desc'] = 'A forum that asks each student to post a short introduction about themselves to get the group acquainted.';

// Generic preset shown when a suggested activity has no bespoke template yet.
$string['p_generic_title'] = 'Add {$a}';
$string['p_generic_desc'] = 'Create this activity with sensible defaults. A tailored template will follow.';
$string['r_generic_heading'] = '{$a} is a good next step';

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
$string['cfgv_quiz2_questions'] = '10';
$string['cfgv_quiz2_attempts'] = 'Unlimited';
$string['cfgv_quiz2_grademethod'] = 'Last attempt';
$string['cfgv_quiz3_questions'] = '20';
$string['cfgv_quiz3_attempts'] = '1';
$string['cfgv_quiz3_grademethod'] = 'Highest grade';
$string['cfgv_assign_submission'] = 'Online text';
$string['cfgv_assign_due'] = 'One week after the start date';
$string['cfgv_assign_grade'] = '100 points';
$string['cfgv_assign2_submission'] = 'File submissions';
$string['cfgv_assign2_due'] = 'Two weeks after the start date';
$string['cfgv_assign2_grade'] = '100 points';
$string['cfgv_forum_type'] = 'Standard forum for general use';
$string['cfgv_forum_sub'] = 'Optional';
$string['cfgv_forum_grading'] = 'None';
$string['cfgv_forum2_type'] = 'Q and A forum';
$string['cfgv_forum2_sub'] = 'Forced';
$string['cfgv_forum2_grading'] = 'Whole forum grading';
$string['cfgv_forum3_type'] = 'Each person posts one discussion';
$string['cfgv_forum3_sub'] = 'Forced';
$string['cfgv_forum3_grading'] = 'None';

// ---------------------------------------------------------------------------
// Suggestion engine (drives the block's automatic recommendation).
// ---------------------------------------------------------------------------

// Settings.
$string['settings'] = 'Settings';
$string['enableai'] = 'Use AI to re-rank suggestions';
$string['enableai_desc'] = 'When a Moodle AI provider is configured, allow it to reorder the matched suggestions to pick the most relevant one. The deterministic rule order is always used as a fallback. Suggestions work fully without AI.';
$string['cooldowndays'] = 'Dismissal cooldown (days)';
$string['cooldowndays_desc'] = 'How long a skipped suggestion stays hidden for the whole course before it can be suggested again.';
$string['enableengagementfacts'] = 'Compute engagement facts';
$string['enableengagementfacts_desc'] = 'Compute the costlier engagement facts (recent activity, completion rate, forum posts, quiz attempts, submission rate). These power the engagement-signal rules but add log and database queries.';

// Rule management.
$string['managerules'] = 'Manage rules';
$string['managerules_desc'] = 'Rules are evaluated top to bottom. The first rule that matches the course, whose activity is installed and not currently dismissed, becomes the suggestion shown.';
$string['addrule'] = 'Add rule';
$string['editrule'] = 'Edit rule';
$string['deleterule'] = 'Delete rule';
$string['confirmdelete'] = 'Are you sure you want to delete the rule "{$a}"?';
$string['ruledeleted'] = 'Rule deleted';
$string['rulesaved'] = 'Rule saved';
$string['norules'] = 'There are no suggestion rules yet.';
$string['moveup'] = 'Move up';
$string['movedown'] = 'Move down';
$string['enable'] = 'Enable';
$string['disable'] = 'Disable';

// Rule fields.
$string['rule_name'] = 'Name';
$string['rule_signal'] = 'Signal';
$string['rule_condition'] = 'Condition';
$string['rule_condition_help'] = 'A condition expression. Clauses are joined with AND, for example: <code>has_purpose_content == true AND has_purpose_assessment == false AND term_stage in (week1|early|mid)</code>. Operators: == != &lt; &lt;= &gt; &gt;= in. An operand may be a number, true/false, an enum word, another fact name, or a set like (a|b|c) for "in".';
$string['rule_suggest'] = 'Suggested activity';
$string['rule_rationale'] = 'Rationale (shown to the teacher)';
$string['rule_preconfig'] = 'Pre-configuration';
$string['rule_preconfig_help'] = 'Optional key=value pairs separated by semicolons, passed to the add-activity flow, for example: <code>type=qanda;name=Questions and answers</code>. A "name" key provides the default activity name.';
$string['rule_enabled'] = 'Enabled';
$string['rule_sortorder'] = 'Order';
$string['conditioninvalid'] = 'The condition could not be parsed near: {$a}';
$string['availablefacts'] = 'Available facts';

// Signals.
$string['signal_gap'] = 'Pedagogical gap';
$string['signal_lifecycle'] = 'Lifecycle/timing';
$string['signal_engagement'] = 'Engagement/health';

// Cache.
$string['cachedef_suggestions'] = 'Computed activity suggestion per course';

// Privacy.
$string['privacy:metadata:dismissed'] = 'Records of which suggestion a user dismissed in a course.';
$string['privacy:metadata:dismissed:courseid'] = 'The course the suggestion was dismissed in.';
$string['privacy:metadata:dismissed:ruleid'] = 'The suggestion rule that was dismissed.';
$string['privacy:metadata:dismissed:userid'] = 'The user who dismissed the suggestion.';
$string['privacy:metadata:dismissed:timecreated'] = 'When the suggestion was dismissed.';
