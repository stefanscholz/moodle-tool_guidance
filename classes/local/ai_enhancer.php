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

use tool_guidance\local\profile\course_profile;

/**
 * Optional AI layer: re-ranks the deterministic candidate list using a configured
 * AI provider. It can only reorder the candidates the rules already produced, so the
 * deterministic result is always a safe fallback.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_enhancer {

    /**
     * Whether AI re-ranking is both enabled by the admin and available at runtime.
     *
     * @return bool
     */
    public static function is_available(): bool {
        if (!get_config('tool_guidance', 'enableai')) {
            return false;
        }
        if (!class_exists('\core_ai\manager') || !class_exists('\core_ai\aiactions\generate_text')) {
            return false;
        }
        try {
            $manager = \core\di::get(\core_ai\manager::class);
            return $manager->is_action_available(\core_ai\aiactions\generate_text::class);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Re-rank candidates. Returns the input order on any failure or invalid response.
     *
     * @param course_profile $profile
     * @param suggestion[] $candidates in deterministic precedence order
     * @param \context $context the course context for the AI action
     * @return suggestion[]
     */
    public static function rerank(course_profile $profile, array $candidates, \context $context): array {
        if (count($candidates) < 2 || !self::is_available()) {
            return $candidates;
        }
        try {
            $order = self::ask_for_order($profile, $candidates, $context);
        } catch (\Throwable $e) {
            return $candidates;
        }
        return self::apply_order($candidates, $order);
    }

    /**
     * Query the AI provider for an ordering of candidate rule ids.
     *
     * @param course_profile $profile
     * @param suggestion[] $candidates
     * @param \context $context
     * @return int[] the rule ids in the AI's preferred order
     */
    private static function ask_for_order(course_profile $profile, array $candidates, \context $context): array {
        global $USER;

        $prompt = self::build_prompt($profile, $candidates);
        $action = new \core_ai\aiactions\generate_text(
            contextid: $context->id,
            userid: $USER->id,
            prompttext: $prompt,
        );
        $response = \core\di::get(\core_ai\manager::class)->process_action($action);
        if (!$response->get_success()) {
            return [];
        }
        $content = $response->get_response_data()['generatedcontent'] ?? '';
        return self::parse_ids($content);
    }

    /**
     * Build a compact, instruction-only prompt asking for a JSON array of ids.
     *
     * @param course_profile $profile
     * @param suggestion[] $candidates
     * @return string
     */
    private static function build_prompt(course_profile $profile, array $candidates): string {
        $facts = [];
        foreach ($profile->all() as $key => $value) {
            $facts[] = $key . '=' . self::scalar($value);
        }
        $options = [];
        foreach ($candidates as $candidate) {
            $options[] = sprintf('{"id":%d,"activity":"%s","reason":"%s"}',
                $candidate->ruleid, $candidate->modname, addslashes($candidate->rationale));
        }
        return "You are helping a teacher improve a Moodle course.\n"
            . "Course facts: " . implode(', ', $facts) . "\n"
            . "Candidate activity suggestions (already valid for this course):\n"
            . implode("\n", $options) . "\n"
            . "Reorder these candidates from most to least relevant for this specific course. "
            . "You may ONLY reorder the given ids; do not invent new ones. "
            . "Respond with ONLY a JSON array of the ids in your preferred order, e.g. [3,1,2].";
    }

    /**
     * Extract a list of integer ids from the AI response text.
     *
     * @param string $content
     * @return int[]
     */
    private static function parse_ids(string $content): array {
        if (!preg_match('/\[[\d,\s]*\]/', $content, $m)) {
            return [];
        }
        $decoded = json_decode($m[0], true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_map('intval', $decoded));
    }

    /**
     * Reorder candidates by the AI id list, but only if it is a permutation of the
     * candidate ids; otherwise keep the deterministic order.
     *
     * @param suggestion[] $candidates
     * @param int[] $order
     * @return suggestion[]
     */
    private static function apply_order(array $candidates, array $order): array {
        $byid = [];
        foreach ($candidates as $candidate) {
            $byid[$candidate->ruleid] = $candidate;
        }
        $expected = array_keys($byid);
        sort($expected);
        $got = $order;
        sort($got);
        if ($expected !== $got) {
            return $candidates;
        }
        $reordered = [];
        foreach ($order as $id) {
            $reordered[] = $byid[$id];
        }
        return $reordered;
    }

    /**
     * Render a fact value as a compact scalar for the prompt.
     *
     * @param mixed $value
     * @return string
     */
    private static function scalar($value): string {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return $value === null ? 'n/a' : (string) $value;
    }
}
