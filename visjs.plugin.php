<?
	// ========================================================================================================
	// for caching, the plugin calls itself with POST parameters
	if(isset($_POST['visjs_internal_proc'])) {
	// ========================================================================================================
		switch($_POST['method']) {
			//~~~~~~~~~~~~~~~~~~~~~
			case 'poll_node_cache':
			//~~~~~~~~~~~~~~~~~~~~~
				// check whether we need to put node positions cache
				header('Content-Type: text/plain');
				if(!@file_exists($dir . '/' . $network_id . '.node_positions'))
					echo '1'; // need put cache
				else
					echo '0'; // no need to put cache
				exit;
			
			//~~~~~~~~~~~~~~~~~~~~
			case 'put_node_cache':	
			//~~~~~~~~~~~~~~~~~~~~
				// write node positions cache
				header('Content-Type: text/plain');
				$network_id = $_POST['network_id'];
				
				$dir = visjs_settings('cache_dir');
				// check whether node positions already exist
				if(@file_exists($dir . '/' . $network_id . '.node_positions'))
					exit;
				
				// otherwise make file
				$node_pos = '<script>' . $_POST['node_positions'] . '</script>';
				@file_put_contents($dir . '/' . $network_id . '.node_positions', $node_pos);
				echo 'OK';
				exit;
		}
		exit;
	}

	// ========================================================================================================
	// Adjust those settings if you like :-)
	function visjs_settings($item = null) {
	// ========================================================================================================	
		static $settings = array(
			// version (increment with any change that affects caching)
			'plugin_version' => 1,
			
			// where to put cached networks. must not end with slash
			'cache_dir' => 'cache',
			
			// weight of the edge is multiplied with this term
			'edge_width_multiplier' => 1.5,
			
			// some default options that may be used
			'default_options' => array(				
				'layout' => array(
					#'randomSeed' => 2,
					'improvedLayout' => true
				),
				'interaction' => array(
					'dragNodes' => true,
					'hover' => true
				),
				'physics'=> array(
					'solver' => 'forceAtlas2Based',
					'stabilization' => array(
						'iterations' => 300,
						'updateInterval' => 50
					),
					'adaptiveTimestep' => true,
					/*'forceAtlas2Based' => array(
						'springLength' => 100,
						'springConstant' => .08,
						'gravitationalConstant' => -50,
						'centralGravity' => .01
					)/**/
				),							
				'nodes' => array(
					'font' => '16px arial black',
					'icon' => array(
						'size' => 75
					),
					'scaling' => array(
						'label' => array(
							'min' => 12,
							'max' => 20
						)	
					)
				),
				'edges' => array(					
					'smooth' => array('type' => 'dynamic'),
					'color' => '#888888',
					'font' => '11px arial #888888',
					'hoverWidth' => 3,
					'selectionWidth' => 3
				)
			)
		);
		
		return $item !== null ? $settings[$item] : $settings;
	}

	// ========================================================================================================
	function visjs_node_id($table, $id) {
	// ========================================================================================================
		return $table . '#' . $id;
	}
	
	// ========================================================================================================
	function visjs_edge_id($table, $id) {
	// ========================================================================================================
		return $table . '#' . $id;
	}
	
	// ========================================================================================================
	function visjs_node(
		$table, 
		$id_column, /* should be null if MODE_VIEW not possible */
		$id_value, 
		$label) {
	// ========================================================================================================
		$node = array(
			'id' => visjs_node_id($table, $id_value),
			'label' => $label,
			'group' => $table
		);
		
		if($id_column !== null)
			$node['href_view'] = '?' . http_build_query(array(
				'table' => $table,
				'mode' => MODE_VIEW, 
				$id_column => $id_value
			));
		
		return $node;
	}

	// ========================================================================================================
	function visjs_edge(
		$edge_id,
		$from_table, 		
		$from_id, 
		$to_table, 		
		$to_id, 		
		$label,
		$weight,
		$linkage_info, /* array, should be null if MODE_VIEW not possible */		
		$options = array()) {
	// ========================================================================================================
		$edge = array(
				'id' 	=> $edge_id,
				'from' 	=> visjs_node_id($from_table, $from_id),
				'to' 	=> visjs_node_id($to_table, $to_id),
				'width' => intval($weight) * visjs_settings('edge_width_multiplier'),
				'label' => $label				
			);
			
		foreach($options as $o => $v)
			$edge[$o] = $v;
		
		if($linkage_info !== null)
			$edge['href_view'] = '?' . http_build_query(array(
				'table' => $linkage_info['table'],
				'mode' => MODE_VIEW, 
				$linkage_info['fk_self'] => $from_id,
				$linkage_info['fk_other'] => $to_id
			));
		
		return $edge;		
	}
	
	// ========================================================================================================
	// returns null if no cache found, otherwise cached page text	
	function visjs_get_cache($network_id, $time_to_live) {
	// ========================================================================================================
		if($time_to_live === 0 || isset($_GET['nocache']))
			return false;
		
		$dir = visjs_settings('cache_dir');
		
		// read cache
		$t = @filemtime($dir . '/' . $network_id . '.definition');
		
		if($t === false) // probably does not exist yet
			return false;
			
		if(time() - $t > $time_to_live) // cache expired
			return false;
			
		$cache = @file_get_contents($dir . '/' . $network_id . '.definition');
		if($cache === false)
			return false;
		
		// check version
		if(preg_match('/^<!-- (?P<ver>\d+) -->\n/', $cache, $matches) !== 1) 
			return false; // can't find version info
		
		if(!isset($matches['ver']))
			return false;
		
		if(intval($matches['ver']) < visjs_settings('plugin_version'))
			return false; // this code is newer version -> don't return cache
		
		$pos = @file_get_contents($dir . '/' . $network_id . '.node_positions');
		if($pos !== false)
			$cache .= $pos;
		
		return $cache;
	}
	
	// ========================================================================================================
	function visjs_put_cache($network_id, $content) {
	// ========================================================================================================
		$dir = visjs_settings('cache_dir');
		
		if(!@is_dir($dir)) {
			if(!@mkdir($dir, 0777, true)) {
				$error = error_get_last();				
				proc_error($error['message']);
			}
		}
		
		// remove stored node positions (if any)
		@unlink($dir . '/' . $network_id . '.node_positions');
		
		// make version
		$version = "<!-- " . visjs_settings('plugin_version') . " -->\n";
		
		return @file_put_contents($dir . '/' . $network_id . '.definition', $version . $content);
	}
	
	// ========================================================================================================
	function visjs_get_network_from_node_and_edge_lists(
		$network_id, // name used for caching
		$cache_ttl,  // cache time to live in seconds (0 to disable cache)
		$div_id,	 // div where to put the network
		$nodes_view, // columns: id, label, table
		$edges_view, // columns: from_id, to_id, from_table, to_table, weight, label, direction {csv of {from,to,middle})
		$node_icons  // node icon options
	) {
	// ========================================================================================================
		$cache = visjs_get_cache($network_id, $cache_ttl);
		if($cache !== false)
			return $cache;
		
		global $TABLES;
				
		$db = db_connect();
		if($db === false)
			return proc_error('Cannot connect to DB.');
		
		$nodes = array();
		$edges = array();
		$edge_id = 1;
		
		// NODES
		$sql = sprintf('select * from %s', db_esc($nodes_view));
		foreach($db->query($sql) as $row) {
			$nodes[] = json_encode(visjs_node(
				$row['table'], 
				in_array(MODE_VIEW, $TABLES[$row['table']]['actions']) ? get_primary_key_column($row['table']) : null,
				$row['id'],								
				html($row['label'])
			));
		}
		
		// EDGES
		$sql = sprintf('select * from %s', db_esc($edges_view));
		$edge_id = 1;
		foreach($db->query($sql) as $row) {
			$edges[] = json_encode(visjs_edge(
				$edge_id++,
				$row['from_table'], $row['from_id'],
				$row['to_table'], $row['to_id'],
				$row['label'],
				$row['weight'],
				null,
				array('arrows' => $row['direction'])
			));
		}
		
		$nodes = implode(",\n\t", $nodes);
		$edges = implode(",\n\t", $edges);
		
		$groups = array();
		foreach($node_icons as $table_name => $node_options)
			$groups[$table_name] = $node_options;
		
		$options = array_merge(visjs_settings('default_options'), array('groups' => $groups));		
		
		$network_js = visjs_network_get_script($network_id, $div_id, $nodes, $edges, $options);
		visjs_put_cache($network_id, $network_js);
		
		return $network_js;
	}
	
	// ========================================================================================================
	function visjs_get_network_from_settings(
		$network_id, // name used for caching
		$cache_ttl,  // cache time to live in seconds (0 to disable cache)
		$div_id, 
		$network_setup) 
	{
	// ========================================================================================================
		$cache = visjs_get_cache($network_id, $cache_ttl);
		if($cache !== false)
			return $cache;
		
		global $TABLES;
		
		$db = db_connect();
		if($db === false)
			return proc_error('Cannot connect to DB.');
		
		$nodes = array();
		$edges = array();
		$edge_id = 1;
		
		foreach($network_setup['nodes'] as $table_name => $table_setup) {			
			$table = $TABLES[$table_name];
			$q_single_fields = array();
			$q_multi_fields = array();
			$pk_column = $table['primary_key']['columns'][0];
			$query_fields = array();
			$query_fields[] = db_esc($pk_column);
			$query_fields[] = resolve_display_expression($table_setup['display']) . ' __node__label__';
			
			// collect all lookup fields
			if(isset($table_setup['fields'])) {
				foreach($table_setup['fields'] as $field_name => $edge_info) {
					$field = $table['fields'][$field_name];
					
					if($field['lookup']['cardinality'] === CARDINALITY_MULTIPLE) {					
						$q_multi_fields[$field_name] = $edge_info;
					}
					else { // === CARDINALITY_SINGLE
						$q_single_fields[$field_name] = $edge_info;						
						$query_fields[] = db_esc($field_name);						
						// also display value
						$query_fields[] = resolve_display_expression($field['lookup']['display']) . ' ' . db_esc("{$field_name}__display__");
					}
				}
			}
			
			$sql = sprintf('select %s from %s', implode(', ', $query_fields), db_esc($table_name));
				
			foreach($db->query($sql) as $row) {
				$pk_value = $row[$pk_column];
				
				$num_node_edges = 0;
				
				// CARDINALITY_SINGLE edges
				foreach($q_single_fields as $single_field_name => $edge_info) {
					$lookup_info = $table['fields'][$single_field_name]['lookup'];					
					
					$edges[$edge_id] = json_encode(visjs_edge($edge_id,
						$table_name, $pk_value,
						$lookup_info['table'], $row[$single_field_name],
						$table['fields'][$single_field_name]['label'], 1,
						null,
						$edge_info['options']
					));
					
					$edge_id++;					
					$num_node_edges++;
					
					// if linked node doesn't exist yet, create:
					$linked_node_id = visjs_node_id($lookup_info['table'], $row[$single_field_name]);
					if(!isset($nodes[$linked_node_id])) {
						$can_view_node = isset($TABLES[$lookup_info['table']]) 
							&& @in_array(MODE_VIEW, $TABLES[$lookup_info['table']]['actions']);
						
						$nodes[$linked_node_id] = json_encode(visjs_node(
							$lookup_info['table'], 
							$can_view_node ? $lookup_info['field'] : null,
							$row[$single_field_name],
							$row["{$single_field_name}__display__"]
						));
					}
				}
				
				// CARDINALITY_MULTIPLE edges
				foreach($q_multi_fields as $multi_field_name => $edge_info) {
					$linkage_info = $table['fields'][$multi_field_name]['linkage'];
					$lookup_info = $table['fields'][$multi_field_name]['lookup'];
						
					$sql = sprintf('select %s, (select %s from %s where %s = %s) __node__label__ from %s t where %s = ?',
						db_esc($linkage_info['fk_other']),						
						resolve_display_expression($network_setup['nodes'][$lookup_info['table']]['display']),
						db_esc($lookup_info['table']),
						db_esc($lookup_info['field']),		
						db_esc($linkage_info['fk_other'], 't'),						
						db_esc($linkage_info['table']),
						db_esc($linkage_info['fk_self']));
						
					
					$stmt = $db->prepare($sql);
					if($stmt === false) {
						proc_error('Cannot prepare statement: ' . $sql, $db);
						continue;
					}
					
					if($stmt->execute(array($pk_value)) === false) {
						proc_error('Cannot execute statement: ' . $sql, $db);
						continue;
					}
					
					while($row_linked = $stmt->fetch(PDO::FETCH_NUM)) {
						$lookup_table_name = $lookup_info['table'];
						$can_view_linkage = isset($TABLES[$linkage_info['table']])
							&& @in_array(MODE_VIEW, $TABLES[$linkage_info['table']]['actions']);
						
						$edges[$edge_id] = json_encode(visjs_edge($edge_id,
							$table_name,							
							$pk_value, 
							$lookup_table_name, 							
							$row_linked[0],
							$table['fields'][$multi_field_name]['label'], 1, 
							$can_view_linkage ? $linkage_info : null,
							$edge_info['options']
						));
							
						$edge_id++;
						$num_node_edges++;

						// if linked node doesn't exist yet, create:
						$linked_node_id = visjs_node_id($lookup_table_name, $row_linked[0]);
						if(!isset($nodes[$linked_node_id])) {
							$can_view_node = isset($TABLES[$lookup_table_name]) 
								&& @in_array(MODE_VIEW, $TABLES[$lookup_table_name]['actions']);
							
							$nodes[$linked_node_id] = json_encode(visjs_node(
								$lookup_table_name, 
								$can_view_node ? $lookup_info['field'] : null,
								$row_linked[0],
								html($row_linked[1])
							));
						}
					}
				}
				
				if($num_node_edges > 0) {
					// only if outgoing or incoming connections were created, we create the node
					$nodes[visjs_node_id($table_name, $pk_value)] = json_encode(visjs_node(
						$table_name, 
						$pk_column,
						$pk_value,
						html($row['__node__label__'])
					));
				}
			}			
		}
		
		$nodes = implode(",\n\t", $nodes);
		$edges = implode(",\n\t", $edges);
		
		$groups = array();
		foreach($network_setup['nodes'] as $table_name => $table_info)
			$groups[$table_name] = $table_info['shape'];
		
		$options = array_merge(visjs_settings('default_options'), array('groups' => $groups));
		
		$network_js = visjs_network_get_script($network_id, $div_id, $nodes, $edges, $options);
		visjs_put_cache($network_id, $network_js);
		
		return $network_js;
	}
	
	// ========================================================================================================
	function visjs_network_get_script($network_id, $div_id, &$nodes, &$edges, $options) {
	// ========================================================================================================
		global $APP;
		$options = str_replace('\\\\', '\\', json_encode($options));
		
		$def_options = visjs_settings('default_options');
		$iterations = $def_options['physics']['stabilization']['iterations'];
		$self_script = $APP['plugins']['visjs'];
		
		add_javascript('https://cdnjs.cloudflare.com/ajax/libs/vis/4.16.1/vis.min.js');
		add_stylesheet('https://cdnjs.cloudflare.com/ajax/libs/vis/4.16.1/vis.min.css');
		add_stylesheet('https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css');
		
		$js = <<<EOT
		<div id="network-loading-progress" class="progress">
			<div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="$iterations" style="width:0%"></div>
		</div>		
		<script>
		var network;
		var stabilization_cancelled = false;
		
		document.addEventListener('DOMContentLoaded', function() {
			var container = document.getElementById('$div_id');	

			var data = {
				nodes: new vis.DataSet([ $nodes ]),
				edges: new vis.DataSet([ $edges ])
			};
			
			var options = $options;
			
			var progress_bar = null;
			
			if(typeof set_node_positions === 'function') { // we'll come from the cache, yo!
				options.physics = false;
				options.edges['smooth'] = {type: 'continuous'}; // dynamic edges have invisible nodes that spoil the drawing
			}
			else {			
				progress_bar = jQuery('#network-loading-progress')
					.offset(jQuery(container).offset())
					.css('width', jQuery(container).width())
					.toggle();
			}
			
			network = new vis.Network(container, data, options);
			
			network.on('doubleClick', function(arg) {
				var clicked_item = null;
				
				if(arg.nodes.length == 1)
					clicked_item = data.nodes.get(arg.nodes[0]);				
				else if(arg.edges.length == 1)
					clicked_item = data.edges.get(arg.edges[0]);
				
				if(clicked_item !== null && clicked_item.hasOwnProperty('href_view'))
					window.open(clicked_item.href_view).focus();				
			});
			
			network.on('stabilizationProgress', function(arg) {
				setTimeout(function() {
					progress_bar.find('div')						
						.attr('aria-valuenow', arg.iterations)
						.css('width', (100 * arg.iterations / arg.total) + '%');
						//.text(Math.floor(100 * arg.iterations / arg.total) + '%');
				}, 0);
			});
			
			network.once('stabilizationIterationsDone', function() {
				setTimeout(function() {
					progress_bar.find('div')						
						.attr('aria-valuenow', $iterations)
						.css('width', '100%')
						.html('Network is still stabilizing, but ready to explore. <a style="" id="stop_simu" href="javascript:void(0)">Stop stabilization</a>');
						
					$('#stop_simu').on('click', function() {
						network.stopSimulation();
						stabilization_cancelled = true;
					});
				}, 0);
			});
			
			network.on('stabilized', function(arg) {
				progress_bar.hide();
				
				if(!stabilization_cancelled) {
					stabilization_cancelled = true; // we do this only once
					// put node cache
					$.post(
						'$self_script', 
						{
							visjs_internal_proc: 'yeah',
							method: 'poll_node_cache', 
							network_id: '$network_id' 
						},
						function(data) {
							console.log('poll node position cache: ' + data);
							if(data != '1')
								return;
							
							// build cache data
							var pos_js = 'function set_node_positions() { network.setOptions({physics: {enabled:false}}); stabilization_cancelled = true; var node_positions = '; 
							var pos_xy = network.getPositions();
							pos_js += JSON.stringify(pos_xy);
							pos_js += '; for(var node_id in node_positions) { ';
							pos_js += 'if(!node_positions.hasOwnProperty(node_id)) continue; ';
							pos_js += 'network.moveNode(node_id, node_positions[node_id].x, node_positions[node_id].y);'
							pos_js += '} network.fit(); } ';
							
							$.post('$self_script', { 
								visjs_internal_proc: 'yeah',
								method: 'put_node_cache',
								network_id: '$network_id',
								node_positions: pos_js
							},
							function(data) {
								console.log('caching returns: ' + data);
							});
						}
					);
				}
			});
			
			
			if(typeof set_node_positions === 'function')
				set_node_positions();
			
			/*network.on('oncontext', function(arg) {				
				var node_id = network.getNodeAt(arg.pointer.DOM);
				if(node_id !== undefined) {
					arg.event.preventDefault(); // don't show default context menu
					console.log(data.nodes.get(node_id).href_view);
				}
			});*/
		});
		</script>
EOT;
		return $js;
	}
?>