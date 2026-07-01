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
 * Canvas editor for a guidance graph.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guidance\graph;

$graphid = required_param('graphid', PARAM_INT);

admin_externalpage_setup('tool_guidance_managegraphs');
$PAGE->set_url(new moodle_url('/admin/tool/guidance/addon/editor/edit.php', ['graphid' => $graphid]));

$graph = new graph($graphid);

// Only the graph id is passed inline; strings load via core/str and the target
// type / activity lists arrive with the get_graph web service response.
$PAGE->requires->js_call_amd('guidanceaddon_editor/editor', 'init', [$graphid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managenodesfor', 'tool_guidance', format_string($graph->get('name'))));
echo html_writer::div(
    html_writer::link(new moodle_url('/admin/tool/guidance/addon/editor/index.php'), get_string('backtographs', 'tool_guidance'))
);
echo $OUTPUT->render_from_template('guidanceaddon_editor/editor', []);
echo $OUTPUT->footer();
