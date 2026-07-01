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
            'id' => ['type' => PARAM_ALPHANUMEXT],
            'isquestion' => ['type' => PARAM_BOOL],
            'isresult' => ['type' => PARAM_BOOL],
            'prompt' => ['type' => PARAM_TEXT],
            'answers' => [
                'multiple' => true,
                'type' => [
                    'label' => ['type' => PARAM_TEXT],
                    'explanation' => ['type' => PARAM_TEXT],
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
                    'description' => ['type' => PARAM_RAW],
                    'useurl' => ['type' => PARAM_URL],
                    'canapply' => ['type' => PARAM_BOOL],
                    'presetid' => ['type' => PARAM_INT],
                    'courseid' => ['type' => PARAM_INT],
                    'section' => ['type' => PARAM_INT],
                    'sesskey' => ['type' => PARAM_RAW],
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
                'explanation' => get_string($answer['explainkey'], 'tool_guidance'),
                'url' => $url->out(false),
                'target' => $answer['target'],
            ];
        }

        // Resolve stored presets from the subplugin, if it is installed and enabled.
        // tool_guidance has no hard dependency on the subplugin: when it is absent
        // the chooser degrades to the display-only placeholder behaviour.
        $stored = $this->get_stored_presets();

        $presets = [];
        foreach ($this->node->get_presets() as $preset) {
            $config = [];
            foreach ($preset->get_config() as $row) {
                $config[] = [
                    'name' => get_string($row['name'], 'tool_guidance'),
                    'value' => get_string($row['value'], 'tool_guidance'),
                ];
            }

            $db = $stored[$preset->get_shortname()] ?? null;
            if ($db) {
                // A real, applyable preset backed by a stored activity backup.
                $modname = $db->modname ?: $preset->get_modname();
                $presets[] = [
                    'modname' => $modname,
                    'iconurl' => $output->image_url('monologo', 'mod_' . $modname)->out(false),
                    'title' => format_string($db->title),
                    'description' => format_text($db->description, $db->descriptionformat ?? FORMAT_HTML),
                    'useurl' => '',
                    'canapply' => true,
                    'presetid' => (int) $db->id,
                    'courseid' => $this->courseid,
                    'section' => $this->sectionnum,
                    'sesskey' => sesskey(),
                    'config' => $config,
                ];
            } else {
                // Placeholder CTA: returns to the course (subplugin absent or preset not seeded).
                $useurl = new moodle_url('/course/view.php', ['id' => $this->courseid]);
                $presets[] = [
                    'modname' => $preset->get_modname(),
                    'iconurl' => $output->image_url('monologo', 'mod_' . $preset->get_modname())->out(false),
                    'title' => get_string($preset->get_titlekey(), 'tool_guidance'),
                    'description' => get_string($preset->get_desckey(), 'tool_guidance'),
                    'useurl' => $useurl->out(false),
                    'canapply' => false,
                    'presetid' => 0,
                    'courseid' => $this->courseid,
                    'section' => $this->sectionnum,
                    'sesskey' => sesskey(),
                    'config' => $config,
                ];
            }
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

    /**
     * Resolve this node's preset short names to stored preset records.
     *
     * Returns an empty array (graceful fallback) when the preset subplugin is
     * not installed or is disabled, so tool_guidance never hard-depends on it.
     *
     * @return \stdClass[] Stored presets keyed by short name.
     */
    protected function get_stored_presets(): array {
        if (!$this->node->is_result()) {
            return [];
        }
        if (!class_exists(\guidanceaddon_preset\local\preset_manager::class)) {
            return [];
        }
        // Honour the addon's enable flag (an unset flag means enabled by default).
        $enabled = get_config('guidanceaddon_preset', 'enabled');
        if ($enabled !== false && !$enabled) {
            return [];
        }

        $shortnames = array_map(static function($preset) {
            return $preset->get_shortname();
        }, $this->node->get_presets());

        return \guidanceaddon_preset\local\preset_manager::get_by_shortnames($shortnames);
    }
}
