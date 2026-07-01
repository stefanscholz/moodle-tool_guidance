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

defined('MOODLE_INTERNAL') || die();

/**
 * Immutable value object for an activity preset/template.
 *
 * A preset names an activity module plus a recommended configuration to show
 * the teacher. In the static prototype the configuration is display-only.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset {

    /** @var string Stable short name; the key linking the tree to a stored preset. */
    private string $shortname;

    /** @var string Activity module name (e.g. 'quiz'). */
    private string $modname;

    /** @var string Language key for the preset title. */
    private string $titlekey;

    /** @var string Language key for the preset description. */
    private string $desckey;

    /** @var array Ordered list of ['name' => langkey, 'value' => langkey] config rows. */
    private array $config;

    /**
     * Constructor.
     *
     * @param string $shortname Stable short name linking to a stored preset.
     * @param string $modname Activity module name.
     * @param string $titlekey Language key for the title.
     * @param string $desckey Language key for the description.
     * @param array $config Ordered config rows.
     */
    private function __construct(string $shortname, string $modname, string $titlekey, string $desckey, array $config) {
        $this->shortname = $shortname;
        $this->modname = $modname;
        $this->titlekey = $titlekey;
        $this->desckey = $desckey;
        $this->config = $config;
    }

    /**
     * Create a preset.
     *
     * @param string $shortname Stable short name linking to a stored preset.
     * @param string $modname Activity module name.
     * @param string $titlekey Language key for the title.
     * @param string $desckey Language key for the description.
     * @param array $config Ordered list of ['name' => langkey, 'value' => langkey].
     * @return self
     */
    public static function make(string $shortname, string $modname, string $titlekey, string $desckey, array $config): self {
        return new self($shortname, $modname, $titlekey, $desckey, $config);
    }

    /**
     * @return string Stable short name linking to a stored preset.
     */
    public function get_shortname(): string {
        return $this->shortname;
    }

    /**
     * @return string Activity module name.
     */
    public function get_modname(): string {
        return $this->modname;
    }

    /**
     * @return string Language key for the title.
     */
    public function get_titlekey(): string {
        return $this->titlekey;
    }

    /**
     * @return string Language key for the description.
     */
    public function get_desckey(): string {
        return $this->desckey;
    }

    /**
     * @return array Ordered list of ['name' => langkey, 'value' => langkey].
     */
    public function get_config(): array {
        return $this->config;
    }
}
