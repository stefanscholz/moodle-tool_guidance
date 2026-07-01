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

namespace tool_guidance\output;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderable;
use renderer_base;
use templatable;
use tool_guidance\graph;
use tool_guidance\node;

/**
 * Renderable for the whole guidance chooser page (one active node).
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chooser_page implements renderable, templatable {

    /** @var \stdClass Course record. */
    protected $course;

    /** @var graph The graph being traversed. */
    protected $graph;

    /** @var node The active node. */
    protected $node;

    /** @var int Id of the graph's chooser entry node. */
    protected $entrynodeid;

    /**
     * Constructor.
     *
     * @param \stdClass $course Course record.
     * @param graph $graph The graph being traversed.
     * @param node $node The active node.
     * @param int $entrynodeid Id of the chooser entry node (the start of the flow).
     */
    public function __construct(\stdClass $course, graph $graph, node $node, int $entrynodeid) {
        $this->course = $course;
        $this->graph = $graph;
        $this->node = $node;
        $this->entrynodeid = $entrynodeid;
    }

    /**
     * Export the page context for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = \context_course::instance($this->course->id);
        $nodeexporter = new node_exporter($this->node, (int) $this->course->id, $context, $this->sectionnum);
        $starturl = new moodle_url('/admin/tool/guidance/chooser.php', [
            'courseid' => $this->course->id,
            'section' => $this->sectionnum,
        ]);

        return [
            'title' => get_string('choosertitle', 'tool_guidance'),
            'intro' => get_string('chooserintro', 'tool_guidance'),
            'isstart' => (int) $this->node->get('id') === $this->entrynodeid,
            'starturl' => $starturl->out(false),
            'startoverlabel' => get_string('startover', 'tool_guidance'),
            'node' => $nodeexporter->export($output),
        ];
    }
}
