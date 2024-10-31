<?php
namespace searchHero;

class document {
	protected $id;
	protected $text;

	public function __construct($id = 0, $value = ''){
		$this->setId($id);
		$this->setText($value);
	}

	public function getId(){
		return $this->id;
	}

	public function setId($value){
		$this->id = $value;
	}

	public function getText(){
		return $this->text;
	}

	public function setText($value){
		$this->text = $value;
	}

	public function getTokens(){
		return tokenize::getTokens($this->text);
	}

}
