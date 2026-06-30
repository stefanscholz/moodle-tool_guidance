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

namespace tool_guidance\local\callbacks;

use action_link;
use context_course;
use core_course\hook\before_activitychooserbutton_exported;
use moodle_url;
use pix_icon;
use section_info;

/**
 * Adds a "Help me choose" link into the activity chooser ("plus") menu.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_activitychooserbutton_exported_handler {

    /**
     * Append the guidance chooser link to the activity chooser button.
     *
     * @param before_activitychooserbutton_exported $hook
     */
    public static function callback(before_activitychooserbutton_exported $hook): void {
        global $PAGE;

        /** @var section_info $section */
        $section = $hook->get_section();
        $courseid = $section->course;

        $context = context_course::instance($courseid);
        if (!has_capability('tool/guidance:view', $context)) {
            return;
        }

        $url = new moodle_url('/admin/tool/guidance/chooser.php', ['courseid' => $courseid]);

        $hook->get_activitychooserbutton()->add_action_link(new action_link(
            $url,
            get_string('choosebutton', 'tool_guidance'),
            null,
            ['class' => 'dropdown-item', 'data-action' => 'guidance-chooser'],
            new pix_icon('i/info', '')
        ));

        // Open the chooser in a modal (progressive enhancement; the link still
        // works as a full page without JavaScript). The module is idempotent,
        // so enqueueing it once per page is enough even though this hook fires
        // for every section.
        static $jsdone = false;
        if (!$jsdone) {
            $jsdone = true;
            $PAGE->requires->js_call_amd('tool_guidance/chooser_modal', 'init');
        }
    }
}
