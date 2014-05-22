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
-- Name: simplesms; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA simplesms;


--
-- Name: SCHEMA simplesms; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA simplesms IS 'Simple text SMS services';


SET search_path = simplesms, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = true;

--
-- Name: ads; Type: TABLE; Schema: simplesms; Owner: -; Tablespace: 
--

CREATE TABLE ads (
    id integer NOT NULL,
    msg character varying(160) NOT NULL,
    campaign_id integer DEFAULT 0 NOT NULL
);


--
-- Name: TABLE ads; Type: COMMENT; Schema: simplesms; Owner: -
--

COMMENT ON TABLE ads IS 'Built-in advertising content to be used in services.';


--
-- Name: ads_id_seq; Type: SEQUENCE; Schema: simplesms; Owner: -
--

CREATE SEQUENCE ads_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ads_id_seq; Type: SEQUENCE OWNED BY; Schema: simplesms; Owner: -
--

ALTER SEQUENCE ads_id_seq OWNED BY ads.id;


SET default_with_oids = false;

--
-- Name: campaigns; Type: TABLE; Schema: simplesms; Owner: -; Tablespace: 
--

CREATE TABLE campaigns (
    id integer NOT NULL,
    name character varying(128) DEFAULT ''::character varying NOT NULL,
    active smallint DEFAULT 0 NOT NULL
);


--
-- Name: campaigns_id_seq; Type: SEQUENCE; Schema: simplesms; Owner: -
--

CREATE SEQUENCE campaigns_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: campaigns_id_seq; Type: SEQUENCE OWNED BY; Schema: simplesms; Owner: -
--

ALTER SEQUENCE campaigns_id_seq OWNED BY campaigns.id;


SET default_with_oids = true;

--
-- Name: content; Type: TABLE; Schema: simplesms; Owner: -; Tablespace: 
--

CREATE TABLE content (
    id integer NOT NULL,
    topic_id integer NOT NULL,
    since timestamp without time zone DEFAULT '1990-01-01 00:00:00'::timestamp without time zone NOT NULL,
    till timestamp without time zone DEFAULT '2050-01-01 00:00:00'::timestamp without time zone NOT NULL,
    text character varying(512) DEFAULT ''::character varying NOT NULL,
    ad_mode smallint DEFAULT 0 NOT NULL
);


--
-- Name: TABLE content; Type: COMMENT; Schema: simplesms; Owner: -
--

COMMENT ON TABLE content IS 'Text content for the services';


--
-- Name: content_id_seq; Type: SEQUENCE; Schema: simplesms; Owner: -
--

CREATE SEQUENCE content_id_seq
    START WITH 991
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: content_id_seq; Type: SEQUENCE OWNED BY; Schema: simplesms; Owner: -
--

ALTER SEQUENCE content_id_seq OWNED BY content.id;


--
-- Name: history; Type: TABLE; Schema: simplesms; Owner: -; Tablespace: 
--

