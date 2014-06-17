<?php

require_once 'vendor/autoload.php';
use \Fhaculty\Graph\Graph as Graph;

/**
 * API class to analyse a railroad network and detect the position of the vehicules from the ETA at each station
 */
class API {

	/** 
	 * Graph describing the network
	 */
	private static $graph;

	/**
	 * List associating each station ID with the corresponding vertex ID, because each station has 1 ID per
	 * direction but only 1 vertex. And we sometimes have to find the vertex from a station ID.
	 */
	private static $vertexIds = array();

	/**
	 * Get the terminal nodes from the network
	 * @param Graph $graph network
	 * @return Vertex[] terminal stations
	 */
	private static function getTerminals($graph) {
		return $graph->getVertices()->getVerticesMatch(
		function ($vertex) {
			return ($vertex->getEdgesOut()->count() == 1);
		});
	}

	/**
	 * Get the main terminal, that is to say the one that appears in most of the possible railroads.
	 * 
	 * @see getTerminals
	 * @param Vertex[] $terminal list of the existing terminal vertices
	 * @return Vertex main terminal station
	 */
	private static function getMainTerminal($terminals) {
		$max = null;
		$maxOccs = 0;
		foreach($terminals as $vertex) {
			$nbOccs = $vertex->getLayoutAttribute('nbOccs');
			if ($nbOccs > $maxOccs) {
				$max = $vertex;
				$maxOccs = $nbOccs;
			}
		}
		return $max;
	}

	/**
	 * Return the next possible nodes from a vertex following a direction
	 * 
	 * @param Vertex $vertex vertex from where to go
	 * @param int $direction direction to follow
	 * @return Vertex[] next vertices
	 */
	private static function getNextNodes($vertex, $direction) {
		$next = array();
		foreach($vertex->getEdgesOut() as $edge) {
			if ($edge->getLayoutAttribute('direction') == $direction) {
				$next[] = $edge->getVerticesTarget()->getVertexFirst();	
			}
		}
		return $next;
	}

	/**
	 * Create the Tree recursively.
	 *
	 * @see createTree
	 *
	 * @param TreeNode $tree tree to complete
	 * @param Vertex $vertex vertex were we are
	 * @param int $diretion direction to follow
	 * @return TreeNode tree completed
	 */
	private static function createTreeRecursive($tree, $vertex, $direction) {

		foreach(self::getNextNodes($vertex, $direction) as $next) {
			$child = $tree->addNewChildren($next);
			self::createTreeRecursive($child, $next, $direction);
		}

	}

	/**
	 * Create a TreeNode from a graph describing the network.
	 * 
	 * @param Graph $graph
	 * @return TreeNode
	 */
	private static function createTree($graph) {

		$terminals = self::getTerminals($graph);
		$max = self::getMainTerminal($terminals);

		$direction = $max->getEdgesOut()->getEdgeFirst()->getLayoutAttribute('direction');
		TreeNode::$toChildrenDir = 'IDdir'.$direction;
		TreeNode::$fromChildrenDir = 'IDdir'.(3 - $direction);
		$tree = new TreeNode($max);
		self::createTreeRecursive($tree, $max, $direction);

		return $tree;
	}

