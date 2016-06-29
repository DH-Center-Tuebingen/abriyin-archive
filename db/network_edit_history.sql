CREATE OR REPLACE FUNCTION get_edit_network()
RETURNS TABLE(from_id int, to_id int, from_table varchar, to_table varchar, weight int, "label" varchar, direction varchar) AS
$func$
DECLARE
   t text;
BEGIN
   FOR t IN 
	SELECT quote_ident(table_name)
	FROM   information_schema.tables
	WHERE  table_schema = 'public'
	AND    table_name IN ('documents','scans')
   LOOP
      RETURN QUERY EXECUTE
      'SELECT id, edit_user, ''' || t || '''::varchar, ''users''::varchar, count(*)::int, (count(*)::varchar || '' edits'')::varchar, ''to''::varchar
       FROM   ' || t || '_history  
       GROUP BY 1, 2';   -- assuming internal_id is numeric
   END LOOP;
END
$func$  LANGUAGE plpgsql;

create or replace view network_edges_editing_history as 
select * from get_edit_network();

-- select * from network_edges_editing_history;