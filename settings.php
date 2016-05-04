<?	
	/* ========================================================================================================	*/
	$CUSTOM_VARIABLES = array(
		'islam_months' => array(
			1 => '1. Muḥarram', 
			2 => '2. Ṣafar', 
			3 => '3. Rabīʿ I', 
			4 => '4. Rabīʿ II', 
			5 => '5. Ǧumādā I', 
			6 => '6. Ǧumādā II', 
			7 => '7. Raǧab' , 
			8 => '8. Šaʿbān', 
			9 => '9. Ramaḍān', 
			10 => '10. Šawwāl', 
			11 => '11. Ḏū ‘l-qaʿda', 
			12 => '12. Ḏū ‘l-ḥiǧǧa'
		),
		
		'person_name_display' => array(
			'columns' => array('lastname_translit', 'forename_translit', 'byname_translit'), 
			'expression' => "concat_ws(', ', %1, %2, %3)"
		),
		
		'all_actions' => array(MODE_EDIT, MODE_NEW, MODE_VIEW, MODE_LIST, MODE_DELETE),
		
		'history' => array(
			'edit_note' => array('label' => 'Editing Note', 'type' => T_TEXT_AREA, 'reset' => false /* not sure */ ),				
			'edit_status' => array('label' => 'Editing Status', 'type' => T_ENUM, 'required' => true, 'default' => 'editing',
				'values' => array('editing' => 'Editing', 'editing_finished' => 'Editing finished', 'unclear' => 'Unclear', 'approved' => 'Approved')),	
			'edit_user' => array('label' => 'Last Editor', 'type' => T_LOOKUP, 'editable' => false, 'default' => '%SESSION_USER%', 'lookup' => array('cardinality' => CARDINALITY_SINGLE, 'table' => 'users', 'field' => 'id', 'display' => 'name'))
		),
		
		// note: for all these tables, an after_insert hook will be automatically added in alhamra_menu_complete
		'tables_with_history' => array(
			'bibliographic_references',
			'countries_and_regions',
			'document_addresses',
			'document_persons',
			'document_to_document_references',
			'documents',
			'keywords',			
			'person_groups',
			'person_places',
			'person_relatives',
			'persons',
			'places',
			'scans',
			'sources',
			'users',

			'document_scans',
			'document_keywords',
			'document_places',
			'document_authors',
			'person_of_group',
			'person_group_places'
		),
		
		'arabic_dates' => 'These are Islamic dates. If available, fill in the <i>Year</i>, <i>Month</i>, and <i>Day</i> fields. If none of those are available, try to provide a date range by filling in the <i>Year From</i> and/or the <i>Year To</i> fields.',
		
		'arabic_dates_fromto' => 'These are Islamic dates. If available provide a date range by filling in the either or both of the <i>From Year</i> and the <i>To Year</i> fields.'
	);	
	
	/* ========================================================================================================	*/
	$APP = array(		
		'plugins' => array('alhamra.php', 'calendar.php'),
		'title' => 'ʿAbrīyīn Archive',
		'view_display_null_fields' => false,
		'page_size'	=> 10,
		'max_text_len' => 250,
		'pages_prevnext' => 2,
		'mainmenu_tables_autosort' => true,
		'search_lookup_resolve' => true,
		'search_string_transformation' => 'dmg_plain(%s)',
		'null_label' => "<span class='nowrap' title='If you check this box, no value will be stored for this field. This may reflect missing, unknown, unspecified or inapplicable information. Note that no value (missing information) is different to providing an empty value: an empty value is a value.'>No Value</span>",
		'menu_complete_proc' => 'alhamra_menu_complete',
		'render_main_page_proc' => 'alhamra_render_main_page'		
	);
	
	/* ========================================================================================================	*/
	require_once 'db/db.php';
	
	/* ========================================================================================================	*/
	$LOGIN = array(
		'users_table' => 'users',
		'primary_key' => 'id',
		'username_field' => 'email',
		'password_field' => 'password',
		'name_field' => 'name',
		'password_hash_func' => 'md5',
		'form' => array('username' => 'Email', 'password' => 'Password'),
		'initializer_proc' => 'alhamra_permissions',
		'login_success_proc' => 'alhamra_login_success'		
	);
	
	/* ========================================================================================================	*/
	$TABLES = array(	
		// ----------------------------------------------------------------------------------------------------
		'users' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Users',
			'description' => 'Users of this application.',
			'item_name' => 'User',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),		
			'sort' => array('name' => 'asc'),
			'fields' => array(
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),
				'name' => array('label' => 'Name', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => true),
				'role' => array('label' => 'Role', 'type' => T_ENUM, 'required' => true, 'default' => 'user', 'values' => array('user' => 'Normal User', 'admin' => 'Admin')),
				'email' => array('label' => 'Email', 'type' => T_TEXT_LINE, 'len' => 100, 'required' => true),
				'password' => array('label' => 'Password', 'type' => T_PASSWORD, 'len' => 32, 'required' => true, 'min_len' => 3),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'scans' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Scans',
			'description' => 'Photo or scan of a document. Maximum file size is 10 MB.',
			'item_name' => 'Scan',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),
			'sort' => array('filename' => 'asc'),
			'hooks' => array(
				'before_insert' => 'alhamra_before_insert_or_update',
				'before_update' => 'alhamra_before_insert_or_update'),
			'fields' => array(
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),				
				'filename' => array('label' => 'File', 'type' => T_UPLOAD, 'required' => true, 'max_size' => 10485760, 'location' => 'scans', 'store' => STORE_FOLDER /*could be extended to STORE_DB to store the file binary in database*/, 'allowed_ext' => array('jpg', 'jpeg'), 'help' => 'Upload <i>.jpg</i> or <i>.jpeg</i> images only. The filename must be unique and reflect the signature of the document.'),
				'filepath' => array('label' => 'Path', 'type' => T_TEXT_LINE, 'len' => 1000, 'editable' => false),
				'filesize' => array('label' => 'Size', 'type' => T_NUMBER, 'editable' => false),
				'filetype' => array('label' => 'Type', 'type' => T_TEXT_LINE, 'len' => 100, 'editable' => false),				
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)			
		),
		
		// ----------------------------------------------------------------------------------------------------
		'documents' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Documents',
			'description' => 'Documents and letters of the archive.',
			'item_name' => 'Document',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),		
			'sort' => array('signatory' => 'asc'),			
			'hooks' => array(
				'before_insert' => 'alhamra_before_insert_or_update',
				'before_update' => 'alhamra_before_insert_or_update'),
			'fields' => array(
				'signatory' => array('label' => 'Signature', 'type' => T_TEXT_LINE, 'len' => 7, 'required' => true,  
					'help' =>	'<p>The signature consists of a maximum of 7 characters, including a hyphen. '.
								'There are max. 4 signs <b>before</b> the hyphen, thereof when indicated one letter, '.
								'e.g., <code>A1</code>, <code>B13</code>, <code>D148</code>. '.
								'<b>After</b> the hyphen there can be max. 2 characters; these indicate the number of picture on the film.</p>'.
								'<p>Examples:<ul style="padding-left:1em">'.
								'<li><code>34-16</code>: film 34, picture 16</li>'.
								'<li><code>A1-13</code>: film A1, picture 13</li>'.
								'<li><code>B12-23</code>: film B12, picture 23</li>'.
								'<li><code>D120-27</code>: film D120, picture 27</li>'.
								'<li><code>E18-3</code>: film E18, picture 3</li>'.
								'</ul></p>'.
								'<p><b>Attention!</b> The number figuring between the film number and the number of the pictures indicates the '.
								'"<b>pack</b>" to which the document belongs. This concerns only some of the documents.</p>'),				
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),				
				'scans' => array('label' => 'Scan Files', 'required' => true, 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'scans',
						'field' => 'id',
						'display' => 'filename'),
					'linkage' => array(
						'table' => 'document_scans',
						'fk_self' => 'document',
						'fk_other' => 'scan',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'type' => array('label' => 'Type', 'type' => T_ENUM, 'required' => true, 'default' => 'letter', 'values' => array('letter' => 'Letter', 'other' => 'Other')),				
				'physical_location' => array('label' => 'Physical Location', 'type' => T_LOOKUP, 'required' => true, 
					'lookup' => array(
						'cardinality' => CARDINALITY_SINGLE,
						'table'  => 'places',
						'field'  => 'id',
						'display' => 'name_translit',
						'default' => 30) 
				),				
				'authors' => array('label' => 'Senders', 'required' => true, 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'persons',
						'field' => 'id',
						'display' => $CUSTOM_VARIABLES['person_name_display']),
					'linkage' => array(
						'table' => 'document_authors',
						'fk_self' => 'document',
						'fk_other' => 'person',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'document_addresses' => array('label' => 'Addressees', 'type' => T_LOOKUP, 
					'help' => 'More details about addressees (their place and whether it is a forwarding address) can be specified as a next step after the document is created.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'persons',
						'field' => 'id',
						'display' => $CUSTOM_VARIABLES['person_name_display']),
					'linkage' => array(
						'table' => 'document_addresses',
						'fk_self' => 'document',
						'fk_other' => 'person',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'date_year' => array('label' => 'Date: Year', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'date_month' => array('label' => 'Date: Month', 'type' => T_ENUM, 'values' => $CUSTOM_VARIABLES['islam_months'], 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'date_day' => array('label' => 'Date: Day', 'type' => T_NUMBER , 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'date_year_from' => array('label' => 'Date: Year From', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'date_year_to' => array('label' => 'Date: Year To', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),				
				'gregorian_year_lower' => array('label' => 'Gregorian Year (Lower)', 'type' => T_NUMBER, 'editable' => false),
				'gregorian_year_upper' => array('label' => 'Gregorian Year (Upper)', 'type' => T_NUMBER, 'editable' => false),								
				'pack_nr' => array('label' => 'Pack Number', 'type' => T_NUMBER, 'help' => 'Bündelnummer'),				
				'places' => array('label' => 'Other Places', 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'places',
						'field' => 'id',
						'display' => 'name_translit'),
					'linkage' => array(
						'table' => 'document_places',
						'fk_self' => 'document',
						'fk_other' => 'place',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'document_persons' => array('label' => 'Related Persons', 'type' => T_LOOKUP, 
					'help' => 'Details about the role of the related persons (whether he was a scribe, attestor or other) can be specified as a next step after the document is created.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'persons',
						'field' => 'id',
						'display' => $CUSTOM_VARIABLES['person_name_display']),
					'linkage' => array(
						'table' => 'document_persons',
						'fk_self' => 'document',
						'fk_other' => 'person',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'document_to_document_references' => array('label' => 'Referenced Documents', 'type' => T_LOOKUP, 
					'help' => 'Comments about the document references can be specified as a next step after the document is created.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'documents',
						'field' => 'id',
						'display' => 'signatory'),
					'linkage' => array(
						'table' => 'document_to_document_references',
						'fk_self' => 'source_doc',
						'fk_other' => 'target_doc',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'keywords' => array('label' => 'Keywords', 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'keywords',
						'field' => 'id',
						'display' => 'keyword'),
					'linkage' => array(
						'table' => 'document_keywords',
						'fk_self' => 'document',
						'fk_other' => 'keyword',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'bibliographic_references' => array('label' => 'References', 'type' => T_LOOKUP, 
					'help' => 'Details about the bibliographic references (i.e., volume and page in the source) can be added later.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'sources',
						'field' => 'id',
						'display' => 'short_title'),
					'linkage' => array(
						'table' => 'bibliographic_references',
						'fk_self' => 'object',
						'fk_other' => 'source',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'content' => array('label' => 'Content (XML)', 'type' => T_TEXT_AREA),
				'abstract' => array('label' => 'Abstract', 'type' => T_TEXT_AREA),
				'edit_note' => $CUSTOM_VARIABLES['history']['edit_note'],		
				'edit_status' => $CUSTOM_VARIABLES['history']['edit_status'],
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			),
			'additional_steps' => array(
				'document_persons' => array('label' => 'Person in Document', 'foreign_key' => 'document'),
				'document_to_document_references' => array('label' => 'Reference to Other Document', 'foreign_key' => 'source_doc'),
				'document_addresses' => array('label' => 'Destination Addresses', 'foreign_key' => 'document'),
				'bibliographic_references' => array('label' => 'Bibliographic Reference', 'foreign_key' => 'object')
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'document_addresses' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Document Addresses',
			'description' => 'Destination addresses of documents.',
			'item_name' => 'Document Address',			
			'primary_key' => array('auto' => false, 'columns' => array('document', 'person')),
			'sort' => array('document' => 'asc', 'person' => 'asc'),
			'fields' => array(				
				'document' => array('label' => 'Document', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'documents',
					'field'   => 'id',
					'display' => 'signatory')
				),				
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'] )
				),				
				'place' => array('label' => 'Address/Place', 'type' => T_LOOKUP, 'required' => false, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'places',
					'field'   => 'id',
					'display' => 'name_translit')
				),				
				'has_forwarded' => array('label' => 'Forwarding Address?', 'type' => T_ENUM, 'required' => true, 'default' => '0', 'values' => array('1' => 'Yes', '0' => 'No')),				
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
				
		// ----------------------------------------------------------------------------------------------------
		'persons' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Persons',
			'description' => 'Any persons occurring in the archive linked to documents, places, families and other person groups',
			'item_name' => 'Person',			
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),
			'sort' => array('lastname_translit' => 'asc', 'forename_translit' => 'asc'),
			'hooks' => array(
				'before_insert' => 'alhamra_before_insert_or_update',
				'before_update' => 'alhamra_before_insert_or_update'),
			'fields' => array(
				'lastname_translit' => array('label' => 'Family name (translit.)', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => false,
					'help' => 'If the family name is missing, unknown or not legible, leave this field empty and put the reason in the field <i>Information</i> at the bottom of the form.'),
				'forename_translit' => array('label' => 'First name (translit.)', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => true),
				'byname_translit' => array('label' => 'Byname (translit.)', 'type' => T_TEXT_LINE, 'len' => 50),				
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),				
				'sex' => array('label' => 'Gender', 'type' => T_ENUM, 'required' => true, 'default' => 'm', 'values' => array('m' => 'Male', 'f' => 'Female')),				
				'lastname_arabic' => array('label' => 'Family name (Arabic)', 'type' => T_TEXT_LINE, 'len' => 50),
				'forename_arabic' => array('label' => 'First name (Arabic)', 'type' => T_TEXT_LINE, 'len' => 50),
				'byname_arabic' => array('label' => 'Byname (Arabic)', 'type' => T_TEXT_LINE, 'len' => 50 ),
				'title' => array('label' => 'Title', 'type' => T_ENUM, 'values' => array('imām' => 'imām', 'sayyid' => 'sayyid', 'šayḫ' => 'šayḫ', 'wālī' => 'wālī')),				
				'person_of_group' => array('label' => 'Person\'s Groups', 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'person_groups',
						'field' => 'id',
						'display' => 'name_translit'),
					'linkage' => array(
						'table' => 'person_of_group',
						'fk_self' => 'person',
						'fk_other' => 'person_group',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'birth_year' => array('label' => 'Birth Date: Year', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'birth_month' => array('label' => 'Birth Date: Month', 'type' => T_ENUM, 'values' => $CUSTOM_VARIABLES['islam_months'], 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'birth_day' => array('label' => 'Birth Date: Day', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'birth_year_from' => array('label' => 'Birth Date: Year From', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates'], 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'birth_year_to' => array('label' => 'Birth Date: Year To', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),				
				'gregorian_birth_year_lower' => array('label' => 'Gregorian Birth Year (Lower)', 'type' => T_NUMBER, 'editable' => false),
				'gregorian_birth_year_upper' => array('label' => 'Gregorian Birth Year (Upper)', 'type' => T_NUMBER, 'editable' => false),
				'death_year' => array('label' => 'Death Date: Year', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'death_month' => array('label' => 'Death Date: Month', 'type' => T_ENUM, 'values' => $CUSTOM_VARIABLES['islam_months'], 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'death_day' => array('label' => 'Death Date: Day', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'death_year_from' => array('label' => 'Death Date: Year From', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'death_year_to' => array('label' => 'Death Date: Year To', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates']),
				'gregorian_death_year_lower' => array('label' => 'Gregorian Death Year (Lower)', 'type' => T_NUMBER, 'editable' => false),
				'gregorian_death_year_upper' => array('label' => 'Gregorian Death Year (Upper)', 'type' => T_NUMBER, 'editable' => false),
				'person_places' => array('label' => 'Places/Locations', 'required' => false, 'type' => T_LOOKUP, 
					'help' => 'More details about the place associations can be added after the person is stored in the database.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'places',
						'field' => 'id',
						'display' => 'name_translit'),
					'linkage' => array(
						'table' => 'person_places',
						'fk_self' => 'person',
						'fk_other' => 'place',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),
				'bibliographic_references' => array('label' => 'References', 'type' => T_LOOKUP, 
					'help' => 'Details about the bibliographic references (i.e., volume and page in the source) can be added later.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'sources',
						'field' => 'id',
						'display' => 'short_title'),
					'linkage' => array(
						'table' => 'bibliographic_references',
						'fk_self' => 'object',
						'fk_other' => 'source',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA),				
				'edit_note' => $CUSTOM_VARIABLES['history']['edit_note'],		
				'edit_status' => $CUSTOM_VARIABLES['history']['edit_status'],
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			),			
			'additional_steps' => array(
				'person_places' => array('label' => 'Places', 'foreign_key' => 'person'),
				'person_relatives' => array('label' => 'Relatives', 'foreign_key' => 'person'),	
				'document_persons' => array('label' => 'Mentioned in a Document', 'foreign_key' => 'person'),
				'document_addresses' => array('label' => 'Addressee of a Document', 'foreign_key' => 'person'),
				'bibliographic_references' => array('label' => 'Bibliographic Reference', 'foreign_key' => 'object')
			)			
		),
		
		// ----------------------------------------------------------------------------------------------------
		'person_places' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Places of Persons',
			'description' => 'Places of persons.',
			'item_name' => 'Place of Person',
			'primary_key' => array('auto' => false, 'columns' => array('person', 'place')),
			'sort' => array('person' => 'asc', 'place' => 'asc'),
			'fields' => array(				
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'] )
				),				
				'place' => array('label' => 'Place', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'places',
					'field'   => 'id',
					'display' => 'name_translit')
				),
				'from_year' => array('label' => 'From Year', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates_fromto']),
				'to_year' => array('label' => 'To Year', 'type' => T_NUMBER, 'help' => $CUSTOM_VARIABLES['arabic_dates_fromto']),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'person_relatives' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Relatives of Persons',
			'description' => 'Relatives of persons.',
			'item_name' => 'Relative of Person',
			'primary_key' => array('auto' => false, 'columns' => array('person', 'relative')),
			'sort' => array('person' => 'asc', 'relative' => 'asc'),
			'fields' => array(		
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'] )
				),				
				'relative' => array('label' => 'Relative', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'] )
				),
				'type' => array('label' => 'Kinship', 'type' => T_ENUM, 'required' => true, 'default' => 'unknown', 'values' => array('mother' => 'Mother', 'father' => 'Father', 'child' => 'Child', 'sibling' => 'Sibling', 'other' => 'Other', 'unknown' => 'Unknown'), 'help' => 'Select the role of the <i>Person</i> in the kinship with the <i>Relative</i>'),				
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA),				
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'document_persons' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Persons in Documents',
			'description' => 'Persons related to the documents in the archive.',
			'item_name' => 'Person in Document',
			'primary_key' => array('auto' => false, 'columns' => array('document', 'person')),
			'sort' => array('document' => 'asc', 'person' => 'asc'),
			'fields' => array(				
				'document' => array('label' => 'Document', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'documents',
					'field'   => 'id',
					'display' => 'signatory')
				),				
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'] )
				),				
				'type' => array('label' => 'Type', 'type' => T_ENUM, 'required' => true, 'default' => 'other', 'values' => array('attestor' => 'Attestor', 'scribe' => 'Scribe', 'other' => 'Other')),				
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'document_to_document_references' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Document-to-Document References',
			'description' => 'References from documents to other documents.',
			'item_name' => 'Document-to-Document Reference',
			'primary_key' => array('auto' => false, 'columns' => array('source_doc', 'target_doc')),
			'sort' => array('source_doc' => 'asc', 'target_doc' => 'asc'),
			'fields' => array(				
				'source_doc' => array('label' => 'Referencing Document', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'documents',
					'field'   => 'id',
					'display' => 'signatory')
				),				
				'target_doc' => array('label' => 'Referenced Document (Target)', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'documents',
					'field'   => 'id',
					'display' => 'signatory')
				),				
				'comment' => array('label' => 'Comment', 'type' => T_TEXT_AREA),				
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'places' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Places',
			'description' => 'Places within countries and regions.',
			'item_name' => 'Place',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),		
			'sort' => array('name_translit' => 'asc'),
			'fields' => array(
				'name_translit' => array('label' => 'Transliterated Name', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => true),				
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),				
				'type' => array('label' => 'Type', 'type' => T_ENUM, 'required' => true, 'values' => array('settlement' => 'Settlement', 'other' => 'Other'), 'default' => 'settlement'),				
				'name_arabic' => array('label' => 'Arabic Name', 'type' => T_TEXT_LINE, 'len' => 50),				
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA),				
				'coordinates' => array('label' => 'Coordinates', 'type' => T_POSTGIS_GEOM, 'SRID' => '4326', 'help' => 'Enter text representation of geometry, e.g. POINT(-71.06 32.4485). See <a href="http://postgis.net/docs/ST_GeomFromText.html" target="_blank">here</a> for help.'),				
				'country_region' => array('label' => 'Country/Region', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'countries_and_regions',
					'field'   => 'id',
					'display' => 'name',
					'default' => 7)
				),				
				'bibliographic_references' => array('label' => 'References', 'type' => T_LOOKUP, 
					'help' => 'Details about the bibliographic references (i.e., volume and page in the source) can be added later.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'sources',
						'field' => 'id',
						'display' => 'short_title'),
					'linkage' => array(
						'table' => 'bibliographic_references',
						'fk_self' => 'object',
						'fk_other' => 'source',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'edit_note' => $CUSTOM_VARIABLES['history']['edit_note'],		
				'edit_status' => $CUSTOM_VARIABLES['history']['edit_status'],
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			),
			'additional_steps' => array(
				'person_places' => array('label' => 'Persons Associated With This Place', 'foreign_key' => 'place'),
				'document_addresses' => array('label' => 'Document Addressed To This Place', 'foreign_key' => 'place'),
				'bibliographic_references' => array('label' => 'Bibliographic Reference', 'foreign_key' => 'object')
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'person_groups' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Person Groups',
			'description' => 'Tribes, tribal units and other person groups.',
			'item_name' => 'Person Group',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),
			'sort' => array('name_translit' => 'asc'),			
			'fields' => array(
				'name_translit' => array('label' => 'Name (transliterated)', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => true),				
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),				
				'type' => array('label' => 'Type', 'type' => T_ENUM, 'required' => true, 'default' => 'other', 'values' => array('tribe' => 'Tribe', 'tribal_unit' => 'Tribal unit', 'other' => 'Other')),				
				'name_arabic' => array('label' => 'Name (Arabic)', 'type' => T_TEXT_LINE, 'len' => 50),				
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA),		
				'places' => array('label' => 'Place(s)', 'type' => T_LOOKUP,
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'places',
						'field' => 'id',
						'display' => 'name_translit'),
					'linkage' => array(
						'table' => 'person_group_places',
						'fk_self' => 'person_group',
						'fk_other' => 'place',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'person_of_group' => array('label' => 'Group\'s Persons', 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'persons',
						'field' => 'id',
						'display' => $CUSTOM_VARIABLES['person_name_display']),
					'linkage' => array(
						'table' => 'person_of_group',
						'fk_self' => 'person_group',
						'fk_other' => 'person',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'bibliographic_references' => array('label' => 'References', 'type' => T_LOOKUP,					
					'help' => 'Details about the bibliographic references (i.e., volume and page in the source) can be added later.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'sources',
						'field' => 'id',
						'display' => 'short_title'),
					'linkage' => array(
						'table' => 'bibliographic_references',
						'fk_self' => 'object',
						'fk_other' => 'source',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'edit_note' => $CUSTOM_VARIABLES['history']['edit_note'],		
				'edit_status' => $CUSTOM_VARIABLES['history']['edit_status'],
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			),
			'additional_steps' => array(
				'bibliographic_references' => array('label' => 'Bibliographic Reference', 'foreign_key' => 'object')
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'countries_and_regions' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Countries/Regions',
			'description' => 'Countries and regions.',
			'item_name' => 'Country/Region',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'), 
			'sort' => array('name' => 'asc'),
			'fields' => array(
				'name' => array('label' => 'Name', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => true),				
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			),
			'additional_steps' => array(
				'places' => array('label' => 'Place In This Region', 'foreign_key' => 'country_region'),				
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'keywords' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Keywords',
			'description' => 'Keywords that can be assigned to documents.',
			'item_name' => 'Keyword',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'), 
			'sort' => array('keyword' => 'asc'),
			'fields' => array(		
				'keyword' => array('label' => 'Keyword', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => true),				
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'sources' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Sources',
			'description' => 'Sources of reference for data in the archive.',
			'item_name' => 'Source',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'), 
			'sort' => array('id' => 'asc'),
			'fields' => array(		
				'short_title' => array('label' => 'Short Title', 'type' => T_TEXT_LINE, 'len' => 100, 'required' => true),
				'full_title' => array('label' => 'Full Title', 'type' => T_TEXT_AREA, 'len' => 1000, 'required' => true),
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'bibliographic_references' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Bibliographic References',
			'description' => 'Citations from places, persons, person groups and documents to sources.',
			'item_name' => 'Bibliographic Reference',
			'primary_key' => array('auto' => false, 'columns' => array('object', 'source')), 
			'sort' => array('object' => 'asc', 'source' => 'asc'),
			'fields' => array(		
				'object' => array('label' => 'Object', 'type' => T_LOOKUP, 'required' => true, 'allow-create' => false,
					'lookup' => array(
						'cardinality' => CARDINALITY_SINGLE,
						'table'   => 'citing_objects',
						'field'   => 'id',
						'display' => 'name'
					)
				),
				'source' => array('label' => 'Source', 'type' => T_LOOKUP, 'required' => true,
					'lookup' => array(
						'cardinality' => CARDINALITY_SINGLE,
						'table'   => 'sources',
						'field'   => 'id',
						'display' => 'short_title'
					)
				),
				'volume' => array('label' => 'Volume', 'type' => T_TEXT_LINE, 'len' => 10),
				'page' => array('label' => 'Page', 'type' => T_TEXT_LINE, 'len' => 10),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),

		// ----------------------------------------------------------------------------------------------------
		'recent_changes_list' => array(
			'actions' => array(MODE_LIST),
			'display_name' => 'All Recent Changes',
			'description' => 'Recent changes in the database.',
			'item_name' => 'Change',
			'primary_key' => array('auto' => false, 'columns' => array('table_name', 'history_id')), 
			'sort' => array('timestamp' => 'desc'),
			'fields' => array(		
				'timestamp' => array('label' => 'Timestamp', 'type' => T_TEXT_LINE),				
				'user_id' => array('label' => 'Editor', 'type' => T_LOOKUP, 'lookup' => array('cardinality' => CARDINALITY_SINGLE, 'table' => 'users', 'field' => 'id', 'display' => 'name')),								
				'action' => array('label' => 'Timestamp', 'type' => T_TEXT_LINE),
				'table_name' => array('label' => 'History Table', 'type' => T_TEXT_LINE),
				'history_id' => array('label' => 'History ID', 'type' => T_NUMBER)
			)
		),

		// ----------------------------------------------------------------------------------------------------
		// n:m tables that will only be visible in history mode
		// ----------------------------------------------------------------------------------------------------
		'document_scans' => array(
			'actions' => array(),
			'display_name' => 'Document-Scan Assignments',
			'description' => 'Document-Scan assignments',
			'item_name' => 'Document-Scan Assignment',			
			'fields' => array(
				'document' => array('label' => 'Document', 'type' => T_TEXT_LINE),
				'scan' => array('label' => 'Document', 'type' => T_TEXT_LINE),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),		
		'document_keywords' => array(
			'actions' => array(),
			'display_name' => 'Document-Keyword Assignments',
			'description' => 'Document-keyword assignments.',
			'item_name' => 'Document-Keyword Assignment',			
			'fields' => array(
				'document' => array('label' => 'Document', 'type' => T_TEXT_LINE),
				'keyword' => array('label' => 'Keyword', 'type' => T_TEXT_LINE),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		'document_places' => array(
			'actions' => array(),
			'display_name' => 'Document-Place Assignments',
			'description' => 'Document-place assignments.',
			'item_name' => 'Document-Place Assignment',		
			'fields' => array(
				'document' => array('label' => 'Document', 'type' => T_TEXT_LINE),
				'place' => array('label' => 'Place', 'type' => T_TEXT_LINE),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),		
		'document_authors' => array(
			'actions' => array(),
			'display_name' => 'Document-Author Assignments',
			'description' => 'Document-author assignments.',
			'item_name' => 'Document-Author Assignment',		
			'fields' => array(
				'document' => array('label' => 'Document', 'type' => T_TEXT_LINE),
				'person' => array('label' => 'Person', 'type' => T_TEXT_LINE),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),		
		'person_of_group' => array(
			'actions' => array(),
			'display_name' => 'Person-Group Assignments',
			'description' => 'Person-group assignments.',
			'item_name' => 'Person-Group Assignment',	
			'fields' => array(
				'person' => array('label' => 'Person', 'type' => T_TEXT_LINE),
				'person_group' => array('label' => 'Person Group', 'type' => T_TEXT_LINE),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),		
		'person_group_places' => array(
			'actions' => array(),
			'display_name' => 'Person Group-Place Assignments',
			'description' => 'Person Group-Place assignments.',
			'item_name' => 'Person Group-Place Assignment',		
			'fields' => array(
				'person_group' => array('label' => 'Person Group', 'type' => T_TEXT_LINE),
				'place' => array('label' => 'Place', 'type' => T_TEXT_LINE),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
	);
?>