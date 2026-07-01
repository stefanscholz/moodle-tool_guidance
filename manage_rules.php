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
 * Manage the suggestion rule table: list, reorder, enable/disable, delete.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guidance\local\rule\rule_repository;

admin_externalpage_setup('tool_guidance_managerules');

$action = optional_param('action', '', PARAM_ALPHA);
$ruleid = optional_param('ruleid', 0, PARAM_INT);

$repository = new rule_repository();
$baseurl = new moodle_url('/admin/tool/guidance/manage_rules.php');

if ($action && $ruleid && confirm_sesskey()) {
    switch ($action) {
        case 'up':
            $repository->move($ruleid, -1);
            break;
        case 'down':
            $repository->move($ruleid, 1);
            break;
        case 'enable':
            $repository->set_enabled($ruleid, true);
            break;
        case 'disable':
            $repository->set_enabled($ruleid, false);
            break;
        case 'delete':
            $rule = $repository->get($ruleid);
            if ($rule && optional_param('confirm', 0, PARAM_BOOL)) {
                $repository->delete($ruleid);
                redirect($baseurl, get_string('ruledeleted', 'tool_guidance'));
            } else if ($rule) {
                $PAGE->set_title(get_string('deleterule', 'tool_guidance'));
                echo $OUTPUT->header();
                $confirmurl = new moodle_url($baseurl,
                    ['action' => 'delete', 'ruleid' => $ruleid, 'confirm' => 1, 'sesskey' => sesskey()]);
                echo $OUTPUT->confirm(
                    get_string('confirmdelete', 'tool_guidance', $rule->name),
                    $confirmurl, $baseurl);
                echo $OUTPUT->footer();
                exit;
            }
            break;
    }
    redirect($baseurl);
}

$PAGE->set_title(get_string('managerules', 'tool_guidance'));
$PAGE->set_heading(get_string('managerules', 'tool_guidance'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managerules', 'tool_guidance'));
echo html_writer::tag('p', get_string('managerules_desc', 'tool_guidance'));

echo html_writer::div(
    $OUTPUT->single_button(new moodle_url('/admin/tool/guidance/edit_rule.php'),
        get_string('addrule', 'tool_guidance'), 'get'),
    'mb-3');

$rules = $repository->get_all_rules();

if (!$rules) {
    echo $OUTPUT->notification(get_string('norules', 'tool_guidance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$signalnames = [
    'gap'        => get_string('signal_gap', 'tool_guidance'),
    'lifecycle'  => get_string('signal_lifecycle', 'tool_guidance'),
    'engagement' => get_string('signal_engagement', 'tool_guidance'),
];

$table = new html_table();
$table->head = [
    get_string('rule_sortorder', 'tool_guidance'),
    get_string('rule_name', 'tool_guidance'),
    get_string('rule_signal', 'tool_guidance'),
    get_string('rule_condition', 'tool_guidance'),
    get_string('rule_suggest', 'tool_guidance'),
    get_string('rule_target', 'tool_guidance'),
    get_string('rule_enabled', 'tool_guidance'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable';

$count = count($rules);
foreach ($rules as $index => $rule) {
    $actions = [];

    if ($index > 0) {
        $actions[] = $OUTPUT->action_icon(
            new moodle_url($baseurl, ['action' => 'up', 'ruleid' => $rule->id, 'sesskey' => sesskey()]),
            new pix_icon('t/up', get_string('moveup', 'tool_guidance')));
    }
    if ($index < $count - 1) {
        $actions[] = $OUTPUT->action_icon(
            new moodle_url($baseurl, ['action' => 'down', 'ruleid' => $rule->id, 'sesskey' => sesskey()]),
            new pix_icon('t/down', get_string('movedown', 'tool_guidance')));
    }
    $actions[] = $OUTPUT->action_icon(
        new moodle_url('/admin/tool/guidance/edit_rule.php', ['id' => $rule->id]),
        new pix_icon('t/edit', get_string('edit')));
    $toggle = $rule->enabled ? 'disable' : 'enable';
    $actions[] = $OUTPUT->action_icon(
        new moodle_url($baseurl, ['action' => $toggle, 'ruleid' => $rule->id, 'sesskey' => sesskey()]),
        new pix_icon($rule->enabled ? 't/hide' : 't/show', get_string($toggle, 'tool_guidance')));
    $actions[] = $OUTPUT->action_icon(
        new moodle_url($baseurl, ['action' => 'delete', 'ruleid' => $rule->id, 'sesskey' => sesskey()]),
        new pix_icon('t/delete', get_string('deleterule', 'tool_guidance')));

    if ($rule->targettype === 'node') {
        $targetlabel = get_string('rule_targetnode', 'tool_guidance') . ' #' . s($rule->targetvalue);
    } else if ($rule->targettype === 'adminlink') {
        $targetlabel = \tool_guidance\local\admin_links::exists($rule->targetvalue)
            ? get_string('adminlink_' . $rule->targetvalue, 'tool_guidance')
            : s($rule->targetvalue);
    } else {
        $targetlabel = get_string('target_activity', 'tool_guidance');
    }

    $table->data[] = [
        $rule->sortorder,
        format_string($rule->name),
        $signalnames[$rule->signaltype] ?? $rule->signaltype,
        html_writer::tag('code', s($rule->conditiontext)),
        s($rule->suggestmod),
        $targetlabel,
        $rule->enabled ? get_string('yes') : get_string('no'),
        implode(' ', $actions),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
