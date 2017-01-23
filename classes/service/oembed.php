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
 * Filter for component 'filter_oembed'
 *
 * @package   filter_oembed
 * @copyright Erich M. Wappis / Guy Thomas 2016
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace filter_oembed\service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');

/**
 * Class oembed
 * @package filter_oembed\service
 * @copyright Erich M. Wappis / Guy Thomas 2016
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * Singleton class providing function for filtering embedded content links in text.
 */
class oembed {

    /**
     * @var array
     */
    protected $warnings = [];

    /**
     * @var array|mixed
     */
    protected $providers = [];

    /**
     * @var array
     */
    protected $sites = [];

    /**
     * Constructor - protected singeton.
     */
    protected function __construct() {
        $this->set_providers();
        $this->sites = $this->get_sites();
    }

    /**
     * Singleton
     *
     * @return oembed
     */
    public static function get_instance() {
        /** @var $instance oembed */
        static $instance;
        if ($instance) {
            return $instance;
        } else {
            return new oembed();
        }
    }

    /**
     * Get cached providers
     *
     * @param bool $ignorelifespan
     * @return bool|mixed
     * @throws \Exception
     * @throws \dml_exception
     */
    protected function get_cached_providers($ignorelifespan = false) {
        $config = get_config('filter_oembed');

        if (empty($config->cachelifespan )) {
            // When unset or set to not cache.
            $cachelifespan = 0;
        } else if ($config->cachelifespan == '1') {
            $cachelifespan = DAYSECS;
        } else if ($config->cachelifespan == '2') {
            $cachelifespan = WEEKSECS;
        } else {
            throw new \coding_exception('Unknown cachelifespan setting!', $config->cachelifespan);
        }

        // If config is present and cache fresh and available then use it.
        if (!empty($config)) {
            if (!empty($config->providerscachestamp) && !empty($config->providerscached)) {
                $lastcached = intval($config->providerscachestamp);
                if ($ignorelifespan || $lastcached > time() - $cachelifespan) {
                    // Use cached providers.
                    $providers = json_decode($config->providerscached, true);
                    return $providers;
                }
            }
        }
        return false;
    }

    /**
     * Cache provider json string.
     *
     * @param string $json
     */
    protected function cache_provider_json($json) {
        set_config('providerscached', $json, 'filter_oembed');
        set_config('providerscachestamp', time(), 'filter_oembed');
    }

    /**
     * Set providers property, retrieve from cache if possible.
     *
     * @throws \Exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function set_providers() {
        global $CFG;

        $config = get_config('filter_oembed');

        // NOTE: useremoteproviderslist is a setting that hasn't been added to settings.php yet. When added it will
        // default to 0 and will require enabling to make it acquire the remote providers list at http://oembed.com/.
        if (empty($config->useremoteproviderslist)) {
            $providers = null;
        } else {
            $providers = $this->get_cached_providers();
            if (empty($providers)) {
                $providers = $this->download_providers();
            }
            if (empty($providers)) {
                // OK - we couldn't retrieve the providers via curl, let's hope we have something cached that's usable.
                $providers = $this->get_cached_providers(true);
            }
        }

        if (empty($providers)) {
            // Either remote providers not enabled or couldn't get anything via curl or from cache, use local static copy.
            $ret = file_get_contents($CFG->dirroot.'/filter/oembed/providers.json');
            $providers = json_decode($ret, true);
        }

        $this->providers = $providers;

        if (!empty($config->providersrestrict)) {
            if (!empty($config->providersallowed)) {
                // We want to restrict the providers that are used.
                $whitelist = explode(',', $config->providersallowed);
                $wlist = array_filter($providers, function ($val) use ($whitelist) {
                    if (in_array($val['provider_name'], $whitelist)) {
                        return true;
                    }
                });
                set_config('providerswhitelisted', $wlist, 'filter_oembed');
                $this->providerswhitelisted = $wlist;
            } else {
                $this->providerswhitelisted = [];
            }
        }
    }

    /**
     * Get the latest provider list from http://oembed.com/providers.json
     * If connection fails, take local list
     *
     * @return space array
     */
    protected function download_providers() {
        $www = 'http://oembed.com/providers.json';

        $timeout = 15;

        $ret = download_file_content($www, null, null, true, $timeout, 20, false, null, false);

        if ($ret->status == '200') {
            $ret = $ret->results;
        } else {
            $this->warnings[] = 'Failed to load providers from '.$www;
            return false;
        }

        $providers = json_decode($ret, true);

        if (!is_array($providers)) {
            $providers = false;
        }

        if (empty($providers)) {
            throw new \moodle_exception('error:noproviders', 'filter_oembed', '');
        }

        // Cache provider json.
        $this->cache_provider_json($ret);

        return $providers;
    }

    /**
     * Check if the provided url matches any supported content providers
     *
     * @return array
     */
    protected function get_sites() {

        $sites = [];
        $config = get_config('filter_oembed');

        if (!empty($config->providersrestrict)) {
            $providerlist = $this->providerswhitelisted;
        } else {
            $providerlist = $this->providers;
        }

        foreach ($providerlist as $provider) {
            $providerurl = $provider['provider_url'];
            $endpoints = $provider['endpoints'];
            $endpointsarr = $endpoints[0];
            $endpointurl = $endpointsarr['url'];
            $endpointurl = str_replace('{format}', 'json', $endpointurl);

            // Check if schemes are definded for this provider.
            // If not take the provider url for creating a regex.
            if (array_key_exists('schemes', $endpointsarr)) {
                $regexschemes = $endpointsarr['schemes'];
            } else {
                $regexschemes = array($providerurl);
            }

            $sites[] = [
                'provider_name' => $provider['provider_name'],
                'regex'         => $this->create_regex_from_scheme($regexschemes),
                'endpoint'      => $endpointurl
            ];

        }
        return $sites;
    }

