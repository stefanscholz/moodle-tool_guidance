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

namespace tool_guidance\local\condition;

use tool_guidance\local\profile\fact_catalogue;

defined('MOODLE_INTERNAL') || die();

/**
 * The one place the condition DSL grammar lives: parse and compile.
 *
 * Grammar: clauses joined by " AND "; each clause is `fact OP operand`.
 * Operators: == != <= >= < > in. An operand is a literal, another fact name,
 * or for `in` a set `(a|b|c)`.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parser {

    /**
     * Parse a DSL string into structured clauses.
     *
     * @param string $condition
     * @return array<int, array{fact: string, op: string, operandkind: string, value: string|array}>
     * @throws \coding_exception on a malformed clause.
     */
    public static function parse(string $condition): array {
        $condition = trim($condition);
        if ($condition === '') {
            return [];
        }
        $clauses = [];
        foreach (preg_split('/\s+AND\s+/', $condition) as $part) {
            $part = trim($part);
            if (!preg_match('/^([\w.]+)\s+(==|!=|<=|>=|<|>|in)\s+(.+)$/', $part, $m)) {
                throw new \coding_exception('Malformed condition clause: ' . $part);
            }
            $fact = $m[1];
            $op = $m[2];
            $operand = trim($m[3]);
            if ($op === 'in') {
                $clauses[] = ['fact' => $fact, 'op' => $op, 'operandkind' => 'set', 'value' => self::parse_set($operand)];
            } else if (fact_catalogue::is_fact($operand)) {
                $clauses[] = ['fact' => $fact, 'op' => $op, 'operandkind' => 'fact', 'value' => $operand];
            } else {
                $clauses[] = ['fact' => $fact, 'op' => $op, 'operandkind' => 'literal', 'value' => $operand];
            }
        }
        return $clauses;
    }

    /**
     * Compile structured clauses back into a DSL string.
     *
     * Incomplete clauses (no fact, no operator, or an empty operand) are skipped.
     *
     * @param array $clauses
     * @return string
     */
    public static function compile(array $clauses): string {
        $parts = [];
        foreach ($clauses as $clause) {
            $fact = trim((string) ($clause['fact'] ?? ''));
            $op = trim((string) ($clause['op'] ?? ''));
            if ($fact === '' || $op === '') {
                continue;
            }
            if (($clause['operandkind'] ?? 'literal') === 'set') {
                $values = array_filter(array_map('trim', (array) ($clause['value'] ?? [])), static fn($v) => $v !== '');
                $operand = '(' . implode('|', $values) . ')';
            } else {
                $operand = trim((string) ($clause['value'] ?? ''));
            }
            if ($operand === '') {
                continue;
            }
            $parts[] = "$fact $op $operand";
        }
        return implode(' AND ', $parts);
    }

    /**
     * Split a `(a|b|c)` set into its members.
     *
     * @param string $operand
     * @return array<int, string>
     */
    private static function parse_set(string $operand): array {
        $operand = trim($operand);
        if (!str_starts_with($operand, '(') || !str_ends_with($operand, ')')) {
            throw new \coding_exception('Set operand must use (a|b|c) syntax: ' . $operand);
        }
        return array_map('trim', explode('|', substr($operand, 1, -1)));
    }
}
