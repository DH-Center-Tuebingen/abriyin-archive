-- v1.01 MD added triggers and function for automatic object_with_history creation
-- v1.02 MD places.coordinates as Postgis geometry
-- v1.03 MD replace spaces with underscores in ENUM values
-- v1.04 MD added on update cascade rules to all foreign keys; 
--          added date constraints; 
--          place_in_dateline as 1:n; 
--			users table revised
--          revised object history schema with global id sequence and mirror tables for editing history
-- v1.05 MD extension unaccent for diacritic-insensitive search
--          place/coordinates accepts any kind of geometry following https://en.wikipedia.org/wiki/Well-known_text
--          gregorian upper/lower year fields
--          information field for person_relatives
--			pack_nr now an integer
--          editing user stored for all tables
-- v1.06 MD dmg_clean function to take care of DMB diacritics
-- v1.07 MD	20160429 recent changes view for all history tables
--          not null dropped for persons.lastname_translit  
--          remove documents/place_in_dateline
--          person_title werte anpassen
-- v1.08 MD 20160511 document_author_groups new table
--          sending & receiving greetings bei person_role enum
-- v1.09 MD 20160512 recent changes charts
--          combination of person last name, first names, byname unique to avoid duplicates
--          new field document/translation
-- v1.10 MD 20160603 document_type enum extension (sales_contract, authorization)
--			document_person_type enum extension (secondary_recipient)
--			COLUMN CHANGES: document.signatory varchar(7) => .signature varchar(8), document.abstract => .summary
--          TABLE NAME CHANGES: document_authors => document_primary_agents, document_author_groups => document_primary_agent_groups,
--             document_addresses => document_recipients
-- v1.11 MD 20160606 update dmg_plain for diacritics that come as dedicated characters (who knows why?)
-- v1.12 MD 20160714 Table and user for setting coordinates of places in QGIS

-- this sequence is important for the history tables. we need "globally" unique IDs for all tables
-- that shall keep a history

create extension if not exists unaccent schema public;
create extension if not exists postgis schema public;

drop sequence if exists unique_object_id_seq cascade;
create sequence unique_object_id_seq;

-- function to automatically insert or delete parent object for objects with history.
-- works regardless of column order (i.e. when new columns are appended at the end of a table)
create or replace function update_history_table() returns trigger as $$
declare
	column_list text;
begin	
	select array_to_string(array_agg(quote_ident(column_name)), ', ') 
	from information_schema.columns
	where table_schema = 'public' and table_name = quote_ident(TG_TABLE_NAME)
	into column_list;
		
	if (TG_OP in ('INSERT', 'UPDATE')) then
		execute format('insert into %s_history ('|| column_list ||', edit_timestamp, edit_action) select ($1).*, now(), ''%s'' ', TG_TABLE_NAME, TG_OP) using NEW;
		return NEW;
	elsif (TG_OP = 'DELETE') then
		execute format('insert into %s_history ('|| column_list ||', edit_timestamp, edit_action) select ($1).*, now(), ''%s''', TG_TABLE_NAME, TG_OP) using OLD;
		return OLD;	
	end if;		
end;
$$ language plpgsql;

-- function to create history table for some table t
create or replace function make_history_table(t text) returns void as $$
begin
	execute format('alter table "%s" add column edit_user int references users(id) on update cascade', t); -- add edit_user to original table
	execute format('drop table if exists "%s_history" cascade', t);	
	execute format('create table "%s_history" as table "%s"', t, t);
	execute format('delete from "%s_history"', t);
	execute format('alter table "%s_history" add column edit_timestamp timestamp not null default current_timestamp', t);
	execute format('alter table "%s_history" add column edit_action varchar(10) not null', t);
	execute format('alter table "%s_history" add column history_id serial primary key', t);
	execute format('drop trigger if exists "%s_history_trigger" on "%s"', t, t);
	execute format('create trigger "%s_history_trigger" after insert or delete or update on "%s" for each row execute procedure update_history_table()', t, t);
