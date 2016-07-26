<style>
	table {
		width: 100%;		
	}
	td, th {
		padding: 5px;		
	}	
	table, th, td {
		border: 1px solid gray;
		border-collapse: collapse;
	}
</style>
<?
	require_once '../../dbWebGen/inc/constants.php';
	require_once '../settings.php';
	
	$help = array();
	
	foreach($TABLES as $table_name => $table_info) {
		foreach($table_info['fields'] as $field_name => $field_info)
			if(isset($field_info['help']))
				$help[] = array($table_info['display_name'], $field_info['label'], $field_info['help']);
	}
	
	echo "<table>\n";
	echo "<tr><th>Table</th><th>Field</th><th>Help Text</th></tr>\n";
	foreach($help as $row) {
		echo "<tr><td>{$row[0]}</td><td>{$row[1]}</td><td>{$row[2]}</td></tr>\n";
	}
	echo "</table>\n";
?>
<script>
	[].slice.call(document.getElementsByTagName('a')).forEach(function(a) {
		a.setAttribute('href', 'javascript:void(0)');
	});
</script>