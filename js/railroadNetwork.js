function computeProgress(eta) {   
  if (eta >= 3) {
    return 0.2;
  }
  else if (eta == 2) {
    return 0.4;
  }
  else if (eta == 1) {
    return 0.6;
  }
  else { // eta <= 0
    return 0.8;
  }
}

function detectVehicules(network, nodes, svg) {
  detectVehiculesRec(network, true, nodes, svg);
}

function wait (ms) {
  var t = new Date().getTime(); while (new Date().getTime() < t + ms);
}

function detectVehiculesRec(network, isTerminal, nodes, svg) {
  
  // names
  var names = {};

  // current node
  var currentBack = network.idFromChildren;
  names[currentBack] = network.name;
  var currentForth = network.idToChildren;
  names[currentForth] = network.name;
  
  // children
  var childrenForth = [];
  var childrenBack = [];
  if (network.children != undefined) {
    for (var i = 0; i < network.children.length; i++) {
      childrenBack[i] = network.children[i].idFromChildren;
      names[childrenBack[i]] = network.children[i].name;
      if (network.children[i].children != undefined) {
        childrenForth[i] = network.children[i].idToChildren;
        names[childrenForth[i]] = network.children[i].name;
      }
    }
  }

  // back
  if (childrenBack.length > 0 && !isTerminal) {
    detectVehiculesQuery(childrenBack, [currentBack], names, nodes, svg);
  }

  // forth
  if (childrenForth.length > 0) {
    detectVehiculesQuery([currentForth], childrenForth, names, nodes, svg);
  }

  // recursive call
  // (this dirty code below is due to the fact we create a delayed job
  // so it doesn't work with network.children[i] as a parameter, because
  // i would change before the function is called :(
  if (network.children != undefined && network.children.length > 0)
    d3.timer( function() { detectVehiculesRec(network.children[0], false, nodes, svg); return true; }, 600);
  if (network.children != undefined && network.children.length > 1)
    d3.timer( function() { detectVehiculesRec(network.children[1], false, nodes, svg); return true; }, 600);
  if (network.children != undefined && network.children.length > 2)
    d3.timer( function() { detectVehiculesRec(network.children[2], false, nodes, svg); return true; }, 600);
}

function detectVehiculesQuery(from, to, names, nodes, svg) {
  console.log("?detectVehicule&from="+JSON.stringify(from)+"&to="+JSON.stringify(to), from, to, names);

  d3.json("?detectVehicule&from="+JSON.stringify(from)+"&to="+JSON.stringify(to), function(errors, vehicules) {
    console.log(vehicules);
    if (vehicules.length > 0) {

      vehicules = computePositions(vehicules, names, nodes);

      var metroNode = svg.selectAll(".metro")
        .data(vehicules)
      .enter().append("g")
        .attr("class", function(d) { return "metroNode " + d.class; })
        .attr("transform", function(d) { return "translate(" + (d.y - 4) + "," + d.x + ")"; });
      
      metroNode.append("path")
        .attr("d", function (d) {
          if (d.class == 'left') {
            return "M 0 0 L 7 -5 L 7 5 L 0 0";
          }
          else {
            return "M 0 0 L 0 -5 L 7 0 L 0 5 L 0 0";
          }
        });

      metroNode.append("text")
        .attr("dx", "-12")
        .attr("dy", "3") 
        .attr("transform", "rotate(-60)") 
        .style("text-anchor", "end")
        .text(function(d) { return "To "+d.destination; });

     metroNode.append("text")
        .attr("dx", "-12")
        .attr("dy", "21") 
        .attr("transform", "rotate(-60)") 
        .style("font-size", "85%") 
        .style("text-anchor", "end")
        .text(function(d) { return "(Next stop in "+d.eta+" min"+(d.eta > 1 ? "s" : "")+")"; });

         
    }
  });
}

function computePositions(vehicules, names, nodes) {
  for (var   i = 0; i < vehicules.length; i++) {
    vehicules[i] = computePosition(vehicules[i], names, nodes);
  }
  return vehicules;
}

function computePosition(vehicule, names, nodes) {
  okBegin = false;
  okEnd = false;
  for (i = 0; i < nodes.length; i++) {
    if (nodes[i].name == names[vehicule.from]) {
      var xBegin = nodes[i].x;
      var yBegin = nodes[i].y;
      var okBegin = true;
    }
    else if (nodes[i].name == names[vehicule.to]) {
      var xEnd = nodes[i].x;
      var yEnd = nodes[i].y;
      var okEnd = true;
    }

    if (okBegin && okEnd) {
      progress = computeProgress(vehicule.eta);
      console.log(names[vehicule.from], xBegin, yBegin, names[vehicule.to], xEnd, yEnd, progress, [xBegin * (1 - progress) + xEnd * progress, yBegin * (1 - progress) + yEnd * progress]);
      vehicule.x = xBegin * (1 - progress) + xEnd * progress;
      vehicule.y = yBegin * (1 - progress) + yEnd * progress;
      vehicule.class = yBegin > yEnd ? "left" : "right";
      return vehicule;
    }
  }

  return null;
}

function displayNetwork(data) {

  var parent = d3.select("#network");

  var width = parseInt(parent.style("width"), 10) - 5,
      height = parseInt(parent.style("height"), 10) - 5;

  var cluster = d3.layout.cluster()
      .size([height * 0.9, width * 0.90]);

  var diagonal = d3.svg.diagonal()
      .projection(function(d) { return [d.y, d.x]; });

  var svg = parent.append("svg")
      .attr("width", width)
      .attr("height", height)
    .append("g")
      .attr("transform", "translate("+width * 0.035+","+(height * 0.03)+")");

  var nodes = cluster.nodes(data),
      links = cluster.links(nodes);

  var link = svg.selectAll(".link")
      .data(links)
    .enter().append("path")
      .attr("class", "link")
      .attr("d", diagonal);

  var node = svg.selectAll(".node")
      .data(nodes)
    .enter().append("g")
      .attr("class", "node")
      .attr("transform", function(d) { return "translate(" + d.y + "," + d.x + ")"; })

  node.append("circle")
      .attr("r", 4.5);

  node.append("text")
      .attr("dx", "9")
      .attr("dy", "3") 
      .attr("transform", "rotate(-60)") 
      .style("text-anchor", "begin")
      .text(function(d) { return d.name; });

  d3.select(self.frameElement).style("height", height + "px");

  return {"nodes": nodes, "svg" : svg};
  
}