end;
$$ language plpgsql;

drop table if exists users cascade;
create table users (
	id int primary key default nextval('unique_object_id_seq'),
	name varchar(50) not null,
	email varchar(100) unique not null,
	password char(32) not null,
	role varchar(100) default 'user' check (role in ('user', 'supervisor', 'admin'))	
);
select make_history_table('users');

drop type if exists edit_history_status cascade;
create type edit_history_status as enum ('editing', 'editing_finished', 'unclear', 'approved');

drop type if exists person_title cascade;
create type person_title as enum ('imām', 'sayyid', 'šayḫ', 'wālī');

drop table if exists persons cascade;
create table persons (
	id int primary key default nextval('unique_object_id_seq'),
	sex char(1) not null default 'm' check (sex in ('m', 'f')),
	forename_translit varchar(50) not null,
	lastname_translit varchar(50), -- nullable!
	byname_translit varchar(50),
	forename_arabic varchar(50),
	lastname_arabic varchar(50),
	byname_arabic varchar(50),
	title person_title,
	birth_year int,
	birth_month int check (birth_month between 1 and 12),
	birth_day int check (birth_day between 1 and 31),
	birth_year_from int,
	birth_year_to int,
	gregorian_birth_year_lower int,
	gregorian_birth_year_upper int,
	death_year int,
	death_month int check (death_month between 1 and 12),
	death_day int check (death_day between 1 and 31),
	death_year_from int,
	death_year_to int,
	gregorian_death_year_lower int,
	gregorian_death_year_upper int,
	information text,
	edit_note text,	
	edit_status edit_history_status not null default 'editing'
);
select make_history_table('persons');

drop table if exists countries_and_regions cascade;
create table countries_and_regions (
	id int primary key default nextval('unique_object_id_seq'),
	name varchar(50) unique not null	
);
select make_history_table('countries_and_regions');

drop type if exists place_type cascade;
create type place_type as enum ('settlement', 'other');

drop table if exists places cascade;
create table places (
	id int primary key default nextval('unique_object_id_seq'),
	name_translit varchar(50) not null,
	name_arabic varchar(50),
	information text,
	coordinates geometry(geometry, 32640), -- WGS 84 / UTM zone 40N
	type place_type not null default 'settlement',
	country_region int not null references countries_and_regions(id) on update cascade,
	edit_note text,	
	edit_status edit_history_status not null default 'editing'
);
select make_history_table('places');

drop type if exists person_group_type cascade;
create type person_group_type as enum ('tribe', 'tribal_unit', 'other');

drop table if exists person_groups cascade;
create table person_groups (
	id int primary key default nextval('unique_object_id_seq'),
	name_translit varchar(50) not null,
	type person_group_type not null default 'tribe',
	name_arabic varchar(50),
	information text,
	edit_note text,	
	edit_status edit_history_status not null default 'editing'
);
select make_history_table('person_groups');
	
drop table if exists sources cascade;
create table sources (
	id int primary key default nextval('unique_object_id_seq'),
	full_title varchar(1000) not null,
	short_title varchar(100) not null	
);
select make_history_table('sources');

drop table if exists keywords cascade;
create table keywords (
	id int primary key default nextval('unique_object_id_seq'),
	keyword varchar(50) not null unique
);
select make_history_table('keywords');

drop type if exists document_type cascade;
create type document_type as enum ('letter', 'sales_contract', 'authorization', 'other');

drop table if exists documents cascade;
create table documents (
	id int primary key default nextval('unique_object_id_seq'),
	signature char(8) not null,	
	type document_type not null default 'letter',
	date_year int,
	date_month int check (date_month between 1 and 12),
	date_day int check (date_day between 1 and 31),
	date_year_from int,
	date_year_to int,
	gregorian_year_lower int,
	gregorian_year_upper int,
	pack_nr int check(pack_nr >= 0),
	content xml,
	abstract text,
	physical_location int not null references places(id) on update cascade,	
	edit_note text,	
	edit_status edit_history_status not null default 'editing'
);
select make_history_table('documents');

