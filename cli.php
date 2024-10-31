<?php
namespace searchHero;
require_once 'lib/autoload.php';

class search_hero_cli {
    /**
     * Executes a custom WP-CLI command with no parameters.
     *
     * ## EXAMPLES
     *
     *     wp search-hero index
     *
     */
    public function index() {
		$search = new \searchHero\searchHero();
		\WP_CLI::log("Uninstalling");
		$search->uninstall();
		\WP_CLI::log("Installing");
		$search->install();
		\WP_CLI::log("Indexing");
		$indexer = new \searchHero\DBIndexer(\searchHero\searchHero::getTablePrefix());
		$indexer->setupIndexing();

		do {
			$count = $search->index();
			\WP_CLI::log("Count $count");
		} while($count);

        \WP_CLI::success("The command executed successfully!");
    }
}

\add_action('init', function(){
	if (defined('WP_CLI') && WP_CLI) {
	    \WP_CLI::add_command('search-hero', '\searchHero\search_hero_cli');
	}
});
