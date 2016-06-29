select 
	a from_id, 
	b to_id,
	'persons'::char(7) from_table,
	'persons'::char(7) to_table,
	array_agg(c) weight,
	'' "label",
	''::char direction
from (
--  recipients with document senders
select m1.person a, m2.person b, '1' c
from document_recipients m1, document_primary_agents m2
where m1.document = m2.document
and m1.person <> m2.person
group by 1, 2

union

--  recipients among each other
select m1.person a, m2.person b, '2' c
from document_recipients m1, document_recipients m2
where m1.document = m2.document
and m1.person > m2.person
group by 1, 2

union

--  recipients with members of document sender groups
select m1.person a, m3.person b, '3' c
from document_recipients m1, document_primary_agent_groups m2, person_of_group m3
where m1.document = m2.document
and m2.person_group = m3.person_group
and m1.person <> m3.person
group by 1, 2

union

--  recipients with related persons
select m1.person a, m2.person b, '4' c
from document_recipients m1, document_persons m2
where m1.document = m2.document
and m1.person <> m2.person
group by 1, 2

union

-- senders with related persons
select m1.person a, m2.person b, '5' c
from document_primary_agents m1, document_persons m2
where m1.document = m2.document
and m1.person <> m2.person
group by 1, 2

union

-- senders with senders
select m1.person a, m2.person b, '6' c
from document_primary_agents m1, document_primary_agents m2
where m1.document = m2.document
and m1.person > m2.person
group by 1, 2

union

-- sender groups with related persons
select m1.person a, m3.person b, '7' c
from document_persons m1, document_primary_agent_groups m2, person_of_group m3
where m1.document = m2.document
and m2.person_group = m3.person_group
and m1.person <> m3.person
group by 1, 2

union

-- related persons among each other
select m1.person a, m2.person b, '8' c
from document_persons  m1, document_persons m2
where m1.document = m2.document
and m1.person > m2.person
group by 1, 2

union

--  members of document sender groups with members of other document sender groups
select m1.person a, m4.person b, '9' c
from person_of_group m1, document_primary_agent_groups m2, document_primary_agent_groups m3, person_of_group m4
where m1.person_group = m2.person_group
and m2.document = m3.document
and m3.person_group = m4.person_group
and m2.person_group > m3.person_group
and m1.person <> m4.person
group by 1, 2

) _v_
where a = 375 or b = 375
group by a, b