	/**
	 * Get the list of stations of the network. Curently the data sources of the network are hardcoded inside this function.
	 * The stations are also saved in the file stations.json.
	 * The network is cached in 2 files graph.dump and vertexIds.dump to avoid useless calls to API and because there is
	 * very little chances that the network will change... Rouen <3
	 * 
	 * @param boolean $return do we need an output
	 * @param boolean $force force to reload and do not look at the cache
	 * @return TreeNode
	 */
	public static function getStations($return = false, $force = false) {
		
		if (file_exists('data/graph.dump') && file_exists('data/vertexIds.dump') && !$force) {
			self::$graph = unserialize(file_get_contents('data/graph.dump'));
			self::$vertexIds = unserialize(file_get_contents('data/vertexIds.dump'));
		}
		else {

			$linesXML = array(
				array(
					'XML' => file_get_contents('<API link1>'),
					'direction' => 1
				),
				array(
					'XML' => file_get_contents('<API link2>'),
					'direction' => 2
				),
				array(
					'XML' => file_get_contents('<API link3>'),
					'direction' => 1
				),
				array(
					'XML' => file_get_contents('<API link4>'),
					'direction' => 2
				)
			);

			self::$graph = new Graph();

			foreach($linesXML as $lineEl) {

				$lineXML = $lineEl['XML'];
				$direction = $lineEl['direction'];

				preg_match('#<PhysicalStopPoint_view.+</PhysicalStopPoint_view>#ism', $lineXML, $matches);
				$lineXML = $matches[0];
				$lineXML = preg_replace('#diffgr:id="PhysicalStopPoint_view[0-9]+" msdata:rowOrder="[0-9]+"#is', '', $lineXML);
				$lineXML = new SimpleXMLElement('<stops>'.$lineXML.'</stops>');

				$previousVertex = null;
				foreach ($lineXML as $station) {
					if (self::$graph->hasVertex((string) $station->Name)) {
						$vertex = self::$graph->getVertex((string) $station->Name);
					}
					else {
						$vertex = self::$graph->createVertex((string) $station->Name);
						$vertex->setLayoutAttribute('nbOccs', 0);
					}

					if ($previousVertex && !$previousVertex->hasEdgeTo($vertex)) {
						$edge = $previousVertex->createEdgeTo($vertex);
						$edge->setLayoutAttribute('direction', $direction);
					}

					// save properties
					$vertex->setLayoutAttribute('nbOccs', $vertex->getLayoutAttribute('nbOccs') + 1);
					$vertex->setLayoutAttribute('IDdir'.$direction, (int) $station->ID_StopPoint);
					$vertex->setLayoutAttribute('locality', (string) $station->LocalityName);

					self::$vertexIds[(int) $station->ID_StopPoint] = array(
						'name' => (string) $station->Name,
						'direction' => $direction
					);

					$previousVertex = $vertex;
				}
			}
			file_put_contents('data/graph.dump', serialize(self::$graph));
			file_put_contents('data/vertexIds.dump', serialize(self::$vertexIds));
			
		}

		if ($return) {
			$tree = self::createTree(self::$graph)->toArray();
			file_put_contents('data/stations.json', json_encode($tree));

			return $tree;
		}
	}

	/**
	 * Function that calls TCAR API on 2 lists of stations (identified by their ID) to get, for each station, the ETA of the next
	 * vehicules.
	 * 
	 * Those ETA are merged for the all the "previous"s and "next"s and grouped by journey (we can have multiple journeys
	 * on the same network).
	 * 
	 * The result is a pair of tables associating each journey with a list of ETA and metadata (destination, stop ID)
	 * 
	 * @param int[] $previous list of previous stations ID
	 * @param int[] $nexts list of next stations ID
	 * @return array
	 */
	private static function getTimeData($previous, $nexts) {

		// datas
		$datas = array(array(), array());
		$ids = array($previous, $nexts);
		foreach ($ids as $i => $ids) {
			foreach ($ids as $id) {
				$k = 0;
				do {
					if ($k > 0) {
						sleep(2);
					}
					$datas[$i][$id] = file_get_contents('<API URL>'.$id);
					$k++;
				}
				while($k < 3 && strpos($datas[$i][$id], 'Erreur: ') !== false);
			}
		}

		// xml
		$xmls = array();
		foreach($datas as $i => $data) {
			$xmls[$i] = array();
			foreach($data as $id => $dataChunk) {
				if ($dataChunk != 'Erreur: ') {
					$xmls[$i][$id] = new SimpleXMLElement($dataChunk);
				}
				else {
					$xmls[$i][$id] = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?>
					<None></None>');
				}
			}
		}

		// generate wait time tables
		$tables = array();
		foreach($xmls as $i => $xml) {
			$tables[$i] = array();
			foreach($xml as $id => $xmlChunk) {
				foreach($xmlChunk->StopRealTimetable->VehicleJourneyAtStop as $vehicule) {
					
					// get data
					$journey = (string) $vehicule->journeyPattern->id;
					$destination = (string) $vehicule->journeyPattern->destination;
					$time = (int) $vehicule->waitingTime["minute"];

					// save in a table
					if (!isset($tables[$i][$journey])) {
						$tables[$i][$journey] = array();
					}
					$tables[$i][$journey][] = array('time' => $time, 'stop' => $id, 'destination' => $destination);
				}
			}
		}

		// sort tables
		foreach ($tables as $key => $table) {
			foreach ($table as $journey => $times) {
				$timesVals = array();
				foreach($times as $id => $time) {
					$timesVals[$id] = $time['time'];
				}
				array_multisort($timesVals, SORT_ASC, $times);
				$table[$journey] = $times;
			}
			$tables[$key] = $table;
		}

		return $tables;
	}

