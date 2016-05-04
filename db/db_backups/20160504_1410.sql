--
-- PostgreSQL database dump
--

-- Dumped from database version 9.2.15
-- Dumped by pg_dump version 9.4.4
-- Started on 2016-05-04 14:09:49

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- TOC entry 238 (class 3079 OID 12648)
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- TOC entry 4421 (class 0 OID 0)
-- Dependencies: 238
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- TOC entry 240 (class 3079 OID 21892)
-- Name: postgis; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;


--
-- TOC entry 4422 (class 0 OID 0)
-- Dependencies: 240
-- Name: EXTENSION postgis; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION postgis IS 'PostGIS geometry, geography, and raster spatial types and functions';


--
-- TOC entry 239 (class 3079 OID 26001)
-- Name: unaccent; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public;


--
-- TOC entry 4423 (class 0 OID 0)
-- Dependencies: 239
-- Name: EXTENSION unaccent; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION unaccent IS 'text search dictionary that removes accents';


SET search_path = public, pg_catalog;

--
-- TOC entry 1642 (class 1247 OID 31932)
-- Name: document_type; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE document_type AS ENUM (
    'letter',
    'other'
);


ALTER TYPE document_type OWNER TO postgres;

--
-- TOC entry 1577 (class 1247 OID 31666)
-- Name: edit_history_status; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE edit_history_status AS ENUM (
    'editing',
    'editing_finished',
    'unclear',
    'approved'
);


ALTER TYPE edit_history_status OWNER TO postgres;

--
-- TOC entry 1706 (class 1247 OID 32259)
-- Name: kinship; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE kinship AS ENUM (
    'father',
    'mother',
    'child',
    'sibling',
    'other',
    'unknown'
);


ALTER TYPE kinship OWNER TO postgres;

--
-- TOC entry 1614 (class 1247 OID 31813)
-- Name: person_group_type; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE person_group_type AS ENUM (
    'tribe',
    'tribal_unit',
    'other'
);


ALTER TYPE person_group_type OWNER TO postgres;

--
-- TOC entry 1689 (class 1247 OID 32177)
-- Name: person_role; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE person_role AS ENUM (
    'scribe',
    'attestor',
    'other'
);


ALTER TYPE person_role OWNER TO postgres;

--
-- TOC entry 1580 (class 1247 OID 31676)
-- Name: person_title; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE person_title AS ENUM (
    'imām',
    'sayyid',
    'šayḫ',
    'wālī'
);


ALTER TYPE person_title OWNER TO postgres;

--
-- TOC entry 1602 (class 1247 OID 31761)
-- Name: place_type; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE place_type AS ENUM (
    'settlement',
    'other'
);


ALTER TYPE place_type OWNER TO postgres;

--
-- TOC entry 1528 (class 1247 OID 34040)
-- Name: recent_changes; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE recent_changes AS (
	table_name character varying(50),
	history_id integer,
	"timestamp" timestamp without time zone,
	action character varying(10),
	user_id integer
);


ALTER TYPE recent_changes OWNER TO postgres;

--
-- TOC entry 1156 (class 1255 OID 32399)
-- Name: dmg_plain(text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION dmg_plain(t text) RETURNS text
    LANGUAGE plpgsql
    AS $$
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
			--when 'ʿ' then ''
			--when 'ʾ' then ''
			else c 
		end);
	end loop;
	return r;
end;
$$;


ALTER FUNCTION public.dmg_plain(t text) OWNER TO postgres;

