<?php
/**
 * Search Hero
 *
 * /search-hero.php
 *
 * @package Search Hero
 * @author  Search Hero
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 *
 * @wordpress-plugin
 * Plugin Name: Search Hero
 * Description: This plugin replaces WordPress default search with a much faster and more relevant search results.
 * Version: 1.0.1
 * Author: Search Hero
 * Text Domain: search-hero
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Copyright 2022 Adam Stevenson
 * This file is part of Search Hero, a search plugin for WordPress.
 *
 * Search Hero is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Search Hero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Search Hero.  If not, see <http://www.gnu.org/licenses/>.
 */

if(!defined('ABSPATH')) die();

require_once 'lib/searchHero.php';
require_once 'cli.php';
\searchHero\searchHero::setPluginFile(__FILE__);
\searchHero\searchHero::init();