	/**
	 * Detected vehicules from a pair of tables listing, for each journey type (there are multiple different
	 * journeys on the network), the ETA of the next vehicules.
	 * We therefore interpolate the pesence of a vehicule between 2 stations depending of these ETA. It's definitely
	 * not a true science but that's enough and the best we can do.
	 *
	 * Each vehicule detected and returned is represented by an array with 4 keys : from (int) : ID of the previous
	 * station, to (int) : ID of the next station, destination (string) and eta to the next station in minutes (int)
	 *
	 * @param array[] $tables
	 * @return array[] detected vehicules
	 */
	private static function detectVehiculeFromTable($tables) {
		$detected = array();
		foreach($tables[0] as $id => $times) {
			if (isset($tables[1][$id]) && isset($tables[1][$id][0]) && isset($tables[0][$id][0])) {

				if ($tables[0][$id][0]['time'] > $tables[1][$id][0]['time'] || 
					$tables[0][$id][0]['time'] == $tables[1][$id][0]['time'] && $tables[0][$id][0]['time'] <= 3) {
					
					$previous = self::$graph->getVertex(self::$vertexIds[$tables[0][$id][0]['stop']]['name']);
					$next     = self::$graph->getVertex(self::$vertexIds[$tables[1][$id][0]['stop']]['name']);

					$detected[] = array(
						'from' => $tables[0][$id][0]['stop'],
						'to' => $tables[1][$id][0]['stop'],
						'destination' => $tables[0][$id][0]['destination'],
						'eta' => $tables[1][$id][0]['time']
					);
				}
			}
		}

		return $detected;
	}

	/**
	 * Unsmart function to compute an approximate progress depending on the ETA. We suppose that it takes
	 * a little bit more than 3 minutes to travel from one station to another, which is quite far from
	 * being true...
	 * @param int $eta
	 * @return float progress estimated between 0 and 1
	 */
	private static function computeProgress($eta) {
		
		if ($eta >= 3) {
			return 0.85;
		}
		elseif ($eta == 2) {
			return 0.65;
		}
		elseif ($eta == 1) {
			return 0.40;
		}
		elseif ($eta <= 0) {
			return 0.15;
		}

	}

	/**
	 * Return all detected vehicules between 2 stations
	 * 
	 * Each vehicule is represented by an array with 2 keys : destination (string) and eta to the next station in minutes (int)
	 * 
	 * @param array $previous id of the previous station of the potential vehicules
	 * @param array $next id of the next station of the potential vehicules
	 * @return array[] list of detected vehicules
	 */
	public static function detectVehicule($previous, $next) {
		$tables = API::getTimeData($previous, $next);	
		return API::detectVehiculeFromTable($tables);
	}

}