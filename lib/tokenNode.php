<?php
namespace searchHero;

class tokenNode {
	public $idf = array();
	public $postings = array();
	public $numTerms = 0;

	protected function postingSort($a, $b){
		if($a[0] < $b[0]) return -1;
		if($b[0] < $a[0]) return 1;
		return 0;
	}

	public function combine(tokenNode $other){
		$results = array();
		foreach($other->postings as $value){
			$this->addDocument($value);
		}
	}

	public function chunk($size){
		$results = array();
		foreach(array_chunk($this->postings, $size) as $id => $chunk){
			$value = end($chunk);
			$length = key($chunk);

			$obj = new \searchHero\tokenNode();
			$obj->postings = $chunk;
			$obj->numTerms = $length + 1;
			$obj->idf = 0;

			$results[] = $obj;
		}

		return $results;
	}

	public function addDocument($id) {
		$start = isset($this->postings[0]) ? 0 : 1;
		$low = $start;
		$high = $this->numTerms - 1;

	
		while ($this->numTerms && $low <= $high) {
			$mid = intdiv($low + $high, 2);

			if ($this->postings[$mid] == $id) {
			   
			    return;
			}

			if ($this->postings[$mid] < $id) {
			    $low = $mid + 1;
			} else {
			    $high = $mid - 1;
			}
		}

	
		array_splice($this->postings, $low - $start, 0, $id);
		$this->numTerms++;

	}

	public function removeDocument($id){
		$start = isset($this->postings[0]) ? 0 : 1;
		$low = $start;
		$high = $this->numTerms - 1;

	
		while ($this->numTerms && $low <= $high) {
			$mid = intdiv($low + $high, 2);

			if ($this->postings[$mid] == $id) {
			   
				array_splice($this->postings, $mid - $start, 1);
				$this->numTerms--;

			    return;
			}

			if ($this->postings[$mid] < $id) {
			    $low = $mid + 1;
			} else {
			    $high = $mid - 1;
			}
		}

	}

	public function gapEncode(){
		$kTotal = null;
		foreach($this->postings as &$value){
			if($kTotal){
				$value[0] -= $kTotal;
			}
			$kTotal += $value[0];

		
			foreach($value[1] as $fieldNum => &$positions){

				$posTotal = null;
				foreach($positions as &$pos){
					if($posTotal){
						$pos -= $posTotal;
					}
					$posTotal += $pos;
				}
			}
		}
	}

	public function gapDecode(){
		$kTotal = null;
		foreach($this->postings as &$value){
			if($kTotal){
				$value[0] += $kTotal;
			}
			$kTotal = $value[0];

		
			foreach($value[1] as $fieldNum => &$positions){

				$posTotal = null;
				foreach($positions as &$pos){
					if($posTotal){
						$pos += $posTotal;
					}
					$posTotal = $pos;
				}
			}

		}
	}

	public function sortPostingsByTermFreq(){
		usort($this->postings, function($a, $b){
			$c1 = count($a[1]);
			$c2 = count($b[1]);
			if($c1 < $c2) return 1;
			if($c2 < $c1) return -1;
			if($a[0] < $b[0]) return -1;
			if($b[0] < $a[0]) return 1;
			return 0;
		});
	}

	public function sortPostings(){
		usort($this->postings, [$this, 'postingSort']);
	}
}
