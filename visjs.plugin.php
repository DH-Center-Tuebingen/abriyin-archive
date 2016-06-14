<?
	// ========================================================================================================
	function visjs_node_id($table, $id) {
	// ========================================================================================================
		return $table . '#' . $id;
	}
	
	// ========================================================================================================
	function visjs_node_json($table, $id, $label) {
	// ========================================================================================================
		return json_encode(
			array(
				'id' => visjs_node_id($table, $id),
				'label' => $label,
				'group' => $table
			)
		);
	}

	// ========================================================================================================
	function visjs_edge_json($from_table, $from_id, $to_table, $to_id, $label, $options = array()) {
	// ========================================================================================================
		return json_encode(
			array(
				'from' 	=> visjs_node_id($from_table, $from_id),
				'to' 	=> visjs_node_id($to_table, $to_id),			
				'label' => $label
			) 
			+ $options
		);
	}

	// ========================================================================================================
	// render network -- main function
	function visjs_get_network($div_id, $network_setup) {
	// ========================================================================================================
		global $TABLES;
		global $CUSTOM_VARIABLES;
		
		$nodes = array();
		$edges = array();
		
		$db = db_connect();
		
		foreach($network_setup['nodes'] as $table_name => $table_setup) {			
			$table = $TABLES[$table_name];
			$q_single_fields = array();
			$q_multi_fields = array();
			$fields_str = db_esc($table['primary_key']['columns'][0]);
			$fields_str .= ', ' . resolve_display_expression($table_setup['display']) . ' __node__label__';
			
			// collect all lookup fields
			if(isset($table_setup['fields'])) foreach($table_setup['fields'] as $field_name => $edge_info) {
				$field = $table['fields'][$field_name];
				
				if($field['lookup']['cardinality'] === CARDINALITY_MULTIPLE) {					
					if(!isset($TABLES[$field['linkage']['table']])
						|| !isset($TABLES[$field['lookup']['table']]))
						continue;
					
					$q_multi_fields[$field_name] = $edge_info;
				}
				else { // CARDINALITY_MULTIPLE
					$q_single_fields[$field_name] = $edge_info;
					$fields_str .= ', ' . db_esc($field_name);
				}
			}
			
			$sql = sprintf('select %s from %s', $fields_str, db_esc($table_name));
			foreach($db->query($sql) as $row) {
				$pk = $row[$table['primary_key']['columns'][0]];
				
				$num_node_edges = 0;
				
				// CARDINALITY_SINGLE edges
				foreach($q_single_fields as $single_field_name => $edge_info) {
					$lookup_table_name = $table['fields'][$single_field_name]['lookup']['table'];
					
					$edges[] = visjs_edge_json(
						$table_name, $pk,
						$lookup_table_name, $row[$single_field_name],
						$table['fields'][$single_field_name]['label'],
						$edge_info);
						
					$num_node_edges++;
					
					if(!isset($nodes[visjs_node_id($lookup_table_name, $row[$single_field_name])])) {
						$nodes[visjs_node_id($lookup_table_name, $row[$single_field_name])] = visjs_node_json(
							$lookup_table_name, 
							$row[$single_field_name],
							html($row['__node__label__'])							
						);
					}
				}
				
				// CARDINALITY_MULTIPLE edges
				foreach($q_multi_fields as $multi_field_name => $edge_info) {
					$sql = sprintf('select %s, (select %s from %s where %s = %s) __node_label__ from %s t where %s = ?',
						db_esc($table['fields'][$multi_field_name]['linkage']['fk_other']),
						
						resolve_display_expression($network_setup['nodes'][$table['fields'][$multi_field_name]['lookup']['table']]['display']),
						db_esc($table['fields'][$multi_field_name]['lookup']['table']),
						db_esc($table['fields'][$multi_field_name]['lookup']['field']),		
						db_esc($table['fields'][$multi_field_name]['linkage']['fk_other'], 't'),
						
						db_esc($table['fields'][$multi_field_name]['linkage']['table']),
						db_esc($table['fields'][$multi_field_name]['linkage']['fk_self']));
					
					$stmt = $db->prepare($sql);					
					$res = $stmt->execute(array($pk));
					while($row_linked = $stmt->fetch(PDO::FETCH_NUM)) {
						$lookup_table_name = $table['fields'][$multi_field_name]['lookup']['table'];
						
						$edges[] = visjs_edge_json($table_name, $pk, $lookup_table_name, $row_linked[0],
							$table['fields'][$multi_field_name]['label'], $edge_info);
							
						$num_node_edges++;

						if(!isset($nodes[visjs_node_id($lookup_table_name, $row_linked[0])])) {
							$nodes[visjs_node_id($lookup_table_name, $row_linked[0])] = visjs_node_json(
								$lookup_table_name, 
								$row_linked[0],
								html($row['__node__label__'])							
							);
						}
					}
				}
				
				if($num_node_edges > 0) {
					// only if outgoing connections, we make the source node 
					$nodes[visjs_node_id($table_name, $pk)] = visjs_node_json(
						$table_name, 
						$pk,
						html($row['__node__label__'])							
					);
				}
			}			
		}
		
		$nodes = implode(",\n\t", $nodes);
		$edges = implode(",\n\t", $edges);
		
		$groups = array();
		foreach($network_setup['nodes'] as $table_name => $table_info)
			$groups[$table_name] = $table_info['shape'];
			
		$groups = str_replace('\\\\', '\\', json_encode($groups));
		
		$js = <<<EOT
		<script src='https://cdnjs.cloudflare.com/ajax/libs/vis/4.16.1/vis.min.js'></script>		
		<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/vis/4.16.1/vis.min.css' type='text/css' />
		<link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" />
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var container = document.getElementById('$div_id');			
			var data = {
				nodes: [ $nodes ],
				edges: [ $edges ]
			};			
			var options = {				
				layout: {
					improvedLayout: false
				},
				interaction: {
					dragNodes: true,
					hover: true
				},
				physics: {
					solver: 'forceAtlas2Based',
					stabilization: {
						iterations: 400
					},
				},
				
				groups: $groups,
				
				nodes: {
					font : '16px arial black',
					icon: {
						size: 75
					},
					scaling: {
						label: {
							min:12,
							max:20
						}	
					}
				},
				edges: {
					color: '#888888',
					font: '11px arial #888888'
				}
			};
			
			var network = new vis.Network(container, data, options);
			network.on('stabilizationProgress', function(arg) {
				console.log(arg);
			});
			
			network.on('stabilized', function(arg) {
				console.log(arg);
			});
			
			network.on('doubleClick', function(arg) {
				console.log(arg);
			});
		});
		</script>
EOT;
		return $js;
	}
?>