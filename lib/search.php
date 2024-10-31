<?php
namespace searchHero;

class search {
	protected $numDocuments = 0;
	protected $indexer;
	protected $allNodes = array();

	public function __construct(\searchHero\indexerInterface $indexer){
		$this->indexer = $indexer;
	}

	public function getDocuments($ids){
		return $this->indexer->getDocumentTexts($ids);
	}

	public function getDocument($id){
		return $this->indexer->getDocumentText($id);
	}

	public function addDocument(document $document){
		$this->indexer->addDocument($document);
	}

	public function removeDocument($id){
		$this->indexer->removeDocument($id);
	}

	protected function idfSort($a, $b){
		if($a->idf < $b->idf) return 1;
		if($b->idf < $a->idf) return -1;
		return 0;
	}

	protected function getSearchOrder($query){
		$searchTokens = tokenize::getTokens($query);

		$allNodes = $this->indexer->getAllNodes($searchTokens);
		uasort($allNodes, [$this, 'idfSort']);
		return $allNodes;
	}

	protected function getSmallestWindows($queryCount, $doc, $idfLimit, $shouldMatch){
		$doc->posScoreTotal = 0;
		$windows = array();
		$windowSize = 6;

		$totalIdf = 0;
		$tokenCount = 0;
		foreach($doc->posScore as $tokenKey => &$dummy){
			$totalIdf += $this->allNodes["$tokenKey"]->idf;
			$tokenCount++;
		}

		usort($doc->posScore, function($a, $b){
			if($a[1][ $a[0] ] < $b[1][ $b[0] ]) return -1;
			if($b[1][ $b[0] ] < $a[1][ $a[0] ]) return 1;
			return 0;
/*
			$ai = $a[0];
			$bi = $b[0];

			$aCmp = $a[1][$ai];
			$bCmp = $b[1][$bi];
			if($aCmp < $bCmp) return -1;
			if($bCmp < $aCmp) return 1;
			return 0;
*/
		});

	
		while(!empty($doc->posScore)){
			$currentValue = null;
			$compareValue = null;
			$match = 1;

		
			for($i = 1; $i < $tokenCount; $i++){
				$previousI = $i - 1;
				$pi = $doc->posScore[$previousI][0];
				$ti = $doc->posScore[$i][0];
				$lastValue = $doc->posScore[$previousI][1][$pi];
				$thisValue = $doc->posScore[$i][1][$ti];
				if($lastValue > $thisValue){
					$temp = $doc->posScore[$previousI];
					$doc->posScore[$previousI] = $doc->posScore[$i];
					$doc->posScore[$i] = $temp;
				} else {
					break;
				}
			}

			$lastCompare = 0;
			$match = 0;
			$diff = 0;
			foreach($doc->posScore as $k => &$value){

				if($currentValue === null){
					$match = 1;
					$ci = $value[0];
					$currentValue = $value[1][$ci];
					$lastCompare = $currentValue;
					$value[0]++;
					if(!isset($value[1][$value[0]])){
						array_shift($doc->posScore);
						$tokenCount--;
					}
					continue;
				} else {
					$ci = $value[0];
					$compareValue = $value[1][$ci];
				}

				$diff = $currentValue - $compareValue;
				if($diff < 0) $diff *= -1;
				if($diff <= $windowSize * $shouldMatch){
					$lastCompare = $compareValue;
					$match++;
				}

				if($diff > $windowSize * $shouldMatch) break;
			}

		
			if($currentValue === null) break;

			$totalDistance = $diff + 1;
			$avgDistance = INF;
			if($match){
				$avgDistance = $totalDistance / $match;
			}

			if($avgDistance <= 3){
				if($match >= $shouldMatch){

					$percentMatch = $match / $queryCount;
					$power = log(5 / max(1, $avgDistance));
					$score = pow(2, $power) * $totalIdf;
					$score += $percentMatch;

				
					$doc->posScoreTotal += $score;

					$windows[] = [$score, $currentValue, $lastCompare];
				}
			}

		}

		return $windows;
	}

	protected function shouldContinue($orderedNodes, $idfLimit){
		$total = 0;
		foreach($orderedNodes as $node){
			$total += $node->node->idf;
		}

		return $total < $idfLimit;
	}

	protected function sortNodes($a, $b){
		if($a->current < $b->current) return -1;
		if($b->current < $a->current) return 1;

		return 0;
	}

