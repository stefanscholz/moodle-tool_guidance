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

namespace tool_guidance;

use tool_guidance\local\condition\parser;

/**
 * @covers \tool_guidance\local\condition\parser
 */
final class condition_parser_test extends \basic_testcase {

    public function test_empty(): void {
        $this->assertSame([], parser::parse(''));
        $this->assertSame('', parser::compile([]));
    }

    public function test_literal_clause(): void {
        $clauses = parser::parse('has_purpose_content == true');
        $this->assertSame([[
            'fact' => 'has_purpose_content', 'op' => '==',
            'operandkind' => 'literal', 'value' => 'true',
        ]], $clauses);
        $this->assertSame('has_purpose_content == true', parser::compile($clauses));
    }

    public function test_fact_operand_detected(): void {
        $clauses = parser::parse('resource_count > interactive_count');
        $this->assertSame('fact', $clauses[0]['operandkind']);
        $this->assertSame('interactive_count', $clauses[0]['value']);
        $this->assertSame('resource_count > interactive_count', parser::compile($clauses));
    }

    public function test_set_operand(): void {
        $clauses = parser::parse('term_stage in (week1|early|mid)');
        $this->assertSame('set', $clauses[0]['operandkind']);
        $this->assertSame(['week1', 'early', 'mid'], $clauses[0]['value']);
        $this->assertSame('term_stage in (week1|early|mid)', parser::compile($clauses));
    }

    public function test_module_count_is_a_fact_key_not_a_fact_operand(): void {
        // The left side is module_count.quiz; the right side 0 is a literal.
        $clauses = parser::parse('module_count.quiz == 0');
        $this->assertSame('module_count.quiz', $clauses[0]['fact']);
        $this->assertSame('literal', $clauses[0]['operandkind']);
    }

    public function test_malformed_throws(): void {
        $this->expectException(\coding_exception::class);
        parser::parse('this is not valid');
    }

    public function test_malformed_set_throws(): void {
        $this->expectException(\coding_exception::class);
        parser::parse('term_stage in prestart|week1'); // missing parens
    }

    public function test_compile_skips_incomplete_and_joins_with_and(): void {
        $clauses = [
            ['fact' => 'activity_count', 'op' => '>', 'operandkind' => 'literal', 'value' => '5'],
            ['fact' => '', 'op' => '==', 'operandkind' => 'literal', 'value' => 'x'], // dropped
            ['fact' => 'term_stage', 'op' => 'in', 'operandkind' => 'set', 'value' => ['late', 'postend']],
        ];
        $this->assertSame('activity_count > 5 AND term_stage in (late|postend)', parser::compile($clauses));
    }

    public function test_roundtrip_all_seeded_rules(): void {
        global $CFG;
        $path = $CFG->dirroot . '/admin/tool/guidance/db/seed_rules.csv';
        $handle = fopen($path, 'r');
        $this->assertNotFalse($handle, 'Cannot open seed_rules.csv at ' . $path);
        fgetcsv($handle, 0, ',', '"', ''); // header
        $checked = 0;
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) < 8) {
                continue;
            }
            $dsl = trim($row[4]); // condition column
            $this->assertSame($dsl, parser::compile(parser::parse($dsl)), "roundtrip failed for: $dsl");
            $checked++;
        }
        fclose($handle);
        $this->assertGreaterThanOrEqual(100, $checked);
    }
}
