<?php
namespace searchHero;
require_once 'autoload.php';

class searchHero {
	static private $pluginFile;

	static public function getDB() {
		global $wpdb;
		return $wpdb;
	}

	static public function getTablePrefix() {
		$db = self::getDB();
		return $db->prefix . 'search_hero_';
	}

	static public function setPluginFile($file) {
		self::$pluginFile = $file;
	}

	static public function install(){
		$collation = 'COLLATE utf8mb4_bin';
		$engine = "ENGINE=innodb";

		$charset = "CHARSET=utf8mb4";

		$sql = "CREATE TABLE IF NOT EXISTS `" . self::getTablePrefix() . "tokens` (
		  `token` bigint signed NOT NULL DEFAULT 0,
		  `num` int unsigned NOT NULL DEFAULT 0,
		  `node` longblob NOT NULL,
		  PRIMARY KEY (`token`)
		) DEFAULT $charset $engine $collation";
		self::getDB()->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `" . self::getTablePrefix() . "indexing` (
		  `doc_id` bigint unsigned NOT NULL DEFAULT 0,
		  PRIMARY KEY (`doc_id`)
		) DEFAULT $charset $engine $collation";
		self::getDB()->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `" . self::getTablePrefix() . "positions` (
		  `doc_id` bigint unsigned NOT NULL DEFAULT 0,
		  `node` longblob NOT NULL,
		  PRIMARY KEY (`doc_id`)
		) DEFAULT $charset $engine $collation";
		self::getDB()->query($sql);
	}

	static public function resetTables(){
		$tables = self::getDB()->get_results("SHOW TABLES LIKE '" . self::getTablePrefix() . "%'", ARRAY_N);
		foreach($tables as $table){
			self::getDB()->query("TRUNCATE TABLE " . $table[0]);
		}
	}

	static public function uninstall(){
		$tables = self::getDB()->get_results("SHOW TABLES LIKE '" . self::getTablePrefix() . "%'", ARRAY_N);
		foreach($tables as $table){
			self::getDB()->query("DROP TABLE " . $table[0]);
		}
	}

	static public function stripHtmlTags($data){
		$block_tags = array('li' => "\t" . '\1' . "\n", 'p' => '\1' . "\n\n", 'h[1-6]' => "\n" . '\1' . "\n\n");
		$data = preg_replace("/<\s*br\s*\/?" . ">/iu", "\n", $data);
		$data = preg_replace("/<\s*a\s+((href=(['\"]?)([^\\3>]*)\\3)|[^>])*? *>(.*?)<\s*\/\s*a[^>]*>/iu", '\5 \4 ', $data);
		foreach($block_tags as $tag => $replace_value){
			$data = preg_replace("/\s*<\s*$tag\s*[^>]*?" . ">\s*(.*?)<\s*\/\s*$tag\s*[^>]*?" . ">\s*/iu", $replace_value, $data);
		}
		$data = trim(preg_replace('/<[^>]*>/u', '', $data));

		$data = preg_replace("/\n[ ]+/u", "\n", $data);
		return $data;
	}

	static public function disable_default_search($request, $query) {
		if ($query->is_search) {
			$request = 'SELECT NULL LIMIT 0';
		}

		return $request;
	}

	static public function search($posts, $query){
		if(!$query->is_main_query() || !$query->is_search() ) return $posts;

		$posts = array();

		$searchTerm = $query->query['s'];
		$page = intval($query->query['paged']);
		if($page) $page--;
		$indexer = new \searchHero\DBIndexer(self::getTablePrefix());

		$s = new \searchHero\search($indexer);

		$ids = array();
		$results = $s->search($searchTerm, $query->query_vars['posts_per_page'], $page, 0);

		$ids = array();
		foreach($results as $result){
			$ids[] = $result->docId;
		}

		if(!empty($ids)){
			$posts = array_flip($ids);
			foreach(get_posts(['include' => $ids, 'post_type' => get_post_types(), 'numberposts' => $query->query_vars['posts_per_page']]) as $post){
				$posts[$post->ID] = $post;
			}

		}

		$query->found_posts = count($results);
		$query->max_num_pages = 10;

	
		return array_values($posts);
	}

	static public function getIndexingCount(){
		$indexer = new \searchHero\DBIndexer(self::getTablePrefix());
		return $indexer->getIndexingCount();
	}

	static public function getIndexingStateKey(){
		return 'search-heroindexing_key';
	}

	static public function getIndexingState(){
		$indexingState = get_option(self::getIndexingStateKey());

		if(empty($indexingState)){
			$indexingState = array();
			$indexingState['state'] = '';
			$indexingState['message'] = '';
			$indexingState['indexed'] = 0;
			$indexingState['count'] = 0;
		}

		return $indexingState;
	}

	static public function index($maxSeconds = 0){
		$indexer = new \searchHero\DBIndexer(self::getTablePrefix());
		$indexer->setIndexing(true);
		$indexer->setMemoryLimitMB(1024);
	
	
		$docCount = 0;
		$chunkSize = 1000;
		if($maxSeconds) $maxSeconds += time();

		wp_suspend_cache_addition( true );

		do {
			$postIds = array();
			$ids = self::getDB()->get_results("SELECT doc_id FROM " . self::getTablePrefix() . "indexing ORDER BY doc_id LIMIT $chunkSize");
			foreach($ids as $value){
				$postIds[] = $value->doc_id;
			}

			$posts = array();
			if(!empty($postIds)){
				$args = array(
				    'post_type' => 'post',          
				    'post__in'  => $postIds,       
				    'posts_per_page' => $chunkSize,        
				    'orderby' => 'post__in',        
				    'ignore_sticky_posts' => true   
				);
				$posts = get_posts($args);
			}

			$processedDocs = array();
			foreach($posts as $post){
				if($maxSeconds && time() > $maxSeconds){
					break;
				}

				$id = $post->ID;
				$processedDocs[] = $id;
				$docCount++;

				$text = self::stripHtmlTags(html_entity_decode($post->post_title)) . "\n";
				$text .= self::stripHtmlTags(html_entity_decode($post->post_content));
				$doc = new \searchHero\document($id, $text);

				$indexer->addBulkDocument($doc);

			}

			self::getDB()->get_results("DELETE FROM " . self::getTablePrefix() . "indexing WHERE doc_id IN('" . implode("','", $processedDocs) . "')");

			if($maxSeconds && time() > $maxSeconds) break;

		} while(!empty($postIds));

		$indexer->addBulkDocument();

		/* translators: %d is the number of indexed documents */
		printf(esc_html__("Indexed documents %d", 'search-hero'), $docCount);

		wp_suspend_cache_addition( false );

		$indexingCount = $indexer->getIndexingCount();
		if(!$indexingCount) esc_html_e("Finished indexing", 'search-hero');

		return $docCount;
	}

	static public function ajaxIndexing(){
	
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized user']);
		}
		$indexer = new \searchHero\DBIndexer(self::getTablePrefix());

		$indexingState = self::getIndexingState();

		$indexCount = $indexer->getIndexingCount();
		$postAction = '';
		if(isset($_POST['indexAction'])) $postAction = sanitize_text_field($_POST['indexAction']);

		if($indexingState['state'] == 'continue') {
			if($indexCount) {
			
				ob_start();
				$indexed = self::index(10);
				$data = ob_get_clean();
				$indexingState['state'] = 'continue';
				$indexingState['message'] = $data;
				$indexingState['indexed'] += $indexed;
			} else {
				$indexingState['state'] = 'finished';
			}
		}

		if($postAction == 'reindex' || $postAction == 'index') {
			ob_start();
			self::resetTables();
			$indexer->setupIndexing();
			$data = ob_get_clean();
			$indexingState['state'] = 'continue';
			$indexingState['count'] = self::getIndexingCount();
			$indexingState['indexed'] = 0;
			$indexingState['message'] = $data;
		}

		update_option(self::getIndexingStateKey(), $indexingState, false);

	
		wp_send_json_success($indexingState);

	
		wp_die();
	}

	static public function init() {
		$menu = array(
			[
				'slug' => 'settings',
				'name' => __('Search Hero', 'search-hero'),
				'icon' => 'dashicons-admin-generic',
				'view' => 'views/indexing.php',
				'submenu' =>
				[
					[
						'slug' => 'settings',
						'name' => __('Indexing', 'search-hero'),
						'view' => 'views/indexing.php',
					],
				],
			],
		);
		require_once 'admin_helper.php';
		\searchHero\admin_helper::getInstance(['plugin_file' => self::$pluginFile, 'rootName' => 'search-hero', 'menu' => $menu]);
		register_activation_hook(self::$pluginFile, array(__CLASS__, 'install') );
	
		register_uninstall_hook(self::$pluginFile, array(__CLASS__, 'uninstall') );

	
		add_filter( 'posts_pre_query', ['\searchHero\searchHero', 'search'], 99, 2 );
		add_filter( 'posts_request', ['\searchHero\searchHero', 'disable_default_search'], 10, 2);

		add_action('wp_ajax_search_hero_indexing', ['\searchHero\searchHero', 'ajaxIndexing']);

	

	}

}
