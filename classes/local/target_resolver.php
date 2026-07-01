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

namespace tool_guidance\local;

use tool_guidance\graph;
use tool_guidance\node;

/**
 * Resolves a suggestion's call-to-action URL for a course.
 *
 * The rule's chosen target is preferred, but resolution always degrades safely with the
 * priority **node → activity → settings** so the CTA never dead-ends:
 *  1. a graph-node target, only if the node exists and its graph is enabled;
 *  2. an admin-link target, if the key is known;
 *  3. the suggested activity (modedit), if that module is installed and enabled;
 *  4. the course settings page as the guaranteed-valid last resort.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class target_resolver {

    /**
     * Resolve the CTA for a suggestion.
     *
     * @param suggestion $suggestion
     * @param int $courseid
     * @param int $sectionnum
     * @return array{url: \moodle_url, ischooser: bool} the URL, and whether it opens the
     *         guidance chooser (so the block can open it in a modal).
     */
    public static function resolve(suggestion $suggestion, int $courseid, int $sectionnum = 0): array {
        // 1. Preferred: a valid graph node — the tree takes over from there.
        if ($suggestion->targettype === 'node' && $suggestion->targetvalue !== '') {
            $url = self::node_url((int) $suggestion->targetvalue, $courseid, $sectionnum);
            if ($url) {
                return ['url' => $url, 'ischooser' => true];
            }
        }

        // 2. An explicit course-admin page.
        if ($suggestion->targettype === 'adminlink' && $suggestion->targetvalue !== '') {
            $url = admin_links::url($suggestion->targetvalue, $courseid);
            if ($url) {
                return ['url' => $url, 'ischooser' => false];
            }
        }

        // 3. Fall back to the suggested activity, if its module is available.
        if ($suggestion->modname !== '' && self::mod_available($suggestion->modname)) {
            return ['url' => new \moodle_url('/course/modedit.php', [
                'add' => $suggestion->modname,
                'course' => $courseid,
                'section' => $sectionnum,
                'return' => 0,
                'sr' => 0,
            ]), 'ischooser' => false];
        }

        // 4. Guaranteed-valid last resort.
        return ['url' => new \moodle_url('/course/edit.php', ['id' => $courseid]), 'ischooser' => false];
    }

    /**
     * A chooser URL for a node, only if the node exists and its graph is enabled.
     *
     * @param int $nodeid
     * @param int $courseid
     * @param int $sectionnum
     * @return \moodle_url|null
     */
    private static function node_url(int $nodeid, int $courseid, int $sectionnum): ?\moodle_url {
        $node = node::get_record(['id' => $nodeid]);
        if (!$node) {
            return null;
        }
        $graph = graph::get_record(['id' => $node->get('graphid')]);
        if (!$graph || !$graph->get('enabled')) {
            return null;
        }
        return new \moodle_url('/admin/tool/guidance/chooser.php', [
            'courseid' => $courseid,
            'node' => $nodeid,
            'section' => $sectionnum,
        ]);
    }

    /**
     * Whether an activity module is installed and enabled.
     *
     * @param string $modname
     * @return bool
     */
    private static function mod_available(string $modname): bool {
        $enabled = \core\plugin_manager::instance()->get_enabled_plugins('mod') ?: [];
        return isset($enabled[$modname]);
    }
}
