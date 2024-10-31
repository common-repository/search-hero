<?php
namespace searchHero;

class resultHeap extends \SplHeap {
	private $maxSize = 0;

	public function __construct($maxSize){
		$this->maxSize = $maxSize;
	}

	public function full(){
		return $this->maxSize == $this->count();
	}

	public function insert($value) : bool {
		return $this->_insert($value);
	}

	protected function _insert(docResult $value) : bool{
		$result = parent::insert($value);
		if($this->count() > $this->maxSize){
			$v = $this->extract();
		}

		return $result;
	}

	protected function compare($a, $b) : int {
		if($a->score < $b->score) return 1;
		if($b->score < $a->score) return -1;
		return 0;
	}

}
