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

use tool_guidance\local\profile\fact_catalogue;

/**
 * Tests for the fact_catalogue class.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_guidance\local\profile\fact_catalogue
 */
final class fact_catalogue_test extends \advanced_testcase {

    public function test_for_form_structure(): void {
        $this->resetAfterTest();
        $form = fact_catalogue::for_form();
        $this->assertArrayHasKey('groups', $form);

        // Flatten facts.
        $bykey = [];
        foreach ($form['groups'] as $group) {
            $this->assertNotEmpty($group['label']);
            foreach ($group['facts'] as $fact) {
                $bykey[$fact['key']] = $fact;
            }
        }

        // Every scalar fact is present, grouped, typed, with operators.
        foreach (array_keys(fact_catalogue::scalar_facts()) as $key) {
            $this->assertArrayHasKey($key, $bykey, "missing fact $key");
            $this->assertNotEmpty($bykey[$key]['label']);
            $this->assertNotEmpty($bykey[$key]['operators']);
        }

        // Enum facts carry value sets; booleans do not.
        $this->assertContains('mid', $bykey['term_stage']['values']);
        $this->assertContains('in', $bykey['term_stage']['operators']);
        $this->assertSame([], $bykey['has_groups']['values']);
        $this->assertSame(['==', '!='], $bykey['has_groups']['operators']);

        // module_count.<mod> appears for an installed module (forum ships by default).
        $this->assertArrayHasKey('module_count.forum', $bykey);
        $this->assertSame('int', $bykey['module_count.forum']['type']);
    }
}
