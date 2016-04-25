<?
	/*
		PLUGIN WITH CUSTOM FUNCTIONS FOR THE AL-HAMRA ABRIYIN ARCHIVE APPLICATION
	*/
	
	// ========================================================================================================
	function alhamra_after_insert_or_update($table_name, $table_info, $primary_key_values) {
	// ========================================================================================================		
	}
	
	// ========================================================================================================
	function alhamra_render_main_page() {
	// ========================================================================================================		
		echo '<p>Choose an action from the top menu.</p>';
		echo "<p><img class='img-rounded img-responsive' src='images/letters.jpg'></img></p>";
		echo "<div style='width:270px' class='center'><a href='http://escience.uni-tuebingen.de'><img src='images/escience-logo-transparent.svg'></img></a></div>";
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
		if($menu[1]['name'] != 'Browse & Edit') { // just to be sure
			echo 'Need to update alhamra_menu_complete()';
			return;
		}
		
		$history_menu = array('name' => 'Editing History', 'items' => array());
		for($i=0; $i<count($menu[1]['items']); $i++) {
			if(substr($menu[1]['items'][$i]['label'], -16) == ' Editing History') {
				$menu[1]['items'][$i]['label'] = substr($menu[1]['items'][$i]['label'], 0, -16);
				$history_menu['items'][]= $menu[1]['items'][$i];
				array_splice($menu[1]['items'], $i, 1);
				$i--;
			}
		}
		
		if(count($history_menu['items']) > 0)
			$menu[]= $history_menu;
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
	function alhamra_permissions() {
	// ========================================================================================================
		global $TABLES;
		global $CUSTOM_VARIABLES;
		
		if(!isset($_SESSION['user_data']) || !isset($_SESSION['user_data']['role']))
			return;
		
		// render a warning that data might be erased at any time >>
		$demo_msg = "<div class='alert alert-info'><b>Important:</b> This is a demo version to play with. Data you enter here may be erased at any time.</div>";
		if(!in_array($demo_msg, $_SESSION['msg']))
			$_SESSION['msg'][] = $demo_msg;
		
		$role = $_SESSION['user_data']['role'];
		
		if($role != 'admin') {
			// non-admins cannot see users
			$TABLES['users']['actions'] = array();
		}
		
		else {
			// Create the history tables by cloning and slighlty adapting the extistings ones
			foreach($CUSTOM_VARIABLES['tables_with_history'] as $table_name) {
				$history_table = $table_name . '_history';
				
				$TABLES[$history_table] = array_merge($TABLES[$table_name], array());
				$TABLES[$history_table]['actions'] = array( MODE_VIEW, MODE_LIST );
				$TABLES[$history_table]['sort'] = 'history_id DESC';
				$TABLES[$history_table]['primary_key'] = array('auto' => true, 'columns' => array('history_id') );
				
				$TABLES[$history_table]['fields'] = array(
					'history_id' => array( 'label' => 'History ID' , 'type' => T_NUMBER ),
					'edit_timestamp' => array( 'label' => 'Timestamp' , 'type' => T_TEXT_LINE ),
					'edit_action' => array( 'label' => 'Action' , 'type' => T_TEXT_LINE )
				) + $TABLES[$history_table]['fields'];
				
				$TABLES[$history_table]['display_name'] .= ' Editing History';
				$TABLES[$history_table]['description'] = 'Editing History of: ' . $TABLES[$history_table]['description'];
				unset($TABLES[$history_table]['additional_steps']);
			}
		}
	}
?>