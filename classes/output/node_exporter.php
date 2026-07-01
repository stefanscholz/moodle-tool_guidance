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

use context;
use core\external\exporter;
use moodle_url;
use renderer_base;
use tool_guidance\link;
use tool_guidance\node;
use tool_guidance\target\manager as targetmanager;

/**
 * Exports a single guidance graph node for the chooser templates.
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

    /** @var int Section number the chooser was launched from. */
    protected $sectionnum;

    /**
     * Constructor.
     *
     * @param node $node The node to export.
     * @param int $courseid Course id.
     * @param context $context Context used to format the text properties.
     * @param int $sectionnum Section number the activity should be created in.
     */
    public function __construct(node $node, int $courseid, context $context, int $sectionnum = 0) {
        $this->node = $node;
        $this->courseid = $courseid;
        $this->sectionnum = $sectionnum;
        parent::__construct((object) [], ['context' => $context]);
    }

    /**
     * Related objects required by this exporter.
     *
     * @return array
     */
    protected static function define_related() {
        return ['context' => 'context'];
    }

    /**
     * Computed properties for the template.
     *
     * @return array
     */
    protected static function define_other_properties() {
        return [
            'id' => ['type' => PARAM_INT],
            'isquestion' => ['type' => PARAM_BOOL],
            'isresult' => ['type' => PARAM_BOOL],
            'prompt' => ['type' => PARAM_TEXT],
            'answers' => [
                'multiple' => true,
                'type' => [
                    'label' => ['type' => PARAM_TEXT],
                    'explanation' => ['type' => PARAM_TEXT],
                    'url' => ['type' => PARAM_URL],
                    'target' => ['type' => PARAM_INT],
                ],
            ],
            'presets' => [
                'multiple' => true,
                'type' => [
                    'modname' => ['type' => PARAM_RAW],
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
     * Build the answer list for a question node.
     *
     * @return array
     */
    protected function export_answers(): array {
        $answers = [];
        foreach (link::get_records(['parentnodeid' => $this->node->get('id')], 'sortorder, id') as $link) {
            $childid = (int) $link->get('childnodeid');
            if (!$childid) {
                // Dangling answer: nothing to navigate to yet.
                continue;
            }
            $url = new moodle_url('/admin/tool/guidance/chooser.php', [
                'courseid' => $this->courseid,
                'node' => $childid,
            ]);
            $answers[] = [
                'label' => $link->get('answerlabel'),
                'explanation' => '',
                'url' => $url->out(false),
                'target' => $childid,
            ];
        }
        return $answers;
    }

    /**
     * Build the single-item preset list for a leaf node's target.
     *
     * The target type resolves its own course-aware action URL and icon, so new
     * target types (e.g. activity presets) work here without changes.
     *
     * @param renderer_base $output
     * @return array
     */
    protected function export_presets(renderer_base $output): array {
        $targettype = $this->node->get('targettype');
        if (!$targettype || !targetmanager::type_exists($targettype)) {
            return [];
        }
        $config = $this->node->get_targetconfig_array();
        $target = targetmanager::get_target($targettype, $config);

        $useurl = $target->get_action_url_for_course($this->courseid, $this->sectionnum);
        if (!$useurl) {
            // Draft leaf with no target details configured yet: nothing to offer.
            return [];
        }

        return [[
            'modname' => $config['modname'] ?? '',
            'iconurl' => $target->get_icon($output)->out(false),
            'title' => $this->node->get('title'),
            'description' => (string) $this->node->get('description'),
            'useurl' => $useurl->out(false),
            'config' => [],
        ]];
    }

    /**
     * Build the template context for the node.
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output) {
        return [
            'id' => $this->node->get('id'),
            'isquestion' => $this->node->is_question(),
            'isresult' => $this->node->is_leaf(),
            'prompt' => $this->node->get('title'),
            'answers' => $this->node->is_question() ? $this->export_answers() : [],
            'presets' => $this->node->is_leaf() ? $this->export_presets($output) : [],
        ];
    }
}
