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
 * @copyright Erich M. Wappis / Guy Thomas 2016
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * code based on the following filter
 * oEmbed filter ( Mike Churchward, James McQuillan, Vinayak (Vin) Bhalerao, Josh Gavant and Rob Dolin)
 */

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__.'/filter.php');
require_once($CFG->libdir.'/formslib.php');

use filter_oembed\service\oembed;

if ($ADMIN->fulltree) {
    $targettags = [
        'a' => get_string('atag', 'filter_oembed'),
        'div' => get_string('divtag', 'filter_oembed')
    ];

    $config = get_config('filter_oembed');

    $item = new admin_setting_configselect(
        'filter_oembed/targettag',
        get_string('targettag', 'filter_oembed'),
        get_string('targettag_desc', 'filter_oembed'),
        'atag',
        ['atag' => 'atag', 'divtag' => 'divtag']
    );

    $settings->add($item);

    $item = new admin_setting_configcheckbox(
        'filter_oembed/providersrestrict',
        get_string('providersrestrict', 'filter_oembed'),
        get_string('providersrestrict_desc', 'filter_oembed'),
        0
    );
    $settings->add($item);

    $targettag = get_config('filter_oembed', 'targettag');
    if (!empty($config->providersrestrict)) {
        oembed::get_instance(); // We have to call this to cache the providers.
        $providers = json_decode($config->providerscached, true);
        foreach ($providers as $provider) {
            $providersalloweddefault[$provider['provider_name']] = $provider['provider_name'];
        }

        $item = new admin_setting_configmulticheckbox(
            'filter_oembed/providersallowed',
            get_string('providersallowed', 'filter_oembed'),
            get_string('providersallowed_desc', 'filter_oembed'),
            implode(',', array_values($providersalloweddefault)),
            $providersalloweddefault
        );
        $settings->add($item);
    }

    $item = new admin_setting_configcheckbox('filter_oembed/lazyload', new lang_string('lazyload', 'filter_oembed'), '', 1);
    $settings->add($item);

}