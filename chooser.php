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

use tool_guidance\local\tree_provider;
use tool_guidance\output\chooser_page;

$courseid = required_param('courseid', PARAM_INT);
$nodeid = optional_param('node', null, PARAM_ALPHANUMEXT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('tool/guidance:view', $context);

// Resolve the requested node; fall back to the start of the tree.
$node = $nodeid ? tree_provider::get_node($nodeid) : null;
if ($node === null) {
    $node = tree_provider::get_start();
}

$pageurl = new moodle_url('/admin/tool/guidance/chooser.php', [
    'courseid' => $course->id,
    'node' => $node->get_id(),
]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('choosertitle', 'tool_guidance'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('tool_guidance/chooser', 'init');

$renderable = new chooser_page($course, $node);
$renderer = $PAGE->get_renderer('tool_guidance');

echo $OUTPUT->header();
echo $renderer->render_chooser_page($renderable);
echo $OUTPUT->footer();
