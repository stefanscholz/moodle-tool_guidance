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

use tool_guidance\local\rule\rule;

/**
 * A single resolved suggestion produced by the engine.
 *
 * Deliberately free of session-specific data (such as the add-activity URL, which
 * carries a sesskey) so it can be cached course-wide.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggestion {

    /**
     * @param int $ruleid
     * @param string $modname suggested activity module
     * @param string $name default activity name (from preconfig)
     * @param string $rationale teacher-facing reason
     * @param string $signal gap|lifecycle|engagement
     * @param string $preconfig opaque preconfig payload for the add-activity flow
     * @param string $targettype where the CTA links: activity|node|adminlink
     * @param string $targetvalue node id (node), admin-link key (adminlink), else empty
     */
    public function __construct(
        public int $ruleid,
        public string $modname,
        public string $name,
        public string $rationale,
        public string $signal,
        public string $preconfig,
        public string $targettype = 'activity',
        public string $targetvalue = '',
    ) {
    }

    /**
     * Build a suggestion from a matched rule.
     *
     * @param rule $rule
     * @return self
     */
    public static function from_rule(rule $rule): self {
        return new self(
            $rule->id,
            $rule->suggestmod,
            self::name_from_preconfig($rule->preconfig),
            $rule->rationale,
            $rule->signal,
            $rule->preconfig,
            $rule->targettype,
            $rule->targetvalue,
        );
    }

    /**
     * Extract a human default name from the `key=value;...` preconfig payload.
     *
     * @param string $preconfig
     * @return string
     */
    private static function name_from_preconfig(string $preconfig): string {
        foreach (explode(';', $preconfig) as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2 && trim($parts[0]) === 'name') {
                return trim($parts[1]);
            }
        }
        return '';
    }

    /**
     * Serialise for the MUC cache.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'ruleid'    => $this->ruleid,
            'modname'   => $this->modname,
            'name'      => $this->name,
            'rationale' => $this->rationale,
            'signal'    => $this->signal,
            'preconfig' => $this->preconfig,
            'targettype' => $this->targettype,
            'targetvalue' => $this->targetvalue,
        ];
    }

    /**
     * Rebuild from a cached array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function from_array(array $data): self {
        return new self(
            (int) $data['ruleid'],
            (string) $data['modname'],
            (string) $data['name'],
            (string) $data['rationale'],
            (string) $data['signal'],
            (string) $data['preconfig'],
            (string) ($data['targettype'] ?? 'activity'),
            (string) ($data['targetvalue'] ?? ''),
        );
    }
}
