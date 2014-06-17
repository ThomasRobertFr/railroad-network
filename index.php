<?php

include('src/API.class.php');
include('src/TreeNode.class.php');
include('src/VehiculePosition.class.php');

// load data for display (saved in JSON files)
API::getStations();

// API call to get 1 vehicule ?
if (isset($_GET['detectVehicule']) && !empty($_GET['from']) && !empty($_GET['to'])) {
	$from = array_map('intval', json_decode($_GET['from']));
	$to = array_map('intval', json_decode($_GET['to']));
	echo json_encode(API::detectVehicule($from, $to));
	die();
}

?>
<!DOCTYPE html>
<html>
	<head>
	<title>Railroad Network</title>
		<meta charset="utf-8" />
		<link href='http://fonts.googleapis.com/css?family=Open+Sans:300' rel='stylesheet' type='text/css'>
		<link href="style/style.css" rel="stylesheet" />
	</head>
	<body>
		<div id="network"></div>
		<div id="credits">Thomas Robert - <a href="">Fork me on GitHub</a></div>
		<script src="js/d3.v3.min.js"></script>
		<script src="js/railroadNetwork.js"></script>
		<script>
			d3.json("data/stations.json", function (errors, stations) {
				network = displayNetwork(stations);
				detectVehicules(stations, network.nodes, network.svg);
			});
		</script>
	</body>
</html>