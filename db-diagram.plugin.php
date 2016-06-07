<?
	// ========================================================================================================
	function str_ends_with($ending, $str) {
	// ========================================================================================================
		return $ending === substr($str, -strlen($ending));
	}
	
	// ========================================================================================================
	function db_diagram_get_js($div_id) {
	// ========================================================================================================
		$js = '';
		$js .= "<script src='cytoscape.min.js'></script>";				
		$js .= "<script>document.addEventListener('DOMContentLoaded', function(){\n";
		$js .= "var cy = cytoscape({\n";
		$js .= "container: document.getElementById('$div_id'),\n";
		
		$js .= <<<EOT
			style: cytoscape.stylesheet()
				.selector('node')
				  .css({
					'content': 'data(label)',					
					'text-valign': 'center',
					/*'color': 'white',
					'text-outline-width': 2,
					'background-color': '#555',
					'text-outline-color': '#999',*/
					'color': 'black',
					'background-color': 'whitesmoke',
					'width': 'label',
					'border-width': '1px',
					'border-color': 'black',
					'padding-left': '.5em',
					'padding-right': '.5em',
					'shape' : 'rectangle'
				  })
				.selector('edge')
				  .css({
					'curve-style': 'bezier',
					'control-point-step-size': 100,
					'target-arrow-shape': 'triangle',
					'target-arrow-color': 'dimgray',
					'line-color': 'dimgray',
					'color': 'dimgray',
					'width': 'data(weight)',
					'label': 'data(label)',
					'text-rotation': 'autorotate'
				  })
				.selector(':selected')
				  .css({
					'background-color': 'gray',
					'line-color': 'black',
					'target-arrow-color': 'gray',
					'color': 'black',
					'source-arrow-color': 'gray'
				  })
				.selector('.faded')
				  .css({
					'opacity': 0.25,
					'text-opacity': 0
				  }),
EOT;
		
		global $TABLES;
		$nodes = array();
		$edges = array();
		
		foreach($TABLES as $table_name => $table) {
			if(str_ends_with('_history', $table_name) 
				|| in_array($table_name, array('users','view_changes_by_user'))
				|| !isset($table['primary_key'])
				|| count($table['primary_key']['columns']) > 1)
				continue;
			
			$nodes[] = sprintf("{data: {id: '%s', label: %s}}", 
				$table_name, json_encode($table['display_name'])
			);
			
			foreach($table['fields'] as $field_name => $field) {
				/*if($field['type'] !== T_LOOKUP 
					|| $field['lookup']['cardinality'] !== CARDINALITY_SINGLE 
					|| !isset($TABLES[$field['lookup']['table']])
					|| in_array($field['lookup']['table'], array('users')))
				{
					continue;
				}
				
				$edges[] = sprintf("{data: {id: '%s', source: '%s', target: '%s', weight:1, label: %s}}",
					$table_name . '#' . $field_name, $table_name, $field['lookup']['table'], json_encode($field['label'])
				);*/
				
				if($field['type'] !== T_LOOKUP)
					continue;
				
				if(($field['lookup']['cardinality'] === CARDINALITY_MULTIPLE
					&& !isset($TABLES[$field['linkage']['table']]))
					|| !isset($TABLES[$field['lookup']['table']])
					|| in_array($field['lookup']['table'], array('users')))
					continue;
				
				$edges[] = sprintf("{data: {id: '%s', source: '%s', target: '%s', weight:1, label: %s}}",
					$table_name . '#' . $field_name, $table_name, $field['lookup']['table'], json_encode($field['label'])
				);
			}
		}
		
		//			$edges[] = "{data:{id:'$f$t',source:$f,target:$t,weight:$w,label:'FUCK'}}";
		
		$nodes = implode(', ', $nodes);
		$edges = implode(', ', $edges);
		
		$js .= "elements: { nodes: [$nodes], edges: [$edges] },\n\n";
		
		$js .= "layout: {name: 'circle', nodeSpacing: '100'},\n";
		
		$js .= "});cy.fit();\n";		
		$js .= "});</script>\n";
		return $js;
	}
?>