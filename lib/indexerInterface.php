<?php
namespace searchHero;

interface indexerInterface {
	public function addDocument(document $document);
	public function removeDocument(document $doc);
	public function getNumDocuments();
	public function getNode($id);
	public function getAllNodes(array $ids);
}

