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
 * Privacy provider for the guidance graph editor subplugin.
 *
 * @package    guidanceaddon_editor
 * @copyright  2026 Lily Asshauer, bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_editor\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * The editor authors data stored in tool_guidance's own tables; it stores none itself.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Reason why this plugin stores no personal data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
