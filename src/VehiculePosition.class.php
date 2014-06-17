<?php

/**
 * Class to describe a vehicule position on the network
 */
class VehiculePosition {

	public $name;
	public $previousStation;
	public $nextStation;
	public $progress;

	public function __construct($name, $previousStation, $nextStation, $progress) {
		$this->name = $name;
		$this->previousStation = $previousStation;
		$this->nextStation = $nextStation;
		$this->progress = $progress;
	}

}

