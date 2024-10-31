<?php
namespace searchHero;

class iteratePostings {
	public $node;
	public $token;
	public $searchComparisons = 0;
	public $current;
	protected $index = 0;
	protected $searchDocId = 0;
	protected $lastCalculatedIndex = 0;
	protected $lastCalculatedValue = 0;

	public function __construct($node, $token){
		$this->node = $node;
		$this->token = $token;
		$this->reset();
	}

	public function hasMore(){
		return isset($this->node->postings[$this->index + 1]);
	}

	public function findPosting($searchNodeValue){
		$this->searchDocId = $searchNodeValue;

		return $this->findPostingL();
	}

	public function findPostingWithSkips(){
		$start = $this->index;
		$end = $this->node->numTerms;
		$skip = ceil(sqrt($end - $start));
		$minSkip = 2;
		for($i = $start; $i < $end; $i++){

			$this->searchComparisons++;
		
			if($this->index + $skip < $end){
				$thisCurrent = $this->node->postings[$this->index + $skip];
				if($thisCurrent < $this->searchDocId){
					$this->index += $skip;
					$i += $skip;
					$skip *= 2;
					continue;
				} else {
					if($skip > $minSkip) $skip = ceil($skip / 2);
				}
			} else {
				if($skip > $minSkip) $skip = ceil($skip / 2);
			}

			if($this->searchDocId > $this->node->postings[$this->index]){
				$this->index++;
			} else {
				break;
			}
		}
		return $this->current != -1;
	}

	public function findPostingL(){

		while($this->index < $this->node->numTerms){

			if($this->searchDocId > $this->node->postings[$this->index]){
				$this->index++;
			} else {
				break;
			}
		}
		$this->calcCurrent();

		return $this->current != -1;
	}

	public function nextChunkValue($chunkSize){
		if($this->index == $this->lastCalculatedIndex && $this->lastCalculatedValue) return $this->lastCalculatedValue;
		$result = 0;

		for($i = $this->index; $i < $this->index + $chunkSize; $i++){
			if(!isset($this->node->postings[$i])) break;
			$result += count($this->node->postings[$i][1]);
		}

		$this->lastCalculatedIndex = $this->index;
		$this->lastCalculatedValue = 1 + log($result) * $this->node->idf;

		return $this->lastCalculatedValue;
	}

	public function incIndex(){
		$this->index++;
		$this->calcCurrent();
	}

	protected function calcCurrent(){
		if(isset($this->node->postings[$this->index])){
			$this->current = $this->node->postings[$this->index];
		} else {
			$this->current = -1;
		}
	}

	public function getCurrent(){
		return $this->current;
	}

	public function reset(){
		$this->index = 0;

	
		if(!isset($this->node->postings[$this->index])) $this->index++;
		$this->calcCurrent();
	}
}
