# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
#
# @package    tool_guidance
# @copyright  2026 bdecent gmbh <https://bdecent.de>
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
#
# NOTE: This scenario uses only standard Moodle Behat steps (labelled fields and
# button clicks) so it is runnable as-is under a real Selenium/JS environment.
# Asserting the internal builder select values (which carry data-role attributes
# but no form name/label) would need a small custom Behat context; that deeper
# round-trip assertion is left as future work.

@tool @tool_guidance @javascript
Feature: Graphical condition builder for suggestion rules
  As an administrator
  I can add a rule through the graphical condition builder

  Scenario: The condition builder renders and a rule saves
    Given I log in as "admin"
    And I navigate to "Plugins > Admin tools > Guidance activity chooser > Manage rules" in site administration
    And I press "Add rule"
    Then "//div[contains(@class, 'tool-guidance-condition-builder')]" "xpath_element" should exist
    And I should see "Add condition"
    When I set the field "Name" to "Behat test rule"
    And I set the field "Rationale (shown to the teacher)" to "Because."
    And I press "Add condition"
    And I press "Save changes"
    Then I should see "Behat test rule"