--
-- TOC entry 1154 (class 1255 OID 34041)
-- Name: get_recent_changes(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION get_recent_changes() RETURNS SETOF recent_changes
    LANGUAGE plpgsql
    AS $$
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
end; $$;


ALTER FUNCTION public.get_recent_changes() OWNER TO postgres;

--
-- TOC entry 1155 (class 1255 OID 23363)
-- Name: make_history_table(text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION make_history_table(t text) RETURNS void
    LANGUAGE plpgsql
    AS $$
begin
	execute format('alter table "%s" add column edit_user int references users(id) on update cascade', t); -- add edit_user to original table
	execute format('drop table if exists "%s_history"', t);	
	execute format('create table "%s_history" as table "%s"', t, t);
	execute format('alter table "%s_history" add column edit_timestamp timestamp not null default current_timestamp', t);
	execute format('alter table "%s_history" add column edit_action varchar(10) not null', t);
	execute format('alter table "%s_history" add column history_id serial primary key', t);
	execute format('drop trigger if exists "%s_history_trigger" on "%s"', t, t);
	execute format('create trigger "%s_history_trigger" after insert or delete or update on "%s" for each row execute procedure update_history_table()', t, t);
end;
$$;


ALTER FUNCTION public.make_history_table(t text) OWNER TO postgres;

--
-- TOC entry 1153 (class 1255 OID 23362)
-- Name: update_history_table(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION update_history_table() RETURNS trigger
    LANGUAGE plpgsql
    AS $_$
begin	
	if (TG_OP = 'INSERT') then
		execute format('insert into %s_history select ($1).*, now(), ''%s'' ', TG_TABLE_NAME, TG_OP) using NEW;
		return NEW;
	elsif (TG_OP = 'DELETE') then
		execute format('insert into %s_history select ($1).*, now(), ''%s''', TG_TABLE_NAME, TG_OP) using OLD;
		return OLD;
	elsif (TG_OP = 'UPDATE') then
		execute format('insert into %s_history select ($1).*, now(), ''%s''', TG_TABLE_NAME, TG_OP) using NEW;
		return NEW;
	end if;		
end;
$_$;


ALTER FUNCTION public.update_history_table() OWNER TO postgres;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- TOC entry 230 (class 1259 OID 32352)
-- Name: bibliographic_references; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE bibliographic_references (
    object integer NOT NULL,
    source integer NOT NULL,
    page character varying(10),
    volume character varying(10),
    edit_user integer
);


ALTER TABLE bibliographic_references OWNER TO postgres;

--
-- TOC entry 231 (class 1259 OID 32367)
-- Name: bibliographic_references_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE bibliographic_references_history (
    object integer,
    source integer,
    page character varying(10),
    volume character varying(10),
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE bibliographic_references_history OWNER TO postgres;

--
-- TOC entry 232 (class 1259 OID 32374)
-- Name: bibliographic_references_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE bibliographic_references_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE bibliographic_references_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4424 (class 0 OID 0)
-- Dependencies: 232
-- Name: bibliographic_references_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE bibliographic_references_history_history_id_seq OWNED BY bibliographic_references_history.history_id;


--
-- TOC entry 182 (class 1259 OID 31631)
-- Name: unique_object_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE unique_object_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE unique_object_id_seq OWNER TO postgres;

--
-- TOC entry 204 (class 1259 OID 31937)
-- Name: documents; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE documents (
    id integer DEFAULT nextval('unique_object_id_seq'::regclass) NOT NULL,
    signatory character(7) NOT NULL,
    type document_type DEFAULT 'letter'::document_type NOT NULL,
    date_year integer,
    date_month integer,
    date_day integer,
    date_year_from integer,
    date_year_to integer,
    gregorian_year_lower integer,
    gregorian_year_upper integer,
    pack_nr integer,
    content xml,
    abstract text,
    physical_location integer NOT NULL,
    edit_note text,
    edit_user integer,
    edit_status edit_history_status DEFAULT 'editing'::edit_history_status NOT NULL,
    CONSTRAINT documents_date_day_check CHECK (((date_day >= 1) AND (date_day <= 31))),
    CONSTRAINT documents_date_month_check CHECK (((date_month >= 1) AND (date_month <= 12))),
    CONSTRAINT documents_pack_nr_check CHECK ((pack_nr >= 0))
);


ALTER TABLE documents OWNER TO postgres;

--
-- TOC entry 195 (class 1259 OID 31819)
-- Name: person_groups; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE person_groups (
    id integer DEFAULT nextval('unique_object_id_seq'::regclass) NOT NULL,
    name_translit character varying(50) NOT NULL,
    type person_group_type DEFAULT 'tribe'::person_group_type NOT NULL,
    name_arabic character varying(50),
    information text,
    edit_note text,
    edit_user integer,
    edit_status edit_history_status DEFAULT 'editing'::edit_history_status NOT NULL
);


ALTER TABLE person_groups OWNER TO postgres;

--
-- TOC entry 186 (class 1259 OID 31683)
-- Name: persons; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE persons (
    id integer DEFAULT nextval('unique_object_id_seq'::regclass) NOT NULL,
    sex character(1) DEFAULT 'm'::bpchar NOT NULL,
    forename_translit character varying(50) NOT NULL,
    lastname_translit character varying(50),
    byname_translit character varying(50),
    forename_arabic character varying(50),
    lastname_arabic character varying(50),
    byname_arabic character varying(50),
    title person_title,
    birth_year integer,
    birth_month integer,
    birth_day integer,
    birth_year_from integer,
    birth_year_to integer,
    gregorian_birth_year_lower integer,
    gregorian_birth_year_upper integer,
    death_year integer,
    death_month integer,
    death_day integer,
    death_year_from integer,
    death_year_to integer,
    gregorian_death_year_lower integer,
    gregorian_death_year_upper integer,
    information text,
    edit_note text,
    edit_user integer,
    edit_status edit_history_status DEFAULT 'editing'::edit_history_status NOT NULL,
    CONSTRAINT persons_birth_day_check CHECK (((birth_day >= 1) AND (birth_day <= 31))),
    CONSTRAINT persons_birth_month_check CHECK (((birth_month >= 1) AND (birth_month <= 12))),
    CONSTRAINT persons_death_day_check CHECK (((death_day >= 1) AND (death_day <= 31))),
    CONSTRAINT persons_death_month_check CHECK (((death_month >= 1) AND (death_month <= 12))),
    CONSTRAINT persons_sex_check CHECK ((sex = ANY (ARRAY['m'::bpchar, 'f'::bpchar])))
);


ALTER TABLE persons OWNER TO postgres;

--
-- TOC entry 192 (class 1259 OID 31765)
-- Name: places; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE places (
    id integer DEFAULT nextval('unique_object_id_seq'::regclass) NOT NULL,
    name_translit character varying(50) NOT NULL,
    name_arabic character varying(50),
    information text,
    coordinates geometry(Geometry,4326),
    type place_type DEFAULT 'settlement'::place_type NOT NULL,
    country_region integer NOT NULL,
    edit_note text,
    edit_user integer,
    edit_status edit_history_status DEFAULT 'editing'::edit_history_status NOT NULL
);


ALTER TABLE places OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 32393)
-- Name: citing_objects; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW citing_objects AS
((SELECT documents.id, ((documents.signatory)::text || ' (Document)'::text) AS name FROM documents UNION SELECT places.id, ((places.name_translit)::text || ' (Place)'::text) AS name FROM places) UNION SELECT person_groups.id, ((person_groups.name_translit)::text || ' (Person Group)'::text) AS name FROM person_groups) UNION SELECT persons.id, (pg_catalog.concat_ws(', '::text, persons.lastname_translit, persons.forename_translit, persons.byname_translit) || ' (Person)'::text) AS name FROM persons;


ALTER TABLE citing_objects OWNER TO postgres;

--
-- TOC entry 189 (class 1259 OID 31730)
-- Name: countries_and_regions; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE countries_and_regions (
    id integer DEFAULT nextval('unique_object_id_seq'::regclass) NOT NULL,
    name character varying(50) NOT NULL,
    edit_user integer
);


ALTER TABLE countries_and_regions OWNER TO postgres;

--
-- TOC entry 190 (class 1259 OID 31743)
-- Name: countries_and_regions_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE countries_and_regions_history (
    id integer,
    name character varying(50),
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE countries_and_regions_history OWNER TO postgres;

--
-- TOC entry 191 (class 1259 OID 31750)
-- Name: countries_and_regions_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE countries_and_regions_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE countries_and_regions_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4425 (class 0 OID 0)
-- Dependencies: 191
-- Name: countries_and_regions_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE countries_and_regions_history_history_id_seq OWNED BY countries_and_regions_history.history_id;


--
-- TOC entry 181 (class 1259 OID 23686)
-- Name: document_address; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_address (
    document integer NOT NULL,
    person integer NOT NULL,
    place integer,
    has_forwarded boolean DEFAULT false NOT NULL
);


ALTER TABLE document_address OWNER TO postgres;

--
-- TOC entry 216 (class 1259 OID 32133)
-- Name: document_addresses; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_addresses (
    document integer NOT NULL,
    person integer NOT NULL,
    place integer,
    has_forwarded boolean DEFAULT false NOT NULL,
    edit_user integer
);


ALTER TABLE document_addresses OWNER TO postgres;

--
-- TOC entry 217 (class 1259 OID 32159)
-- Name: document_addresses_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_addresses_history (
    document integer,
    person integer,
    place integer,
    has_forwarded boolean,
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE document_addresses_history OWNER TO postgres;

--
-- TOC entry 218 (class 1259 OID 32166)
-- Name: document_addresses_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE document_addresses_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE document_addresses_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4426 (class 0 OID 0)
-- Dependencies: 218
-- Name: document_addresses_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE document_addresses_history_history_id_seq OWNED BY document_addresses_history.history_id;


--
-- TOC entry 215 (class 1259 OID 32118)
-- Name: document_authors; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_authors (
    document integer NOT NULL,
    person integer NOT NULL
);


ALTER TABLE document_authors OWNER TO postgres;

--
-- TOC entry 213 (class 1259 OID 32088)
-- Name: document_keywords; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_keywords (
    document integer NOT NULL,
    keyword integer NOT NULL
);


ALTER TABLE document_keywords OWNER TO postgres;

--
-- TOC entry 219 (class 1259 OID 32183)
-- Name: document_persons; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_persons (
    document integer NOT NULL,
    person integer NOT NULL,
    type person_role DEFAULT 'other'::person_role NOT NULL,
    edit_user integer
);


ALTER TABLE document_persons OWNER TO postgres;

--
-- TOC entry 220 (class 1259 OID 32204)
-- Name: document_persons_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_persons_history (
    document integer,
    person integer,
    type person_role,
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE document_persons_history OWNER TO postgres;

--
-- TOC entry 221 (class 1259 OID 32211)
-- Name: document_persons_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE document_persons_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE document_persons_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4427 (class 0 OID 0)
-- Dependencies: 221
-- Name: document_persons_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE document_persons_history_history_id_seq OWNED BY document_persons_history.history_id;


--
-- TOC entry 214 (class 1259 OID 32103)
-- Name: document_places; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_places (
    document integer NOT NULL,
    place integer NOT NULL
);


ALTER TABLE document_places OWNER TO postgres;

--
-- TOC entry 237 (class 1259 OID 34069)
-- Name: document_scans; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_scans (
    document integer NOT NULL,
    scan integer NOT NULL
);


ALTER TABLE document_scans OWNER TO postgres;

--
-- TOC entry 210 (class 1259 OID 32050)
-- Name: document_to_document_references; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_to_document_references (
    source_doc integer NOT NULL,
    target_doc integer NOT NULL,
    comment character varying(100),
    edit_user integer,
    CONSTRAINT document_to_document_references_check CHECK ((source_doc <> target_doc))
);


ALTER TABLE document_to_document_references OWNER TO postgres;

--
-- TOC entry 211 (class 1259 OID 32071)
-- Name: document_to_document_references_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE document_to_document_references_history (
    source_doc integer,
    target_doc integer,
    comment character varying(100),
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE document_to_document_references_history OWNER TO postgres;

--
-- TOC entry 212 (class 1259 OID 32078)
-- Name: document_to_document_references_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE document_to_document_references_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE document_to_document_references_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4428 (class 0 OID 0)
-- Dependencies: 212
-- Name: document_to_document_references_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE document_to_document_references_history_history_id_seq OWNED BY document_to_document_references_history.history_id;


--
-- TOC entry 205 (class 1259 OID 31966)
-- Name: documents_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE documents_history (
    id integer,
    signatory character(7),
    type document_type,
    date_year integer,
    date_month integer,
    date_day integer,
    date_year_from integer,
    date_year_to integer,
    gregorian_year_lower integer,
    gregorian_year_upper integer,
    pack_nr integer,
    content xml,
    abstract text,
    physical_location integer,
    edit_note text,
    edit_user integer,
    edit_status edit_history_status,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE documents_history OWNER TO postgres;

--
-- TOC entry 206 (class 1259 OID 31979)
-- Name: documents_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE documents_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE documents_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4429 (class 0 OID 0)
-- Dependencies: 206
-- Name: documents_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE documents_history_history_id_seq OWNED BY documents_history.history_id;


--
-- TOC entry 201 (class 1259 OID 31901)
-- Name: keywords; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE keywords (
    id integer DEFAULT nextval('unique_object_id_seq'::regclass) NOT NULL,
    keyword character varying(50) NOT NULL,
    edit_user integer
);


ALTER TABLE keywords OWNER TO postgres;

--
-- TOC entry 202 (class 1259 OID 31914)
-- Name: keywords_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE keywords_history (
    id integer,
    keyword character varying(50),
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE keywords_history OWNER TO postgres;

--
-- TOC entry 203 (class 1259 OID 31921)
-- Name: keywords_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE keywords_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE keywords_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4430 (class 0 OID 0)
-- Dependencies: 203
-- Name: keywords_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE keywords_history_history_id_seq OWNED BY keywords_history.history_id;


--
-- TOC entry 229 (class 1259 OID 32337)
-- Name: person_group_places; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE person_group_places (
    person_group integer NOT NULL,
    place integer NOT NULL
);


ALTER TABLE person_group_places OWNER TO postgres;

--
-- TOC entry 196 (class 1259 OID 31835)
-- Name: person_groups_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE person_groups_history (
    id integer,
    name_translit character varying(50),
    type person_group_type,
    name_arabic character varying(50),
    information text,
    edit_note text,
    edit_user integer,
    edit_status edit_history_status,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE person_groups_history OWNER TO postgres;

--
-- TOC entry 197 (class 1259 OID 31848)
-- Name: person_groups_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE person_groups_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE person_groups_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4431 (class 0 OID 0)
-- Dependencies: 197
-- Name: person_groups_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE person_groups_history_history_id_seq OWNED BY person_groups_history.history_id;


--
-- TOC entry 228 (class 1259 OID 32322)
-- Name: person_of_group; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE person_of_group (
    person integer NOT NULL,
    person_group integer NOT NULL
);


ALTER TABLE person_of_group OWNER TO postgres;

--
-- TOC entry 222 (class 1259 OID 32221)
-- Name: person_places; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE person_places (
    person integer NOT NULL,
    place integer NOT NULL,
    from_year integer,
    to_year integer,
    edit_user integer
);


ALTER TABLE person_places OWNER TO postgres;

--
-- TOC entry 223 (class 1259 OID 32241)
-- Name: person_places_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE person_places_history (
    person integer,
    place integer,
    from_year integer,
    to_year integer,
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE person_places_history OWNER TO postgres;

--
-- TOC entry 224 (class 1259 OID 32248)
-- Name: person_places_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE person_places_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE person_places_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4432 (class 0 OID 0)
-- Dependencies: 224
-- Name: person_places_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE person_places_history_history_id_seq OWNED BY person_places_history.history_id;


--
-- TOC entry 225 (class 1259 OID 32271)
-- Name: person_relatives; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE person_relatives (
    person integer NOT NULL,
    relative integer NOT NULL,
    type kinship DEFAULT 'unknown'::kinship NOT NULL,
    information text,
    edit_user integer,
    CONSTRAINT person_relatives_check CHECK ((person <> relative))
);


ALTER TABLE person_relatives OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 32296)
-- Name: person_relatives_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE person_relatives_history (
    person integer,
    relative integer,
    type kinship,
    information text,
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE person_relatives_history OWNER TO postgres;

--
-- TOC entry 227 (class 1259 OID 32309)
-- Name: person_relatives_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE person_relatives_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE person_relatives_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4433 (class 0 OID 0)
-- Dependencies: 227
-- Name: person_relatives_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE person_relatives_history_history_id_seq OWNED BY person_relatives_history.history_id;


--
-- TOC entry 187 (class 1259 OID 31704)
-- Name: persons_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE persons_history (
    id integer,
    sex character(1),
    forename_translit character varying(50),
    lastname_translit character varying(50),
    byname_translit character varying(50),
    forename_arabic character varying(50),
    lastname_arabic character varying(50),
    byname_arabic character varying(50),
    title person_title,
    birth_year integer,
    birth_month integer,
    birth_day integer,
    birth_year_from integer,
    birth_year_to integer,
    gregorian_birth_year_lower integer,
    gregorian_birth_year_upper integer,
    death_year integer,
    death_month integer,
    death_day integer,
    death_year_from integer,
    death_year_to integer,
    gregorian_death_year_lower integer,
    gregorian_death_year_upper integer,
    information text,
    edit_note text,
    edit_user integer,
    edit_status edit_history_status,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE persons_history OWNER TO postgres;

--
-- TOC entry 188 (class 1259 OID 31717)
-- Name: persons_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE persons_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE persons_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4434 (class 0 OID 0)
-- Dependencies: 188
-- Name: persons_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE persons_history_history_id_seq OWNED BY persons_history.history_id;


--
-- TOC entry 193 (class 1259 OID 31786)
-- Name: places_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE places_history (
    id integer,
    name_translit character varying(50),
    name_arabic character varying(50),
    information text,
    coordinates geometry(Geometry,4326),
    type place_type,
    country_region integer,
    edit_note text,
    edit_user integer,
    edit_status edit_history_status,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE places_history OWNER TO postgres;

--
-- TOC entry 194 (class 1259 OID 31799)
-- Name: places_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE places_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE places_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4435 (class 0 OID 0)
-- Dependencies: 194
-- Name: places_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE places_history_history_id_seq OWNED BY places_history.history_id;


--
-- TOC entry 236 (class 1259 OID 34042)
-- Name: recent_changes_list; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW recent_changes_list AS
SELECT get_recent_changes.table_name, get_recent_changes.history_id, get_recent_changes."timestamp", get_recent_changes.action, get_recent_changes.user_id FROM get_recent_changes() get_recent_changes(table_name, history_id, "timestamp", action, user_id) ORDER BY get_recent_changes."timestamp" DESC;


ALTER TABLE recent_changes_list OWNER TO postgres;

--
-- TOC entry 207 (class 1259 OID 31992)
-- Name: scans; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE scans (
    id integer DEFAULT nextval('unique_object_id_seq'::regclass) NOT NULL,
    filename character varying(1000) NOT NULL,
    filepath character varying(1000) NOT NULL,
    filesize integer,
    filetype character varying(100),
    information text,
    edit_user integer,
    CONSTRAINT scans_filesize_check CHECK ((filesize >= 0))
);


ALTER TABLE scans OWNER TO postgres;

--
-- TOC entry 208 (class 1259 OID 32009)
-- Name: scans_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE scans_history (
    id integer,
    filename character varying(1000),
    filepath character varying(1000),
    filesize integer,
    filetype character varying(100),
    information text,
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE scans_history OWNER TO postgres;

--
-- TOC entry 209 (class 1259 OID 32022)
-- Name: scans_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE scans_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE scans_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4436 (class 0 OID 0)
-- Dependencies: 209
-- Name: scans_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE scans_history_history_id_seq OWNED BY scans_history.history_id;


--
-- TOC entry 198 (class 1259 OID 31861)
-- Name: sources; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE sources (
    id integer DEFAULT nextval('unique_object_id_seq'::regclass) NOT NULL,
    full_title character varying(1000) NOT NULL,
    short_title character varying(100) NOT NULL,
    edit_user integer
);


ALTER TABLE sources OWNER TO postgres;

--
-- TOC entry 199 (class 1259 OID 31875)
-- Name: sources_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE sources_history (
    id integer,
    full_title character varying(1000),
    short_title character varying(100),
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE sources_history OWNER TO postgres;

--
-- TOC entry 200 (class 1259 OID 31888)
-- Name: sources_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE sources_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE sources_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4437 (class 0 OID 0)
-- Dependencies: 200
-- Name: sources_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE sources_history_history_id_seq OWNED BY sources_history.history_id;


--
-- TOC entry 233 (class 1259 OID 32384)
-- Name: user_sessions; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE user_sessions (
    user_id integer NOT NULL,
    action character varying(20) NOT NULL,
    "timestamp" timestamp without time zone DEFAULT now() NOT NULL
);


ALTER TABLE user_sessions OWNER TO postgres;

--
-- TOC entry 183 (class 1259 OID 31633)
-- Name: users; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE users (
    id integer DEFAULT nextval('unique_object_id_seq'::regclass) NOT NULL,
    name character varying(50) NOT NULL,
    email character varying(100) NOT NULL,
    password character(32) NOT NULL,
    role character varying(100) DEFAULT 'user'::character varying,
    edit_user integer,
    CONSTRAINT users_role_check CHECK (((role)::text = ANY ((ARRAY['user'::character varying, 'supervisor'::character varying, 'admin'::character varying])::text[])))
);


ALTER TABLE users OWNER TO postgres;

--
-- TOC entry 184 (class 1259 OID 31648)
-- Name: users_history; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE users_history (
    id integer,
    name character varying(50),
    email character varying(100),
    password character(32),
    role character varying(100),
    edit_user integer,
    edit_timestamp timestamp without time zone DEFAULT now() NOT NULL,
    edit_action character varying(10) NOT NULL,
    history_id integer NOT NULL
);


ALTER TABLE users_history OWNER TO postgres;

--
-- TOC entry 185 (class 1259 OID 31655)
-- Name: users_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE users_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE users_history_history_id_seq OWNER TO postgres;

--
-- TOC entry 4438 (class 0 OID 0)
-- Dependencies: 185
-- Name: users_history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE users_history_history_id_seq OWNED BY users_history.history_id;


--
-- TOC entry 4104 (class 2604 OID 32376)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY bibliographic_references_history ALTER COLUMN history_id SET DEFAULT nextval('bibliographic_references_history_history_id_seq'::regclass);


--
-- TOC entry 4059 (class 2604 OID 31752)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY countries_and_regions_history ALTER COLUMN history_id SET DEFAULT nextval('countries_and_regions_history_history_id_seq'::regclass);


--
-- TOC entry 4093 (class 2604 OID 32168)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_addresses_history ALTER COLUMN history_id SET DEFAULT nextval('document_addresses_history_history_id_seq'::regclass);


--
-- TOC entry 4096 (class 2604 OID 32213)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_persons_history ALTER COLUMN history_id SET DEFAULT nextval('document_persons_history_history_id_seq'::regclass);


--
-- TOC entry 4090 (class 2604 OID 32080)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_to_document_references_history ALTER COLUMN history_id SET DEFAULT nextval('document_to_document_references_history_history_id_seq'::regclass);


--
-- TOC entry 4083 (class 2604 OID 31981)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY documents_history ALTER COLUMN history_id SET DEFAULT nextval('documents_history_history_id_seq'::regclass);


--
-- TOC entry 4075 (class 2604 OID 31923)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY keywords_history ALTER COLUMN history_id SET DEFAULT nextval('keywords_history_history_id_seq'::regclass);


--
-- TOC entry 4069 (class 2604 OID 31850)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_groups_history ALTER COLUMN history_id SET DEFAULT nextval('person_groups_history_history_id_seq'::regclass);


--
-- TOC entry 4098 (class 2604 OID 32250)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_places_history ALTER COLUMN history_id SET DEFAULT nextval('person_places_history_history_id_seq'::regclass);


--
-- TOC entry 4102 (class 2604 OID 32311)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_relatives_history ALTER COLUMN history_id SET DEFAULT nextval('person_relatives_history_history_id_seq'::regclass);


--
-- TOC entry 4056 (class 2604 OID 31719)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY persons_history ALTER COLUMN history_id SET DEFAULT nextval('persons_history_history_id_seq'::regclass);


--
-- TOC entry 4064 (class 2604 OID 31801)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY places_history ALTER COLUMN history_id SET DEFAULT nextval('places_history_history_id_seq'::regclass);


--
-- TOC entry 4087 (class 2604 OID 32024)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY scans_history ALTER COLUMN history_id SET DEFAULT nextval('scans_history_history_id_seq'::regclass);


--
-- TOC entry 4072 (class 2604 OID 31890)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY sources_history ALTER COLUMN history_id SET DEFAULT nextval('sources_history_history_id_seq'::regclass);


--
-- TOC entry 4046 (class 2604 OID 31657)
-- Name: history_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY users_history ALTER COLUMN history_id SET DEFAULT nextval('users_history_history_id_seq'::regclass);


--
-- TOC entry 4409 (class 0 OID 32352)
-- Dependencies: 230
-- Data for Name: bibliographic_references; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4410 (class 0 OID 32367)
-- Dependencies: 231
-- Data for Name: bibliographic_references_history; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4439 (class 0 OID 0)
-- Dependencies: 232
-- Name: bibliographic_references_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('bibliographic_references_history_history_id_seq', 1, false);


--
-- TOC entry 4368 (class 0 OID 31730)
-- Dependencies: 189
-- Data for Name: countries_and_regions; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO countries_and_regions (id, name, edit_user) VALUES (7, 'Oman', 1);
INSERT INTO countries_and_regions (id, name, edit_user) VALUES (99, 'East Africa', 2);


--
-- TOC entry 4369 (class 0 OID 31743)
-- Dependencies: 190
-- Data for Name: countries_and_regions_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO countries_and_regions_history (id, name, edit_user, edit_timestamp, edit_action, history_id) VALUES (7, 'Oman', 1, '2016-04-22 10:01:27.386981', 'INSERT', 1);
INSERT INTO countries_and_regions_history (id, name, edit_user, edit_timestamp, edit_action, history_id) VALUES (62, 'asdasd', 1, '2016-04-26 11:10:49.467286', 'INSERT', 2);
INSERT INTO countries_and_regions_history (id, name, edit_user, edit_timestamp, edit_action, history_id) VALUES (62, 'asdasd', 1, '2016-04-26 11:10:52.102326', 'DELETE', 3);
INSERT INTO countries_and_regions_history (id, name, edit_user, edit_timestamp, edit_action, history_id) VALUES (63, 'Remove', 1, '2016-04-26 11:11:09.17871', 'INSERT', 4);
INSERT INTO countries_and_regions_history (id, name, edit_user, edit_timestamp, edit_action, history_id) VALUES (63, 'Remove', 1, '2016-04-26 11:11:18.751279', 'DELETE', 5);
INSERT INTO countries_and_regions_history (id, name, edit_user, edit_timestamp, edit_action, history_id) VALUES (99, 'East Africa', 2, '2016-04-29 09:44:05.418014', 'INSERT', 6);


--
-- TOC entry 4440 (class 0 OID 0)
-- Dependencies: 191
-- Name: countries_and_regions_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('countries_and_regions_history_history_id_seq', 6, true);


--
-- TOC entry 4360 (class 0 OID 23686)
-- Dependencies: 181
-- Data for Name: document_address; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4395 (class 0 OID 32133)
-- Dependencies: 216
-- Data for Name: document_addresses; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO document_addresses (document, person, place, has_forwarded, edit_user) VALUES (69, 67, 30, false, 4);


--
-- TOC entry 4396 (class 0 OID 32159)
-- Dependencies: 217
-- Data for Name: document_addresses_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO document_addresses_history (document, person, place, has_forwarded, edit_user, edit_timestamp, edit_action, history_id) VALUES (69, 67, 30, false, 4, '2016-04-29 10:09:26.195648', 'INSERT', 1);


--
-- TOC entry 4441 (class 0 OID 0)
-- Dependencies: 218
-- Name: document_addresses_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('document_addresses_history_history_id_seq', 1, true);


--
-- TOC entry 4394 (class 0 OID 32118)
-- Dependencies: 215
-- Data for Name: document_authors; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO document_authors (document, person) VALUES (69, 64);
INSERT INTO document_authors (document, person) VALUES (69, 67);
INSERT INTO document_authors (document, person) VALUES (71, 67);
INSERT INTO document_authors (document, person) VALUES (71, 64);
INSERT INTO document_authors (document, person) VALUES (73, 67);
INSERT INTO document_authors (document, person) VALUES (73, 64);
INSERT INTO document_authors (document, person) VALUES (75, 67);
INSERT INTO document_authors (document, person) VALUES (75, 64);
INSERT INTO document_authors (document, person) VALUES (77, 67);
INSERT INTO document_authors (document, person) VALUES (77, 64);
INSERT INTO document_authors (document, person) VALUES (82, 64);
INSERT INTO document_authors (document, person) VALUES (82, 79);
INSERT INTO document_authors (document, person) VALUES (82, 80);
INSERT INTO document_authors (document, person) VALUES (82, 81);
INSERT INTO document_authors (document, person) VALUES (86, 85);
INSERT INTO document_authors (document, person) VALUES (89, 88);
INSERT INTO document_authors (document, person) VALUES (89, 67);


--
-- TOC entry 4392 (class 0 OID 32088)
-- Dependencies: 213
-- Data for Name: document_keywords; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4398 (class 0 OID 32183)
-- Dependencies: 219
-- Data for Name: document_persons; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4399 (class 0 OID 32204)
-- Dependencies: 220
-- Data for Name: document_persons_history; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4442 (class 0 OID 0)
-- Dependencies: 221
-- Name: document_persons_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('document_persons_history_history_id_seq', 1, false);


--
-- TOC entry 4393 (class 0 OID 32103)
-- Dependencies: 214
-- Data for Name: document_places; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4413 (class 0 OID 34069)
-- Dependencies: 237
-- Data for Name: document_scans; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO document_scans (document, scan) VALUES (69, 68);
INSERT INTO document_scans (document, scan) VALUES (71, 70);
INSERT INTO document_scans (document, scan) VALUES (73, 72);
INSERT INTO document_scans (document, scan) VALUES (75, 74);
INSERT INTO document_scans (document, scan) VALUES (77, 76);
INSERT INTO document_scans (document, scan) VALUES (82, 78);
INSERT INTO document_scans (document, scan) VALUES (86, 83);
INSERT INTO document_scans (document, scan) VALUES (89, 87);
INSERT INTO document_scans (document, scan) VALUES (69, 100);
INSERT INTO document_scans (document, scan) VALUES (69, 74);


--
-- TOC entry 4389 (class 0 OID 32050)
-- Dependencies: 210
-- Data for Name: document_to_document_references; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4390 (class 0 OID 32071)
-- Dependencies: 211
-- Data for Name: document_to_document_references_history; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4443 (class 0 OID 0)
-- Dependencies: 212
-- Name: document_to_document_references_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('document_to_document_references_history_history_id_seq', 1, false);


--
-- TOC entry 4383 (class 0 OID 31937)
-- Dependencies: 204
-- Data for Name: documents; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO documents (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status) VALUES (71, 'D6-18  ', 'letter', NULL, 6, 29, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 30, NULL, 4, 'editing');
INSERT INTO documents (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status) VALUES (73, 'D6-20  ', 'letter', 1249, 8, 19, NULL, NULL, 1834, 1834, NULL, NULL, NULL, 30, NULL, 4, 'editing');
INSERT INTO documents (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status) VALUES (77, 'D6-26  ', 'letter', 1249, 7, 13, NULL, NULL, 1833, 1833, NULL, NULL, NULL, 30, NULL, 4, 'editing');
INSERT INTO documents (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status) VALUES (82, 'D6-27  ', 'letter', 1249, 6, 18, NULL, NULL, 1833, 1833, NULL, NULL, NULL, 30, NULL, 4, 'editing');
INSERT INTO documents (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status) VALUES (86, 'D6-28  ', 'letter', 1249, 6, 8, NULL, NULL, 1833, 1833, 9, NULL, NULL, 30, NULL, 4, 'editing');
INSERT INTO documents (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status) VALUES (89, 'D6-30  ', 'letter', 1249, 3, 20, NULL, NULL, 1833, 1833, NULL, NULL, NULL, 30, NULL, 4, 'editing');
INSERT INTO documents (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status) VALUES (75, 'D6-22  ', 'letter', NULL, 8, 24, NULL, NULL, NULL, NULL, 9, NULL, NULL, 30, 'Datum: möglicherweise auch 26.', 2, 'editing');
INSERT INTO documents (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status) VALUES (69, 'D6-15  ', 'letter', 1249, 6, 16, NULL, NULL, 1833, 1833, 9, NULL, NULL, 30, NULL, 4, 'editing');


--
-- TOC entry 4384 (class 0 OID 31966)
-- Dependencies: 205
-- Data for Name: documents_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (69, 'D06-15 ', 'letter', 1249, 6, 16, NULL, NULL, 1833, 1833, NULL, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-28 18:50:44.566469', 'INSERT', 1);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (71, 'D6-18  ', 'letter', NULL, 6, 29, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-28 19:00:31.650571', 'INSERT', 2);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (73, 'D6-20  ', 'letter', 1249, 8, 19, NULL, NULL, 1834, 1834, NULL, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-28 19:02:41.674864', 'INSERT', 3);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (75, 'D6-22  ', 'letter', NULL, 8, 24, NULL, NULL, NULL, NULL, 9, NULL, NULL, 30, 'Datum: möglicherweise auch 26.', 4, 'editing', '2016-04-28 19:16:10.188462', 'INSERT', 4);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (77, 'D6-26  ', 'letter', 1249, 7, 13, NULL, NULL, 1833, 1833, NULL, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-28 19:20:15.601403', 'INSERT', 5);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (82, 'D6-27  ', 'letter', 1249, 6, 18, NULL, NULL, 1833, 1833, NULL, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-28 19:34:07.672116', 'INSERT', 6);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (86, 'D6-28  ', 'letter', 1249, 6, 8, NULL, NULL, 1833, 1833, 9, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-28 19:39:22.60062', 'INSERT', 7);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (89, 'D6-30  ', 'letter', 1249, 3, 20, NULL, NULL, 1833, 1833, NULL, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-28 19:44:34.189818', 'INSERT', 8);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (69, 'D06-15 ', 'letter', 1249, 6, 16, NULL, NULL, 1833, 1833, NULL, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-29 10:05:16.031135', 'UPDATE', 9);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (69, 'D06-15 ', 'letter', 1249, 6, 16, NULL, NULL, 1833, 1833, 9, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-29 10:08:45.594557', 'UPDATE', 10);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (69, 'D6-15  ', 'letter', 1249, 6, 16, NULL, NULL, 1833, 1833, 9, NULL, NULL, 30, NULL, 2, 'editing', '2016-04-29 10:40:31.440437', 'UPDATE', 11);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (69, 'D6-15  ', 'letter', 1249, 6, 16, NULL, NULL, 1833, 1833, 9, NULL, NULL, 30, NULL, 4, 'editing', '2016-04-29 10:44:52.073934', 'UPDATE', 12);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (75, 'D6-22  ', 'letter', NULL, 8, 24, NULL, NULL, NULL, NULL, 9, NULL, NULL, 30, 'Datum: möglicherweise auch 26.', 2, 'editing', '2016-04-29 10:45:57.201673', 'UPDATE', 13);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (75, 'D6-22  ', 'letter', NULL, 8, 24, NULL, NULL, NULL, NULL, 9, NULL, NULL, 30, 'Datum: möglicherweise auch 26.', 2, 'editing', '2016-04-29 10:46:21.420763', 'UPDATE', 14);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (69, 'D6-15  ', 'letter', 1249, 6, 16, NULL, NULL, 1833, 1833, 9, NULL, NULL, 30, NULL, 4, 'editing', '2016-05-02 12:57:28.790569', 'UPDATE', 15);
INSERT INTO documents_history (id, signatory, type, date_year, date_month, date_day, date_year_from, date_year_to, gregorian_year_lower, gregorian_year_upper, pack_nr, content, abstract, physical_location, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (69, 'D6-15  ', 'letter', 1249, 6, 16, NULL, NULL, 1833, 1833, 9, NULL, NULL, 30, NULL, 4, 'editing', '2016-05-02 13:29:53.227155', 'UPDATE', 16);


--
-- TOC entry 4444 (class 0 OID 0)
-- Dependencies: 206
-- Name: documents_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('documents_history_history_id_seq', 16, true);


--
-- TOC entry 4380 (class 0 OID 31901)
-- Dependencies: 201
-- Data for Name: keywords; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4381 (class 0 OID 31914)
-- Dependencies: 202
-- Data for Name: keywords_history; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4445 (class 0 OID 0)
-- Dependencies: 203
-- Name: keywords_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('keywords_history_history_id_seq', 1, false);


--
-- TOC entry 4408 (class 0 OID 32337)
-- Dependencies: 229
-- Data for Name: person_group_places; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO person_group_places (person_group, place) VALUES (65, 30);
INSERT INTO person_group_places (person_group, place) VALUES (84, 36);
INSERT INTO person_group_places (person_group, place) VALUES (90, 12);
INSERT INTO person_group_places (person_group, place) VALUES (92, 91);
INSERT INTO person_group_places (person_group, place) VALUES (93, 32);
INSERT INTO person_group_places (person_group, place) VALUES (93, 14);


--
-- TOC entry 4374 (class 0 OID 31819)
-- Dependencies: 195
-- Data for Name: person_groups; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (84, 'Āl Bū-Saʿīd', 'tribe', NULL, 'Ruling family of Oman', NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (90, 'B. Ḏuhl', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (93, 'Āl Yaʿrub', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (92, 'B. ʿAwf ', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (101, 'B. Hināʾ ', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (102, 'B. Ġāfir', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (103, 'B. Ḫarūṣ', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (104, 'B. Riyām', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (105, 'B. Šukayl ', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (65, 'ʿAbrīyīn', 'tribe', NULL, NULL, NULL, 1, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (106, 'B. Ruwāḥa', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (107, 'Yaʿāqīb', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (108, 'Āl Ramāḥ', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (109, 'Maʿāwil', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (110, 'B. Kalbān', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (111, 'B. Kaʿb', 'tribe', NULL, NULL, NULL, 4, 'editing');
INSERT INTO person_groups (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status) VALUES (112, 'B. Ḥirṯ', 'tribe', NULL, NULL, NULL, 4, 'editing');


--
-- TOC entry 4375 (class 0 OID 31835)
-- Dependencies: 196
-- Data for Name: person_groups_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (65, 'ʿAbrīyīn ', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-04-28 18:15:53.267232', 'INSERT', 1);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (66, 'ʿAbrīyīn', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-04-28 18:17:05.538148', 'INSERT', 2);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (84, 'Āl Bū-Saʿīd', 'tribe', NULL, 'Ruling family of Oman', NULL, 4, 'editing', '2016-04-28 19:37:14.251035', 'INSERT', 3);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (90, 'B. Ḏuhl', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-04-29 08:14:49.784284', 'INSERT', 4);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (92, 'B. ʿAuf ', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-04-29 08:17:09.397345', 'INSERT', 5);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (93, 'Āl Yaʿrub', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-04-29 08:18:22.530073', 'INSERT', 6);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (66, 'ʿAbrīyīn', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-04-29 11:03:08.571185', 'DELETE', 7);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (92, 'B. ʿAwf ', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-02 13:57:40.851118', 'UPDATE', 8);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (101, 'B. Hināʾ ', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-02 14:01:29.314234', 'INSERT', 9);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (102, 'B. Ġāfir', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-02 14:02:27.094002', 'INSERT', 10);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (103, 'B. Ḫarūṣ', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-02 14:03:12.540099', 'INSERT', 11);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (104, 'B. Riyām', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-02 14:06:04.381613', 'INSERT', 12);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (105, 'B. Šukayl ', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-02 14:14:24.626944', 'INSERT', 13);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (65, 'ʿAbrīyīn', 'tribe', NULL, NULL, NULL, 1, 'editing', '2016-05-02 23:08:35.639195', 'UPDATE', 14);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (106, 'B. Ruwāḥa', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-03 13:52:30.393982', 'INSERT', 15);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (107, 'Yaʿāqīb', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-03 13:53:04.432879', 'INSERT', 16);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (108, 'Āl Ramāḥ', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-03 13:54:09.837654', 'INSERT', 17);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (109, 'Maʿāwil', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-03 13:54:52.563694', 'INSERT', 18);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (110, 'B. Kalbān', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-03 13:55:56.652361', 'INSERT', 19);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (111, 'B. Kaʿb', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-03 13:56:22.799596', 'INSERT', 20);
INSERT INTO person_groups_history (id, name_translit, type, name_arabic, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (112, 'B. Ḥirṯ', 'tribe', NULL, NULL, NULL, 4, 'editing', '2016-05-03 13:57:40.803773', 'INSERT', 21);


--
-- TOC entry 4446 (class 0 OID 0)
-- Dependencies: 197
-- Name: person_groups_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('person_groups_history_history_id_seq', 21, true);


--
-- TOC entry 4407 (class 0 OID 32322)
-- Dependencies: 228
-- Data for Name: person_of_group; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO person_of_group (person, person_group) VALUES (67, 65);
INSERT INTO person_of_group (person, person_group) VALUES (85, 84);
INSERT INTO person_of_group (person, person_group) VALUES (94, 93);
INSERT INTO person_of_group (person, person_group) VALUES (95, 93);
INSERT INTO person_of_group (person, person_group) VALUES (96, 93);
INSERT INTO person_of_group (person, person_group) VALUES (97, 93);
INSERT INTO person_of_group (person, person_group) VALUES (98, 93);
INSERT INTO person_of_group (person, person_group) VALUES (113, 90);
INSERT INTO person_of_group (person, person_group) VALUES (114, 90);
INSERT INTO person_of_group (person, person_group) VALUES (115, 90);
INSERT INTO person_of_group (person, person_group) VALUES (116, 90);
INSERT INTO person_of_group (person, person_group) VALUES (117, 84);


--
-- TOC entry 4401 (class 0 OID 32221)
-- Dependencies: 222
-- Data for Name: person_places; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO person_places (person, place, from_year, to_year, edit_user) VALUES (113, 12, NULL, NULL, 4);
INSERT INTO person_places (person, place, from_year, to_year, edit_user) VALUES (114, 12, NULL, NULL, 4);
INSERT INTO person_places (person, place, from_year, to_year, edit_user) VALUES (115, 12, NULL, NULL, 4);
INSERT INTO person_places (person, place, from_year, to_year, edit_user) VALUES (116, 12, NULL, NULL, 4);


--
-- TOC entry 4402 (class 0 OID 32241)
-- Dependencies: 223
-- Data for Name: person_places_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO person_places_history (person, place, from_year, to_year, edit_user, edit_timestamp, edit_action, history_id) VALUES (113, 12, NULL, NULL, 4, '2016-05-03 14:03:12.16015', 'INSERT', 1);
INSERT INTO person_places_history (person, place, from_year, to_year, edit_user, edit_timestamp, edit_action, history_id) VALUES (114, 12, NULL, NULL, 4, '2016-05-03 14:17:19.422913', 'INSERT', 2);
INSERT INTO person_places_history (person, place, from_year, to_year, edit_user, edit_timestamp, edit_action, history_id) VALUES (115, 12, NULL, NULL, 4, '2016-05-03 14:18:59.606031', 'INSERT', 3);
INSERT INTO person_places_history (person, place, from_year, to_year, edit_user, edit_timestamp, edit_action, history_id) VALUES (116, 12, NULL, NULL, 4, '2016-05-03 14:20:02.419967', 'INSERT', 4);


--
-- TOC entry 4447 (class 0 OID 0)
-- Dependencies: 224
-- Name: person_places_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('person_places_history_history_id_seq', 4, true);


--
-- TOC entry 4404 (class 0 OID 32271)
-- Dependencies: 225
-- Data for Name: person_relatives; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO person_relatives (person, relative, type, information, edit_user) VALUES (98, 97, 'child', NULL, 2);


--
-- TOC entry 4405 (class 0 OID 32296)
-- Dependencies: 226
-- Data for Name: person_relatives_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO person_relatives_history (person, relative, type, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (98, 97, 'child', NULL, 2, '2016-04-29 09:39:39.647366', 'INSERT', 1);


--
-- TOC entry 4448 (class 0 OID 0)
-- Dependencies: 227
-- Name: person_relatives_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('person_relatives_history_history_id_seq', 1, true);


--
-- TOC entry 4365 (class 0 OID 31683)
-- Dependencies: 186
-- Data for Name: persons; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (64, 'm', 'Saʿūd b. ʿAlī ', 'Unknown', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (67, 'm', 'Muḥsin b. Zahrān b. Muḥammad ', 'al-ʿAbrī ', NULL, NULL, NULL, NULL, 'šayḫ', 1220, NULL, NULL, NULL, NULL, 1805, 1806, 1290, 3, 12, NULL, NULL, 1873, 1873, NULL, NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (85, 'm', 'Saʿīd b. Sulṭān', 'al-Būsaʿīdī ', NULL, NULL, NULL, NULL, 'sayyid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Ruler of Oman from 1804 to 1856', NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (88, 'm', 'Ḥamād b. Aḥmad ', 'Unknown', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (98, 'm', 'Nāṣir b. Muḥammad b. Sulaymān ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Muḥammad b. Sulaymān; residing in Bahlāʾ
', NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (97, 'm', 'Muḥammad b. Sulaymān', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Governor (wālī) of Bahlāʾ
', NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (95, 'm', 'Sulṭān b. Mālik b. Sayf ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Mālik b. Sayf; residing in al-Ḥazm

', NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (96, 'm', 'Yaʿrub b. Mālik b. Sayf ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Mālik b. Sayf; residing in al-Ḥazm

', NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (79, 'm', 'Ḫalfān b. ʿUwayd', 'Unclear', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father''s name unsure', 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (81, 'm', 'Ḫamīs b. ʿUwayd', 'Unclear', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father''s name unsure', 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (80, 'm', 'Marhūn b. ʿUwayd', 'Unclear', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father''s name unclear', 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (94, 'm', 'Mālik b. Sayf', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Residing in al-Ḥazm
', NULL, 1, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (113, 'm', 'Šāmis b. ʿAbdallāh', 'aḏ-Ḏuhlī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Follower of the ʿAbrīyīn of al-Ḥamrāʾ', NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (114, 'm', 'Nāṣir b. ʿAbdallāh', 'aḏ-Ḏuhlī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (115, 'm', 'ʿAbdallāh b. Šāmis b. ʿAbdallāh', 'aḏ-Ḏuhlī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Šāmis b. ʿAbdallāh', NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (116, 'm', 'Saif b. Nāṣir b. ʿAbdallāh', 'aḏ-Ḏuhlī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (117, 'm', 'Ṯuwainī b. Saʿīd', 'al-Būsaʿīdī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing');
INSERT INTO persons (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status) VALUES (118, 'm', 'Ḥammūd b. ʿAzzān', 'al-Būsaʿīdī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing');


--
-- TOC entry 4366 (class 0 OID 31704)
-- Dependencies: 187
-- Data for Name: persons_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (64, 'm', 'Saʿūd b. ʿAlī ', 'Unknown', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing', '2016-04-28 18:09:45.026008', 'INSERT', 1);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (67, 'm', 'Muḥsin b. Zahrān b. Muḥammad ', 'al-ʿAbrī ', NULL, NULL, NULL, NULL, 'šayḫ', 1220, NULL, NULL, NULL, NULL, 1805, 1806, 1290, 3, 12, NULL, NULL, 1873, 1873, NULL, NULL, 4, 'editing', '2016-04-28 18:18:40.671828', 'INSERT', 2);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (79, 'm', 'Ḫalfān b. ʿUwaid', 'Unclear', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father''s name unsure', 4, 'editing', '2016-04-28 19:29:28.702543', 'INSERT', 3);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (80, 'm', 'Marhūn b. ʿUwaid', 'Unclear', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father''s name unclear', 4, 'editing', '2016-04-28 19:30:49.17872', 'INSERT', 4);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (81, 'm', 'Ḫamīs b. ʿUwaid', 'Unclear', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father''s name unsure', 4, 'editing', '2016-04-28 19:31:47.840585', 'INSERT', 5);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (85, 'm', 'Saʿīd b. Sulṭān', 'al-Būsaʿīdī ', NULL, NULL, NULL, NULL, 'sayyid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Ruler of Oman from 1804 to 1856', NULL, 4, 'editing', '2016-04-28 19:38:07.215348', 'INSERT', 6);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (88, 'm', 'Ḥamād b. Aḥmad ', 'Unknown', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing', '2016-04-28 19:43:54.598715', 'INSERT', 7);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (94, 'm', 'Mālik b. Saif ', 'al-Yaʿrubī ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Residing in al-Ḥazm
', NULL, 4, 'editing', '2016-04-29 08:21:26.886524', 'INSERT', 8);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (95, 'm', 'Sulṭān b. Mālik b. Saif ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Mālik b. Saif; residing in al-Ḥazm

', NULL, 4, 'editing', '2016-04-29 08:30:59.17489', 'INSERT', 9);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (96, 'm', 'Yaʿrub b. Mālik b. Saif ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Mālik b. Saif; residing in al-Ḥazm

', NULL, 4, 'editing', '2016-04-29 08:32:03.82071', 'INSERT', 10);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (97, 'm', 'Muḥammad b. Sulaimān', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Statthalter (wālī) von Bahlāʾ
', NULL, 4, 'editing', '2016-04-29 08:33:29.243631', 'INSERT', 11);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (98, 'm', 'Nāṣir b. Muḥammad b. Sulaimān ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Muḥammad b. Sulaimān; residing in Bahlāʾ
', NULL, 4, 'editing', '2016-04-29 08:34:59.51799', 'INSERT', 12);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (98, 'm', 'Nāṣir b. Muḥammad b. Sulaymān ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Muḥammad b. Sulaymān; residing in Bahlāʾ
', NULL, 4, 'editing', '2016-04-29 08:35:44.04446', 'UPDATE', 13);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (97, 'm', 'Muḥammad b. Sulaymān', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Governor (wālī) of Bahlāʾ
', NULL, 4, 'editing', '2016-04-29 08:36:36.527646', 'UPDATE', 14);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (95, 'm', 'Sulṭān b. Mālik b. Sayf ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Mālik b. Sayf; residing in al-Ḥazm

', NULL, 4, 'editing', '2016-04-29 08:37:10.981015', 'UPDATE', 15);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (96, 'm', 'Yaʿrub b. Mālik b. Sayf ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Mālik b. Sayf; residing in al-Ḥazm

', NULL, 4, 'editing', '2016-04-29 08:37:48.32984', 'UPDATE', 16);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (94, 'm', 'Mālik b. Sayf ', 'al-Yaʿrubī ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Residing in al-Ḥazm
', NULL, 4, 'editing', '2016-04-29 08:38:20.176837', 'UPDATE', 17);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (79, 'm', 'Ḫalfān b. ʿUwayd', 'Unclear', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father''s name unsure', 4, 'editing', '2016-04-29 08:38:43.010911', 'UPDATE', 18);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (81, 'm', 'Ḫamīs b. ʿUwayd', 'Unclear', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father''s name unsure', 4, 'editing', '2016-04-29 08:39:15.139511', 'UPDATE', 19);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (80, 'm', 'Marhūn b. ʿUwayd', 'Unclear', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father''s name unclear', 4, 'editing', '2016-04-29 08:39:33.88556', 'UPDATE', 20);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (94, 'm', 'Mālik b. Sayf ', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Residing in al-Ḥazm
', NULL, 1, 'editing', '2016-05-02 22:57:36.103706', 'UPDATE', 21);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (94, 'm', 'Mālik b. Sayf', 'al-Yaʿrubī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Residing in al-Ḥazm
', NULL, 1, 'editing', '2016-05-02 22:57:41.202803', 'UPDATE', 22);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (113, 'm', 'Šāmis b. ʿAbdallāh', 'aḏ-Ḏuhlī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Follower of the ʿAbrīyīn of al-Ḥamrāʾ', NULL, 4, 'editing', '2016-05-03 14:03:12.133134', 'INSERT', 23);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (114, 'm', 'Nāṣir b. ʿAbdallāh', 'aḏ-Ḏuhlī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing', '2016-05-03 14:17:19.357797', 'INSERT', 24);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (115, 'm', 'ʿAbdallāh b. Šāmis b. ʿAbdallāh', 'aḏ-Ḏuhlī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Son of Šāmis b. ʿAbdallāh', NULL, 4, 'editing', '2016-05-03 14:18:59.554487', 'INSERT', 25);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (116, 'm', 'Saif b. Nāṣir b. ʿAbdallāh', 'aḏ-Ḏuhlī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing', '2016-05-03 14:20:02.380369', 'INSERT', 26);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (117, 'm', 'Ṯuwainī b. Saʿīd', 'al-Būsaʿīdī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing', '2016-05-03 14:24:34.016744', 'INSERT', 27);
INSERT INTO persons_history (id, sex, forename_translit, lastname_translit, byname_translit, forename_arabic, lastname_arabic, byname_arabic, title, birth_year, birth_month, birth_day, birth_year_from, birth_year_to, gregorian_birth_year_lower, gregorian_birth_year_upper, death_year, death_month, death_day, death_year_from, death_year_to, gregorian_death_year_lower, gregorian_death_year_upper, information, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (118, 'm', 'Ḥammūd b. ʿAzzān', 'al-Būsaʿīdī', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 'editing', '2016-05-03 14:25:27.571068', 'INSERT', 28);


--
-- TOC entry 4449 (class 0 OID 0)
-- Dependencies: 188
-- Name: persons_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('persons_history_history_id_seq', 28, true);


--
-- TOC entry 4371 (class 0 OID 31765)
-- Dependencies: 192
-- Data for Name: places; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (8, 'Adam', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (9, 'ʿAmq', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (10, 'ʿArāqī, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (11, 'ʿArīḍ, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (12, 'ʿAwābī, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (13, 'ʿAyn, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (14, 'Baḥlāʾ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (15, 'Barkāʾ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (16, 'Bāt', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (17, 'Baṭina, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (18, 'Bayt al-ʿAynayn', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (19, 'Bayt al-Qarn', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (20, 'Birkat al-Mawz', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (21, 'Bisyāʾ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (22, 'Dāḫilīya, al- ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (23, 'Ḍank', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (24, 'Darīz', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (25, 'Falaǧ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (26, 'Fulayǧ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (27, 'Ġabbī, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (28, 'Ġāfāt, al- ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (29, 'Ḫadrāʾ, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (30, 'Ḥamrāʾ, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (31, 'Ḥawqayn, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (32, 'Ḥazm, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (33, 'ʿIbrī', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (34, 'Istāl ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (35, 'Manaḥ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (36, 'Masqaṭ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (37, 'Mazāḥīṭ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (38, 'Muṣanaʿa, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (39, 'Naḫl', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (40, 'Nizwā', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (41, 'Rustāq', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (42, 'Sayfa', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (43, 'Sayǧāʾ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (44, 'Ṣuḥār', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (45, 'Tīḫa, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (46, 'Umm Ḥimār', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (47, 'Wādī al-Kabīr', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (48, 'Wādī al-Maʿāwil', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (49, 'Wādī Banī Ġāfir', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (50, 'Wādī Banī Ḫarūs', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (51, 'Wādī Faraʿ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (52, 'Wādī Rustāq', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (53, 'Wādī Saḥṭan', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (54, 'Wādī Samāʾil', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (55, 'Wādī Sanaysal', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (56, 'Wādī Šarṣa', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (57, 'Wādī Sayfam', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (58, 'Wahra', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (59, 'Yabrīn', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (60, 'Ẓāhira, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing');
INSERT INTO places (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status) VALUES (91, 'Wādī Banī ʿAwf', NULL, NULL, NULL, 'other', 7, NULL, 4, 'editing');


--
-- TOC entry 4372 (class 0 OID 31786)
-- Dependencies: 193
-- Data for Name: places_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (8, 'Adam', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 1);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (9, 'ʿAmq', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 2);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (10, 'ʿArāqī, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 3);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (11, 'ʿArīḍ, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 4);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (12, 'ʿAwābī, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 5);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (13, 'ʿAyn, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 6);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (14, 'Baḥlāʾ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 7);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (15, 'Barkāʾ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 8);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (16, 'Bāt', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 9);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (17, 'Baṭina, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 10);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (18, 'Bayt al-ʿAynayn', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 11);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (19, 'Bayt al-Qarn', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 12);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (20, 'Birkat al-Mawz', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 13);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (21, 'Bisyāʾ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 14);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (22, 'Dāḫilīya, al- ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 15);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (23, 'Ḍank', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 16);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (24, 'Darīz', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 17);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (25, 'Falaǧ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 18);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (26, 'Fulayǧ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 19);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (27, 'Ġabbī, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 20);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (28, 'Ġāfāt, al- ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 21);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (29, 'Ḫadrāʾ, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 22);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (30, 'Ḥamrāʾ, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 23);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (31, 'Ḥawqayn, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 24);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (32, 'Ḥazm, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 25);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (33, 'ʿIbrī', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 26);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (34, 'Istāl ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 27);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (35, 'Manaḥ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 28);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (36, 'Masqaṭ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 29);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (37, 'Mazāḥīṭ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 30);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (38, 'Muṣanaʿa, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 31);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (39, 'Naḫl', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 32);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (40, 'Nizwā', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 33);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (41, 'Rustāq', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 34);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (42, 'Sayfa', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 35);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (43, 'Sayǧāʾ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 36);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (44, 'Ṣuḥār', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 37);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (45, 'Tīḫa, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 38);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (46, 'Umm Ḥimār', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 39);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (47, 'Wādī al-Kabīr', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 40);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (48, 'Wādī al-Maʿāwil', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 41);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (49, 'Wādī Banī Ġāfir', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 42);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (50, 'Wādī Banī Ḫarūs', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 43);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (51, 'Wādī Faraʿ', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 44);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (52, 'Wādī Rustāq', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 45);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (53, 'Wādī Saḥṭan', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 46);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (54, 'Wādī Samāʾil', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 47);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (55, 'Wādī Sanaysal', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 48);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (56, 'Wādī Šarṣa', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 49);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (57, 'Wādī Sayfam', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 50);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (58, 'Wahra', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 51);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (59, 'Yabrīn', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 52);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (60, 'Ẓāhira, al-', NULL, NULL, NULL, 'settlement', 7, NULL, 1, 'editing', '2016-04-22 10:01:35.270598', 'INSERT', 53);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (91, 'Wādī Banī ʿAuf', NULL, NULL, NULL, 'other', 7, NULL, 4, 'editing', '2016-04-29 08:16:57.349645', 'INSERT', 54);
INSERT INTO places_history (id, name_translit, name_arabic, information, coordinates, type, country_region, edit_note, edit_user, edit_status, edit_timestamp, edit_action, history_id) VALUES (91, 'Wādī Banī ʿAwf', NULL, NULL, NULL, 'other', 7, NULL, 4, 'editing', '2016-05-02 13:59:16.42891', 'UPDATE', 55);


--
-- TOC entry 4450 (class 0 OID 0)
-- Dependencies: 194
-- Name: places_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('places_history_history_id_seq', 55, true);


--
-- TOC entry 4386 (class 0 OID 31992)
-- Dependencies: 207
-- Data for Name: scans; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO scans (id, filename, filepath, filesize, filetype, information, edit_user) VALUES (68, 'D6-9_16A.jpg', 'scans/D6-9_16A.jpg', 2908508, 'image/jpeg', NULL, 4);
INSERT INTO scans (id, filename, filepath, filesize, filetype, information, edit_user) VALUES (70, 'D6-9_18A.jpg', 'scans/D6-9_18A.jpg', 3017678, 'image/jpeg', NULL, 4);
INSERT INTO scans (id, filename, filepath, filesize, filetype, information, edit_user) VALUES (72, 'D-6-9_20A.jpg', 'scans/D-6-9_20A.jpg', 4046722, 'image/jpeg', NULL, 4);
INSERT INTO scans (id, filename, filepath, filesize, filetype, information, edit_user) VALUES (76, 'D6-9_26A.jpg', 'scans/D6-9_26A.jpg', 4126192, 'image/jpeg', NULL, 4);
INSERT INTO scans (id, filename, filepath, filesize, filetype, information, edit_user) VALUES (78, 'D6-9_27A.jpg', 'scans/D6-9_27A.jpg', 4201550, 'image/jpeg', NULL, 4);
INSERT INTO scans (id, filename, filepath, filesize, filetype, information, edit_user) VALUES (83, 'D6-9_28A.jpg', 'scans/D6-9_28A.jpg', 4086242, 'image/jpeg', NULL, 4);
INSERT INTO scans (id, filename, filepath, filesize, filetype, information, edit_user) VALUES (87, 'D6-9_30A.jpg', 'scans/D6-9_30A.jpg', 4084642, 'image/jpeg', NULL, 4);
INSERT INTO scans (id, filename, filepath, filesize, filetype, information, edit_user) VALUES (100, 'D6-9_17A.jpg', 'scans/D6-9_17A.jpg', 2907129, 'image/jpeg', NULL, 4);
INSERT INTO scans (id, filename, filepath, filesize, filetype, information, edit_user) VALUES (74, 'D6-9_22A.jpg', 'scans/D6-9_22A.jpg', 4075593, 'image/jpeg', NULL, 1);


--
-- TOC entry 4387 (class 0 OID 32009)
-- Dependencies: 208
-- Data for Name: scans_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (61, 'flo.jpg', 'scans/flo.jpg', 484589, 'image/jpeg', 'Flo', 1, '2016-04-25 11:16:14.292223', 'INSERT', 1);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (61, 'flo.jpg', 'scans/flo.jpg', 484589, 'image/jpeg', 'Flo', 1, '2016-04-25 11:16:20.199183', 'DELETE', 2);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (68, 'D6-9_16A.jpg', 'scans/D6-9_16A.jpg', 2908508, 'image/jpeg', NULL, 4, '2016-04-28 18:49:44.1167', 'INSERT', 3);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (70, 'D6-9_18A.jpg', 'scans/D6-9_18A.jpg', 3017678, 'image/jpeg', NULL, 4, '2016-04-28 18:56:04.89319', 'INSERT', 4);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (72, 'D-6-9_20A.jpg', 'scans/D-6-9_20A.jpg', 4046722, 'image/jpeg', NULL, 4, '2016-04-28 19:01:59.377746', 'INSERT', 5);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (74, 'D-6-9_22A.jpg', 'scans/D-6-9_22A.jpg', 4075593, 'image/jpeg', NULL, 4, '2016-04-28 19:15:04.930521', 'INSERT', 6);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (76, 'D6-9_26A.jpg', 'scans/D6-9_26A.jpg', 4126192, 'image/jpeg', NULL, 4, '2016-04-28 19:17:52.427457', 'INSERT', 7);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (78, 'D6-9_27A.jpg', 'scans/D6-9_27A.jpg', 4201550, 'image/jpeg', NULL, 4, '2016-04-28 19:23:56.16565', 'INSERT', 8);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (83, 'D6-9_28A.jpg', 'scans/D6-9_28A.jpg', 4086242, 'image/jpeg', NULL, 4, '2016-04-28 19:34:38.652136', 'INSERT', 9);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (87, 'D6-9_30A.jpg', 'scans/D6-9_30A.jpg', 4084642, 'image/jpeg', NULL, 4, '2016-04-28 19:42:31.046058', 'INSERT', 10);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (100, 'D6-9_17A.jpg', 'scans/D6-9_17A.jpg', 2907129, 'image/jpeg', NULL, 4, '2016-04-29 10:05:07.898243', 'INSERT', 11);
INSERT INTO scans_history (id, filename, filepath, filesize, filetype, information, edit_user, edit_timestamp, edit_action, history_id) VALUES (74, 'D6-9_22A.jpg', 'scans/D6-9_22A.jpg', 4075593, 'image/jpeg', NULL, 1, '2016-04-29 10:53:05.679813', 'UPDATE', 12);


--
-- TOC entry 4451 (class 0 OID 0)
-- Dependencies: 209
-- Name: scans_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('scans_history_history_id_seq', 12, true);


--
-- TOC entry 4377 (class 0 OID 31861)
-- Dependencies: 198
-- Data for Name: sources; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4378 (class 0 OID 31875)
-- Dependencies: 199
-- Data for Name: sources_history; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4452 (class 0 OID 0)
-- Dependencies: 200
-- Name: sources_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('sources_history_history_id_seq', 1, false);


--
-- TOC entry 4039 (class 0 OID 22132)
-- Dependencies: 169
-- Data for Name: spatial_ref_sys; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- TOC entry 4453 (class 0 OID 0)
-- Dependencies: 182
-- Name: unique_object_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('unique_object_id_seq', 118, true);


--
-- TOC entry 4412 (class 0 OID 32384)
-- Dependencies: 233
-- Data for Name: user_sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-22 13:00:58.545961');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-22 15:47:33.080957');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-25 11:04:28.08562');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-25 14:00:33.128597');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (5, 'login', '2016-04-25 14:28:24.663334');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-26 10:57:41.42715');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (6, 'login', '2016-04-27 16:24:13.138145');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (3, 'login', '2016-04-28 14:16:25.244626');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (2, 'login', '2016-04-28 16:12:34.519865');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (4, 'login', '2016-04-28 18:02:17.529476');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (2, 'login', '2016-04-28 19:43:20.775969');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-28 22:38:02.220249');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (4, 'login', '2016-04-29 08:13:59.980734');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (2, 'login', '2016-04-29 09:30:49.661204');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (4, 'login', '2016-04-29 10:01:09.655766');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (2, 'login', '2016-04-29 10:32:51.8735');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-29 10:32:58.099115');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-29 11:07:33.304032');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-29 11:08:05.202942');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-04-29 11:28:46.623025');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-05-02 11:29:57.019484');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (4, 'login', '2016-05-02 12:56:38.702867');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (4, 'login', '2016-05-02 13:09:06.626837');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (4, 'login', '2016-05-02 13:29:24.349923');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-05-02 19:44:56.279578');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (4, 'login', '2016-05-03 13:26:46.656142');
INSERT INTO user_sessions (user_id, action, "timestamp") VALUES (1, 'login', '2016-05-03 13:47:39.403111');


--
-- TOC entry 4362 (class 0 OID 31633)
-- Dependencies: 183
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO users (id, name, email, password, role, edit_user) VALUES (1, 'Michael Derntl', 'michael.derntl@uni-tuebingen.de', 'cc03e747a6afbbcbf8be7668acfebee5', 'admin', NULL);
INSERT INTO users (id, name, email, password, role, edit_user) VALUES (2, 'Fabian Schwabe', 'fabian.schwabe@uni-tuebingen.de', '81dc9bdb52d04dc20036dbd8313ed055', 'admin', NULL);
INSERT INTO users (id, name, email, password, role, edit_user) VALUES (3, 'Johann Büssow', 'johann.buessow@uni-tuebingen.de', '20ea802bd1fd66abb4ccc9037ef3af34', 'admin', NULL);
INSERT INTO users (id, name, email, password, role, edit_user) VALUES (4, 'Michaela Hoffmann-Ruf', 'michaela.hoffmann-ruf@uni-tuebingen.de', '20ea802bd1fd66abb4ccc9037ef3af34', 'admin', NULL);
INSERT INTO users (id, name, email, password, role, edit_user) VALUES (5, 'Steve Kaminski', 'steve.kaminski@uni-tuebingen.de', '20ea802bd1fd66abb4ccc9037ef3af34', 'user', NULL);
INSERT INTO users (id, name, email, password, role, edit_user) VALUES (6, 'Matthias Lang', 'matthias.lang@uni-tuebingen.de', '20ea802bd1fd66abb4ccc9037ef3af34', 'user', NULL);


--
-- TOC entry 4363 (class 0 OID 31648)
-- Dependencies: 184
-- Data for Name: users_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO users_history (id, name, email, password, role, edit_user, edit_timestamp, edit_action, history_id) VALUES (1, 'Michael Derntl', 'michael.derntl@uni-tuebingen.de', 'cc03e747a6afbbcbf8be7668acfebee5', 'admin', NULL, '2016-04-22 10:01:21.515872', 'INSERT', 1);
INSERT INTO users_history (id, name, email, password, role, edit_user, edit_timestamp, edit_action, history_id) VALUES (2, 'Fabian Schwabe', 'fabian.schwabe@uni-tuebingen.de', '81dc9bdb52d04dc20036dbd8313ed055', 'admin', NULL, '2016-04-22 10:01:21.515872', 'INSERT', 2);
INSERT INTO users_history (id, name, email, password, role, edit_user, edit_timestamp, edit_action, history_id) VALUES (3, 'Johann Büssow', 'johann.buessow@uni-tuebingen.de', '20ea802bd1fd66abb4ccc9037ef3af34', 'admin', NULL, '2016-04-22 10:01:21.515872', 'INSERT', 3);
INSERT INTO users_history (id, name, email, password, role, edit_user, edit_timestamp, edit_action, history_id) VALUES (4, 'Michaela Hoffmann-Ruf', 'michaela.hoffmann-ruf@uni-tuebingen.de', '20ea802bd1fd66abb4ccc9037ef3af34', 'admin', NULL, '2016-04-22 10:01:21.515872', 'INSERT', 4);
INSERT INTO users_history (id, name, email, password, role, edit_user, edit_timestamp, edit_action, history_id) VALUES (5, 'Steve Kaminski', 'steve.kaminski@uni-tuebingen.de', '20ea802bd1fd66abb4ccc9037ef3af34', 'user', NULL, '2016-04-22 10:01:21.515872', 'INSERT', 5);
INSERT INTO users_history (id, name, email, password, role, edit_user, edit_timestamp, edit_action, history_id) VALUES (6, 'Matthias Lang', 'matthias.lang@uni-tuebingen.de', '20ea802bd1fd66abb4ccc9037ef3af34', 'user', NULL, '2016-04-22 10:01:21.515872', 'INSERT', 6);


--
-- TOC entry 4454 (class 0 OID 0)
-- Dependencies: 185
-- Name: users_history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('users_history_history_id_seq', 6, true);


--
-- TOC entry 4185 (class 2606 OID 32378)
-- Name: bibliographic_references_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY bibliographic_references_history
    ADD CONSTRAINT bibliographic_references_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4183 (class 2606 OID 32356)
-- Name: bibliographic_references_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY bibliographic_references
    ADD CONSTRAINT bibliographic_references_pkey PRIMARY KEY (object, source);


--
-- TOC entry 4123 (class 2606 OID 31754)
-- Name: countries_and_regions_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY countries_and_regions_history
    ADD CONSTRAINT countries_and_regions_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4119 (class 2606 OID 31737)
-- Name: countries_and_regions_name_key; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY countries_and_regions
    ADD CONSTRAINT countries_and_regions_name_key UNIQUE (name);


--
-- TOC entry 4121 (class 2606 OID 31735)
-- Name: countries_and_regions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY countries_and_regions
    ADD CONSTRAINT countries_and_regions_pkey PRIMARY KEY (id);


--
-- TOC entry 4107 (class 2606 OID 23691)
-- Name: document_address_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_address
    ADD CONSTRAINT document_address_pkey PRIMARY KEY (document, person);


--
-- TOC entry 4165 (class 2606 OID 32170)
-- Name: document_addresses_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_addresses_history
    ADD CONSTRAINT document_addresses_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4163 (class 2606 OID 32138)
-- Name: document_addresses_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_addresses
    ADD CONSTRAINT document_addresses_pkey PRIMARY KEY (document, person);


--
-- TOC entry 4161 (class 2606 OID 32122)
-- Name: document_authors_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_authors
    ADD CONSTRAINT document_authors_pkey PRIMARY KEY (document, person);


--
-- TOC entry 4157 (class 2606 OID 32092)
-- Name: document_keywords_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_keywords
    ADD CONSTRAINT document_keywords_pkey PRIMARY KEY (document, keyword);


--
-- TOC entry 4169 (class 2606 OID 32215)
-- Name: document_persons_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_persons_history
    ADD CONSTRAINT document_persons_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4167 (class 2606 OID 32188)
-- Name: document_persons_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_persons
    ADD CONSTRAINT document_persons_pkey PRIMARY KEY (document, person);


--
-- TOC entry 4159 (class 2606 OID 32107)
-- Name: document_places_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_places
    ADD CONSTRAINT document_places_pkey PRIMARY KEY (document, place);


--
-- TOC entry 4187 (class 2606 OID 34073)
-- Name: document_scans_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_scans
    ADD CONSTRAINT document_scans_pkey PRIMARY KEY (document, scan);


--
-- TOC entry 4155 (class 2606 OID 32082)
-- Name: document_to_document_references_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_to_document_references_history
    ADD CONSTRAINT document_to_document_references_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4153 (class 2606 OID 32055)
-- Name: document_to_document_references_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY document_to_document_references
    ADD CONSTRAINT document_to_document_references_pkey PRIMARY KEY (source_doc, target_doc);


--
-- TOC entry 4145 (class 2606 OID 31983)
-- Name: documents_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY documents_history
    ADD CONSTRAINT documents_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4143 (class 2606 OID 31950)
-- Name: documents_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY documents
    ADD CONSTRAINT documents_pkey PRIMARY KEY (id);


--
-- TOC entry 4141 (class 2606 OID 31925)
-- Name: keywords_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY keywords_history
    ADD CONSTRAINT keywords_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4137 (class 2606 OID 31908)
-- Name: keywords_keyword_key; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY keywords
    ADD CONSTRAINT keywords_keyword_key UNIQUE (keyword);


--
-- TOC entry 4139 (class 2606 OID 31906)
-- Name: keywords_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY keywords
    ADD CONSTRAINT keywords_pkey PRIMARY KEY (id);


--
-- TOC entry 4181 (class 2606 OID 32341)
-- Name: person_group_places_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY person_group_places
    ADD CONSTRAINT person_group_places_pkey PRIMARY KEY (person_group, place);


--
-- TOC entry 4131 (class 2606 OID 31852)
-- Name: person_groups_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY person_groups_history
    ADD CONSTRAINT person_groups_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4129 (class 2606 OID 31829)
-- Name: person_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY person_groups
    ADD CONSTRAINT person_groups_pkey PRIMARY KEY (id);


--
-- TOC entry 4179 (class 2606 OID 32326)
-- Name: person_of_group_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY person_of_group
    ADD CONSTRAINT person_of_group_pkey PRIMARY KEY (person, person_group);


--
-- TOC entry 4173 (class 2606 OID 32252)
-- Name: person_places_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY person_places_history
    ADD CONSTRAINT person_places_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4171 (class 2606 OID 32225)
-- Name: person_places_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY person_places
    ADD CONSTRAINT person_places_pkey PRIMARY KEY (person, place);


--
-- TOC entry 4177 (class 2606 OID 32313)
-- Name: person_relatives_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY person_relatives_history
    ADD CONSTRAINT person_relatives_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4175 (class 2606 OID 32280)
-- Name: person_relatives_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY person_relatives
    ADD CONSTRAINT person_relatives_pkey PRIMARY KEY (person, relative);


--
-- TOC entry 4117 (class 2606 OID 31721)
-- Name: persons_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY persons_history
    ADD CONSTRAINT persons_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4115 (class 2606 OID 31698)
-- Name: persons_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY persons
    ADD CONSTRAINT persons_pkey PRIMARY KEY (id);


--
-- TOC entry 4127 (class 2606 OID 31803)
-- Name: places_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY places_history
    ADD CONSTRAINT places_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4125 (class 2606 OID 31775)
-- Name: places_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY places
    ADD CONSTRAINT places_pkey PRIMARY KEY (id);


--
-- TOC entry 4147 (class 2606 OID 32003)
-- Name: scans_filepath_key; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY scans
    ADD CONSTRAINT scans_filepath_key UNIQUE (filepath);


--
-- TOC entry 4151 (class 2606 OID 32026)
-- Name: scans_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY scans_history
    ADD CONSTRAINT scans_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4149 (class 2606 OID 32001)
-- Name: scans_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY scans
    ADD CONSTRAINT scans_pkey PRIMARY KEY (id);


--
-- TOC entry 4135 (class 2606 OID 31892)
-- Name: sources_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY sources_history
    ADD CONSTRAINT sources_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4133 (class 2606 OID 31869)
-- Name: sources_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY sources
    ADD CONSTRAINT sources_pkey PRIMARY KEY (id);


--
-- TOC entry 4109 (class 2606 OID 31642)
-- Name: users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- TOC entry 4113 (class 2606 OID 31659)
-- Name: users_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY users_history
    ADD CONSTRAINT users_history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4111 (class 2606 OID 31640)
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- TOC entry 4244 (class 2620 OID 32383)
-- Name: bibliographic_references_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER bibliographic_references_history_trigger AFTER INSERT OR DELETE OR UPDATE ON bibliographic_references FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4232 (class 2620 OID 31759)
-- Name: countries_and_regions_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER countries_and_regions_history_trigger AFTER INSERT OR DELETE OR UPDATE ON countries_and_regions FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4240 (class 2620 OID 32175)
-- Name: document_addresses_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER document_addresses_history_trigger AFTER INSERT OR DELETE OR UPDATE ON document_addresses FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4241 (class 2620 OID 32220)
-- Name: document_persons_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER document_persons_history_trigger AFTER INSERT OR DELETE OR UPDATE ON document_persons FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4239 (class 2620 OID 32087)
-- Name: document_to_document_references_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER document_to_document_references_history_trigger AFTER INSERT OR DELETE OR UPDATE ON document_to_document_references FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4237 (class 2620 OID 31991)
-- Name: documents_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER documents_history_trigger AFTER INSERT OR DELETE OR UPDATE ON documents FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4236 (class 2620 OID 31930)
-- Name: keywords_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER keywords_history_trigger AFTER INSERT OR DELETE OR UPDATE ON keywords FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4234 (class 2620 OID 31860)
-- Name: person_groups_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER person_groups_history_trigger AFTER INSERT OR DELETE OR UPDATE ON person_groups FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4242 (class 2620 OID 32257)
-- Name: person_places_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER person_places_history_trigger AFTER INSERT OR DELETE OR UPDATE ON person_places FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4243 (class 2620 OID 32321)
-- Name: person_relatives_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER person_relatives_history_trigger AFTER INSERT OR DELETE OR UPDATE ON person_relatives FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4231 (class 2620 OID 31729)
-- Name: persons_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER persons_history_trigger AFTER INSERT OR DELETE OR UPDATE ON persons FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4233 (class 2620 OID 31811)
-- Name: places_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER places_history_trigger AFTER INSERT OR DELETE OR UPDATE ON places FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4238 (class 2620 OID 32034)
-- Name: scans_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER scans_history_trigger AFTER INSERT OR DELETE OR UPDATE ON scans FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4235 (class 2620 OID 31900)
-- Name: sources_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER sources_history_trigger AFTER INSERT OR DELETE OR UPDATE ON sources FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4230 (class 2620 OID 31664)
-- Name: users_history_trigger; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER users_history_trigger AFTER INSERT OR DELETE OR UPDATE ON users FOR EACH ROW EXECUTE PROCEDURE update_history_table();


--
-- TOC entry 4226 (class 2606 OID 32362)
-- Name: bibliographic_references_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY bibliographic_references
    ADD CONSTRAINT bibliographic_references_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4225 (class 2606 OID 32357)
-- Name: bibliographic_references_source_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY bibliographic_references
    ADD CONSTRAINT bibliographic_references_source_fkey FOREIGN KEY (source) REFERENCES sources(id) ON UPDATE CASCADE;


--
-- TOC entry 4190 (class 2606 OID 31738)
-- Name: countries_and_regions_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY countries_and_regions
    ADD CONSTRAINT countries_and_regions_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4208 (class 2606 OID 32139)
-- Name: document_addresses_document_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_addresses
    ADD CONSTRAINT document_addresses_document_fkey FOREIGN KEY (document) REFERENCES documents(id) ON UPDATE CASCADE;


--
-- TOC entry 4211 (class 2606 OID 32154)
-- Name: document_addresses_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_addresses
    ADD CONSTRAINT document_addresses_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4209 (class 2606 OID 32144)
-- Name: document_addresses_person_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_addresses
    ADD CONSTRAINT document_addresses_person_fkey FOREIGN KEY (person) REFERENCES persons(id) ON UPDATE CASCADE;


--
-- TOC entry 4210 (class 2606 OID 32149)
-- Name: document_addresses_place_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_addresses
    ADD CONSTRAINT document_addresses_place_fkey FOREIGN KEY (place) REFERENCES places(id) ON UPDATE CASCADE;


--
-- TOC entry 4206 (class 2606 OID 32123)
-- Name: document_authors_document_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_authors
    ADD CONSTRAINT document_authors_document_fkey FOREIGN KEY (document) REFERENCES documents(id) ON UPDATE CASCADE;


--
-- TOC entry 4207 (class 2606 OID 32128)
-- Name: document_authors_person_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_authors
    ADD CONSTRAINT document_authors_person_fkey FOREIGN KEY (person) REFERENCES persons(id) ON UPDATE CASCADE;


--
-- TOC entry 4202 (class 2606 OID 32093)
-- Name: document_keywords_document_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_keywords
    ADD CONSTRAINT document_keywords_document_fkey FOREIGN KEY (document) REFERENCES documents(id) ON UPDATE CASCADE;


--
-- TOC entry 4203 (class 2606 OID 32098)
-- Name: document_keywords_keyword_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_keywords
    ADD CONSTRAINT document_keywords_keyword_fkey FOREIGN KEY (keyword) REFERENCES keywords(id) ON UPDATE CASCADE;


--
-- TOC entry 4212 (class 2606 OID 32189)
-- Name: document_persons_document_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_persons
    ADD CONSTRAINT document_persons_document_fkey FOREIGN KEY (document) REFERENCES documents(id) ON UPDATE CASCADE;


--
-- TOC entry 4214 (class 2606 OID 32199)
-- Name: document_persons_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_persons
    ADD CONSTRAINT document_persons_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4213 (class 2606 OID 32194)
-- Name: document_persons_person_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_persons
    ADD CONSTRAINT document_persons_person_fkey FOREIGN KEY (person) REFERENCES persons(id) ON UPDATE CASCADE;


--
-- TOC entry 4204 (class 2606 OID 32108)
-- Name: document_places_document_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_places
    ADD CONSTRAINT document_places_document_fkey FOREIGN KEY (document) REFERENCES documents(id) ON UPDATE CASCADE;


--
-- TOC entry 4205 (class 2606 OID 32113)
-- Name: document_places_place_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_places
    ADD CONSTRAINT document_places_place_fkey FOREIGN KEY (place) REFERENCES places(id) ON UPDATE CASCADE;


--
-- TOC entry 4229 (class 2606 OID 34074)
-- Name: document_scans_document_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_scans
    ADD CONSTRAINT document_scans_document_fkey FOREIGN KEY (document) REFERENCES documents(id) ON UPDATE CASCADE;


--
-- TOC entry 4228 (class 2606 OID 34079)
-- Name: document_scans_scan_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_scans
    ADD CONSTRAINT document_scans_scan_fkey FOREIGN KEY (scan) REFERENCES scans(id) ON UPDATE CASCADE;


--
-- TOC entry 4201 (class 2606 OID 32066)
-- Name: document_to_document_references_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_to_document_references
    ADD CONSTRAINT document_to_document_references_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4199 (class 2606 OID 32056)
-- Name: document_to_document_references_source_doc_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_to_document_references
    ADD CONSTRAINT document_to_document_references_source_doc_fkey FOREIGN KEY (source_doc) REFERENCES documents(id) ON UPDATE CASCADE;


--
-- TOC entry 4200 (class 2606 OID 32061)
-- Name: document_to_document_references_target_doc_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY document_to_document_references
    ADD CONSTRAINT document_to_document_references_target_doc_fkey FOREIGN KEY (target_doc) REFERENCES documents(id) ON UPDATE CASCADE;


--
-- TOC entry 4197 (class 2606 OID 31961)
-- Name: documents_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY documents
    ADD CONSTRAINT documents_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4196 (class 2606 OID 31951)
-- Name: documents_physical_location_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY documents
    ADD CONSTRAINT documents_physical_location_fkey FOREIGN KEY (physical_location) REFERENCES places(id) ON UPDATE CASCADE;


--
-- TOC entry 4195 (class 2606 OID 31909)
-- Name: keywords_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY keywords
    ADD CONSTRAINT keywords_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4223 (class 2606 OID 32342)
-- Name: person_group_places_person_group_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_group_places
    ADD CONSTRAINT person_group_places_person_group_fkey FOREIGN KEY (person_group) REFERENCES person_groups(id) ON UPDATE CASCADE;


--
-- TOC entry 4224 (class 2606 OID 32347)
-- Name: person_group_places_place_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_group_places
    ADD CONSTRAINT person_group_places_place_fkey FOREIGN KEY (place) REFERENCES places(id) ON UPDATE CASCADE;


--
-- TOC entry 4193 (class 2606 OID 31830)
-- Name: person_groups_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_groups
    ADD CONSTRAINT person_groups_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4221 (class 2606 OID 32327)
-- Name: person_of_group_person_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_of_group
    ADD CONSTRAINT person_of_group_person_fkey FOREIGN KEY (person) REFERENCES persons(id) ON UPDATE CASCADE;


--
-- TOC entry 4222 (class 2606 OID 32332)
-- Name: person_of_group_person_group_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_of_group
    ADD CONSTRAINT person_of_group_person_group_fkey FOREIGN KEY (person_group) REFERENCES person_groups(id) ON UPDATE CASCADE;


--
-- TOC entry 4217 (class 2606 OID 32236)
-- Name: person_places_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_places
    ADD CONSTRAINT person_places_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4215 (class 2606 OID 32226)
-- Name: person_places_person_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_places
    ADD CONSTRAINT person_places_person_fkey FOREIGN KEY (person) REFERENCES persons(id) ON UPDATE CASCADE;


--
-- TOC entry 4216 (class 2606 OID 32231)
-- Name: person_places_place_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_places
    ADD CONSTRAINT person_places_place_fkey FOREIGN KEY (place) REFERENCES places(id) ON UPDATE CASCADE;


--
-- TOC entry 4220 (class 2606 OID 32291)
-- Name: person_relatives_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_relatives
    ADD CONSTRAINT person_relatives_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4218 (class 2606 OID 32281)
-- Name: person_relatives_person_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_relatives
    ADD CONSTRAINT person_relatives_person_fkey FOREIGN KEY (person) REFERENCES persons(id) ON UPDATE CASCADE;


--
-- TOC entry 4219 (class 2606 OID 32286)
-- Name: person_relatives_relative_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY person_relatives
    ADD CONSTRAINT person_relatives_relative_fkey FOREIGN KEY (relative) REFERENCES persons(id) ON UPDATE CASCADE;


--
-- TOC entry 4189 (class 2606 OID 31699)
-- Name: persons_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY persons
    ADD CONSTRAINT persons_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4191 (class 2606 OID 31776)
-- Name: places_country_region_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY places
    ADD CONSTRAINT places_country_region_fkey FOREIGN KEY (country_region) REFERENCES countries_and_regions(id) ON UPDATE CASCADE;


--
-- TOC entry 4192 (class 2606 OID 31781)
-- Name: places_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY places
    ADD CONSTRAINT places_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4198 (class 2606 OID 32004)
-- Name: scans_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY scans
    ADD CONSTRAINT scans_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4194 (class 2606 OID 31870)
-- Name: sources_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY sources
    ADD CONSTRAINT sources_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4227 (class 2606 OID 32388)
-- Name: user_sessions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY user_sessions
    ADD CONSTRAINT user_sessions_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(id);


--
-- TOC entry 4188 (class 2606 OID 31643)
-- Name: users_edit_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_edit_user_fkey FOREIGN KEY (edit_user) REFERENCES users(id) ON UPDATE CASCADE;


--
-- TOC entry 4420 (class 0 OID 0)
-- Dependencies: 5
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


-- Completed on 2016-05-04 14:09:51

--
-- PostgreSQL database dump complete
--

