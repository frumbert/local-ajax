<?php
/**
 * ajax utility plugin
 *
 * @package    local/ajax
 * @copyright  2022 tim st.clair (https://github.com/frumbert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(__FILE__)).'/config.php');

redirect($CFG->wwwroot);