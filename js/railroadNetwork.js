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

function interpolateCubicBezierCurveFromSVG(lambda, d) {
  var pos = d.match(/-?[0-9]+(\.[0-9]+)?/g);
  return interpolateCubicBezierCurve(lambda, parseFloat(pos[0]), parseFloat(pos[1]), parseFloat(pos[2]), parseFloat(pos[3]), parseFloat(pos[4]), parseFloat(pos[5]), parseFloat(pos[6]), parseFloat(pos[7]));
}

// from http://www.drububu.com/animation/bezier_curves/
function interpolateCubicBezierCurve(lambda, xAnchorStart, yAnchorStart, xHandleStart, yHandleStart, xHandleEnd, yHandleEnd, xAnchorEnd, yAnchorEnd) {
  var x0, y0, x1, y1, x2, y2, x3, y3, x4, y4, xPoint, yPoint;

  x0 = xAnchorStart + lambda * ( xHandleStart - xAnchorStart );
  y0 = yAnchorStart + lambda * ( yHandleStart - yAnchorStart );
  x1 = xHandleStart + lambda * ( xHandleEnd   - xHandleStart );
  y1 = yHandleStart + lambda * ( yHandleEnd   - yHandleStart );
  x2 = xHandleEnd   + lambda * ( xAnchorEnd   - xHandleEnd   );
  y2 = yHandleEnd   + lambda * ( yAnchorEnd   - yHandleEnd   );

  x3 = x0 + lambda * ( x1 - x0 );
  y3 = y0 + lambda * ( y1 - y0 );
  x4 = x1 + lambda * ( x2 - x1 );
  y4 = y1 + lambda * ( y2 - y1 );

  xPoint = x3 + lambda * ( x4 - x3 );
  yPoint = y3 + lambda * ( y4 - y3 );

  return {"x" : xPoint, "y" : yPoint}
}

function detectVehicules(network, edges, diagonal, svg) {
  detectVehiculesRec(network, true, edges, diagonal, svg);
}

function detectVehiculesRec(network, isTerminal, edges, diagonal, svg) {

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
    detectVehiculesQuery(childrenBack, [currentBack], names, edges, diagonal, svg);
  }

  // forth
  if (childrenForth.length > 0) {
    detectVehiculesQuery([currentForth], childrenForth, names, edges, diagonal, svg);
  }

  // recursive call
  // (this dirty code below is due to the fact we create a delayed job
  // so it doesn't work with network.children[i] as a parameter, because
  // i would change before the function is called :(
  if (network.children != undefined && network.children.length > 0)
    d3.timer( function() { detectVehiculesRec(network.children[0], false, edges, diagonal, svg); return true; }, 600);
  if (network.children != undefined && network.children.length > 1)
    d3.timer( function() { detectVehiculesRec(network.children[1], false, edges, diagonal, svg); return true; }, 600);
  if (network.children != undefined && network.children.length > 2)
    d3.timer( function() { detectVehiculesRec(network.children[2], false, edges, diagonal, svg); return true; }, 600);
}

function detectVehiculesQuery(from, to, names, edges, diagonal, svg) {
  d3.json("?detectVehicule&from="+JSON.stringify(from)+"&to="+JSON.stringify(to), function(errors, vehicules) {
    displayVehicules(vehicules, names, edges, diagonal, svg);
  });
}

function displayVehicules(vehicules, names, edges, diagonal, svg) {
  if (vehicules.length > 0) {
    vehicules = computePositions(vehicules, names, edges, diagonal);

    var metroNode = svg.selectAll(".metro")
      .data(vehicules)
    .enter().append("g")
      .attr("class", function(d) { return "metroNode " + d.class; })
      .attr("transform", function(d) { return "translate(" + (d.x - 4) + "," + d.y + ")"; });

   metroNode.append("title")
      .text(function(d) { return "(Next stop in "+d.eta+" min"+(d.eta > 1 ? "s" : "")+")"; });

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
      .attr("dx", "-8")
      .attr("dy", "6")
      .attr("transform", "rotate(-60)")
      .style("text-anchor", "end")
      .text(function(d) { return "To "+d.destination+" ("+d.eta+" min"+(d.eta > 1 ? "s" : "")+")"; });
  }
}

function computePositions(vehicules, names, edges, diagonal) {
  for (var i = 0; i < vehicules.length; i++) {
    vehicules[i] = computePosition(vehicules[i], names, edges, diagonal);
  }
  return vehicules;
}

function computePosition(vehicule, names, edges, diagonal) {
  okBegin = false;
  okEnd = false;
  var edge, sens;
  for (var i = 0; i < edges.length; i++) {
    if (edges[i].source.name == names[vehicule.from] && edges[i].target.name == names[vehicule.to]) {
      edge = edges[i];
      sens = 1;
      break;
    }
    else if (edges[i].target.name == names[vehicule.from] && edges[i].source.name == names[vehicule.to]) {
      edge = edges[i];
      sens = -1;
      break;
    }
  }

  if (edge != undefined) {
    progress = computeProgress(vehicule.eta);
    if (sens == -1)
      progress = 1 - progress;
    position = interpolateCubicBezierCurveFromSVG(progress, diagonal(edge));
    vehicule.x = position.x;
    vehicule.y = position.y;
    vehicule.class = (sens == 1) ? "right" : "left";
    return vehicule;
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

  return {"diagonal" : diagonal, "nodes": nodes, "edges": links, "svg" : svg};

}
