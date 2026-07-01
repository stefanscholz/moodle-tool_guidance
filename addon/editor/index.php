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
 * List of guidance graphs.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guidance\api;
use tool_guidance\graph;

admin_externalpage_setup('tool_guidance_managegraphs');

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$baseurl = new moodle_url('/admin/tool/guidance/addon/editor/index.php');

if ($delete) {
    $graph = new graph($delete);
    if ($confirm && confirm_sesskey()) {
        api::delete_graph($graph);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeletegraph', 'tool_guidance', $graph->get('name')),
        new moodle_url($baseurl, ['delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managegraphs', 'tool_guidance'));

$graphs = graph::get_records([], 'name');
if (!$graphs) {
    echo $OUTPUT->notification(get_string('nographs', 'tool_guidance'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('graphname', 'tool_guidance'),
        get_string('graphenabled', 'tool_guidance'),
        get_string('nodes', 'tool_guidance'),
        '',
    ];
    foreach ($graphs as $graph) {
        $id = $graph->get('id');
        $nodesurl = new moodle_url('/admin/tool/guidance/addon/editor/edit.php', ['graphid' => $id]);
        $editurl = new moodle_url('/admin/tool/guidance/addon/editor/editgraph.php', ['id' => $id]);
        $deleteurl = new moodle_url($baseurl, ['delete' => $id]);
        $actions = $OUTPUT->action_icon($nodesurl, new pix_icon('i/manageboxes', get_string('nodes', 'tool_guidance')))
            . $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit')))
            . $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')));
        $nodecount = count($graph->get_nodes());
        $table->data[] = [
            html_writer::link($nodesurl, format_string($graph->get('name'))),
            $graph->get('enabled') ? get_string('yes') : get_string('no'),
            $nodecount,
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->single_button(
    new moodle_url('/admin/tool/guidance/addon/editor/editgraph.php'),
    get_string('addgraph', 'tool_guidance'),
    'get'
);

echo $OUTPUT->footer();
