<?php
/**
 *
 * @package Search Hero
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 */

if(!defined('WP_UNINSTALL_PLUGIN')) exit();
require_once 'lib/searchHero.php';

$search = new \searchHero\searchHero();
$search->uninstall();
