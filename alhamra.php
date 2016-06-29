<?
	/*
		PLUGIN WITH CUSTOM FUNCTIONS FOR THE AL-HAMRA ABRIYIN ARCHIVE APPLICATION
	*/
	
	// ========================================================================================================
	function alhamra_after_insert_or_update($table_name, $table_info, $primary_key_values) {
	// ========================================================================================================		
	}
	
	// ========================================================================================================
	function alhamra_recent_changes_item_view($table_name, $table, $record, $action) {
	// ========================================================================================================
		return "<a href='?table={$record['table_name']}&amp;history_id={$record['history_id']}&amp;mode=".MODE_VIEW."'><span title='View history entry' class='glyphicon glyphicon-zoom-in'></span></a>";
	}
	
	// ========================================================================================================
	function alhamra_render_main_page() {
	// ========================================================================================================		
		echo "<p><img class='img-rounded img-responsive' src='images/letters.jpg'></img></p>";		
		echo "<div style='width:270px' class='center'><a href='http://escience.uni-tuebingen.de'><img src='images/escience-logo-transparent.svg'></img></a></div>";		
	}
	
	// ========================================================================================================
	function alhamra_preprocess_html($html) {
	// ========================================================================================================
		// wrap arabic text in enlarged font container in MODE_LIST and MODE_VIEW
		if(isset($_GET['mode']) && ($_GET['mode'] == MODE_LIST || $_GET['mode'] == MODE_VIEW))
			return preg_replace('/(\p{Arabic}+(\s+\p{Arabic}+)*)/u', '<span lang="ar">$1</span>', $html);
		return $html;
	}
	
	// ========================================================================================================
	function alhamra_network($title, $description, $network_js) {
	// ========================================================================================================
		echo '<h3>' . html($title) . '</h3>';
		if($description != '') 
			echo '<p>' . $description . '</p>';
		echo '<div class="fill-height" id="network"></div>';
		echo $network_js;
		echo '<script>adjust_div_full_height();</script>';
	}
	
	
	// ========================================================================================================
	function alhamra_network_persons_documents() {
	// ========================================================================================================
		global $CUSTOM_VARIABLES;
		
		$network_setup = array(
			'nodes' => array(				
				'persons' => array(				
					'shape' => array('shape' => 'icon', 'icon' => array('face' => 'Ionicons', 'code' => '\uf47e', 'color' => 'darkgreen')),
					'display' => $CUSTOM_VARIABLES['person_name_display'],
					'fields' => array(
						'group_memberships' => array(
							'options' => array('arrows' => '')
						)
					)
				),
				'documents' => array(				
					'shape' => array('shape' => 'icon', 'icon' => array('face' => 'Ionicons', 'code' => '\uf12e', 'color' => 'navy')),
					'display' => 'signature',
					'fields' => array(
						'primary_agents' => array(
							'options' => array('arrows' => 'from')
						),
						'primary_agent_groups' => array(
							'options' => array('arrows' => 'from')
						),
						'recipients' => array(
							'options' => array('arrows' => 'to')
						)
					)
				),
				'person_groups' => array(
					'shape' => array('shape' => 'icon', 'icon' => array('face' => 'Ionicons', 'code' => '\uf47c', 'color' => 'darkred')),
					'display' => 'name_translit',
				)
			)
		);
		
		$network_js = visjs_get_network_from_settings(
			'alhamra_network_persons_documents',
			3600,
			'network', 
			$network_setup);
		
		alhamra_network(
			'Network of Persons, Person Groups and Documents', 
			'Network of primary agents, primary agent groups, and recipients of documents. Note that for performance reasons this network is updated only once per hour.',
			$network_js);
	}
	
	// ========================================================================================================
	function alhamra_network_persons_via_documents() {
	// ========================================================================================================
		global $CUSTOM_VARIABLES;
		
		$node_icons = array(
			'persons' => array('shape' => 'icon', 'icon' => array('face' => 'Ionicons', 'code' => '\uf47e', 'color' => 'darkgreen'))
		);
		
		$network_js = visjs_get_network_from_node_and_edge_lists(
			'alhamra_network_persons_via_documents',
			3600,
			'network', 
			'network_nodes_persons_via_documents', 
			'network_edges_persons_via_documents',
			$node_icons);
		
		alhamra_network(
			'Communication Network', 
			'Network of persons and person groups connected via documents as primary agents, members of primary agent groups, recipients, or related persons.  Note that for performance reasons this network is updated only once per hour.',
			$network_js);
	}
	
	
	// ========================================================================================================
	function alhamra_after_delete($table_name, $table_info, $primary_key_values) {
	// in the _history tables, we overwrite the last editor (only set on insert/update) with the current editor
	// ========================================================================================================
		global $CUSTOM_VARIABLES;
		
		if(!in_array($table_name, $CUSTOM_VARIABLES['tables_with_history']))
			return;		
		
		$where_conds = array();		
		foreach($primary_key_values as $pk => $val)
			$where_conds[] = db_esc($pk) . ' = ?';
		
		$sql = sprintf("UPDATE %s SET edit_user = ? WHERE %s AND edit_action = 'DELETE' AND history_id = (SELECT MAX(history_id) FROM %s WHERE %s)",
			db_esc($table_name . '_history'), implode(' AND ', $where_conds),
			db_esc($table_name . '_history'), implode(' AND ', $where_conds));
			
		$db = db_connect();
		if($db === false)
			return false;
		
		$stmt = $db->prepare($sql);
		if($stmt === false)
			return false;
		
		$params = array_merge(
			array($_SESSION['user_id']),
			array_values($primary_key_values), 
			array_values($primary_key_values));
		
		return $stmt->execute($params);
	}
	
	// ========================================================================================================
	function alhamra_before_insert_or_update($table_name, $table_info, &$columns, &$values) {
	// ========================================================================================================
		// set additional file properties for uploaded scans
		if('scans' == $table_name) {
			if(!isset($table_info['fields']['filename']))
				return proc_error('Config mismatch in alhamra_before_insert_or_update()');
			
			if(isset($_FILES['filename']['size'])) {
				if(!isset($table_info['fields']['filename']))
					return proc_error('Config mismatch in alhamra_before_insert_or_update()');
				
				$columns[] = 'filesize';
				$values[] = $_FILES['filename']['size'];
			}
			
			if(isset($_FILES['filename']['type'])) {
				if(!isset($table_info['fields']['filetype']))
					return proc_error('Config mismatch in alhamra_before_insert_or_update()');
			
				$columns[] = 'filetype';
				$values[] = $_FILES['filename']['type'];
			}
			
			if(isset($_FILES['filename']['path'])) {
				if(!isset($table_info['fields']['filepath']))
					return proc_error('Config mismatch in alhamra_before_insert_or_update()');
			
				$columns[] = 'filepath';
				$values[] = $_FILES['filename']['path'];
			}
		}
		
		// convert arabic to gregorian dates
		$date_conversion = array(
			# THIS NEEDS TO BE UPDATED WHEN TABLES ARE ADDED/REMOVED OR DATE FIELDS CHANGED
			array(
				'table' => 'persons',
				'gregorian' => array(
					'lower' => 'gregorian_birth_year_lower', 
					'upper' => 'gregorian_birth_year_upper'),
				'hijri' => array(
					'year' => 'birth_year',
					'month' => 'birth_month',
					'day' => 'birth_day',
					'from' => 'birth_year_from',
					'to' => 'birth_year_to')
			),
			array(
				'table' => 'persons',
				'gregorian' => array(
					'lower' => 'gregorian_death_year_lower', 
					'upper' => 'gregorian_death_year_upper'),
				'hijri' => array(
					'year' => 'death_year',
					'month' => 'death_month',
					'day' => 'death_day',
					'from' => 'death_year_from',
					'to' => 'death_year_to')
			),
			array(
				'table' => 'documents',
				'gregorian' => array(
					'lower' => 'gregorian_year_lower', 
					'upper' => 'gregorian_year_upper'),
				'hijri' => array(
					'year' => 'date_year',
					'month' => 'date_month',
					'day' => 'date_day',
					'from' => 'date_year_from',
					'to' => 'date_year_to')
			)
		);
		
		foreach($date_conversion as $conv_info) {
			if($conv_info['table'] != $table_name)
				continue;
			
			// first check whether all column names are correct
			foreach(array_merge(array_values($conv_info['gregorian']), 
				array_values($conv_info['hijri'])) as $column_name) {
				if(!isset($table_info['fields'][$column_name]))
					proc_error("Field $column_name does not exist. Check alhamra_before_insert_or_update() function.");
			}
			
			// convert hijri to gregorian
			// if year/month/day are set (year priority), calc lower and upper limits for gergorian year
			// otherwise, if from and/or to are set, calculate lower and/or upper limits for gregorian year
				
			$lower = array(
				'y' => $values[array_search($conv_info['hijri']['year'], $columns)],
				'm' => $values[array_search($conv_info['hijri']['month'], $columns)],
				'd' => $values[array_search($conv_info['hijri']['day'], $columns)]
			);
			
			$upper = array_merge($lower, array());
			
			if($lower['y'] === null) {
				# maybe we find something in _from
				$lower['y'] = $values[array_search($conv_info['hijri']['from'], $columns)];
			}
			
			if($upper['y'] === null) {
				# maybe we find something in _to
				$upper['y'] = $values[array_search($conv_info['hijri']['to'], $columns)];
			}
			
			if($lower['y'] !== null) {
				$lower['y'] = intval($lower['y']);
				
				if($lower['m'] !== null)
					$lower['m'] = intval($lower['m']);				
				else
					$lower['m'] = 1;									
				
				if($lower['d'] !== null)
					$lower['d'] = intval($lower['d']);
				else
					$lower['d'] = 1;
		
				$greg_lower = hijri_to_gregorian($lower['y'], $lower['m'], $lower['d']);
								
				$columns[] = $conv_info['gregorian']['lower']; 
				$values[] = $greg_lower['year'];				
			}
			else {
				$columns[] = $conv_info['gregorian']['lower'];
				$values[] = null;			
			}
			
			if($upper['y'] === null) { // hasn't set from-to fields
				$upper = array_merge($lower, array());
			}
			
			if($upper['y'] !== null) {
				$upper['y'] = intval($upper['y']);
				if($upper['m'] === null)
					$upper['m'] = 12;
				if($upper['d'] === null)
					$upper['d'] = intval(substr(date("Y-m-t", strtotime("{$upper['y']}-{$upper['m']}-01")), -2));
				
				$greg_upper = hijri_to_gregorian($upper['y'], $upper['m'], $upper['d']);
				
				$columns[] = $conv_info['gregorian']['upper'];
				$values[] = $greg_upper['year'];
			}
			else {
				$columns[] = $conv_info['gregorian']['upper'];
				$values[] = null;
			}
		}
	}
	
	// ========================================================================================================
	function alhamra_menu_complete(&$menu) {
	// ========================================================================================================
		global $TABLES;		
		global $CUSTOM_VARIABLES;
		
		$extras_menu = array('name' => 'Extras', 'items' => array());
		$extras_menu['items'][] = array(
			'label' => 'Network of Persons and Documents',
			'href' => '?' . http_build_query(array(
				'mode' => MODE_PLUGIN, 
				PLUGIN_PARAM_NAVBAR => PLUGIN_NAVBAR_ON, 
				PLUGIN_PARAM_FUNC => 'alhamra_network_persons_documents'))
		);
		$extras_menu['items'][] = array(
			'label' => 'Communication Network',
			'href' => '?' . http_build_query(array(
				'mode' => MODE_PLUGIN, 
				PLUGIN_PARAM_NAVBAR => PLUGIN_NAVBAR_ON, 
				PLUGIN_PARAM_FUNC => 'alhamra_network_persons_via_documents'))
		);
		
		if($_SESSION['user_data']['role'] != 'admin') {
			$menu[] = $extras_menu;
			return;
		}
			
		if($menu[1]['name'] != 'Browse & Edit') { // just to be sure
			echo 'Need to update alhamra_menu_complete()';
			return;
		}
		
		$history_menu = array('name' => 'Editing History', 'items' => array());
		
		$history_menu['items'][] = array(); 
		$history_menu['items'][] = array();
		$history_menu['items'][] = '<li class="divider"></li>';
		
		for($i=0; $i<count($menu[1]['items']); $i++) {
			if(substr($menu[1]['items'][$i]['label'], -16) == ' Editing History') {
				$menu[1]['items'][$i]['label'] = substr($menu[1]['items'][$i]['label'], 0, -16);
				$history_menu['items'][]= $menu[1]['items'][$i];
				array_splice($menu[1]['items'], $i, 1);
				$i--;
			}
			/*else if($menu[1]['items'][$i]['label'] == $TABLES['recent_changes_list']['display_name']) {
				$history_menu['items'][0] = first(array_splice($menu[1]['items'], $i, 1));
				$i--;
			}*/
		}
		
		$TABLES['recent_changes_list'] = $CUSTOM_VARIABLES['extra_tables']['recent_changes_list'];
		$history_menu['items'][0] = array('href' => '?table=recent_changes_list&amp;mode=' . MODE_LIST, 'label' => 'All Recent Changes');
		
		$TABLES['view_changes_by_user'] = $CUSTOM_VARIABLES['extra_tables']['view_changes_by_user'];
		$history_menu['items'][1] = array('href' => '?table=view_changes_by_user&amp;mode=' . MODE_LIST, 'label' => 'Changes By User');
		
		if(count($history_menu['items']) > 0)
			$menu[]= $history_menu;
		
		$menu[] = $extras_menu;
	}
	
	// ========================================================================================================
	// We want to log the session logins
	function alhamra_login_success() {
	// ========================================================================================================
		# log the login
		if(!is_logged_in())
			return;
		
		$db = db_connect();
		if($db === false)
			return; // proc_error('Login could not be logged. Do');
			
		$stmt = $db->prepare("INSERT INTO user_sessions (user_id, action) values (? , 'login')");
		if($stmt === FALSE)
			return; // proc_error('alhamra_after_login: prepare');
		
		if(false === $stmt->execute( array( $_SESSION['user_id'] ) ) )
			return; // proc_error('alhamra_after_login: execute');
	}
	
	// ========================================================================================================
	function alhamra_custom_related_list($table_name, /*const*/ &$table, 
		/*const*/ &$pk_vals, /*in,out*/ &$rel_list) {
	// ========================================================================================================
		global $CUSTOM_VARIABLES;
		if(in_array($table_name, $CUSTOM_VARIABLES['tables_with_history'])
			&& count($pk_vals) == 1) // search is currently limited to 1 column
		{
			$rel_list[] = array(
				'table_name' => $table_name . '_history', 
				'table_label' => '',
				'field_name' => first(array_keys($pk_vals)),
				'field_label' => '',
				'display_label' => 'Editing History Of This ' . $table['item_name']);
		}
	}
	
	// ========================================================================================================
	function alhamra_permissions() {
	// ========================================================================================================
		global $TABLES;
		global $CUSTOM_VARIABLES;
		
		// set after_delete hook for all tables with history >>
		foreach($CUSTOM_VARIABLES['tables_with_history'] as $table_name) {			
			if(!isset($TABLES[$table_name]['hooks']))
				$TABLES[$table_name]['hooks'] = array();				
			
			$TABLES[$table_name]['hooks']['after_delete'] = 'alhamra_after_delete';			
		} // <<
		
		if(!isset($_SESSION['user_data']) || !isset($_SESSION['user_data']['role']))
			return;
		
		// render a warning that data might be erased at any time >>
		/*$demo_msg = "<div class='alert alert-info'><b>Important:</b> This is a demo version to play with. Data you enter here may be erased at any time.</div>";
		if(!in_array($demo_msg, $_SESSION['msg']))
			$_SESSION['msg'][] = $demo_msg;*/
		
		$role = $_SESSION['user_data']['role'];
		
		if($role != 'admin') {
			// non-admins cannot see users
			$TABLES['users']['actions'] = array();
		}
		
		else {
			$hidden_assocs = array();
			// Create the history tables by cloning and slighlty adapting the extistings ones
			foreach($CUSTOM_VARIABLES['tables_with_history'] as $table_name) {
				$history_table = $table_name . '_history';
				
				$TABLES[$history_table] = array_merge($TABLES[$table_name], array());				
				$TABLES[$history_table]['actions'] = array( MODE_VIEW, MODE_LIST );
				$TABLES[$history_table]['sort'] = array('history_id' => 'desc');
				
				// turn primary key column (if only one) into a lookup, so to link the original items from their history
				if(isset($TABLES[$history_table]['primary_key'])
					&& count($TABLES[$history_table]['primary_key']['columns']) == 1 
					&& $TABLES[$history_table]['fields'][$key_col = $TABLES[$history_table]['primary_key']['columns'][0]] != T_LOOKUP)
				{
					$TABLES[$history_table]['fields'][$key_col] = array(
						'type' => T_LOOKUP,
						'label' => $TABLES[$history_table]['item_name'] . ' ' . $TABLES[$history_table]['fields'][$key_col]['label'],
						'lookup' => array(
							'cardinality' => CARDINALITY_SINGLE, 
							'table' => $table_name, 
							'field' => $key_col, 
							'display' => $key_col)
					);
				}
				
				// now we need to make history_id the primary key to enable MODE_VIEW correctly
				$TABLES[$history_table]['primary_key'] = array('auto' => true, 'columns' => array('history_id') );
				
				$TABLES[$history_table]['fields'] = array(
					'history_id' => array( 'label' => 'History ID' , 'type' => T_NUMBER ),
					'edit_timestamp' => array( 'label' => 'Timestamp' , 'type' => T_TEXT_LINE ),
					'edit_action' => array( 'label' => 'Action' , 'type' => T_TEXT_LINE )
				) + $TABLES[$history_table]['fields'];
				
				$TABLES[$history_table]['display_name'] .= ' Editing History';
				$TABLES[$history_table]['item_name'] .= ' Editing History Entry';
				$TABLES[$history_table]['description'] = '';//'History of insert, update and delete actions performed by users. '; 
				$TABLES[$history_table]['show_in_related'] = false;
				unset($TABLES[$history_table]['additional_steps']);
				
				foreach($TABLES[$history_table]['fields'] as $field_name => $field) {					
					// m:n associations cannot be reasonably displayed in history table, so remove
					if($field['type'] == T_LOOKUP && $field['lookup']['cardinality'] == CARDINALITY_MULTIPLE) {
						
						$linkage_table = $field['linkage']['table'];
						$linkage_history = $linkage_table . '_history';
						
						if(in_array($linkage_table, $CUSTOM_VARIABLES['tables_with_history']) 
							&& isset($TABLES[$linkage_history])) 
						{
							if(!isset($hidden_assocs[$history_table]))
								$hidden_assocs[$history_table] = array();
							
							$hidden_assocs[$history_table][$linkage_table] = '<a href="?'. http_build_query(array('table' => $linkage_history , 'mode' => MODE_LIST)) .'">'. 
								html($TABLES[$linkage_history]['display_name']) . '</a>';
						}
						
						unset($TABLES[$history_table]['fields'][$field_name]);
					}
				}
			}
			
			foreach($hidden_assocs as $assoc_table => $assoc_linkages) {
				if(count($assoc_linkages) == 0)
					continue;
				
				$desc = '<p>Note that associations with other records stored in dedicated association tables cannot be displayed within this table. To view these histories visit any of the following associated history tables: ';
				$desc .= implode(', ', array_values($assoc_linkages));				
				$desc .= '</p>';
				$TABLES[$assoc_table]['description'] .= $desc;
			}
		}
	}
?>