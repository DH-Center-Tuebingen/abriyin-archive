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
		echo "<style>.mw1000 { max-width: 1000px }</style>";
		echo "<div class='mw1000'>";
		echo "<p class='text-center'><img class='img-rounded img-responsive' src='images/letters.jpg'></img></p>";
		echo "<p class='text-center'><a href='http://escience.uni-tuebingen.de'><img src='images/escience-logo-transparent.svg' width='270'></img></a></p>";
		echo "<p class='text-muted small text-center'>★ <a href='https://github.com/eScienceCenter/abriyin-archive'>Source Code on GitHub</a>. Built with <a href='https://github.com/eScienceCenter/dbWebGen'>dbWebGen</a> ★</p>";
		echo "</div>";
	}

	// ========================================================================================================
	function alhamra_preprocess_html($html) {
	// ========================================================================================================
		// wrap arabic text in enlarged font container in MODE_LIST, MODE_VIEW, MODE_SEARCH
		if(isset($_GET['mode']) && in_array($_GET['mode'], array(MODE_LIST, MODE_VIEW, MODE_GLOBALSEARCH)))
			return preg_replace('/(\p{Arabic}+(\s+\p{Arabic}+)*)/u', '<span dir="rtl" lang="ar">$1</span>', $html);
		return $html;
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

		// create stored queries
		if($_SESSION['user_data']['role'] == 'admin') {
		  	if($_SESSION['user_id'] == 1) {
				$extras_menu['items'][] = array(
					'label' => 'Query the Database',
					'href' => '?' . http_build_query(array('mode' => MODE_QUERY))
				);
				$extras_menu['items'][] = '<li class="divider"></li>';
				$extras_menu['items'][] = array(
					'label' => 'Import Module',
					'href' => '?' . http_build_query(array('mode' => MODE_PLUGIN, PLUGIN_PARAM_FUNC => 'import_render'))
				);
			}
			$extras_menu['items'][] = array(
				'label' => 'Show Imported Persons',
				'href' => '?' . http_build_query(array(
					'mode' => MODE_QUERY,
					PLUGIN_PARAM_NAVBAR => PLUGIN_NAVBAR_ON,
					QUERY_PARAM_VIEW => QUERY_VIEW_RESULT,
					QUERY_PARAM_ID => 'J7Eu3MSIWQWn')
				)
			);
			$extras_menu['items'][] = '<li class="divider"></li>';
		}

		// stored queries item
		$stored_queries_list = array(
			'label' => 'Show Stored Queries',
			'href' => '?' . http_build_query(array('table' => 'stored_queries', 'mode' => MODE_LIST))
		);

		if($_SESSION['user_data']['role'] != 'admin') {
			$TABLES['stored_queries']['actions'] = array(MODE_LINK, MODE_LIST);
			#$TABLES['stored_queries']['fields']['id']['list_hide'] = true;
			$TABLES['stored_queries']['fields']['params_json']['list_hide'] = true;
		}
		else {
			$TABLES['stored_queries']['render_links'][] = array('icon' => 'export', 'field' => 'id', 'href_format' => '?mode=query&navbar=on&view=full&id=%s', 'title' => 'Open this stored query in editor');
		}

		$extras_menu['items'][] = $stored_queries_list;

		$featured_queries = array(
			//'NjRHJyhI6TjO' => 'Network of Persons and Documents',
			//'nADglu04RgpM' => 'Communication Network',
			'araJkEFefCfM' => 'Map of Places',
			'NKJZDUriSEdY' => 'Timeline of Documents',
			'Ge287xnvkgia' => 'Timeline of Daily Edits'
		);

		if(count($featured_queries) > 0) {
			$extras_menu['items'][] = '<li class="divider"></li>';
			$extras_menu['items'][] = '<li class="dropdown-header center">FEATURED VISUALIZATIONS</li>';

			foreach($featured_queries as $q_id => $q_label) {
				$extras_menu['items'][] = array(
					'label' => $q_label,
					'href' => '?' . http_build_query(array(
						'mode' => MODE_QUERY,
						PLUGIN_PARAM_NAVBAR => PLUGIN_NAVBAR_ON,
						QUERY_PARAM_VIEW => QUERY_VIEW_RESULT,
						QUERY_PARAM_ID => $q_id))
				);
			}
		}

		if(count($extras_menu['items']) > 0 && $_SESSION['user_data']['role'] != 'admin') {
			$menu[] = $extras_menu;
			return;
		}

		if($menu[1]['name'] != l10n('menu.browse+edit')) { // just to be sure
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
				$history_table_name = $table_name . '_history';
				$TABLES[$history_table_name] = array_merge($TABLES[$table_name], array());
				$history_table = &$TABLES[$history_table_name];
				$history_table['actions'] = array( MODE_VIEW, MODE_LIST );
				$history_table['global_search'] = array('include_table' => false);
				$history_table['sort'] = array('history_id' => 'desc');

				// turn primary key column (if only one) into a lookup, so to link the original items from their history
				if(isset($history_table['primary_key'])
					&& count($history_table['primary_key']['columns']) == 1
					&& $history_table['fields'][$key_col = $history_table['primary_key']['columns'][0]] != T_LOOKUP)
				{
					$history_table['fields'][$key_col] = array(
						'type' => T_LOOKUP,
						'label' => $history_table['item_name'] . ' ' . $history_table['fields'][$key_col]['label'],
						'lookup' => array(
							'cardinality' => CARDINALITY_SINGLE,
							'table' => $table_name,
							'field' => $key_col,
							'display' => $key_col)
					);
				}

				// now we need to make history_id the primary key to enable MODE_VIEW correctly
				$history_table['primary_key'] = array('auto' => true, 'columns' => array('history_id') );

				$history_table['fields'] = array(
					'history_id' => array( 'label' => 'History ID' , 'type' => T_NUMBER ),
					'edit_timestamp' => array( 'label' => 'Timestamp' , 'type' => T_TEXT_LINE ),
					'edit_action' => array( 'label' => 'Action' , 'type' => T_TEXT_LINE )
				) + $history_table['fields'];

				$history_table['display_name'] .= ' Editing History';
				$history_table['item_name'] .= ' Editing History Entry';
				$history_table['description'] = '';//'History of insert, update and delete actions performed by users. ';
				$history_table['show_in_related'] = false;
				unset($history_table['additional_steps']);

				foreach($history_table['fields'] as $field_name => $field) {
					// m:n associations cannot be reasonably displayed in history table, so remove
					if($field['type'] == T_LOOKUP && $field['lookup']['cardinality'] == CARDINALITY_MULTIPLE) {

						$linkage_table = $field['linkage']['table'];
						$linkage_history = $linkage_table . '_history';

						if(in_array($linkage_table, $CUSTOM_VARIABLES['tables_with_history'])
							&& isset($TABLES[$linkage_history]))
						{
							if(!isset($hidden_assocs[$history_table_name]))
								$hidden_assocs[$history_table_name] = array();

							$hidden_assocs[$history_table_name][$linkage_table] = '<a href="?'. http_build_query(array('table' => $linkage_history , 'mode' => MODE_LIST)) .'">'.
								html($TABLES[$linkage_history]['display_name']) . '</a>';
						}

						unset($history_table['fields'][$field_name]);
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

	// ========================================================================================================
	function /*bool*/ alhamra_querypage_check_permission() {
	// ========================================================================================================
		// arriving here, we know that a user is logged in

		// every logged in user can view a query visualization
		if(QueryPage::is_stored_query())
			return true;

		// only admins can make and share queries
		return $_SESSION['user_data']['role'] === 'admin';
	}
?>
