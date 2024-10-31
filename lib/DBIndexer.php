<?php
namespace searchHero;

class DBIndexer implements \searchHero\indexerInterface {
	protected $db;
	protected $tablePrefix;
	protected $nodeCache = array();
	protected $numDocumentsCache = null;
	protected $indexing = false;
	protected $MBMemoryLimit = 0;

	public function __construct($tablePrefix){
		global $wpdb;
		$this->db = $wpdb;
		$this->tablePrefix = preg_replace('/[^a-z0-9_]/', '', $tablePrefix);
		$this->setMemoryLimitMB(1024);
	}

	protected function serializeArray($postings, $type = 'N'){
		$d = deflate_init(ZLIB_ENCODING_RAW);
		$postings = pack($type . '*', ...$postings);
		return deflate_add($d, $postings, ZLIB_SYNC_FLUSH);
	}

	protected function unSerializeArray($postings, $type = 'N'){
	
		return unpack($type . '*', gzinflate($postings . "\03\00"));
	}

	public function setMemoryLimitMB($value){
		$this->MBMemoryLimit = $value * pow(2, 20);
	}

	public function getMemoryLimitMB(){
		return $this->MBMemoryLimit;
	}

	public function setupIndexing(){
		$this->db->query("DELETE FROM {$this->tablePrefix}indexing");
		$this->db->query("INSERT IGNORE INTO {$this->tablePrefix}indexing (SELECT ID AS doc_id FROM " . $this->db->prefix . "posts WHERE post_type NOT IN ('revision') AND post_type = 'post' AND post_status IN('publish') )");
	}

	public function getIndexingCount(){
		$result = $this->db->get_row("SELECT COUNT(*) AS count FROM {$this->tablePrefix}indexing");
		return intval($result->count);
	}

	public function setIndexing($value){
		$this->indexing = (bool)$value;
		if($value && function_exists('wp_suspend_cache_addition')) wp_suspend_cache_addition(true);
	}

	public function resetTables(){
		$this->db->query("TRUNCATE TABLE {$this->tablePrefix}tokens");
		$this->db->query("TRUNCATE TABLE {$this->tablePrefix}positions");
	}

	public function updateDocument(document $document){
		$this->removeDocument($document);
		$this->addDocument($document);
	}

	public function removeDocument(document $doc){
		$id = $doc->getId();
		$updates = array();

		list($positions, $dummy) = $this->getPositions($doc->getId());
		if(isset($positions[$id])){
			$tokens = $this->getAllNodes($positions[$id]);
			foreach($tokens as $token => $node){
				$node->removeDocument($id);
				$updates[] = $this->getNodeSQL($token, $node->numTerms, $node->postings);
			}
			$this->updateIndex($updates);

			$this->db->query($this->db->prepare("DELETE FROM {$this->tablePrefix}positions WHERE doc_id = %d", intval($id)));
		}
	}

	public function addDocument(document $document){
		$id = intval($document->getId());
		$tokens = $document->getTokens();

		$str = $this->getPositionsSQL($id, $tokens);
		$this->updatePositions([$str]);

		$updates = array();
		$nodes = $this->getAllNodes($tokens);
		foreach($nodes as $token => $node){
			$node->addDocument($id);
			$updates[] = $this->getNodeSQL($token, $node->numTerms, $node->postings);
		}
		$this->updateIndex($updates);
	}

	protected function getMaxQuerySize(){
		static $querySize = -1;
		if($querySize < 0){
			$result = $this->db->get_row("SHOW VARIABLES LIKE 'max_allowed_packet'");
			$querySize = $result->Value - pow(2, 20) * 2;
		}
		return $querySize;
	}

	public function getNumDocuments(){
		if($this->numDocumentsCache === NULL){
			$this->numDocumentsCache = -1;
			$result = $this->db->get_row("SELECT count(*) AS total_documents FROM {$this->tablePrefix}positions");
			if($result){
				$this->numDocumentsCache = intval($result->total_documents);
			}
		}

		return $this->numDocumentsCache;
	}

	public function getNode($id){
		return current($this->getAllNodes([$id]));
	}

	public function getAllNodes(array $ids){
		$results = array();

		if(!$this->indexing){
			foreach($ids as $key => $id){
				if(isset($this->nodeCache["$id"])){
					$results[$id] = $this->nodeCache["$id"];
					unset($ids[$key]);
				} else {
					if(isset($this->nodeCache["$id"])) unset($ids[$key]);
					$this->nodeCache["$id"] = null;
				}
			}
		}

		if(!empty($ids)){
		
			foreach($ids as &$id){
				$id = $this->db->_escape($id);
			}

			$nodes = $this->getNodesSql("SELECT token, node, num FROM {$this->tablePrefix}tokens WHERE token IN ('" . implode("','", $ids) . "')");
			foreach($nodes as $token => $node){
				$results["$token"] = $node;
				if(!$this->indexing) $this->nodeCache["$token"] = $node;
			}
		}

		return $results;
	}

