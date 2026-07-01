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

namespace tool_guidance\local\rule;

/**
 * Value object for a single suggestion rule row.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule {

    /**
     * @param int $id
     * @param int $sortorder precedence; lower wins
     * @param bool $enabled
     * @param string $signaltype gap|lifecycle|engagement
     * @param string $name
     * @param string $conditiontext condition DSL
     * @param string $suggestmod suggested activity module name
     * @param string $rationale teacher-facing reason
     * @param string $preconfig opaque preconfig payload
     */
    public function __construct(
        public int $id,
        public int $sortorder,
        public bool $enabled,
        public string $signaltype,
        public string $name,
        public string $conditiontext,
        public string $suggestmod,
        public string $rationale,
        public string $preconfig,
    ) {
    }

    /**
     * Build a rule from a DB record.
     *
     * @param \stdClass $record
     * @return self
     */
    public static function from_record(\stdClass $record): self {
        return new self(
            (int) $record->id,
            (int) $record->sortorder,
            (bool) $record->enabled,
            (string) $record->signaltype,
            (string) $record->name,
            (string) $record->conditiontext,
            (string) $record->suggestmod,
            (string) $record->rationale,
            (string) ($record->preconfig ?? ''),
        );
    }

    /**
     * Convert to a DB record (id omitted when zero so it can be inserted).
     *
     * @return \stdClass
     */
    public function to_record(): \stdClass {
        $record = new \stdClass();
        if ($this->id) {
            $record->id = $this->id;
        }
        $record->sortorder = $this->sortorder;
        $record->enabled = (int) $this->enabled;
        $record->signaltype = $this->signaltype;
        $record->name = $this->name;
        $record->conditiontext = $this->conditiontext;
        $record->suggestmod = $this->suggestmod;
        $record->rationale = $this->rationale;
        $record->preconfig = $this->preconfig;
        return $record;
    }
}