drop table if exists scans cascade;
create table scans (
	id int primary key default nextval('unique_object_id_seq'),	
	filename varchar(1000) not null,	
	filepath varchar(1000) unique not null,
	filesize int check (filesize >= 0),
	filetype varchar(100),
	information text	
);
select make_history_table('scans');

drop table if exists document_scans cascade;
create table document_scans (
	document int references documents(id) on update cascade,
	scan int references scans(id) on update cascade,
	primary key(document, scan)
);
select make_history_table('document_scans');

----------------------------------------

drop table if exists document_to_document_references cascade;
create table document_to_document_references (
	source_doc int references documents(id) on update cascade,
	target_doc int references documents(id) on update cascade,	
	comment varchar(100),
	primary key (source_doc, target_doc),
	check (source_doc <> target_doc)
);
select make_history_table('document_to_document_references');

drop table if exists document_keywords cascade;
create table document_keywords (
	document int references documents(id) on update cascade,
	keyword int references keywords(id) on update cascade,
	primary key (document, keyword)	
);
select make_history_table('document_keywords');

drop table if exists document_places cascade;
create table document_places (
	document int references documents(id) on update cascade,
	place int references places(id) on update cascade,
	primary key (document, place)
);
select make_history_table('document_places');

drop table if exists document_primary_agents cascade;
create table document_primary_agents (
	document int references documents(id) on update cascade,
	person int references persons(id) on update cascade,	
	primary key (document, person)
);
select make_history_table('document_primary_agents');

drop table if exists document_recipients cascade;
create table document_recipients (
	document int references documents(id) on update cascade,
	person int references persons(id) on update cascade,
	place int references places(id) on update cascade,
	has_forwarded boolean not null default false,
	primary key (document, person)
);
select make_history_table('document_recipients');

drop type if exists person_role cascade;
create type person_role as enum ('scribe', 'attestor', 'sending_greetings', 'receiving_greetings', 'secondary_recipient', 'other');

drop table if exists document_persons cascade;
create table document_persons (
	document int references documents(id) on update cascade,
	person int references persons(id) on update cascade,
	primary key (document, person),
	type person_role not null default 'other'
);
select make_history_table('document_persons');

drop table if exists person_places cascade;
create table person_places (
	person int references persons(id) on update cascade,
	place int references places(id) on update cascade,
	primary key (person, place),	
	from_year int,
	to_year int
);
select make_history_table('person_places');

drop type if exists kinship cascade;
create type kinship as enum ('father', 'mother', 'child', 'sibling', 'other', 'unknown');

drop table if exists person_relatives cascade;
create table person_relatives (
	person int references persons(id) on update cascade,
	relative int references persons(id) on update cascade,
	primary key (person, relative),	
	check (person <> relative),
	type kinship not null default 'unknown',
	information text
);
select make_history_table('person_relatives');

drop table if exists person_of_group cascade;
create table person_of_group (
	person int references persons(id) on update cascade,
	person_group int references person_groups(id) on update cascade,
	primary key (person, person_group)	
);
select make_history_table('person_of_group');

drop table if exists person_group_places cascade;
create table person_group_places (
	person_group int references person_groups(id) on update cascade,
	place int references places(id) on update cascade,
	primary key (person_group, place)
);
select make_history_table('person_group_places');

drop table if exists bibliographic_references cascade;
create table bibliographic_references (
	object int,
	source int references sources(id) on update cascade,
	page varchar(10),
	volume varchar(10),
	primary key (object, source)
);
select make_history_table('bibliographic_references');

drop table if exists user_sessions cascade;
create table user_sessions (
	user_id int not null references users(id),
	action varchar(20) not null,
	timestamp timestamp not null default current_timestamp
);

