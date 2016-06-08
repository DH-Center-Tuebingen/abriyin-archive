<?
	// ========================================================================================================
	function db_network_get_js($div_id) {
	// ========================================================================================================
		global $TABLES;
		$nodes = array();
		$edges = array();
		
		$db = db_connect();
		
		foreach($TABLES as $table_name => $table) {
			if(str_ends_with('_history', $table_name) 
				|| in_array($table_name, array('users','view_changes_by_user'))
				|| !isset($table['primary_key'])
				|| count($table['primary_key']['columns']) > 1)
				continue;
			
			// get fields: "label", all T_LOOKUP
			$q_single_fields = array();
			$q_multi_fields = array();
			$fields_str = db_esc($table['primary_key']['columns'][0]);
			
			// lookup fields
			foreach($table['fields'] as $field_name => $field) {
				if($field['type'] !== T_LOOKUP || in_array($field['lookup']['table'], array('users')))
					continue;
				
				if($field['lookup']['cardinality'] === CARDINALITY_MULTIPLE) {
					
					if(!isset($TABLES[$field['linkage']['table']])
						|| !isset($TABLES[$field['lookup']['table']]))
						continue;
					
					$q_multi_fields[] = $field_name;
				}
				else {
					$q_single_fields[] = $field_name;
					$fields_str .= ', ' . db_esc($field_name);
				}
			}
			
			$sql = sprintf('select %s from %s', $fields_str, db_esc($table_name));
			foreach($db->query($sql) as $row) {
				$pk = $row[$table['primary_key']['columns'][0]];
				
				$nodes[] = sprintf("{data: {id: %s, label: %s}}", 
					json_encode($table_name . '#' . $pk),
					json_encode($table['item_name'] . ' #' . $row[$table['primary_key']['columns'][0]]) // todo: what to display as label?
				);
				
				// CARDINALITY_SINGLE edges
				foreach($q_single_fields as $single_field_name) {
					$edges[] = sprintf("{data: {id: %s, source: %s, target: %s, weight:1, label: %s}}",
						json_encode($table_name . '#' . $pk . ':' . $table['fields'][$single_field_name]['lookup']['table'] . '#' . $row[$single_field_name]), 
						json_encode($table_name . '#' . $pk),
						json_encode($table['fields'][$single_field_name]['lookup']['table'] . '#' . $row[$single_field_name]),
						json_encode('' /*$table['fields'][$single_field_name]['display']*/)
					);
				}
				
				// CARDINALITY_MULTIPLE edges
				foreach($q_multi_fields as $multi_field_name) {
					$sql = sprintf('select %s from %s where %s = ?',
						db_esc($table['fields'][$multi_field_name]['linkage']['fk_other']),
						db_esc($table['fields'][$multi_field_name]['linkage']['table']),
						db_esc($table['fields'][$multi_field_name]['linkage']['fk_self']));
					
					$stmt = $db->prepare($sql);					
					$res = $stmt->execute(array($pk));
					while($row = $stmt->fetch(PDO::FETCH_NUM)) {
						$edges[] = sprintf("{data: {id: %s, source: %s, target: %s, weight:1, label: %s}}",
							json_encode($table_name . '#' . $pk . ':' . $table['fields'][$multi_field_name]['lookup']['table'] . '#' . $row[0]), 
							json_encode($table_name . '#' . $pk),
							json_encode($table['fields'][$multi_field_name]['lookup']['table'] . '#' . $row[0]),
							json_encode('' /*$table['fields'][$field_name]['display']*/)
						);						
					}
				}
			}			
		}
		
		$nodes = implode(",\n\t", $nodes);
		$edges = implode(",\n\t", $edges);
		
		$js = <<<EOT
		<script src='cytoscape.min.js'></script>
		<script src='cytoscape-cose-bilkent.js'></script>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var cy = cytoscape({
				container: document.getElementById('$div_id'),
			
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
			
				elements: { 
nodes: [
	$nodes
], 
edges: [
	$edges
] 
				},
			
				layout: {name: 'cose', numIter: 5000 }
			});
			
			cy.fit();		
		});
		</script>
EOT;
		return $js;
	}
?>