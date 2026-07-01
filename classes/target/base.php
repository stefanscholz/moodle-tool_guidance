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
 * Base class for leaf targets.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\target;

/**
 * A leaf target describes what happens when a teacher reaches a leaf node.
 *
 * Each subclass owns its own config schema (stored as JSON in node.targetconfig),
 * validates it, renders the matching form fields and resolves an action URL/label.
 */
abstract class base {
    /** @var array Decoded target configuration. */
    protected $config;

    /**
     * Construct a target from its decoded configuration.
     *
     * @param array $config Decoded configuration (associative array).
     */
    public function __construct(array $config = []) {
        $this->config = $config;
    }

    /**
     * The stable type key stored in node.targettype.
     *
     * @return string
     */
    abstract public function get_type(): string;

    /**
     * Validate the current configuration.
     *
     * @return true|\lang_string True when valid, otherwise the error message.
     */
    abstract public function validate_config();

    /**
     * Add this target's config fields to a node form.
     *
     * Field names must be namespaced with the "target_" prefix so they do not
     * collide with the node's own fields.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    abstract public function add_config_form_elements(\MoodleQuickForm $mform): void;

    /**
     * Resolve the action URL a teacher should be sent to.
     *
     * @return \moodle_url|null
     */
    abstract public function get_action_url(): ?\moodle_url;

    /**
     * Human readable label for the action button.
     *
     * @return string
     */
    public function get_action_label(): string {
        return get_string('target:' . $this->get_type() . ':action', 'tool_guidance');
    }

    /**
     * Menu label for the target-type selector.
     *
     * Core types resolve against tool_guidance; addon-provided targets should
     * override this to read from their own component.
     *
     * @return string
     */
    public function get_menu_label(): string {
        return get_string('target:' . $this->get_type(), 'tool_guidance');
    }

    /**
     * Resolve the action URL for a teacher in a specific course/section.
     *
     * Context-independent targets (url, route) ignore the arguments; targets that
     * create something in the course (activity, preset) override this.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @return \moodle_url|null
     */
    public function get_action_url_for_course(int $courseid, int $sectionnum = 0): ?\moodle_url {
        return $this->get_action_url();
    }

    /**
     * Icon to show next to the action.
     *
     * @param \renderer_base $output
     * @return \moodle_url
     */
    public function get_icon(\renderer_base $output): \moodle_url {
        return $output->image_url('i/info', 'core');
    }

    /**
     * Read the decoded config back (e.g. to populate a form).
     *
     * @return array
     */
    public function get_config(): array {
        return $this->config;
    }
}
