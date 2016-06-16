drop view if exists network_edges_persons_via_documents cascade;
create view network_edges_persons_via_documents as 
select 
	a from_id, 
	b to_id,
	'persons'::char(7) from_table,
	'persons'::char(7) to_table,
	sum(c) weight,
	sum(c) || ' shared documents'::char "label",
	''::char direction
from (
--  recipients with document senders
select m1.person a, m2.person b, count(*) c
from document_recipients m1, document_primary_agents m2
where m1.document = m2.document
and m1.person <> m2.person
group by 1, 2

union

--  recipients among each other
select m1.person a, m2.person b, count(*) c
from document_recipients m1, document_recipients m2
where m1.document = m2.document
and m1.person > m2.person
group by 1, 2

union

--  recipients with members of document sender groups
select m1.person a, m3.person b, count(*) c
from document_recipients m1, document_primary_agent_groups m2, person_of_group m3
where m1.document = m2.document
and m2.person_group = m3.person_group
and m1.person <> m3.person
group by 1, 2

union

--  recipients with related persons
select m1.person a, m2.person b, count(*) c  
from document_recipients m1, document_persons m2
where m1.document = m2.document
and m1.person <> m2.person
group by 1, 2

union

-- senders with related persons
select m1.person a, m2.person b, count(*) c
from document_primary_agents m1, document_persons m2
where m1.document = m2.document
and m1.person <> m2.person
group by 1, 2

union

-- senders with senders
select m1.person a, m2.person b, count(*) c
from document_primary_agents m1, document_primary_agents m2
where m1.document = m2.document
and m1.person > m2.person
group by 1, 2

union

-- sender groups with related persons
select m1.person a, m3.person b, count(*) c
from document_persons m1, document_primary_agent_groups m2, person_of_group m3
where m1.document = m2.document
and m2.person_group = m3.person_group
and m1.person <> m3.person
group by 1, 2

union

-- related persons among each other
select m1.person a, m2.person b, count(*) c
from document_persons  m1, document_persons m2
where m1.document = m2.document
and m1.person > m2.person
group by 1, 2

union

--  members of document sender groups with members of other document sender groups
select m1.person a, m4.person b, count(*) c
from person_of_group m1, document_primary_agent_groups m2, document_primary_agent_groups m3, person_of_group m4
where m1.person_group = m2.person_group
and m2.document = m3.document
and m3.person_group = m4.person_group
and m2.person_group > m3.person_group
and m1.person <> m4.person
group by 1, 2

) _v_
group by a, b;

drop view if exists network_nodes_persons_via_documents cascade;
create view network_nodes_persons_via_documents as
select 
	id, 
	(select concat_ws(', ', lastname_translit, forename_translit, byname_translit) from persons where id = u.id) "label", 
	'persons'::char(7) "table"
from
	(select from_id id from network_edges_persons_via_documents
	union
	select to_id id from network_edges_persons_via_documents) u;