-- view for all things that be an object that references a source
create or replace view citing_objects as 
        (        (         select documents.id, 
                            documents.signature || ' (Document)' as name
                           from documents
                union 
                         select places.id, 
                            places.name_translit || ' (Place)' as name
                           from places)
        union 
                 select person_groups.id, 
                    person_groups.name_translit || ' (Person Group)' as name
                   from person_groups)
union 
         select persons.id, 
            pg_catalog.concat_ws(', ', persons.lastname_translit, persons.forename_translit, persons.byname_translit) || ' (Person)' as name
           from persons;

-- cleans all diacritics in DMG Umschrift that are not cleaned by unaccent()
create or replace function dmg_plain(t text) returns text as $$
declare
	c char;
	arr char[] := string_to_array(unaccent(lower(t)), null);
	r text := '';
begin  
	if arr is null then
		return '';
	end if;
	
	foreach c in array arr loop    
		r := r || (case c 
			when 'ṯ' then 't'
			when 'ṭ' then 't'
			when 'ḏ' then 'd'
			when 'ḍ' then 'd'
			when 'ǧ' then 'g'
			when 'ḥ' then 'h'
			when 'ḫ' then 'h'
			when 'ṣ' then 's'
			when 'ẓ' then 'z'
			when 'ḷ' then 'l'	
			
			when '̄' then ''
			when '̣' then ''
			
			--when 'ʿ' then ''''
			--when 'ʾ' then ''''
			
			when ' ' then E'\011' -- \011 is horizontal tab, needed as word separator because blanks are removed for whatever reason!!!
			else c 
		end);
	end loop;
	return r;
end;
$$ language plpgsql;

drop type if exists recent_changes cascade;
create type recent_changes as(
	table_name varchar(50),
	history_id int,	
	timestamp timestamp,
	action varchar(10),
	user_id int);

create or replace function get_recent_changes() returns setof recent_changes as $$
declare
	r recent_changes%rowtype;
	t record;
	x record;
begin
	for t in SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' and table_type = 'BASE TABLE' and table_name like '%_history' loop
		for x in EXECUTE 'select * from ' || t.table_name loop
			r.table_name := t.table_name;
			r.history_id := x.history_id;
			r.timestamp := x.edit_timestamp;
			r.action := x.edit_action;
			r.user_id := x.edit_user;
			return next r;
		end loop;
	end loop;
return;
end; $$
language 'plpgsql';

create or replace view recent_changes_list as select * from get_recent_changes() order by timestamp desc;

drop table if exists document_primary_agent_groups cascade;
create table document_primary_agent_groups (
	document int references documents(id) on update cascade,
	person_group int references person_groups(id) on update cascade,	
	primary key (document, person_group)
);
select make_history_table('document_primary_agent_groups');

create or replace view view_changes_by_user as
select user_id, (select role from users where id=user_id) user_role, count(*) num_changes, min(timestamp) first_change, max(timestamp) last_change 
from recent_changes_list 
where user_id is not null
group by user_id, user_role
order by count(*) desc;

alter table persons add constraint person_name_unique unique (lastname_translit, forename_translit, byname_translit);
alter table documents add column translation text;
alter table documents_history add column translation text;

-- for location setting via QGIS we need a separate table that stores coordinates and references the respective place. Places are then updated with a trigger
drop table if exists verortung cascade;
create table verortung (
  id serial primary key,
  geom geometry(Point,32640),
  place integer unique not null
);

create or replace function set_place_coordinates() returns trigger as
$BODY$
BEGIN
	if TG_OP = 'INSERT' then
		update places set coordinates = NEW.geom where id = NEW.place;
		return NEW;	
	end if;    
END;
$BODY$
language plpgsql;

drop trigger if exists trigger_vertortung_to_places on verortung;
create trigger trigger_vertortung_to_places 
	after insert on verortung 
	for each row execute procedure set_place_coordinates();