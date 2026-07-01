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

use tool_guidance\local\condition\evaluator;
use tool_guidance\local\profile\course_profile;

/**
 * Regression guard for evaluator matching semantics.
 *
 * Run BEFORE the refactor to lock in current behaviour, then again after
 * to confirm nothing changed.
 *
 * @covers \tool_guidance\local\condition\evaluator
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class condition_evaluator_test extends \basic_testcase {

    private function profile(): course_profile {
        return new course_profile([
            'has_purpose_content' => true,
            'has_purpose_assessment' => false,
            'activity_count' => 6,
            'resource_count' => 5,
            'interactive_count' => 1,
            'term_stage' => 'mid',
            'module_count.forum' => 0,
        ]);
    }

    public function test_empty_matches(): void {
        $this->assertTrue(evaluator::matches('', $this->profile()));
    }

    public function test_boolean_and_numeric(): void {
        $p = $this->profile();
        $this->assertTrue(evaluator::matches('has_purpose_content == true AND activity_count > 5', $p));
        $this->assertFalse(evaluator::matches('has_purpose_assessment == true', $p));
    }

    public function test_enum_set(): void {
        $this->assertTrue(evaluator::matches('term_stage in (week1|mid|late)', $this->profile()));
        $this->assertFalse(evaluator::matches('term_stage in (prestart|week1)', $this->profile()));
    }

    public function test_fact_vs_fact(): void {
        $this->assertTrue(evaluator::matches('resource_count > interactive_count', $this->profile()));
        $this->assertFalse(evaluator::matches('interactive_count > resource_count', $this->profile()));
    }

    public function test_module_count_default_zero(): void {
        $this->assertTrue(evaluator::matches('module_count.quiz == 0', $this->profile()));
    }

    public function test_null_fact_never_matches(): void {
        $this->assertFalse(evaluator::matches('forum_post_count < 3', $this->profile()));
    }

    public function test_validate(): void {
        $this->assertTrue(evaluator::validate('activity_count > 5'));
        $this->assertNotTrue(evaluator::validate('this is not valid'));
    }
}