	protected function getNodesSql($sql){
		$results = array();
		$numDocs = $this->getNumDocuments();
		$dbResults = $this->db->get_results($sql);
		foreach($dbResults as $dbResult){
			$token = $dbResult->token;
			$num = $dbResult->num;
			$n = new \searchHero\tokenNode();

			$n->postings = $this->unSerializeArray($dbResult->node);

			$n->numTerms = $num;
$numDocs += 100000;
			$n->idf = log($numDocs / $num);

			$results["$token"] = $n;
		}

		return $results;
	}

	protected function getPrecision(){
		if(PHP_INT_SIZE == 8) return 'q';
		return 'l';
	}

	public function getPositions($id, array $tokenFilters = array()){
		$results = array();
		$docLength = 0;
		$dbResults = $this->db->get_results("SELECT doc_id, node FROM {$this->tablePrefix}positions WHERE doc_id = " . intval($id));
		foreach($dbResults as $dbResult){
			$positions = $this->unSerializeArray($dbResult->node, $this->getPrecision());
			foreach($positions as $position => $token){
				if(empty($tokenFilters) || isset($tokenFilters[$token])) $results[$token][] = $position;
				$docLength++;
			}
		}

		return [$results, $docLength];
	}

	protected function startTransaction(){
		$this->db->query("SET autocommit=0");
		$this->db->query("START TRANSACTION");
	}

	protected function endTransaction(){
		$this->db->query("COMMIT");
		$this->db->query("SET autocommit=1");
	}

	protected function updatePositions(&$values){
		if(empty($values)) return;
		$this->startTransaction();
		$this->db->query("REPLACE INTO {$this->tablePrefix}positions (doc_id, node) VALUES " . implode(',', $values));
		$this->endTransaction();
	}

	protected function updateIndex(&$values){
		if(empty($values)) return;
		$this->startTransaction();
		$this->db->query("REPLACE INTO {$this->tablePrefix}tokens (token, num, node) VALUES " . implode(',', $values));
		$this->endTransaction();
	}

	protected function concatIndex(&$values){
		if(empty($values)) return;
		$this->startTransaction();
		$this->db->query("INSERT INTO {$this->tablePrefix}tokens (token, num, node) VALUES " . implode(',', $values) . " AS new_values(token, num, node) ON DUPLICATE KEY UPDATE node = CONCAT({$this->tablePrefix}tokens.node, new_values.node), {$this->tablePrefix}tokens.num = {$this->tablePrefix}tokens.num + new_values.num");
		$this->endTransaction();
	}

	private function getNodeSQL($token, $numTerms, $ids){
		return $this->db->prepare("(%s, %d, %s)", $token, intval($numTerms), $this->serializeArray($ids));
	}

	private function getPositionsSQL($id, $positions){
		return $this->db->prepare("(%d, %s)", $id, $this->serializeArray($positions, $this->getPrecision()));
	}

	public function addBulkDocument(document $document = null){
		static $replaces = array();
		static $postings = array();
		static $totalLen = 0;

	
		if($document == null){
			if(!empty($replaces)) $this->updatePositions($replaces);
			if(!empty($postings)) $this->processPostings($postings);
			$replaces = array();
			$postings = array();
			return;
		}

		$id = intval($document->getId());
		$tokens = $document->getTokens();

		$str = $this->getPositionsSQL($id, $tokens);
		$replaces[] = $str;
		$totalLen += strlen($str);

		if($totalLen > $this->getMaxQuerySize()){
			$this->updatePositions($replaces);
			$totalLen = 0;
			$replaces = array();
		}

	
		$tokens = array_flip($tokens);

		foreach($tokens as $token => $dummy){
			$postings["$token"][] = $id;
		}

		if(memory_get_usage() > $this->getMemoryLimitMB()){
			$this->processPostings($postings);
		}
	}

	protected function processPostings(&$postings){
	
		$totalLen = 0;
		$replaces = array();
		foreach($postings as $token => $ids){
			$numTerms = count($ids);

			$str = $this->getNodeSQL($token, $numTerms, $ids);
			$replaces[] = $str;
			$totalLen += strlen($str);

			if($totalLen > $this->getMaxQuerySize()){
				if($this->indexing){
					$this->concatIndex($replaces);
				} else {
					$this->updateIndex($replaces);
				}
				$totalLen = 0;
				$replaces = array();
			}

			unset($postings[$token]);
		}

		if(!empty($replaces)){
			if($this->indexing){
				$this->concatIndex($replaces);
			} else {
				$this->updateIndex($replaces);
			}
			$replaces = array();
		}

	}

	public function compressIndex(){
		$tokens = array();
		foreach($this->db->get_results("SELECT token FROM {$this->tablePrefix}tokens") as $value){
			$tokens[] = $value->token;
		}

		$this->indexing = true;
		$chunkSize = 5000;
		foreach(array_chunk($tokens, $chunkSize) as $values){
			$nodes = $this->getAllNodes($values);

			$replaces = array();
			foreach($nodes as $token => $node){
				$str = $this->getNodeSQL($token, $node->numTerms, $node->postings);
				$replaces[] = $str;
			}

			$this->updateIndex($replaces);
			$replaces = array();
		}

	}

}

