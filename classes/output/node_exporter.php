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

use core\external\exporter;
use moodle_url;
use renderer_base;
use tool_guidance\local\node;

/**
 * Exports a single decision-tree node for the chooser templates.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class node_exporter extends exporter {

    /** @var node The node being exported. */
    protected $node;

    /** @var int Course id used to build answer/CTA URLs. */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param node $node The node to export.
     * @param int $courseid Course id.
     */
    public function __construct(node $node, int $courseid) {
        $this->node = $node;
        $this->courseid = $courseid;
        parent::__construct((object) []);
    }

    /**
     * Computed properties for the template.
     *
     * @return array
     */
    protected static function define_other_properties() {
        return [
            'id' => ['type' => PARAM_ALPHANUMEXT],
            'isquestion' => ['type' => PARAM_BOOL],
            'isresult' => ['type' => PARAM_BOOL],
            'prompt' => ['type' => PARAM_TEXT],
            'answers' => [
                'multiple' => true,
                'type' => [
                    'label' => ['type' => PARAM_TEXT],
                    'url' => ['type' => PARAM_URL],
                    'target' => ['type' => PARAM_ALPHANUMEXT],
                ],
            ],
            'presets' => [
                'multiple' => true,
                'type' => [
                    'modname' => ['type' => PARAM_PLUGIN],
                    'iconurl' => ['type' => PARAM_URL],
                    'title' => ['type' => PARAM_TEXT],
                    'description' => ['type' => PARAM_TEXT],
                    'useurl' => ['type' => PARAM_URL],
                    'config' => [
                        'multiple' => true,
                        'type' => [
                            'name' => ['type' => PARAM_TEXT],
                            'value' => ['type' => PARAM_TEXT],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the template context for the node.
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output) {
        $answers = [];
        foreach ($this->node->get_answers() as $answer) {
            $url = new moodle_url('/admin/tool/guidance/chooser.php', [
                'courseid' => $this->courseid,
                'node' => $answer['target'],
            ]);
            $answers[] = [
                'label' => get_string($answer['labelkey'], 'tool_guidance'),
                'url' => $url->out(false),
                'target' => $answer['target'],
            ];
        }

        $presets = [];
        foreach ($this->node->get_presets() as $preset) {
            $config = [];
            foreach ($preset->get_config() as $row) {
                $config[] = [
                    'name' => get_string($row['name'], 'tool_guidance'),
                    'value' => get_string($row['value'], 'tool_guidance'),
                ];
            }
            // Placeholder CTA: returns to the course. Real instance creation comes with the backend.
            $useurl = new moodle_url('/course/view.php', ['id' => $this->courseid]);
            $presets[] = [
                'modname' => $preset->get_modname(),
                'iconurl' => $output->image_url('monologo', 'mod_' . $preset->get_modname())->out(false),
                'title' => get_string($preset->get_titlekey(), 'tool_guidance'),
                'description' => get_string($preset->get_desckey(), 'tool_guidance'),
                'useurl' => $useurl->out(false),
                'config' => $config,
            ];
        }

        return [
            'id' => $this->node->get_id(),
            'isquestion' => $this->node->is_question(),
            'isresult' => $this->node->is_result(),
            'prompt' => get_string($this->node->get_textkey(), 'tool_guidance'),
            'answers' => $answers,
            'presets' => $presets,
        ];
    }
}