CREATE TABLE history (
    id bigint NOT NULL,
    msisdn character varying(16) NOT NULL,
    content_id integer NOT NULL,
    order_time timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: TABLE history; Type: COMMENT; Schema: simplesms; Owner: -
--

COMMENT ON TABLE history IS 'Cache';


--
-- Name: services; Type: TABLE; Schema: simplesms; Owner: -; Tablespace: 
--

CREATE TABLE services (
    id integer NOT NULL,
    name character varying(32) NOT NULL,
    sn character varying(16) NOT NULL,
    active boolean DEFAULT true NOT NULL,
    class character varying(16) DEFAULT 'LAST'::character varying NOT NULL,
    keyword character varying(1024) NOT NULL,
    descr character varying(256) DEFAULT ''::character varying NOT NULL,
    msg_help character varying(256) DEFAULT '- change this -'::character varying NOT NULL,
    CONSTRAINT services_class_check CHECK (((class)::text = ANY (ARRAY[('LAST'::character varying)::text, ('RANDOM'::character varying)::text])))
);


--
-- Name: TABLE services; Type: COMMENT; Schema: simplesms; Owner: -
--

COMMENT ON TABLE services IS 'Services descriptors';


--
-- Name: COLUMN services.class; Type: COMMENT; Schema: simplesms; Owner: -
--

COMMENT ON COLUMN services.class IS 'LAST - get last active content, RANDOM - get any active';


--
-- Name: services_id_seq; Type: SEQUENCE; Schema: simplesms; Owner: -
--

CREATE SEQUENCE services_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: services_id_seq; Type: SEQUENCE OWNED BY; Schema: simplesms; Owner: -
--

ALTER SEQUENCE services_id_seq OWNED BY services.id;


--
-- Name: topics; Type: TABLE; Schema: simplesms; Owner: -; Tablespace: 
--

CREATE TABLE topics (
    id integer NOT NULL,
    service_id integer NOT NULL,
    name character varying(32) NOT NULL,
    active boolean DEFAULT true NOT NULL,
    keyword character varying(1024) NOT NULL,
    descr character varying(256) DEFAULT ''::character varying NOT NULL,
    template character varying(256) DEFAULT '%content'::character varying NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    campaign_id integer DEFAULT 0 NOT NULL
);


--
-- Name: TABLE topics; Type: COMMENT; Schema: simplesms; Owner: -
--

COMMENT ON TABLE topics IS 'Service topics';


--
-- Name: topics_id_seq; Type: SEQUENCE; Schema: simplesms; Owner: -
--

CREATE SEQUENCE topics_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: topics_id_seq; Type: SEQUENCE OWNED BY; Schema: simplesms; Owner: -
--

ALTER SEQUENCE topics_id_seq OWNED BY topics.id;


--
-- Name: id; Type: DEFAULT; Schema: simplesms; Owner: -
--

ALTER TABLE ads ALTER COLUMN id SET DEFAULT nextval('ads_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: simplesms; Owner: -
--

ALTER TABLE campaigns ALTER COLUMN id SET DEFAULT nextval('campaigns_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: simplesms; Owner: -
--

ALTER TABLE content ALTER COLUMN id SET DEFAULT nextval('content_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: simplesms; Owner: -
--

ALTER TABLE services ALTER COLUMN id SET DEFAULT nextval('services_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: simplesms; Owner: -
--

ALTER TABLE topics ALTER COLUMN id SET DEFAULT nextval('topics_id_seq'::regclass);


--
-- Name: ads_pkey; Type: CONSTRAINT; Schema: simplesms; Owner: -; Tablespace: 
--

ALTER TABLE ONLY ads
    ADD CONSTRAINT ads_pkey PRIMARY KEY (id);


--
-- Name: campaigns_pkey; Type: CONSTRAINT; Schema: simplesms; Owner: -; Tablespace: 
--

ALTER TABLE ONLY campaigns
    ADD CONSTRAINT campaigns_pkey PRIMARY KEY (id);


--
-- Name: content_pkey; Type: CONSTRAINT; Schema: simplesms; Owner: -; Tablespace: 
--

ALTER TABLE ONLY content
    ADD CONSTRAINT content_pkey PRIMARY KEY (id);


--
-- Name: history_pkey; Type: CONSTRAINT; Schema: simplesms; Owner: -; Tablespace: 
--

ALTER TABLE ONLY history
    ADD CONSTRAINT history_pkey PRIMARY KEY (id);


--
-- Name: services_name_key; Type: CONSTRAINT; Schema: simplesms; Owner: -; Tablespace: 
--

ALTER TABLE ONLY services
    ADD CONSTRAINT services_name_key UNIQUE (name);


--
-- Name: services_pkey; Type: CONSTRAINT; Schema: simplesms; Owner: -; Tablespace: 
--

ALTER TABLE ONLY services
    ADD CONSTRAINT services_pkey PRIMARY KEY (id);


--
-- Name: topics_pkey; Type: CONSTRAINT; Schema: simplesms; Owner: -; Tablespace: 
--

ALTER TABLE ONLY topics
    ADD CONSTRAINT topics_pkey PRIMARY KEY (id);


--
-- Name: topics_service_id_key; Type: CONSTRAINT; Schema: simplesms; Owner: -; Tablespace: 
--

ALTER TABLE ONLY topics
    ADD CONSTRAINT topics_service_id_key UNIQUE (service_id, name);


--
-- Name: ads_campaign_id_fkey; Type: FK CONSTRAINT; Schema: simplesms; Owner: -
--

ALTER TABLE ONLY ads
    ADD CONSTRAINT ads_campaign_id_fkey FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: content_topic_id_fkey; Type: FK CONSTRAINT; Schema: simplesms; Owner: -
--

ALTER TABLE ONLY content
    ADD CONSTRAINT content_topic_id_fkey FOREIGN KEY (topic_id) REFERENCES topics(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: history_content_id_fkey; Type: FK CONSTRAINT; Schema: simplesms; Owner: -
--

ALTER TABLE ONLY history
    ADD CONSTRAINT history_content_id_fkey FOREIGN KEY (content_id) REFERENCES content(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: topics_service_id_fkey; Type: FK CONSTRAINT; Schema: simplesms; Owner: -
--

ALTER TABLE ONLY topics
    ADD CONSTRAINT topics_service_id_fkey FOREIGN KEY (service_id) REFERENCES services(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