	protected function searchWAND($orderedNodes, $idfLimit, $page, $limit){
	
	

		if(empty($orderedNodes)) return array();

	
		$maxDocProcessed = 50000;
		$docsProcessed = 0;
		$docsScored = 0;
		$docsToEval = ($page + 1) * $limit;
		$queryCount = count($orderedNodes);
		$orderedNodesCount = $queryCount;
		$indexedNodes = array();

		$shouldMatch = 0;
		foreach($orderedNodes as $node){
			if($node->node->idf >= $idfLimit) $shouldMatch++;
			$indexedNodes[$node->token] = $node;
		}

		usort($orderedNodes, [$this, 'sortNodes']);

		$heap = new \searchHero\resultHeap($docsToEval);
		do {
		
			$lastSwap = 0;
			for($i = 1; $i < $orderedNodesCount; $i++){
				if($orderedNodes[$lastSwap]->current > $orderedNodes[$i]->current){
					$temp = $orderedNodes[$lastSwap];
					$orderedNodes[$lastSwap] = $orderedNodes[$i];
					$orderedNodes[$i] = $temp;
					$lastSwap = $i;
				} else {
					break;
				}
			}

			$stopSearching = false;
			$iterateNode = $orderedNodes[0];

			$current = $iterateNode->current;

		
			$matchCount = 0;

			$idfQueryCount = 0;
			for($i = 0; $i < $orderedNodesCount; $i++){
				$workingNode = $orderedNodes[$i];
				if($workingNode->node->idf >= $idfLimit) $idfQueryCount++;
				$currentWorking = $workingNode->current;

				if($current < $currentWorking){
					if(!$iterateNode->findPosting($currentWorking)){
						unset($orderedNodes[0]);
						$orderedNodesCount--;
						$orderedNodes = array_values($orderedNodes);
						if(!$this->shouldContinue($orderedNodes, $idfLimit)) $stopSearching = true;
						continue 2;
					}

				}

				if($current == $orderedNodes[$i]->current){
					$matchCount++;
				}
			}

		
			if($current < 0) break;

				$docsProcessed++;
				if($docsProcessed >= $maxDocProcessed){
					$stopSearching = true;
					break;
				}

			if($matchCount < $shouldMatch) continue;

		
			$docPositions = array();
			$docLength = 1;
			list($docPositions, $docLength) = $this->indexer->getPositions($current, $indexedNodes);

			for($i = 0; $i < $orderedNodesCount; $i++){
				$currentNode = $orderedNodes[$i];
				if($current == $currentNode->current){
					$currentNode->incIndex();
				}
			}

		
			$docsScored++;
			$docResult = new \searchHero\docResult();
			$docResult->docId = $current;

			foreach($docPositions as $token => $positions){
				$totalPos = count($positions);
				$docResult->posScore[$token] = [0, $positions, $token];

				$docResult->tokens[$token] = $totalPos;

			
			
				$docResult->accum_score += 100 * ($totalPos * $indexedNodes[$token]->node->idf) / $docLength;
			
			}
			$docResult->score = $docResult->accum_score;

		
			$windows = $this->getSmallestWindows($queryCount, $docResult, $idfLimit, $shouldMatch);

				$docResult->snippet = $windows;
				$docResult->score += $docResult->posScoreTotal;

			if($docResult){
				$heap->insert($docResult);
			}

			usort($orderedNodes, [$this, 'sortNodes']);

			if($idfQueryCount < $shouldMatch) $stopSearching = true;
			if(!$orderedNodes) $stopSearching = true;

		} while(!$stopSearching);

		$docs = array();
		while(!$heap->isEmpty()){
			$value = $heap->extract();
			$docs[] = $value;
		}

		$docs = array_reverse($docs);

		return array_slice($docs, $docsToEval - $limit);
	}

	public function search($query, $limit = 50, $page = 0, $scoreFilter = .05){

		$docs = array();
		$this->allNodes = $this->getSearchOrder($query);
		$queryCount = count($this->allNodes);

		$idfLimit = 0;
		$orderedNodes = array();
		$maxSearchTerms = 15;
		foreach($this->allNodes as $token => $node){
			if(!$idfLimit) $idfLimit = $node->idf / 8;
			if($maxSearchTerms-- < 0) continue;
			if($node->idf < $idfLimit) continue;
			$orderedNodes[] = new \searchHero\iteratePostings($node, $token);
		}

		$docs = $this->searchWAND($orderedNodes, $idfLimit, $page, $limit);

		return $docs;
	}

}
