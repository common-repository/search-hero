<?php
namespace searchHero;

class docResult {
	public $tokens = array();
	public $posScore = array();
	public $accum_score = 0;
	public $score = 0;

	public $posScoreTotal = 0;
	public $snippet = array();
	public $snippet_title = '';
	public $snippet_text = '';
	public $docLen = 0;
	public $docId = 0;
}

