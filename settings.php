<?php
// This file is part of Moodle-oembed-Filter
//
// Moodle-oembed-Filter is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle-oembed-Filter is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle-oembed-Filter.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Filter for component 'filter_oembed'
 *
 * @package   filter_oembed
 * @copyright 2012 Matthew Cannings, Sandwell College; modified 2015 by Microsoft, Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * code based on the following filters...
 * Screencast (Mark Schall)
 * Soundcloud (Troy Williams)
 */

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__.'/filter.php');
require_once($CFG->libdir.'/formslib.php');

use filter_oembed\service\oembed;

if ($ADMIN->fulltree) {

    $targettags = [
        'a'  =>  get_string('atag', 'filter_oembed'),
        'div'=>  get_string('divtag', 'filter_oembed'),
    ];

    $cachelifespan =[
        '0' =>  get_string('cachelifespan_disabled', 'filter_oembed'),
        '1' =>  get_string('cachelifespan_daily', 'filter_oembed'),
        '2' =>  get_string('cachelifespan_weekly', 'filter_oembed')
    ];

    $config = get_config('filter_oembed');

    $item = new admin_setting_configselect('filter_oembed/cachelifespan', get_string('cachelifespan', 'filter_oembed'), get_string('cachelifespan_desc', 'filter_oembed'),'1', $cachelifespan);

    $item = new admin_setting_configselect('filter_oembed/targettag', get_string('targettag', 'filter_oembed'),  get_string('targettag_desc', 'filter_oembed'), 'atag', ['atag' => 'atag','divtag'=>'divtag']);
    $settings->add($item);

    $oembed = oembed::get_instance();
    foreach ($oembed->providers as $provider) {
        $providers_allowed_default[$provider['provider_name']] = $provider['provider_name'];
    }

    $item = new admin_setting_configcheckbox('filter_oembed/providers_restrict', get_string('providers_restrict', 'filter_oembed'), get_string('providers_restrict_desc', 'filter_oembed'), '0');
    $settings->add($item);

    $item = new admin_setting_configmulticheckbox('filter_oembed/providers_allowed', get_string('providers_allowed', 'filter_oembed'), get_string('providers_allowed_desc', 'filter_oembed'), implode(',', array_values($providers_allowed_default)), $providers_allowed_default);
    $settings->add($item);

    $item = new admin_setting_configcheckbox('filter_oembed/lazyload', new lang_string('lazyload', 'filter_oembed'), '', 0);
    $settings->add($item);
    $retrylist = array('0' => new lang_string('none'), '1' => new lang_string('once', 'filter_oembed'),
                                                  '2' => new lang_string('times', 'filter_oembed', '2'),
                                                  '3' => new lang_string('times', 'filter_oembed', '3'));
    $item = new admin_setting_configselect('filter_oembed/retrylimit', new lang_string('retrylimit', 'filter_oembed'), '', '1', $retrylist);
    $settings->add($item);
}
