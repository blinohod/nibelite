--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

--
-- Name: voting; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA voting;


--
-- Name: SCHEMA voting; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA voting IS 'Mobile voting services';


SET search_path = voting, pg_catalog;

--
-- Name: already_voted(character varying, integer); Type: FUNCTION; Schema: voting; Owner: -
--

CREATE FUNCTION already_voted(in_msisdn character varying, in_voting_id integer) RETURNS boolean
    LANGUAGE plpgsql
    AS $$declare
	v_id integer;
begin
	select into v_id v.id
		from voting.votes v
		join voting.answers a on (a.id = v.answer_id)
		where v.msisdn = in_msisdn and a.voting_id = in_voting_id;

	if found then
		return 'true';
	else
		return 'false';
	end if;

end$$;


--
-- Name: FUNCTION already_voted(in_msisdn character varying, in_voting_id integer); Type: COMMENT; Schema: voting; Owner: -
--

COMMENT ON FUNCTION already_voted(in_msisdn character varying, in_voting_id integer) IS 'Check if subscriber already participated - for service with multivote disabled';


--
-- Name: find_voting_info(character varying, character varying); Type: FUNCTION; Schema: voting; Owner: -
--

CREATE FUNCTION find_voting_info(in_query character varying, in_sn character varying) RETURNS TABLE(out_voting_id integer, out_answer_id integer, out_multivote boolean)
    LANGUAGE sql
    AS $_$
select
	v.id,
	a.id,
	v.multivote
	from voting.votings v
	join voting.answers a on (a.voting_id = v.id)
	where v.active = true
	and $1 ~* a.keyword
	and ($2 = v.sn or v.sn = '')
	and now() between v.since and v.till
	order by v.id desc limit 1;

$_$;


--
-- Name: FUNCTION find_voting_info(in_query character varying, in_sn character varying); Type: COMMENT; Schema: voting; Owner: -
--

COMMENT ON FUNCTION find_voting_info(in_query character varying, in_sn character varying) IS 'Return voting related information';


SET default_tablespace = '';

SET default_with_oids = true;

--
-- Name: answers; Type: TABLE; Schema: voting; Owner: -; Tablespace: 
--

CREATE TABLE answers (
    id integer NOT NULL,
    voting_id integer NOT NULL,
    descr character varying(256),
    num_votes integer DEFAULT 0 NOT NULL,
    keyword character varying(128),
    msg_voted_ok character varying(512) DEFAULT ''::character varying NOT NULL
);


--
-- Name: TABLE answers; Type: COMMENT; Schema: voting; Owner: -
--

COMMENT ON TABLE answers IS 'Available answers';


--
-- Name: answers_id_seq; Type: SEQUENCE; Schema: voting; Owner: -
--

CREATE SEQUENCE answers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: answers_id_seq; Type: SEQUENCE OWNED BY; Schema: voting; Owner: -
--

ALTER SEQUENCE answers_id_seq OWNED BY answers.id;


--
-- Name: answers_id_seq; Type: SEQUENCE SET; Schema: voting; Owner: -
--

SELECT pg_catalog.setval('answers_id_seq', 1, false);


SET default_with_oids = false;

--
-- Name: replies; Type: TABLE; Schema: voting; Owner: -; Tablespace: 
--

CREATE TABLE replies (
    id integer NOT NULL,
    voting_id integer DEFAULT 0 NOT NULL,
    answer_id integer DEFAULT 0 NOT NULL,
    reply character varying(1024) DEFAULT ''::character varying NOT NULL
);


--
-- Name: replies_id_seq; Type: SEQUENCE; Schema: voting; Owner: -
--

CREATE SEQUENCE replies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: replies_id_seq; Type: SEQUENCE OWNED BY; Schema: voting; Owner: -
--

ALTER SEQUENCE replies_id_seq OWNED BY replies.id;


--
-- Name: replies_id_seq; Type: SEQUENCE SET; Schema: voting; Owner: -
--

SELECT pg_catalog.setval('replies_id_seq', 1, false);


SET default_with_oids = true;

--
-- Name: votes; Type: TABLE; Schema: voting; Owner: -; Tablespace: 
--

