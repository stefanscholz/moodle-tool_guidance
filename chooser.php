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
 * Alternative activity chooser: a question-and-answer decision tree.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use tool_guidance\graph;
use tool_guidance\node;
use tool_guidance\output\chooser_page;

$courseid = required_param('courseid', PARAM_INT);
$nodeid = optional_param('node', 0, PARAM_INT);
$sectionnum = optional_param('section', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('tool/guidance:view', $context);

$PAGE->set_url(new moodle_url('/admin/tool/guidance/chooser.php',
    ['courseid' => $course->id, 'node' => $nodeid, 'section' => $sectionnum]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('choosertitle', 'tool_guidance'));
$PAGE->set_heading($course->fullname);

// Prefer an enabled graph; fall back to any graph so authors can preview
// before publishing.
$graphs = graph::get_records(['enabled' => 1], 'id', 'ASC', 0, 1);
if (!$graphs) {
    $graphs = graph::get_records([], 'id', 'ASC', 0, 1);
}
$graph = reset($graphs) ?: null;

$node = null;
if ($graph && $graph->get('rootnodeid')) {
    if ($nodeid) {
        $node = node::get_record(['id' => $nodeid, 'graphid' => $graph->get('id')]);
    }
    if (!$node) {
        $node = node::get_record(['id' => $graph->get('rootnodeid')]);
    }
}

echo $OUTPUT->header();
if (!$graph || !$node) {
    echo $OUTPUT->heading(get_string('choosertitle', 'tool_guidance'));
    echo $OUTPUT->notification(get_string('chooserunavailable', 'tool_guidance'), 'info');
} else {
    $renderable = new chooser_page($course, $graph, $node, $sectionnum);
    $renderer = $PAGE->get_renderer('tool_guidance');
    echo $renderer->render_chooser_page($renderable);
}
echo $OUTPUT->footer();
