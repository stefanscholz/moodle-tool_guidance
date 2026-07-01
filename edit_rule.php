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
 * Add or edit a single suggestion rule.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guidance\form\rule_form;
use tool_guidance\local\rule\rule;
use tool_guidance\local\rule\rule_repository;

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('tool_guidance_managerules');

$repository = new rule_repository();
$manageurl = new moodle_url('/admin/tool/guidance/manage_rules.php');
$editurl = new moodle_url('/admin/tool/guidance/edit_rule.php', ['id' => $id]);

$existing = $id ? $repository->get($id) : null;
if ($id && !$existing) {
    throw new moodle_exception('invalidrecordunknown');
}

$form = new rule_form($editurl);

if ($existing) {
    $form->set_data([
        'id'            => $existing->id,
        'name'          => $existing->name,
        'signal'        => $existing->signal,
        'suggestmod'    => $existing->suggestmod,
        'conditiontext' => $existing->conditiontext,
        'rationale'     => $existing->rationale,
        'preconfig'     => $existing->preconfig,
        'sortorder'     => $existing->sortorder,
        'enabled'       => (int) $existing->enabled,
    ]);
}

if ($form->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $form->get_data()) {
    $sortorder = $data->sortorder ?? 0;
    if (!$sortorder) {
        $sortorder = $repository->next_sortorder();
    }
    $rule = new rule(
        (int) $data->id,
        (int) $sortorder,
        (bool) $data->enabled,
        $data->signal,
        $data->name,
        $data->conditiontext,
        $data->suggestmod,
        $data->rationale,
        $data->preconfig ?? '',
    );
    $repository->save($rule);
    redirect($manageurl, get_string('rulesaved', 'tool_guidance'));
}

$PAGE->set_title(get_string($existing ? 'editrule' : 'addrule', 'tool_guidance'));
$PAGE->set_heading(get_string($existing ? 'editrule' : 'addrule', 'tool_guidance'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($existing ? 'editrule' : 'addrule', 'tool_guidance'));
$form->display();
echo $OUTPUT->footer();