    /**
     * Create regular expressions from the providers list to check
     * for supported providers
     *
     * @param array $schemes
     */
    protected function create_regex_from_scheme(array $schemes) {

        foreach ($schemes as $scheme) {

            $url1 = preg_split('/(https?:\/\/)/', $scheme);
            $url2 = preg_split('/\//', $url1[1]);

            $regexarr = [];

            foreach ($url2 as $url) {
                $find = ['.', '*'];
                $replace = ['\.', '.*?'];
                $url = str_replace($find, $replace, $url);
                $regexarr[] = '('.$url.')';
            }

            $regex[] = '/(https?:\/\/)'.implode('\/', $regexarr).'/';
        }
        return $regex;
    }

    /**
     * Get the actual json from content provider
     *
     * @param string $www
     * @return array
     */
    protected function oembed_curlcall($www) {

        $ret = download_file_content($www, null, null, true, 300, 20, false, null, false);

        $this->providerurl = $www;
        $this->providerjson = $ret->results;
        $result = json_decode($ret->results, true);

        return $result;
    }

    /**
     * Get oembed html.
     *
     * @param array $jsonarr
     * @param string $params
     * @return string
     * @throws \coding_exception
     */
    protected function oembed_gethtml($jsonarr, $params = '') {

        if ($jsonarr === null) {
            $this->warnings[] = get_string('connection_error', 'filter_oembed');
            return '';
        }

        $embed = $jsonarr['html'];

        if ($params != '') {
            $embed = str_replace('?feature=oembed', '?feature=oembed'.htmlspecialchars($params), $embed);
        }

        $output = '<div class="oembed-content">' . $embed . '</div>'; // Wrapper for responsive processing.
        return $output;
    }

    /**
     * Attempt to get aspect ratio from strings.
     * @param string $width
     * @param string $height
     * @return float|int
     */
    protected function get_aspect_ratio($width, $height) {
        $bothperc = strpos($height, '%') !== false && strpos($width, '%') !== false;
        $neitherperc = strpos($height, '%') === false && strpos($width, '%') === false;
        // If both height and width use percentages or both don't then we can calculate an aspect ratio.
        if ($bothperc || $neitherperc) {
            // Calculate aspect ratio.
            $aspectratio = intval($height) / intval($width);
        } else {
            $aspectratio = 0;
        }
        return $aspectratio;
    }

    /**
     * Generate preloader html.
     * @param array $jsonarr
     * @param string $params
     * @return string
     */
    protected function oembed_getpreloadhtml(array $jsonarr, $params = '') {
        global $PAGE;
        /** @var \filter_oembed_renderer $renderer */
        $renderer = $PAGE->get_renderer('filter_oembed');

        // To surpress the loadHTML Warnings.
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($jsonarr['html']);
        libxml_use_internal_errors(false);

        // Get aspect ratio of iframe or use width in json.
        if ($dom->getElementsByTagName('iframe')->length > 0) {
            $width = $dom->getElementsByTagName('iframe')->item(0)->getAttribute('width');
            $height = $dom->getElementsByTagName('iframe')->item(0)->getAttribute('height');
            $aspectratio = $this->get_aspect_ratio($width, $height);
            if ($aspectratio === 0) {
                if (isset($jsonarr['width']) && isset($jsonarr['height'])) {
                    $width = $jsonarr['width'];
                    $height = $jsonarr['height'];
                    $aspectratio = $this->get_aspect_ratio($width, $height);
                    if ($aspectratio === 0) {
                        // Couldn't get a decent aspect ratio, let's go with 0.5625 (16:9)!
                        $aspectratio = 0.5625;
                    }
                }
            }

            // This html is intentionally hardcoded and excluded from the mustache template as javascript relies on it.
            $jsonarr['jshtml'] = ' data-aspect-ratio = "'.$aspectratio.'" ';
        }

        return $renderer->preload($this->oembed_gethtml($jsonarr, $params), $jsonarr);
    }

    /**
     * Filter text - convert oembed divs and links into oembed code.
     *
     * @param string $text
     * @return string
     */
    public function html_output($text) {
        $lazyload = get_config('filter_oembed', 'lazyload');
        $lazyload = $lazyload == 1 || $lazyload === false;

        $url2 = '&format=json';
        foreach ($this->sites as $site) {
            foreach ($site['regex'] as $regex) {
                if (preg_match($regex, $text)) {
                    $url = $site['endpoint'].'?url='.$text.$url2;
                    $jsonret = $this->oembed_curlcall($url);
                    if (!$jsonret) {
                        return '';
                    }

                    if ($lazyload) {
                        return $this->oembed_getpreloadhtml($jsonret);
                    } else {
                        return $this->oembed_gethtml($jsonret);
                    }
                }
            }
        }
        return '';
    }

    /**
     * Magic method for getting properties.
     * @param string $name
     * @return mixed
     * @throws \coding_exception
     */
    public function __get($name) {
        $allowed = ['providers', 'warnings', 'sites'];
        if (in_array($name, $allowed)) {
            return $this->$name;
        } else {
            throw new \coding_exception($name.' is not a publicly accessible property of '.get_class($this));
        }
    }
}