CREATE TABLE votes (
    id bigint NOT NULL,
    msisdn character varying(16) NOT NULL,
    answer_id integer NOT NULL,
    vote_time timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: TABLE votes; Type: COMMENT; Schema: voting; Owner: -
--

COMMENT ON TABLE votes IS 'Votes logging';


--
-- Name: votes_id_seq; Type: SEQUENCE; Schema: voting; Owner: -
--

CREATE SEQUENCE votes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: votes_id_seq; Type: SEQUENCE OWNED BY; Schema: voting; Owner: -
--

ALTER SEQUENCE votes_id_seq OWNED BY votes.id;


--
-- Name: votes_id_seq; Type: SEQUENCE SET; Schema: voting; Owner: -
--

SELECT pg_catalog.setval('votes_id_seq', 1, false);


--
-- Name: votings; Type: TABLE; Schema: voting; Owner: -; Tablespace: 
--

CREATE TABLE votings (
    id integer NOT NULL,
    active boolean DEFAULT true NOT NULL,
    descr character varying(256),
    since timestamp without time zone DEFAULT now() NOT NULL,
    till timestamp without time zone DEFAULT (now() + '1 day'::interval) NOT NULL,
    login character varying(64),
    passwd character varying(64),
    multivote boolean DEFAULT true NOT NULL,
    sn character varying(6),
    msg_help character varying(512) DEFAULT ''::character varying NOT NULL,
    msg_notfound character varying(512) DEFAULT ''::character varying NOT NULL,
    msg_rejected character varying(512) DEFAULT ''::character varying NOT NULL,
    CONSTRAINT votings_check CHECK ((till >= since))
);


--
-- Name: TABLE votings; Type: COMMENT; Schema: voting; Owner: -
--

COMMENT ON TABLE votings IS 'Votings';


--
-- Name: COLUMN votings.msg_help; Type: COMMENT; Schema: voting; Owner: -
--

COMMENT ON COLUMN votings.msg_help IS 'help message - duplicated';


--
-- Name: COLUMN votings.msg_notfound; Type: COMMENT; Schema: voting; Owner: -
--

COMMENT ON COLUMN votings.msg_notfound IS 'code not found - duplicated';


--
-- Name: COLUMN votings.msg_rejected; Type: COMMENT; Schema: voting; Owner: -
--

COMMENT ON COLUMN votings.msg_rejected IS 'code found but vote rejected';


--
-- Name: votings_id_seq; Type: SEQUENCE; Schema: voting; Owner: -
--

CREATE SEQUENCE votings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: votings_id_seq; Type: SEQUENCE OWNED BY; Schema: voting; Owner: -
--

ALTER SEQUENCE votings_id_seq OWNED BY votings.id;


--
-- Name: votings_id_seq; Type: SEQUENCE SET; Schema: voting; Owner: -
--

SELECT pg_catalog.setval('votings_id_seq', 1, false);


--
-- Name: id; Type: DEFAULT; Schema: voting; Owner: -
--

ALTER TABLE answers ALTER COLUMN id SET DEFAULT nextval('answers_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: voting; Owner: -
--

ALTER TABLE replies ALTER COLUMN id SET DEFAULT nextval('replies_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: voting; Owner: -
--

ALTER TABLE votes ALTER COLUMN id SET DEFAULT nextval('votes_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: voting; Owner: -
--

ALTER TABLE votings ALTER COLUMN id SET DEFAULT nextval('votings_id_seq'::regclass);


--
-- Data for Name: answers; Type: TABLE DATA; Schema: voting; Owner: -
--



--
-- Data for Name: replies; Type: TABLE DATA; Schema: voting; Owner: -
--



--
-- Data for Name: votes; Type: TABLE DATA; Schema: voting; Owner: -
--



--
-- Data for Name: votings; Type: TABLE DATA; Schema: voting; Owner: -
--



--
-- Name: answers_pkey; Type: CONSTRAINT; Schema: voting; Owner: -; Tablespace: 
--

ALTER TABLE ONLY answers
    ADD CONSTRAINT answers_pkey PRIMARY KEY (id);


--
-- Name: replies_pkey; Type: CONSTRAINT; Schema: voting; Owner: -; Tablespace: 
--

ALTER TABLE ONLY replies
    ADD CONSTRAINT replies_pkey PRIMARY KEY (id);


--
-- Name: votes_pkey; Type: CONSTRAINT; Schema: voting; Owner: -; Tablespace: 
--

ALTER TABLE ONLY votes
    ADD CONSTRAINT votes_pkey PRIMARY KEY (id);


--
-- Name: votings_pkey; Type: CONSTRAINT; Schema: voting; Owner: -; Tablespace: 
--

ALTER TABLE ONLY votings
    ADD CONSTRAINT votings_pkey PRIMARY KEY (id);


--
-- Name: replies_by_ids; Type: INDEX; Schema: voting; Owner: -; Tablespace: 
--

CREATE INDEX replies_by_ids ON replies USING btree (voting_id, answer_id);


--
-- Name: answers_voting_id_fkey; Type: FK CONSTRAINT; Schema: voting; Owner: -
--

ALTER TABLE ONLY answers
    ADD CONSTRAINT answers_voting_id_fkey FOREIGN KEY (voting_id) REFERENCES votings(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: votes_answer_id_fkey; Type: FK CONSTRAINT; Schema: voting; Owner: -
--

ALTER TABLE ONLY votes
    ADD CONSTRAINT votes_answer_id_fkey FOREIGN KEY (answer_id) REFERENCES answers(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

