<?php

namespace marcocesarato\DatabaseAPI;

/**
 * PHP Hooks Class (Modified)
 * The PHP Hooks Class is a fork of the WordPress filters hook system rolled in
 * to a class to be ported into any php based system
 * This class is heavily based on the WordPress plugin API and most (if not all)
 * of the code comes from there.
 *
 * @copyright   2011 - 2017
 * @author      Ohad Raz <admin@bainternet.info>
 *
 * @see        http://en.bainternet.info
 *
 * @author      David Miles <david@amereservant.com>
 *
 * @see        http://github.com/amereservant/PHP-Hooks
 *
 * @author      Lars Moelleken <lars@moelleken.org>
 *
 * @see        https://github.com/voku/PHP-Hooks/
 *
 * @author      Damien "Mistic" Sorel <contact@git.strangeplanet.fr>
 *
 * @see        http://www.strangeplanet.fr
 *
 * @license     GNU General Public License v3.0 - license.txt
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NON-INFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
class Hooks
{
    /**
     * Default priority.
     *
     * @const int
     */
    const PRIORITY_NEUTRAL = 50;
    /**
     * Container for storing shortcode tags and their hook to call for the shortcode.
     *
     * @var array
     */
    public static $shortcode_tags = array();
    /**
     * Filters - holds list of hooks.
     *
     * @var array
     */
    protected $filters = array();
    /**
     * Merged Filters.
     *
     * @var array
     */
    protected $merged_filters = array();
    /**
     * Actions.
     *
     * @var array
     */
    protected $actions = array();
    /**
     * Current Filter - holds the name of the current filter.
     *
     * @var array
     */
    protected $current_filter = array();

    /**
     * This class is not allowed to call from outside: private!
     */
    protected function __construct()
    {
    }

    /**
     * Returns a Singleton instance of this class.
     *
     * @return Hooks
     */
    public static function getInstance()
    {
        static $instance;
        if (null === $instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Avoid serialization.
     */
    public function __wakeup()
    {
    }

    /**
     * Execute functions hooked on a specific filter hook, specifying arguments in an array.
     *
     * @param string $tag  the name of the filter hook
     * @param array  $args The arguments supplied to the functions hooked to $tag
     *
     * @return   mixed        the filtered value after all hooked functions are applied to it
     */
    public function apply_filters_ref_array($tag, $args)
    {
        // Do 'all' actions first
        if (isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
            $all_args = func_get_args();
            $this->_call_all_hook($all_args);
        }
        if (!isset($this->filters[$tag])) {
            if (isset($this->filters['all'])) {
                array_pop($this->current_filter);
            }

            return $args[0];
        }
        if (!isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
        }
        // Sort
        if (!isset($this->merged_filters[$tag])) {
            ksort($this->filters[$tag]);
            $this->merged_filters[$tag] = true;
        }
        reset($this->filters[$tag]);
        do {
            foreach ((array)current($this->filters[$tag]) as $the_) {
                if (null !== $the_['function']) {
                    if (null !== $the_['include_path']) {
                        /** @noinspection PhpIncludeInspection */
                        include_once $the_['include_path'];
                    }
                    $args[0] = call_user_func_array($the_['function'], $args);
                }
            }
        } while (next($this->filters[$tag]) !== false);
        array_pop($this->current_filter);

        return $args[0];
    }

    /**
     * FILTERS.
     */

    /**
     * Call "All" Hook.
     *
     * @param array $args
     */
    public function _call_all_hook($args)
    {
        reset($this->filters['all']);
        do {
            foreach ((array)current($this->filters['all']) as $the_) {
                if (null !== $the_['function']) {
                    if (null !== $the_['include_path']) {
                        /** @noinspection PhpIncludeInspection */
                        include_once $the_['include_path'];
                    }
                    call_user_func_array($the_['function'], $args);
                }
            }
        } while (next($this->filters['all']) !== false);
    }

    /**
     * Hooks a function on to a specific action.
     *
     * @param string  $tag
     *                                    The name of the action to which the
     *                                    $function_to_add is hooked
     * @param string  $function_to_add    the name of the function you wish to be called
     * @param int $priority
     *                                    [optional] Used to specify the order in which
     *                                    the functions associated with a particular
     *                                    action are executed (default: 50).
     *                                    Lower numbers correspond with earlier execution,
     *                                    and functions with the same priority are executed
     *                                    in the order in which they were added to the action.
     * @param string  $include_path       [optional] File to include before executing the callback
     *
     * @return bool
     */
    public function add_action($tag, $function_to_add, $priority = self::PRIORITY_NEUTRAL, $include_path = null)
    {
        return $this->add_filter($tag, $function_to_add, $priority, $include_path);
    }

    /**
     * Adds Hooks to a function or method to a specific filter action.
     *
     * @param string       $tag                the name of the filter to hook the
     *                                         {@link $function_to_add} to
     * @param string|array $function_to_add    the name of the function to be called
     *                                         when the filter is applied
     * @param int      $priority           [optional] Used to specify the order in
     *                                         which the functions associated with a
     *                                         particular action are executed (default: 50).
     *                                         Lower numbers correspond with earlier execution,
     *                                         and functions with the same priority are executed
     *                                         in the order in which they were added to the action.
     * @param string       $include_path       [optional] File to include before executing the callback
     *
     * @return bool
     */
    public function add_filter($tag, $function_to_add, $priority = self::PRIORITY_NEUTRAL, $include_path = null)
    {
        $idx = $this->_filter_build_unique_id($function_to_add);
        $this->filters[$tag][$priority][$idx] = array(
            'function' => $function_to_add,
            'include_path' => is_string($include_path) ? $include_path : null,
        );
        unset($this->merged_filters[$tag]);

        return true;
    }

    /**
     * Build Unique ID for storage and retrieval.
     *
     * @param string|array $function     used for creating unique id
     *
     * @return   string|bool
     *                                   Unique ID for usage as array key or false if
     *                                   $priority === false and $function is an
     *                                   object reference, and it does not already have a unique id
     */
    private function _filter_build_unique_id($function)
    {
        if (is_string($function)) {
            return $function;
        }
        if (is_object($function)) {
            // Closures are currently implemented as objects
            $function = array(
                $function,
                '',
            );
        } else {
            $function = (array)$function;
        }
        if (is_object($function[0])) {
            // Object Class Calling
            return spl_object_hash($function[0]) . $function[1];
        }
        if (is_string($function[0])) {
            // Static Calling
            return $function[0] . $function[1];
        }

        return false;
    }

    /**
     * Check if any action has been registered for a hook.
     * <strong>INFO:</strong> Use !== false to check if it's true!
     *
     * @param string      $tag               the name of the action hook
     * @param bool|string $function_to_check [optional]
     *
     * @return   mixed
     *                                       If $function_to_check is omitted,
     *                                       returns boolean for whether the hook has
     *                                       anything registered.
     *                                       When checking a specific function,
     *                                       the priority of that hook is returned,
     *                                       or false if the function is not attached.
     *                                       When using the $function_to_check
     *                                       argument, this function may return a non-boolean
     *                                       value that evaluates to false (e.g.) 0,
     *                                       so use the === operator for testing the return value.
     */
    public function has_action($tag, $function_to_check = false)
    {
        return $this->has_filter($tag, $function_to_check);
    }

    /**
     * Check if any filter has been registered for the given hook.
     * <strong>INFO:</strong> Use !== false to check if it's true!
     *
     * @param string $tag                    the name of the filter hook
     * @param bool   $function_to_check      [optional] Callback function name to check for
     *
     * @return   mixed
     *                                       If {@link $function_to_check} is omitted,
     *                                       returns boolean for whether the hook has
     *                                       anything registered.
     *                                       When checking a specific function, the priority
     *                                       of that hook is returned, or false if the
     *                                       function is not attached.
     *                                       When using the {@link $function_to_check} argument,
     *                                       this function may return a non-boolean value that
     *                                       evaluates to false
     *                                       (e.g.) 0, so use the === operator for testing the return value.
     */
    public function has_filter($tag, $function_to_check = false)
    {
        $has = isset($this->filters[$tag]);
        if (false === $function_to_check || !$has) {
            return $has;
        }
        if (!($idx = $this->_filter_build_unique_id($function_to_check))) {
            return false;
        }
        foreach ((array)array_keys($this->filters[$tag]) as $priority) {
            if (isset($this->filters[$tag][$priority][$idx])) {
                return $priority;
            }
        }

        return false;
    }

    /**
     * Removes a function from a specified action hook.
     *
     * @param string $tag                the action hook to which the function to be removed is hooked
     * @param mixed  $function_to_remove the name of the function which should be removed
     * @param int    $priority           [optional] The priority of the function (default: 50)
     *
     * @return bool whether the function is removed
     */
    public function remove_action($tag, $function_to_remove, $priority = self::PRIORITY_NEUTRAL)
    {
        return $this->remove_filter($tag, $function_to_remove, $priority);
    }

    /**
     * Removes a function from a specified filter hook.
     *
     * @param string $tag                The filter hook to which the function to be removed is hooked
     * @param mixed  $function_to_remove The name of the function which should be removed
     * @param int    $priority           [optional] The priority of the function (default: 50)
     *
     * @return bool
     */
    public function remove_filter($tag, $function_to_remove, $priority = self::PRIORITY_NEUTRAL)
    {
        $function_to_remove = $this->_filter_build_unique_id($function_to_remove);
        if (!isset($this->filters[$tag][$priority][$function_to_remove])) {
            return false;
        }
        unset($this->filters[$tag][$priority][$function_to_remove]);
        if (empty($this->filters[$tag][$priority])) {
            unset($this->filters[$tag][$priority]);
        }
        unset($this->merged_filters[$tag]);

        return true;
    }

    /**
     * Remove all of the hooks from an action.
     *
     * @param string $tag      the action to remove hooks from
     * @param bool   $priority the priority number to remove them from
     *
     * @return bool
     */
    public function remove_all_actions($tag, $priority = false)
    {
        return $this->remove_all_filters($tag, $priority);
    }

    /**
     * Remove all of the hooks from a filter.
     *
     * @param string $tag      the filter to remove hooks from
     * @param bool   $priority the priority number to remove
     *
     * @return bool
     */
    public function remove_all_filters($tag, $priority = false)
    {
        if (isset($this->merged_filters[$tag])) {
            unset($this->merged_filters[$tag]);
        }
        if (!isset($this->filters[$tag])) {
            return true;
        }
        if (false !== $priority && isset($this->filters[$tag][$priority])) {
            unset($this->filters[$tag][$priority]);
        } else {
            unset($this->filters[$tag]);
        }

        return true;
    }

    /**
     * Execute functions hooked on a specific action hook.
     *
     * @param string $tag        the name of the action to be executed
     * @param mixed  $arg
     *                           [optional] Additional arguments which are passed on
     *                           to the functions hooked to the action
     *
     * @return   bool            will return false if $tag does not exist in $filter array
     */
    public function do_action($tag, $arg = '')
    {
        if (!is_array($this->actions)) {
            $this->actions = array();
        }
        if (!isset($this->actions[$tag])) {
            $this->actions[$tag] = 1;
        } else {
            $this->actions[$tag]++;
        }
        // Do 'all' actions first
        if (isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
            $all_args = func_get_args();
            $this->_call_all_hook($all_args);
        }
        if (!isset($this->filters[$tag])) {
            if (isset($this->filters['all'])) {
                array_pop($this->current_filter);
            }

            return false;
        }
        if (!isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
        }
        $args = array();
        if (
            is_array($arg)
            &&
            isset($arg[0])
            &&
            is_object($arg[0])
            &&
            1 == count($arg)
        ) {
            $args[] = &$arg[0];
        } else {
            $args[] = $arg;
        }
        $numArgs = func_num_args();
        for ($a = 2; $a < $numArgs; $a++) {
            $args[] = func_get_arg($a);
        }
        // Sort
        if (!isset($this->merged_filters[$tag])) {
            ksort($this->filters[$tag]);
            $this->merged_filters[$tag] = true;
        }
        reset($this->filters[$tag]);
        do {
            foreach ((array)current($this->filters[$tag]) as $the_) {
                if (null !== $the_['function']) {
                    if (null !== $the_['include_path']) {
                        /** @noinspection PhpIncludeInspection */
                        include_once $the_['include_path'];
                    }
                    call_user_func_array($the_['function'], $args);
                }
            }
        } while (next($this->filters[$tag]) !== false);
        array_pop($this->current_filter);

        return true;
    }

    /**
     * Execute functions hooked on a specific action hook, specifying arguments in an array.
     *
     * @param string $tag  the name of the action to be executed
     * @param array  $args The arguments supplied to the functions hooked to $tag
     *
     * @return   bool         will return false if $tag does not exist in $filter array
     */
    public function do_action_ref_array($tag, $args)
    {
        if (!is_array($this->actions)) {
            $this->actions = array();
        }
        if (!isset($this->actions[$tag])) {
            $this->actions[$tag] = 1;
        } else {
            $this->actions[$tag]++;
        }
        // Do 'all' actions first
        if (isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
            $all_args = func_get_args();
            $this->_call_all_hook($all_args);
        }
        if (!isset($this->filters[$tag])) {
            if (isset($this->filters['all'])) {
                array_pop($this->current_filter);
            }

            return false;
        }
        if (!isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
        }
        // Sort
        if (!isset($merged_filters[$tag])) {
            ksort($this->filters[$tag]);
            $merged_filters[$tag] = true;
        }
        reset($this->filters[$tag]);
        do {
            foreach ((array)current($this->filters[$tag]) as $the_) {
                if (null !== $the_['function']) {
                    if (null !== $the_['include_path']) {
                        /** @noinspection PhpIncludeInspection */
                        include_once $the_['include_path'];
                    }
                    call_user_func_array($the_['function'], $args);
                }
            }
        } while (next($this->filters[$tag]) !== false);
        array_pop($this->current_filter);

        return true;
    }

    /**
     * Retrieve the number of times an action has fired.
     *
     * @param string $tag the name of the action hook
     *
     * @return int the number of times action hook $tag is fired
     */
    public function did_action($tag)
    {
        if (!is_array($this->actions) || !isset($this->actions[$tag])) {
            return 0;
        }

        return $this->actions[$tag];
    }

    /**
     * Retrieve the name of the current filter or action.
     *
     * @return string hook name of the current filter or action
     */
    public function current_filter()
    {
        return end($this->current_filter);
    }

    /**
     * @param array $args
     *
     * @deprecated use "this->_call_all_hook()"
     */
    public function __call_all_hook($args)
    {
        // <-- refactoring "__call_all_hook()" into "_call_all_hook()" is a breaking change (BC),
        // so we will only deprecate the usage
        $this->_call_all_hook($args);
    }

    /**
     * Add hook for shortcode tag.
     * There can only be one hook for each shortcode. Which means that if another
     * plugin has a similar shortcode, it will override yours or yours will override
     * theirs depending on which order the plugins are included and/or ran.
     * Simplest example of a shortcode tag using the API:
     * <code>
     * // [footag foo="bar"]
     * function footag_func($atts) {
     *  return "foo = {$atts[foo]}";
     * }
     * add_shortcode('footag', 'footag_func');
     * </code>
     * Example with nice attribute defaults:
     * <code>
     * // [bartag foo="bar"]
     * function bartag_func($atts) {
     *  $args = shortcode_atts(array(
     *    'foo' => 'no foo',
     *    'baz' => 'default baz',
     *  ), $atts);
     *  return "foo = {$args['foo']}";
     * }
     * add_shortcode('bartag', 'bartag_func');
     * </code>
     * Example with enclosed content:
     * <code>
     * // [baztag]content[/baztag]
     * function baztag_func($atts, $content='') {
     *  return "content = $content";
     * }
     * add_shortcode('baztag', 'baztag_func');
     * </code>.
     *
     * @param string   $tag  shortcode tag to be searched in post content
     * @param callable $func hook to run when shortcode is found
     *
     * @return bool
     */
    public function add_shortcode($tag, $func)
    {
        if (is_callable($func)) {
            self::$shortcode_tags[$tag] = $func;

            return true;
        }

        return false;
    }

    /** @noinspection MagicMethodsValidityInspection */

    /**
     * Removes hook for shortcode.
     *
     * @param string $tag shortcode tag to remove hook for
     *
     * @return bool
     */
    public function remove_shortcode($tag)
    {
        if (isset(self::$shortcode_tags[$tag])) {
            unset(self::$shortcode_tags[$tag]);

            return true;
        }

        return false;
    }

    /**
     * This function is simple, it clears all of the shortcode tags by replacing the
     * shortcodes by a empty array. This is actually a very efficient method
     * for removing all shortcodes.
     *
     * @return bool
     */
    public function remove_all_shortcodes()
    {
        self::$shortcode_tags = array();

        return true;
    }

    /**
     * Whether the passed content contains the specified shortcode.
     *
     * @param string $content
     * @param string $tag
     *
     * @return bool
     */
    public function has_shortcode($content, $tag)
    {
        if (false === strpos($content, '[')) {
            return false;
        }
        if ($this->shortcode_exists($tag)) {
            preg_match_all('/' . $this->get_shortcode_regex() . '/s', $content, $matches, PREG_SET_ORDER);
            if (empty($matches)) {
                return false;
            }
            foreach ($matches as $shortcode) {
                if ($tag === $shortcode[2]) {
                    return true;
                }
                if (!empty($shortcode[5]) && $this->has_shortcode($shortcode[5], $tag)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a registered shortcode exists named $tag.
     *
     * @param string $tag
     *
     * @return bool
     */
    public function shortcode_exists($tag)
    {
        return array_key_exists($tag, self::$shortcode_tags);
    }

    /**
     * Retrieve the shortcode regular expression for searching.
     * The regular expression combines the shortcode tags in the regular expression
     * in a regex class.
     * The regular expression contains 6 different sub matches to help with parsing.
     * 1 - An extra [ to allow for escaping shortcodes with double [[]]
     * 2 - The shortcode name
     * 3 - The shortcode argument list
     * 4 - The self closing /
     * 5 - The content of a shortcode when it wraps some content.
     * 6 - An extra ] to allow for escaping shortcodes with double [[]].
     *
     * @return string The shortcode search regular expression
     */
    public function get_shortcode_regex()
    {
        $tagnames = array_keys(self::$shortcode_tags);
        $tagregexp = implode('|', array_map('preg_quote', $tagnames));
        // WARNING! Do not change this regex without changing __do_shortcode_tag() and __strip_shortcode_tag()
        // Also, see shortcode_unautop() and shortcode.js.
        return
            '\\[' // Opening bracket
            . '(\\[?)' // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
            . "($tagregexp)" // 2: Shortcode name
            . '(?![\\w-])' // Not followed by word character or hyphen
            . '(' // 3: Unroll the loop: Inside the opening shortcode tag
            . '[^\\]\\/]*' // Not a closing bracket or forward slash
            . '(?:'
            . '\\/(?!\\])' // A forward slash not followed by a closing bracket
            . '[^\\]\\/]*' // Not a closing bracket or forward slash
            . ')*?'
            . ')'
            . '(?:'
            . '(\\/)' // 4: Self closing tag ...
            . '\\]' // ... and closing bracket
            . '|'
            . '\\]' // Closing bracket
            . '(?:'
            . '(' // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
            . '[^\\[]*+' // Not an opening bracket
            . '(?:'
            . '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
            . '[^\\[]*+' // Not an opening bracket
            . ')*+'
            . ')'
            . '\\[\\/\\2\\]' // Closing shortcode tag
            . ')?'
            . ')'
            . '(\\]?)'; // 6: Optional second closing brocket for escaping shortcodes: [[tag]]
    }

    /**
     * Search content for shortcodes and filter shortcodes through their hooks.
     * If there are no shortcode tags defined, then the content will be returned
     * without any filtering. This might cause issues when plugins are disabled but
     * the shortcode will still show up in the post or content.
     *
     * @param string $content content to search for shortcodes
     *
     * @return string content with shortcodes filtered out
     */
    public function do_shortcode($content)
    {
        if (empty(self::$shortcode_tags) || !is_array(self::$shortcode_tags)) {
            return $content;
        }
        $pattern = $this->get_shortcode_regex();

        return preg_replace_callback(
            "/$pattern/s",
            array(
                $this,
                '_do_shortcode_tag',
            ),
            $content
        );
    }

    /**
     * Combine user attributes with known attributes and fill in defaults when needed.
     * The pairs should be considered to be all of the attributes which are
     * supported by the caller and given as a list. The returned attributes will
     * only contain the attributes in the $pairs list.
     * If the $atts list has unsupported attributes, then they will be ignored and
     * removed from the final returned list.
     *
     * @param array  $pairs     entire list of supported attributes and their defaults
     * @param array  $atts      user defined attributes in shortcode tag
     * @param string $shortcode [optional] The name of the shortcode, provided for context to enable filtering
     *
     * @return array combined and filtered attribute list
     */
    public function shortcode_atts($pairs, $atts, $shortcode = '')
    {
        $atts = (array)$atts;
        $out = array();
        foreach ($pairs as $name => $default) {
            if (array_key_exists($name, $atts)) {
                $out[$name] = $atts[$name];
            } else {
                $out[$name] = $default;
            }
        }
        /**
         * Filter a shortcode's default attributes.
         * If the third parameter of the shortcode_atts() function is present then this filter is available.
         * The third parameter, $shortcode, is the name of the shortcode.
         *
         * @param array $out   the output array of shortcode attributes
         * @param array $pairs the supported attributes and their defaults
         * @param array $atts  the user defined shortcode attributes
         */
        if ($shortcode) {
            $out = $this->apply_filters(
                array(
                    $this,
                    "shortcode_atts_{$shortcode}",
                ),
                $out,
                $pairs,
                $atts
            );
        }

        return $out;
    }

    /**
     * Call the functions added to a filter hook.
     * <strong>INFO:</strong> Additional variables passed to the functions hooked to $tag.
     *
     * @param string|array $tag   the name of the filter hook
     * @param mixed        $value the value on which the filters hooked to $tag are applied on
     *
     * @return   mixed               the filtered value after all hooked functions are applied to it
     */
    public function apply_filters($tag, $value)
    {
        $args = array();
        // Do 'all' actions first
        if (isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
            $args = func_get_args();
            $this->_call_all_hook($args);
        }
        if (!isset($this->filters[$tag])) {
            if (isset($this->filters['all'])) {
                array_pop($this->current_filter);
            }

            return $value;
        }
        if (!isset($this->filters['all'])) {
            $this->current_filter[] = $tag;
        }
        // Sort
        if (!isset($this->merged_filters[$tag])) {
            ksort($this->filters[$tag]);
            $this->merged_filters[$tag] = true;
        }
        reset($this->filters[$tag]);
        if (empty($args)) {
            $args = func_get_args();
        }
        array_shift($args);
        do {
            foreach ((array)current($this->filters[$tag]) as $the_) {
                if (null !== $the_['function']) {
                    if (null !== $the_['include_path']) {
                        /** @noinspection PhpIncludeInspection */
                        include_once $the_['include_path'];
                    }
                    $args[0] = $value;
                    $value = call_user_func_array($the_['function'], $args);
                }
            }
        } while (next($this->filters[$tag]) !== false);
        array_pop($this->current_filter);

        return $value;
    }

    /**
     * Remove all shortcode tags from the given content.
     *
     * @param string $content content to remove shortcode tags
     *
     * @return string content without shortcode tags
     */
    public function strip_shortcodes($content)
    {
        if (empty(self::$shortcode_tags) || !is_array(self::$shortcode_tags)) {
            return $content;
        }
        $pattern = $this->get_shortcode_regex();

        return preg_replace_callback(
            "/$pattern/s",
            array(
                $this,
                '_strip_shortcode_tag',
            ),
            $content
        );
    }

    /**
     * Prevent the object from being cloned.
     */
    protected function __clone()
    {
    }

    /**
     * Regular Expression callable for do_shortcode() for calling shortcode hook.
     *
     * @param array $m regular expression match array
     *
     * @return mixed <strong>false</strong> on failure
     *
     * @see self::get_shortcode_regex for details of the match array contents.
     */
    private function _do_shortcode_tag($m)
    {
        // allow [[foo]] syntax for escaping a tag
        if ($m[1] == '[' && $m[6] == ']') {
            return substr($m[0], 1, -1);
        }
        $tag = $m[2];
        $attr = $this->shortcode_parse_atts($m[3]);
        // enclosing tag - extra parameter
        if (isset($m[5])) {
            return $m[1] . call_user_func(self::$shortcode_tags[$tag], $attr, $m[5], $tag) . $m[6];
        }

        // self-closing tag
        return $m[1] . call_user_func(self::$shortcode_tags[$tag], $attr, null, $tag) . $m[6];
    }

    /**
     * Retrieve all attributes from the shortcodes tag.
     * The attributes list has the attribute name as the key and the value of the
     * attribute as the value in the key/value pair. This allows for easier
     * retrieval of the attributes, since all attributes have to be known.
     *
     * @param string $text
     *
     * @return array list of attributes and their value
     */
    public function shortcode_parse_atts($text)
    {
        $atts = array();
        $pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $text);
        if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (!empty($m[1])) {
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                } elseif (!empty($m[3])) {
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                } elseif (!empty($m[5])) {
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                } elseif (isset($m[7]) && $m[7] !== '') {
                    $atts[] = stripcslashes($m[7]);
                } elseif (isset($m[8])) {
                    $atts[] = stripcslashes($m[8]);
                }
            }
        } else {
            $atts = ltrim($text);
        }

        return $atts;
    }

    /**
     * Strip shortcode by tag.
     *
     * @param array $m
     *
     * @return string
     */
    private function _strip_shortcode_tag($m)
    {
        // allow [[foo]] syntax for escaping a tag
        if ($m[1] == '[' && $m[6] == ']') {
            return substr($m[0], 1, -1);
        }

        return $m[1] . $m[6];
    }
}
