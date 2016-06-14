<?
	// ========================================================================================================
	function db_diagram_get_js($div_id) {
	// ========================================================================================================
		$js = "<script src='cytoscape.min.js'></script>";		
		$js .= "<script>\n";
		$js .= "var cy = cytoscape({\n";
		$js .= "container: document.getElementById('$div_id'),\n";
		
		$elems = array();
		for($i=0; $i<25; $i++)
			$elems[] = "{data:{id:$i}}";
		
		for($i=0; $i<120; $i++) {
			$f = $i % 20;
			$t = rand(0, 24);
			$w = rand(1,10) / 10.;
			$elems[] = "{data:{id:'$f$t',source:$f,target:$t,weight:$w}}";
		}
		
		$elems = implode(', ', $elems);
		
		$js .= "elements: [$elems],\n";
		
		$js .= "layout: {name: 'cose', idealEdgeLength: 100, nodeOverlap: 20 },\n";
		
		$js .= "});\n";		
		$js .= "</script>\n";
		return $js;
	}
?>