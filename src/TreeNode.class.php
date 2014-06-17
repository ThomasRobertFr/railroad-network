<?php

require_once 'vendor/autoload.php';

/**
 * Class describing a TreeNode (and therefore a Tree)
 * 
 * It is usefull to transform the network to a tree that can be saved as JSON and displayed as a dendrogram
 * with d3.js
 */
class TreeNode {
	public $vertex;
	public static $toChildrenDir;
	public static $fromChildrenDir;
	public $children = array();

	public function __construct($vertex) {
		$this->vertex = $vertex;
	}

	public function addChildren($child) {
		$this->children[] = $child;
		return $child;
	}

	public function addNewChildren($childVertex) {
		$child = new TreeNode($childVertex);
		return $this->addChildren($child);
	}

	public function toArray() {
		$array = array(
			'name' => $this->vertex->getId(),
			'idToChildren' => $this->vertex->getLayoutAttribute(self::$toChildrenDir),
			'idFromChildren' => $this->vertex->getLayoutAttribute(self::$fromChildrenDir)
			);
		if (!empty($this->children)) {
			$array['children'] = array();
			foreach ($this->children as $child) {
				$array['children'][] = $child->toArray();
			}
		}
		return $array;
	}

	public function __toString() {
		$str = $this->vertex->getId().' => {';
		foreach ($this->children as $child) {
			$str .= $child.', ';
		}
		return $str.'}';
	}
}
