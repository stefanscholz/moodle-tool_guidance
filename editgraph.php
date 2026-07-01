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
 * Create or edit a guidance graph.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guidance\graph;
use tool_guidance\form\graph_form;

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('tool_guidance_managegraphs');
$PAGE->set_url(new moodle_url('/admin/tool/guidance/editgraph.php', ['id' => $id]));

$listurl = new moodle_url('/admin/tool/guidance/index.php');

$graph = $id ? new graph($id) : null;

$form = new graph_form($PAGE->url->out(false));

if ($graph) {
    $data = (object) [
        'id' => $graph->get('id'),
        'name' => $graph->get('name'),
        'idnumber' => $graph->get('idnumber'),
        'enabled' => $graph->get('enabled'),
        'description' => [
            'text' => $graph->get('description'),
            'format' => $graph->get('descriptionformat'),
        ],
    ];
    $form->set_data($data);
}

if ($form->is_cancelled()) {
    redirect($listurl);
} else if ($data = $form->get_data()) {
    $record = (object) [
        'name' => $data->name,
        'idnumber' => $data->idnumber !== '' ? $data->idnumber : null,
        'description' => $data->description['text'],
        'descriptionformat' => $data->description['format'],
        'enabled' => !empty($data->enabled),
    ];
    if (!empty($data->id)) {
        $graph = new graph($data->id);
        $graph->from_record($record);
        $graph->update();
    } else {
        $graph = new graph(0, $record);
        $graph->create();
    }
    redirect($listurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('editgraph', 'tool_guidance') : get_string('addgraph', 'tool_guidance'));
$form->display();
echo $OUTPUT->footer();
