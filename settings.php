<?	
	/* ========================================================================================================	*/
	// These $CUSTOM_VARIABLES entries are helper variables
	$CUSTOM_VARIABLES['islam_months'] = array(
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
	);
		
	$CUSTOM_VARIABLES['person_name_display'] = array(
		'columns' => array('lastname_translit', 'forename_translit', 'byname_translit'), 
		'expression' => "concat_ws(', ', %1, %2, %3)"
	);
	
	$CUSTOM_VARIABLES['all_actions'] = array(MODE_EDIT, MODE_NEW, MODE_VIEW, MODE_LIST, MODE_DELETE);
	
	$CUSTOM_VARIABLES['history'] = array(
		'edit_note' => array('label' => 'Editing Note', 'type' => T_TEXT_AREA, 'reset' => false /* not sure */, 'help' => 'This is an informal field that you can use to leave personal editoral comments and notes (e.g. what you are unclear about)'),				
		'edit_status' => array('label' => 'Editing Status', 'type' => T_ENUM, 'required' => true, 'default' => 'editing', 'help' => 'While you are editing the document set the status to <b>Editing</b>. When you are done editing and feel the record is ready for review, set to <b>Editing finished</b>. If you are unclear about any field or information set the status to <b>Unclear</b> and put an explanatory comment or note in the <i>Editing note</i> field. The status <b>Approved</b> should only be set by the seminar leaders.',
			'values' => array('editing' => 'Editing', 'editing_finished' => 'Editing finished', 'unclear' => 'Unclear', 'approved' => 'Approved')),	
		'edit_user' => array('label' => 'Last Editor', 'type' => T_LOOKUP, 'editable' => false, 'default' => '%SESSION_USER%', 'lookup' => array('cardinality' => CARDINALITY_SINGLE, 'table' => 'users', 'field' => 'id', 'display' => 'name'))
	);
	
	// note: for all these tables, an after_insert hook will be automatically added in alhamra_menu_complete
	$CUSTOM_VARIABLES['tables_with_history'] = array(
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
		'document_author_groups',
		'person_of_group',
		'person_group_places'
	);
	
	$CUSTOM_VARIABLES['help_details_edit'] = 'Details of each assignment can be edited by clicking the <span class="glyphicon glyphicon-th-list"></span> icon.';
	
	$CUSTOM_VARIABLES['arabic_dates'] = 'These are Islamic dates. If available, fill in the <i>Year</i>, <i>Month</i>, and <i>Day</i> fields. If none of those are available, try to provide a date range by filling in the <i>Year From</i> and/or the <i>Year To</i> fields.';
	
	$CUSTOM_VARIABLES['arabic_dates_fromto'] = 'These are Islamic dates. If available provide a date range by filling in the either or both of the <i>From Year</i> and the <i>To Year</i> fields.';
	
	$CUSTOM_VARIABLES['fk'] = array(
		'document' => array('cardinality' => CARDINALITY_SINGLE, 'table' => 'documents', 'field' => 'id', 'display' => 'signatory'),
		'scan' => array('cardinality' => CARDINALITY_SINGLE, 'table' => 'scans', 'field' => 'id', 'display' => 'filename'),
		'person' => array('cardinality' => CARDINALITY_SINGLE, 'table' => 'persons', 'field' => 'id', 'display' => $CUSTOM_VARIABLES['person_name_display']),
		'person_group' => array('cardinality' => CARDINALITY_SINGLE, 'table' => 'person_groups', 'field' => 'id', 'display' => 'name_translit'),
		'place' => array('cardinality' => CARDINALITY_SINGLE, 'table' => 'places', 'field' => 'id', 'display' => 'name_translit'),
		'keyword' => array('cardinality' => CARDINALITY_SINGLE, 'table' => 'keywords', 'field' => 'id', 'display' => 'keyword'),
	);
	
	// only for admins
	$CUSTOM_VARIABLES['extra_tables'] = array(
		'view_changes_by_user' => array(
			'actions' => array(MODE_LIST),
			'display_name' => 'Changes By User',
			'description' => '',
			'item_name' => 'Change By User',
			'primary_key' => array('auto' => false, 'columns' => array('user_id')),		
			'sort' => array('num_changes' => 'desc'),
			'show_in_related' => false,
			'fields' => array(				
				'user_id' => array('label' => 'User', 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_SINGLE, 
						'table' => 'users', 
						'field' => 'id', 
						'display' => 'name')),
				'num_changes' => array('label' => '# Changes', 'type' => T_TEXT_LINE),
				'user_role' => array('label' => 'Role', 'type' => T_ENUM, 'values' => array('user' => 'User', 'admin' => 'Admin')),				
				'first_change' => array('label' => 'First Change', 'type' => T_TEXT_LINE),
				'last_change' => array('label' => 'Last Change', 'type' => T_TEXT_LINE)
			)
		),
		'recent_changes_list' => array(
			'actions' => array(MODE_LIST),
			'display_name' => 'All Recent Changes',
			'description' => 'Recent changes made by users in the database. Actions are either INSERT (new record), UPDATE (edited record) and DELETE (removed record). To see details of each change, please go to the editing history of the table mentioned in the "History Table" column.',
			'item_name' => 'Change',
			'primary_key' => array('auto' => false, 'columns' => array('table_name', 'history_id')), 
			'sort' => array('timestamp' => 'desc'),
			'custom_actions' => array(
				array('mode' => MODE_LIST, 'handler' => 'alhamra_recent_changes_item_view')
			),
			'show_in_related' => false,
			'fields' => array(		
				'timestamp' => array('label' => 'Timestamp', 'type' => T_TEXT_LINE),				
				'user_id' => array('label' => 'Editor', 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_SINGLE, 
						'table' => 'users', 
						'field' => 'id', 
						'display' => 'name')),
				'action' => array('label' => 'Action', 'type' => T_TEXT_LINE),
				'table_name' => array('label' => 'History Table', 'type' => T_TEXT_LINE),
				'history_id' => array('label' => 'History ID', 'type' => T_NUMBER)
			)
		)
	);
	
	
	/* ========================================================================================================	*/
	$APP = array(
		'bootstrap_css' => 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css',
		'plugins' => array('alhamra.php', 'calendar.php'),
		'title' => 'ʿAbrīyīn Archive',
		'view_display_null_fields' => false,
		'page_size'	=> 10,
		'max_text_len' => 125,
		'pages_prevnext' => 2,
		'mainmenu_tables_autosort' => true,
		'search_lookup_resolve' => true,
		'timezone' => 'Europe/Berlin',
		'search_string_transformation' => 'dmg_plain((%s)::text)',
		'null_label' => "<span class='nowrap' title='If you check this box, no value will be stored for this field. This may reflect missing, unknown, unspecified or inapplicable information. Note that no value (missing information) is different to providing an empty value: an empty value is a value.'>No Value</span>",
		'menu_complete_proc' => 'alhamra_menu_complete',
		'render_main_page_proc' => 'alhamra_render_main_page',
		'list_mincolwidth_max' => 300,
		'list_mincolwidth_pxperchar' => 6
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
			'description' => 'A <b>user</b> can log into the ʿAbrīyīn Archive and perform actions based on his/her assigned role.',
			'item_name' => 'User',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),		
			'sort' => array('name' => 'asc'),
			'fields' => array(
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),
				'name' => array('label' => 'Name', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => true, 'help' => 'This should be the user\'s real full name'),
				'role' => array('label' => 'Role', 'type' => T_ENUM, 'required' => true, 'default' => 'user', 'values' => array('user' => 'Normal User', 'admin' => 'Admin'), 'help' => 'Normal users can create new records, edit and delete existing records (except user accounts). Admins can additionally manage user accounts and browse editing histories.'),
				'email' => array('label' => 'Email', 'type' => T_TEXT_LINE, 'len' => 100, 'required' => true, 'help' => 'The email address is used for logging in.'),
				'password' => array('label' => 'Password', 'type' => T_PASSWORD, 'len' => 32, 'required' => true, 'min_len' => 3, 'help' => 'The password must have at least 3 characters'),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'scans' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Scans',
			'description' => 'A <b>scan</b> is a photo or scan of a document (or a document page). Scans can be assigned to documents. The maximum photo file size is 10 MB.',
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
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA, 'help' => 'Use this field to specify any additional factual information that is not reflected in any other field'),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)			
		),
		
		// ----------------------------------------------------------------------------------------------------
		'documents' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Documents',
			'description' => 'A <b>document</b> reflects a letter or other kind of document in the archive and its associated data.',
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
								'"<b>bundle</b>" to which the document belongs. This concerns only some of the documents.</p>'),				
				'pack_nr' => array('label' => 'Bundle Number', 'type' => T_NUMBER, 'help' => 'Number of the bundle, without leading zeros.'),
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),				
				'scans' => array('label' => 'Scan Files', 'required' => false, 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'scans',
						'field' => 'id',
						'display' => 'filename',
						'related_label' => 'Document Captured By This Scan'),
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
						'default' => 30,
						'related_label' => 'Documents Physically Located At This Place') 
				),				
				'authors' => array('label' => 'Individual Senders', 'required' => false, 'type' => T_LOOKUP, 
					'help' => 'Specify here the individual sender(s) of the letter. If a whole person group appears as the sender of the letter, pick this person group in the following field.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'persons',
						'field' => 'id',
						'display' => $CUSTOM_VARIABLES['person_name_display'],
						'related_label' => 'Documents This Person Has Sent'),
					'linkage' => array(
						'table' => 'document_authors',
						'fk_self' => 'document',
						'fk_other' => 'person',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'document_author_groups' => array('label' => 'Sender Groups', 'required' => false, 'type' => T_LOOKUP,
					'help' => '<b>Attention</b>: This field is only to be filled in if a person group is the actual sender of a letter. It is <b>not</b> to be filled to indicate the group affiliation of an individual sender.',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'person_groups',
						'field' => 'id',
						'display' => 'name_translit',
						'related_label' => 'Documents This Person Group Has Sent'),
					'linkage' => array(
						'table' => 'document_author_groups',
						'fk_self' => 'document',
						'fk_other' => 'person_group',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'document_addresses' => array('label' => 'Addressees', 'type' => T_LOOKUP, 
					'help' => 'Use this field to assign persons as addressees of the document. These assignments are stored as <a href="?table=document_addresses">Document Addresses</a> in a separate table. ' . $CUSTOM_VARIABLES['help_details_edit'],
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'persons',
						'field' => 'id',
						'display' => $CUSTOM_VARIABLES['person_name_display'],
						'related_label' => 'Documents Addressed To This Person'),
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
				'places' => array('label' => 'Other Places', 'type' => T_LOOKUP, 
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'places',
						'field' => 'id',
						'display' => 'name_translit',
						'related_label' => 'Documents That Mention This Place'),
					'linkage' => array(
						'table' => 'document_places',
						'fk_self' => 'document',
						'fk_other' => 'place',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'document_persons' => array('label' => 'Related Persons', 'type' => T_LOOKUP, 
					'help' => 'Use this field to assign persons occurring in some role in this document. These assignments are stored in a separate table <a href="?table=document_persons">Persons in Documents</a>. ' . $CUSTOM_VARIABLES['help_details_edit'],
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'persons',
						'field' => 'id',
						'display' => $CUSTOM_VARIABLES['person_name_display'],
						'related_label' => 'Documents Where This Person Played A Particular Role'),
					'linkage' => array(
						'table' => 'document_persons',
						'fk_self' => 'document',
						'fk_other' => 'person',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'document_to_document_references' => array('label' => 'Referenced Documents', 'type' => T_LOOKUP, 
					'help' => 'Use this field to specify which other documents in the archive are referenced by this one. These are stored in a separate table <a href="?table=document_to_document_references">Document-to-Document References</a>. ' . $CUSTOM_VARIABLES['help_details_edit'],
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'documents',
						'field' => 'id',
						'display' => 'signatory',
						'related_label' => 'Documents That Reference This Document'),
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
						'display' => 'keyword',
						'related_label' => 'Documents Tagged With This Keyword'),
					'linkage' => array(
						'table' => 'document_keywords',
						'fk_self' => 'document',
						'fk_other' => 'keyword',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'bibliographic_references' => array('label' => 'Bibliographic References', 'type' => T_LOOKUP, 
					'help' => 'Use this field to assign <a href="?table=sources">sources</a> as references for this document. These assignments are stored in a separate table <a href="?table=bibliographic_references">Bibliographic References</a>. ' . $CUSTOM_VARIABLES['help_details_edit'],
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'sources',
						'field' => 'id',
						'display' => 'short_title',
						'related_label' => 'Documents With Bibliographic References To This Source'),
					'linkage' => array(
						'table' => 'bibliographic_references',
						'fk_self' => 'object',
						'fk_other' => 'source',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				//'content' => array('label' => 'Content (XML)', 'type' => T_TEXT_AREA),
				'abstract' => array('label' => 'Summary', 'type' => T_TEXT_AREA),
				'translation' => array('label' => 'English Translation', 'type' => T_TEXT_AREA),
				'edit_note' => array('label' => 'Editing Note', 'type' => T_TEXT_AREA, 'help' => 'Remarks about<ul><li>unclear / illegible words, unclear vocalization (i.e. personal name)</li><li>“relative dates”, i.e. date deduced from affiliation to a bundle and / or with regards to content (events, people involved).</li><li>possible errors (e.g. unclear reading of dates etc.)</li></ul>'),
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
			'description' => 'A <b>document address</b> reflects the destination address of a document. Each document address consists of a person and optionally a place reflecting the destination location. This association between a document and its addressees can also be made in the <i>Addressees</i> field of the document editing form.',
			'item_name' => 'Document Address',			
			'primary_key' => array('auto' => false, 'columns' => array('document', 'person')),
			'sort' => array('document' => 'asc', 'person' => 'asc'),
			'fields' => array(				
				'document' => array('label' => 'Document', 'type' => T_LOOKUP, 'required' => true, 'help' => 'The document for which you want to specify an addressee and destination place', 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'documents',
					'field'   => 'id',
					'display' => 'signatory',
					'related_label' => 'Document Addresses')
				),				
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'required' => true, 'help' => 'A person who is an addressee of the selected document', 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'],
					'related_label' => 'Document Addresses Where This Person Appears')
				),				
				'place' => array('label' => 'Address/Place', 'type' => T_LOOKUP, 'required' => false, 'help' => 'The destination address of the document for the selected person', 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'places',
					'field'   => 'id',
					'display' => 'name_translit',
					'related_label' => 'Document Addresses Where This Place Appears')
				),				
				'has_forwarded' => array('label' => 'Forwarding Address?', 'type' => T_ENUM, 'required' => true, 'help' => 'Specify whether this document address is a forwarding address, i.e. the addressee acted as an intermediary in the delivery of the document', 'default' => '0', 'values' => array('1' => 'Yes', '0' => 'No')),				
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
				
		// ----------------------------------------------------------------------------------------------------
		'persons' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Persons',
			'description' => 'A <b>person</b> record captures personal data of people occurring in the archive. Persons are <a href="?table=document_persons">associated with documents</a> (e.g. as senders, attestors, etc.), with <a href="?table=person_places">places where they live</a>, with <a href="?table=person_relatives">their relatives</a> (e.g. father, brother, etc.), and with <a href="?table=person_groups">person groups to which they belong</a> (e.g. their tribal unit).',
			'item_name' => 'Person',			
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),
			'sort' => array('lastname_translit' => 'asc', 'forename_translit' => 'asc'),
			'hooks' => array(
				'before_insert' => 'alhamra_before_insert_or_update',
				'before_update' => 'alhamra_before_insert_or_update'),
			'fields' => array(
				'lastname_translit' => array('label' => 'Family name (translit.)', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => false,
					'help' => 'If the family name is missing, please enter "Unknown" here. If the family name is illegible, please enter "Unclear" and make an explanatory note in the field <i>Information</i>'),
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
						'display' => 'name_translit',
						'related_label' => 'Persons of This Group'),
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
				/*'person_documents' => array('label' => 'Related Documents', 'type' => T_LOOKUP, 'editable' => false,
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'documents',
						'field' => 'id',
						'display' => 'signatory'),
					'linkage' => array(
						'table' => 'document_persons',
						'fk_self' => 'person',
						'fk_other' => 'document')
				),*/
				'person_places' => array('label' => 'Places/Locations', 'required' => false, 'type' => T_LOOKUP, 
					'help' => 'Use this field to assign this person to places. These assignments are stored in a separate table <a href="?table=person_places">Places of Persons</a>. ' . $CUSTOM_VARIABLES['help_details_edit'],
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'places',
						'field' => 'id',
						'display' => 'name_translit',
						'related_label' => 'Persons Associated With This Place'),
					'linkage' => array(
						'table' => 'person_places',
						'fk_self' => 'person',
						'fk_other' => 'place',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),
				'bibliographic_references' => array('label' => 'Bibliographic References', 'type' => T_LOOKUP, 
					'help' => 'Use this field to assign <a href="?table=sources">sources</a> as references for this person. These assignments are stored in a separate table <a href="?table=bibliographic_references">Bibliographic References</a>. ' . $CUSTOM_VARIABLES['help_details_edit'],
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'sources',
						'field' => 'id',
						'display' => 'short_title',
						'related_label' => 'Persons With Bibliographic References To This Source'),
					'linkage' => array(
						'table' => 'bibliographic_references',
						'fk_self' => 'object',
						'fk_other' => 'source',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA, 'help' => 'Use this field to specify any additional factual information that is not reflected in any other field'),				
				'edit_note' => array('label' => 'Editing Note', 'type' => T_TEXT_AREA, 'help' => 'Remarks about<ul><li>kinship (e.g. son / brother / uncle of…)</li><li>social / political position (e.g. governor (wālī) of…)</li><li>ALSO: murdered by…; varying dates of birth / death</li></ul>'),
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
			'description' => 'A <b>place of a person</b> reflects an association between a person and a place, usually reflecting that the person lived in that place for the specified time frame. The same association can also be defined in the <i>Places/Locations</i> field of the person editing form',
			'item_name' => 'Place of Person',
			'primary_key' => array('auto' => false, 'columns' => array('person', 'place')),
			'sort' => array('person' => 'asc', 'place' => 'asc'),
			'fields' => array(				
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'],
					'related_label' => 'Details Of Place Associations Of This Person')
				),				
				'place' => array('label' => 'Place', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'places',
					'field'   => 'id',
					'display' => 'name_translit',
					'related_label' => 'Details Of Person Associations With This Place')
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
			'description' => 'A <b>relative of person</b> establishes a kinship between two persons.',
			'item_name' => 'Relative of Person',
			'primary_key' => array('auto' => false, 'columns' => array('person', 'relative')),
			'sort' => array('person' => 'asc', 'relative' => 'asc'),
			'fields' => array(		
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'],
					'related_label' => 'Details Of Family Relationships Where This Is The Relating Person')
				),				
				'relative' => array('label' => 'Relative', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'],
					'related_label' => 'Details Of Family Relationships Where This Is The Related Person')
				),
				'type' => array('label' => 'Kinship', 'type' => T_ENUM, 'required' => true, 'default' => 'unknown', 'values' => array('mother' => 'Mother', 'father' => 'Father', 'child' => 'Child', 'sibling' => 'Sibling', 'other' => 'Other', 'unknown' => 'Unknown'), 'help' => 'Select the role of the <i>Person</i> in the kinship with the <i>Relative</i>'),				
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA, 'help' => 'Use this field to specify any additional factual information that is not reflected in any other field'),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'document_persons' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Persons in Documents',
			'description' => 'A <b>person in document</b> reflects a person who occurs in a document as an attestor, a scribe, or who is sending/receiving greetings or being mentioned in any other way. These associations can also be defined in the <i>Related Persons</i> field of the document editing form',
			'item_name' => 'Person in Document',
			'primary_key' => array('auto' => false, 'columns' => array('document', 'person')),
			'sort' => array('document' => 'asc', 'person' => 'asc'),
			'fields' => array(				
				'document' => array('label' => 'Document', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'documents',
					'field'   => 'id',
					'display' => 'signatory',
					'related_label' => 'Details Of Person Associations With This Document')
				),				
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'persons',
					'field'   => 'id',
					'display' => $CUSTOM_VARIABLES['person_name_display'],
					'related_label' => 'Details Of Associations of This Person With Documents')
				),				
				'type' => array('label' => 'Role', 'type' => T_ENUM, 'required' => true, 'default' => 'other', 'values' => array('attestor' => 'Attestor', 'scribe' => 'Scribe', 'sending_greetings' => 'Sending greetings', 'receiving_greetings' => 'Receiving greetings', 'other' => 'Other'), 'help' => 'In what role does the person occur in the document?'),				
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'document_to_document_references' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Document-to-Document References',
			'description' => 'A <b>document-to-document reference</b> establishes a any kind of direct reference from one document (the referencing document) to another document (the referenced document). The same association can also be defined in the <i>Referenced Documents</i> field of the document editing form.',
			'item_name' => 'Document-to-Document Reference',
			'primary_key' => array('auto' => false, 'columns' => array('source_doc', 'target_doc')),
			'sort' => array('source_doc' => 'asc', 'target_doc' => 'asc'),
			'fields' => array(				
				'source_doc' => array('label' => 'Referencing Document', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'documents',
					'field'   => 'id',
					'display' => 'signatory',
					'related_label' => 'Details Of References Where This Is A Referencing Document')
				),				
				'target_doc' => array('label' => 'Referenced Document (Target)', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'documents',
					'field'   => 'id',
					'display' => 'signatory',
					'related_label' => 'Details Of References Where This Is A Referenced Document')
				),				
				'comment' => array('label' => 'Comment', 'type' => T_TEXT_AREA),				
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'places' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Places',
			'description' => 'A <b>place</b> represents any settlement or other place of relevance to documents, persons, and person groups.',
			'item_name' => 'Place',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),		
			'sort' => array('name_translit' => 'asc'),
			'fields' => array(
				'name_translit' => array('label' => 'Transliterated Name', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => true),				
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),				
				'type' => array('label' => 'Type', 'type' => T_ENUM, 'required' => true, 'values' => array('settlement' => 'Settlement', 'other' => 'Other'), 'default' => 'settlement'),				
				'name_arabic' => array('label' => 'Arabic Name', 'type' => T_TEXT_LINE, 'len' => 50),				
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA, 'help' => 'Use this field to specify any additional factual information that is not reflected in any other field'),
				'coordinates' => array('label' => 'Coordinates', 'type' => T_POSTGIS_GEOM, 'SRID' => '4326', 'help' => 'Enter the <a href="https://en.wikipedia.org/wiki/Well-known_text">well-known text (WKT)</a> representation of any geometry, e.g. <code>POINT(-71.06 32.4485)</code>. See also <a href="http://postgis.net/docs/ST_GeomFromText.html" target="_blank">here</a> for help. The spatial reference system is <a href="http://spatialreference.org/ref/epsg/wgs-84/">EPSG:4326 / WGS84</a>.'),				
				'country_region' => array('label' => 'Country/Region', 'type' => T_LOOKUP, 'required' => true, 'lookup' => array(
					'cardinality' => CARDINALITY_SINGLE,
					'table'   => 'countries_and_regions',
					'field'   => 'id',
					'display' => 'name',
					'default' => 7,
					'related_label' => 'Places In This Country/Region')
				),				
				'bibliographic_references' => array('label' => 'Bibliographic References', 'type' => T_LOOKUP, 
					'help' => 'Use this field to assign <a href="?table=sources">sources</a> as references for this place. These assignments are stored in a separate table <a href="?table=bibliographic_references">Bibliographic References</a>. ' . $CUSTOM_VARIABLES['help_details_edit'],
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'sources',
						'field' => 'id',
						'display' => 'short_title',
						'related_label' => 'Places With Bibliographic References To This Source'),
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
			'description' => 'A <b>person group</b> represents a tribe, tribal unit and other groupings of <a href="?table=persons">persons</a>.',
			'item_name' => 'Person Group',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'),
			'sort' => array('name_translit' => 'asc'),			
			'fields' => array(
				'name_translit' => array('label' => 'Name (transliterated)', 'type' => T_TEXT_LINE, 'len' => 50, 'required' => true),				
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),				
				'type' => array('label' => 'Type', 'type' => T_ENUM, 'required' => true, 'default' => 'other', 'values' => array('tribe' => 'Tribe', 'tribal_unit' => 'Tribal unit', 'other' => 'Other')),				
				'name_arabic' => array('label' => 'Name (Arabic)', 'type' => T_TEXT_LINE, 'len' => 50),				
				'places' => array('label' => 'Place(s)', 'type' => T_LOOKUP,
				'help' => 'Places associated with this person group',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'places',
						'field' => 'id',
						'display' => 'name_translit',
						'related_label' => 'Person Groups At This Place'),
					'linkage' => array(
						'table' => 'person_group_places',
						'fk_self' => 'person_group',
						'fk_other' => 'place',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'person_of_group' => array('label' => 'Group\'s Persons', 'type' => T_LOOKUP, 'help' => 'All persons belonging to this person group',
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'persons',
						'field' => 'id',
						'display' => $CUSTOM_VARIABLES['person_name_display'],
						'related_label' => 'Person Groups This Person Belongs To'),
					'linkage' => array(
						'table' => 'person_of_group',
						'fk_self' => 'person_group',
						'fk_other' => 'person',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),				
				'bibliographic_references' => array('label' => 'Bibliographic References', 'type' => T_LOOKUP, 
					'help' => 'Use this field to assign <a href="?table=sources">sources</a> as references for this person group. These assignments are stored in a separate table <a href="?table=bibliographic_references">Bibliographic References</a>. ' . $CUSTOM_VARIABLES['help_details_edit'],
					'lookup' => array(
						'cardinality' => CARDINALITY_MULTIPLE,
						'table' => 'sources',
						'field' => 'id',
						'display' => 'short_title',
						'related_label' => 'Person Groups With Bibliographic References To This Source'),
					'linkage' => array(
						'table' => 'bibliographic_references',
						'fk_self' => 'object',
						'fk_other' => 'source',
						'defaults' => array('edit_user' => '%SESSION_USER%'))
				),		
				'information' => array('label' => 'Information', 'type' => T_TEXT_AREA, 'help' => 'Use this field to specify any additional factual information that is not reflected in any other field'),
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
			'description' => 'A <b>country or region</b> is a regional container for the places of relevance in the archive.',
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
			'description' => 'A <b>keyword</b> is a word used to tag documents of the archive.',
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
			'description' => 'A <b>source</b> represents as a bibliographic reference for documents, places, persons and person groups in the archive.',
			'item_name' => 'Source',
			'primary_key' => array('auto' => true, 'columns' => array('id'), 'sequence_name' => 'unique_object_id_seq'), 
			'sort' => array('id' => 'asc'),
			'fields' => array(		
				'short_title' => array('label' => 'Short Title', 'type' => T_TEXT_LINE, 'len' => 100, 'required' => true, 'help' => '<ul><li>Secondary literature: Author date (e.g. Hoffmann-Ruf 2008)</li><li>Primary source: Author, Short title (e.g. al-ʿAbri, Tabaṣṣurāt)</li></ul>'),
				'full_title' => array('label' => 'Full Title', 'type' => T_TEXT_AREA, 'len' => 1000, 'required' => true, 'help' => '<ul><li>Secondary literature: Hoffmann-Ruf, Michaela, Scheich Muḥsin b. Zahrān al-ʿAbrī: Tribale Macht im Oman des 19. Jahrhunderts, Berlin 2008.</li><li>Primary source: ʿAbrī, Ibrāhīm b. Saʿīd al-, Tabaṣṣurāt al-muʿtabirīn fī tārīḫ al-ʿAbrīyīn (manuscript) 1959.</li></ul>'),
				'id' => array('label' => 'ID', 'type' => T_NUMBER, 'editable' => false),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		
		// ----------------------------------------------------------------------------------------------------
		'bibliographic_references' => array(
			'actions' => $CUSTOM_VARIABLES['all_actions'],
			'display_name' => 'Bibliographic References',
			'description' => 'A <b>bibliographic reference</b> connects places, persons, person groups and documents to bibliographic <a href="?table=sources">sources</a>.',
			'item_name' => 'Bibliographic Reference',
			'primary_key' => array('auto' => false, 'columns' => array('object', 'source')), 
			'sort' => array('object' => 'asc', 'source' => 'asc'),
			'fields' => array(		
				'object' => array('label' => 'Object', 'type' => T_LOOKUP, 'required' => true, 'allow-create' => false, 'help' => 'Select the place, person, person group or document to link to a source',
					'lookup' => array(
						'cardinality' => CARDINALITY_SINGLE,
						'table'   => 'citing_objects',
						'field'   => 'id',
						'display' => 'name',
						'related_label' => 'Details Of Bibliographic References Of This Object'
					)
				),
				'source' => array('label' => 'Source', 'type' => T_LOOKUP, 'required' => true,
					'lookup' => array(
						'cardinality' => CARDINALITY_SINGLE,
						'table'   => 'sources',
						'field'   => 'id',
						'display' => 'short_title',
						'related_label' => 'Details Of Bibliographic References To This Source'
					)
				),
				'volume' => array('label' => 'Volume', 'type' => T_TEXT_LINE, 'len' => 10),
				'page' => array('label' => 'Page', 'type' => T_TEXT_LINE, 'len' => 10),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
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
			'show_in_related' => false,
			'fields' => array(
				'document' => array('label' => 'Document', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['document']),
				'scan' => array('label' => 'Scan', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['scan']),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),		
		'document_keywords' => array(
			'actions' => array(),
			'display_name' => 'Document-Keyword Assignments',
			'description' => 'Document-keyword assignments.',
			'item_name' => 'Document-Keyword Assignment',	
			'show_in_related' => false,			
			'fields' => array(
				'document' => array('label' => 'Document', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['document']),
				'keyword' => array('label' => 'Keyword', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['keyword']),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
		'document_places' => array(
			'actions' => array(),
			'display_name' => 'Document-Place Assignments',
			'description' => 'Document-place assignments.',
			'item_name' => 'Document-Place Assignment',		
			'show_in_related' => false,
			'fields' => array(
				'document' => array('label' => 'Document', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['document']),
				'place' => array('label' => 'Place', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['place']),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),		
		'document_authors' => array(
			'actions' => array(),
			'display_name' => 'Document-Sender Assignments',
			'description' => 'Document-sender assignments.',
			'item_name' => 'Document-Sender Assignment',
			'show_in_related' => false,
			'fields' => array(
				'document' => array('label' => 'Document', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['document']),
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['person']),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),	
		'document_author_groups' => array(
			'actions' => array(),
			'display_name' => 'Document-Sender Group Assignments',
			'description' => 'Document-sender group assignments.',
			'item_name' => 'Document-Sender Group Assignment',	
			'show_in_related' => false,			
			'fields' => array(
				'document' => array('label' => 'Document', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['document']),
				'person_group' => array('label' => 'Person Group', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['person_group']),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),	
		'person_of_group' => array(
			'actions' => array(),
			'display_name' => 'Person-Group Assignments',
			'description' => 'Person-group assignments.',
			'item_name' => 'Person-Group Assignment',	
			'show_in_related' => false,
			'fields' => array(
				'person' => array('label' => 'Person', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['person']),
				'person_group' => array('label' => 'Person Group', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['person_group']),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),		
		'person_group_places' => array(
			'actions' => array(),
			'display_name' => 'Person Group-Place Assignments',
			'description' => 'Person Group-Place assignments.',
			'item_name' => 'Person Group-Place Assignment',		
			'show_in_related' => false,
			'fields' => array(
				'person_group' => array('label' => 'Person Group', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['person_group']),
				'place' => array('label' => 'Place', 'type' => T_LOOKUP, 'lookup' => $CUSTOM_VARIABLES['fk']['place']),
				'edit_user' => $CUSTOM_VARIABLES['history']['edit_user']
			)
		),
	);
?>