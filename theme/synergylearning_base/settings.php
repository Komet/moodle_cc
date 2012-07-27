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
 * Settings for the synergylearning_base theme
 *
 * @package   theme_synergylearning_base
 * @copyright 2011 Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {


 /*
// Block region width
$name = 'theme_synergylearning_base/regionwidth';
$title = get_string('regionwidth','theme_synergylearning_base');
$description = get_string('regionwidthdesc', 'theme_synergylearning_base');
$default = 240;
$choices = array(200=>'200px', 240=>'240px', 290=>'290px', 350=>'350px', 420=>'420px');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting); */


// Custom CSS file
$name = 'theme_synergylearning_base/customcss';
$title = get_string('customcss','theme_synergylearning_base');
$description = get_string('customcssdesc', 'theme_synergylearning_base');
$setting = new admin_setting_configtextarea($name, $title, $description, '');
$settings->add($setting);

}