--
-- PostgreSQL database dump
--

\restrict KB0erUvLw9EAqPJrMNf2RT1wcKlgMfk8DxpaIaGuNOkCxOPQfJU7588wQTep5UD

-- Dumped from database version 16.14
-- Dumped by pg_dump version 17.10 (Debian 17.10-0+deb13u1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_logs (
    id uuid NOT NULL,
    user_id uuid,
    branch_id uuid,
    auditable_type character varying(255) NOT NULL,
    auditable_id uuid NOT NULL,
    event character varying(255) NOT NULL,
    old_values json,
    new_values json,
    url character varying(255),
    ip_address character varying(45),
    user_agent text,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    method character varying(10),
    status_code smallint
);


--
-- Name: bank_reconciliations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bank_reconciliations (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    operating_account_id uuid NOT NULL,
    statement_date date NOT NULL,
    statement_balance numeric(12,2) NOT NULL,
    ledger_balance numeric(12,2) NOT NULL,
    difference numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(20) DEFAULT 'draft'::character varying NOT NULL,
    reconciled_at timestamp(0) without time zone,
    notes text,
    created_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: branch_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.branch_user (
    user_id uuid NOT NULL,
    branch_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: branches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.branches (
    id uuid NOT NULL,
    name character varying(100) NOT NULL,
    location character varying(255) NOT NULL,
    timezone character varying(50) NOT NULL,
    edge_device_id character varying(50),
    cloud_sync_status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    last_sync_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    business_type character varying(255) DEFAULT 'bar_restaurant'::character varying NOT NULL,
    CONSTRAINT branches_business_type_check CHECK (((business_type)::text = ANY ((ARRAY['bar_restaurant'::character varying, 'retail'::character varying, 'service'::character varying, 'pharmacy'::character varying])::text[]))),
    CONSTRAINT branches_cloud_sync_status_check CHECK (((cloud_sync_status)::text = ANY ((ARRAY['pending'::character varying, 'syncing'::character varying, 'synced'::character varying])::text[])))
);


--
-- Name: business_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.business_profiles (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    legal_business_name character varying(255) NOT NULL,
    trading_name character varying(255),
    business_type character varying(255) DEFAULT 'bar_restaurant'::character varying NOT NULL,
    tax_id character varying(50),
    vat_registered boolean DEFAULT false NOT NULL,
    currency character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    country character varying(100) NOT NULL,
    timezone character varying(50) DEFAULT 'UTC'::character varying NOT NULL,
    address_line1 character varying(255),
    address_line2 character varying(255),
    city character varying(100),
    state_province character varying(100),
    postal_code character varying(20),
    phone character varying(30),
    email character varying(255),
    website character varying(255),
    logo_url character varying(255),
    registration_number character varying(100),
    established_year integer,
    description text,
    settings json,
    onboarding_completed boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    location character varying(255),
    CONSTRAINT business_profiles_business_type_check CHECK (((business_type)::text = ANY ((ARRAY['bar_restaurant'::character varying, 'retail'::character varying, 'service'::character varying, 'pharmacy'::character varying])::text[])))
);


--
-- Name: cash_register_shifts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cash_register_shifts (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    branch_id uuid NOT NULL,
    opened_at timestamp(0) without time zone,
    closed_at timestamp(0) without time zone,
    opening_balance numeric(12,2) NOT NULL,
    cash_sales numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    expected_balance numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    actual_balance numeric(12,2),
    variance numeric(12,2),
    revenue_to_bank numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(10) DEFAULT 'open'::character varying NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categories (
    id uuid NOT NULL,
    name character varying(100) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    parent_id uuid,
    returnable boolean DEFAULT false NOT NULL
);


--
-- Name: chart_of_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chart_of_accounts (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(200) NOT NULL,
    type character varying(30) NOT NULL,
    "group" character varying(50),
    normal_balance character varying(10) NOT NULL,
    description text,
    is_system boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: customers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customers (
    id uuid NOT NULL,
    phone character varying(20) NOT NULL,
    email character varying(100),
    name character varying(255) NOT NULL,
    location character varying(255),
    loyalty_points integer DEFAULT 0 NOT NULL,
    member_level character varying(255) DEFAULT 'bronze'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    branch_id uuid,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT customers_member_level_check CHECK (((member_level)::text = ANY ((ARRAY['bronze'::character varying, 'silver'::character varying, 'gold'::character varying, 'platinum'::character varying])::text[])))
);


--
-- Name: devices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.devices (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    name character varying(100) NOT NULL,
    device_id character varying(255) NOT NULL,
    type character varying(50) DEFAULT 'edge_node'::character varying NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    description text,
    firmware_version character varying(50),
    ip_address character varying(45),
    mac_address character varying(17),
    os character varying(100),
    enrollment_token character varying(255),
    enrolled_at timestamp(0) without time zone,
    last_seen_at timestamp(0) without time zone,
    last_sync_at timestamp(0) without time zone,
    capabilities json,
    config json,
    certificate_serial character varying(255),
    certificate_expires_at date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: document_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_items (
    id uuid NOT NULL,
    document_id uuid NOT NULL,
    product_id uuid,
    description character varying(500) NOT NULL,
    quantity numeric(12,2) NOT NULL,
    unit_price numeric(12,2) NOT NULL,
    discount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    tax_rate numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(12,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: document_payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_payments (
    id uuid NOT NULL,
    document_id uuid NOT NULL,
    amount numeric(12,2) NOT NULL,
    method character varying(50) NOT NULL,
    reference character varying(255),
    payment_date date NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.documents (
    id uuid NOT NULL,
    document_number character varying(30) NOT NULL,
    document_type character varying(10) NOT NULL,
    status character varying(20) DEFAULT 'draft'::character varying NOT NULL,
    customer_id uuid,
    branch_id uuid NOT NULL,
    issue_date date NOT NULL,
    expiry_date date,
    due_date date,
    subtotal numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    discount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    paid_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    notes text,
    terms_conditions text,
    converted_from_id uuid,
    created_by uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: efris_configs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.efris_configs (
    id uuid NOT NULL,
    integration_id uuid NOT NULL,
    branch_id uuid NOT NULL,
    tin character varying(20) NOT NULL,
    weaf_email character varying(200) NOT NULL,
    weaf_token text,
    weaf_token_expires_at timestamp(0) without time zone,
    weaf_environment character varying(20) DEFAULT 'sandbox'::character varying NOT NULL,
    company_name character varying(200),
    company_weaf_id integer,
    auto_fiscalize boolean DEFAULT true NOT NULL,
    fiscalize_receipts boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: efris_fiscal_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.efris_fiscal_logs (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    sale_id uuid,
    efris_invoice_no character varying(50),
    efris_fdn character varying(50),
    efris_qr_code text,
    efris_verification_code character varying(50),
    request_payload json NOT NULL,
    response_payload json,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    error_message text,
    retry_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: expenses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.expenses (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    payee character varying(255) NOT NULL,
    amount numeric(12,2) NOT NULL,
    method character varying(50) NOT NULL,
    category character varying(100) NOT NULL,
    reference character varying(255),
    expense_date date NOT NULL,
    notes text,
    purchase_order_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: grn; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grn (
    id uuid NOT NULL,
    purchase_order_id uuid NOT NULL,
    received_by uuid NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: grn_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.grn_items (
    id uuid NOT NULL,
    grn_id uuid NOT NULL,
    product_id uuid NOT NULL,
    quantity numeric(12,3) NOT NULL,
    unit_cost numeric(10,2) NOT NULL,
    batch_number character varying(50),
    expiry_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: hold_sales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hold_sales (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    user_id uuid NOT NULL,
    customer_id uuid,
    cart_data json NOT NULL,
    promo_code character varying(50),
    tax_profile_id uuid,
    loyalty_points_redeemed integer DEFAULT 0 NOT NULL,
    note text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: integrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.integrations (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    type character varying(50) NOT NULL,
    name character varying(100) NOT NULL,
    status character varying(20) DEFAULT 'inactive'::character varying NOT NULL,
    config json,
    last_sync_at timestamp(0) without time zone,
    last_error text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: inventory; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventory (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    warehouse_id uuid NOT NULL,
    quantity numeric(12,3) NOT NULL,
    batch_number character varying(50),
    expiry_date date,
    serial_number character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    reserved_quantity numeric(12,3) DEFAULT '0'::numeric NOT NULL,
    sync_status character varying(20) DEFAULT 'synced'::character varying NOT NULL
);


--
-- Name: inventory_adjustments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventory_adjustments (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    product_id uuid NOT NULL,
    warehouse_id uuid NOT NULL,
    quantity numeric(12,2) NOT NULL,
    type character varying(30) NOT NULL,
    reason character varying(500) NOT NULL,
    reference character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: journal_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.journal_entries (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    entry_number character varying(30) NOT NULL,
    entry_date date NOT NULL,
    description text NOT NULL,
    reference_type character varying(50),
    reference_id uuid,
    created_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: journal_entry_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.journal_entry_lines (
    id uuid NOT NULL,
    journal_entry_id uuid NOT NULL,
    account_id uuid NOT NULL,
    debit_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    credit_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT jel_check_debit_credit CHECK (((debit_amount >= (0)::numeric) AND (credit_amount >= (0)::numeric) AND ((debit_amount = (0)::numeric) OR (credit_amount = (0)::numeric)) AND (NOT ((debit_amount = (0)::numeric) AND (credit_amount = (0)::numeric)))))
);


--
-- Name: loyalty_rules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.loyalty_rules (
    id uuid NOT NULL,
    points_per_amount numeric(12,2) DEFAULT '10'::numeric NOT NULL,
    points_earned integer DEFAULT 1 NOT NULL,
    signup_bonus_points integer DEFAULT 0 NOT NULL,
    member_levels json,
    reward_thresholds json,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id uuid NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: operating_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.operating_accounts (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    account_id uuid NOT NULL,
    name character varying(100) NOT NULL,
    type character varying(30) NOT NULL,
    account_number character varying(50),
    bank_name character varying(100),
    currency character varying(3) DEFAULT 'KES'::character varying NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    opening_balance numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    current_balance numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    is_system boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payments (
    id uuid NOT NULL,
    sale_id uuid NOT NULL,
    amount numeric(12,2) NOT NULL,
    method character varying(255) NOT NULL,
    gateway character varying(50),
    txn_id character varying(100),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT payments_method_check CHECK (((method)::text = ANY ((ARRAY['cash'::character varying, 'momo'::character varying, 'card'::character varying, 'qr'::character varying, 'transfer'::character varying])::text[]))),
    CONSTRAINT payments_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'success'::character varying, 'failed'::character varying, 'voided'::character varying])::text[])))
);


--
-- Name: permission_role; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permission_role (
    permission_id uuid NOT NULL,
    role_id uuid NOT NULL
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id uuid NOT NULL,
    name character varying(50) NOT NULL,
    guard_name character varying(50) DEFAULT 'web'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id uuid NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.products (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    barcode character varying(100),
    price numeric(10,2) NOT NULL,
    cost numeric(10,2),
    stock_uom character varying(20) NOT NULL,
    min_stock integer NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    category_id uuid,
    image character varying(500),
    returnable boolean
);


--
-- Name: promotions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.promotions (
    id uuid NOT NULL,
    code character varying(50) NOT NULL,
    type character varying(255) DEFAULT 'percentage'::character varying NOT NULL,
    value numeric(10,2) NOT NULL,
    min_order_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    max_discount_amount numeric(12,2),
    usage_limit integer,
    used_count integer DEFAULT 0 NOT NULL,
    valid_from date,
    valid_until date,
    is_active boolean DEFAULT true NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT promotions_type_check CHECK (((type)::text = ANY ((ARRAY['percentage'::character varying, 'flat'::character varying])::text[])))
);


--
-- Name: purchase_order_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_order_items (
    id uuid NOT NULL,
    purchase_order_id uuid NOT NULL,
    product_id uuid NOT NULL,
    quantity numeric(12,3) NOT NULL,
    unit_cost numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_orders (
    id uuid NOT NULL,
    supplier_id uuid NOT NULL,
    branch_id uuid NOT NULL,
    po_number character varying(50) NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    total_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT purchase_orders_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'pending'::character varying, 'approved'::character varying, 'received'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: reconciliation_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reconciliation_items (
    id uuid NOT NULL,
    reconciliation_id uuid NOT NULL,
    journal_entry_id uuid NOT NULL,
    amount numeric(12,2) NOT NULL,
    type character varying(30) NOT NULL,
    is_cleared boolean DEFAULT false NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: return_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.return_items (
    id uuid NOT NULL,
    return_id uuid NOT NULL,
    product_id uuid NOT NULL,
    quantity numeric(12,3) NOT NULL,
    reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    condition character varying(20) DEFAULT 'returnable'::character varying NOT NULL
);


--
-- Name: returns; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.returns (
    id uuid NOT NULL,
    sale_id uuid NOT NULL,
    branch_id uuid NOT NULL,
    reason text,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    refund_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    refund_payment_id uuid,
    refunded_at timestamp(0) without time zone
);


--
-- Name: role_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_user (
    user_id uuid NOT NULL,
    role_id uuid NOT NULL,
    branch_id uuid NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id uuid NOT NULL,
    name character varying(50) NOT NULL,
    guard_name character varying(50) DEFAULT 'web'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_editable boolean DEFAULT true NOT NULL
);


--
-- Name: sale_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sale_items (
    id uuid NOT NULL,
    sale_id uuid NOT NULL,
    product_id uuid NOT NULL,
    quantity numeric(12,3) NOT NULL,
    price numeric(10,2) NOT NULL,
    subtotal numeric(12,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tax_rate numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: sales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    customer_id uuid,
    invoice_number character varying(50) NOT NULL,
    total_amount numeric(12,2) NOT NULL,
    tax_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    discount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    payment_method character varying(255) NOT NULL,
    status character varying(20) DEFAULT 'pending_sync'::character varying NOT NULL,
    sync_status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    efris_fdn character varying(50),
    efris_qr_code text,
    efris_verification_code character varying(50),
    efris_fiscal_status character varying(20),
    CONSTRAINT sales_payment_method_check CHECK (((payment_method)::text = ANY ((ARRAY['cash'::character varying, 'mobile_money'::character varying, 'card'::character varying, 'qr'::character varying])::text[]))),
    CONSTRAINT sales_status_check CHECK (((status)::text = ANY ((ARRAY['pending_sync'::character varying, 'synced'::character varying, 'completed'::character varying, 'voided'::character varying, 'payment_failed'::character varying, 'refunded'::character varying])::text[]))),
    CONSTRAINT sales_sync_status_check CHECK (((sync_status)::text = ANY ((ARRAY['pending'::character varying, 'synced'::character varying, 'conflict'::character varying])::text[])))
);


--
-- Name: stock_movements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_movements (
    id uuid NOT NULL,
    inventory_id uuid NOT NULL,
    product_id uuid NOT NULL,
    warehouse_id uuid NOT NULL,
    quantity_change numeric(12,2) NOT NULL,
    running_balance numeric(12,2) NOT NULL,
    reference_type character varying(50) NOT NULL,
    reference_id uuid,
    reason character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: stock_transfer_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_transfer_items (
    id uuid NOT NULL,
    stock_transfer_id uuid NOT NULL,
    product_id uuid NOT NULL,
    quantity numeric(12,3) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: stock_transfers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_transfers (
    id uuid NOT NULL,
    from_warehouse_id uuid NOT NULL,
    to_warehouse_id uuid NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    notes text,
    transferred_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscriptions (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    plan_type character varying(50) DEFAULT 'standard'::character varying NOT NULL,
    billing_cycle character varying(20) DEFAULT 'monthly'::character varying NOT NULL,
    status character varying(20) DEFAULT 'trial'::character varying NOT NULL,
    trial_ends_at timestamp(0) without time zone,
    starts_at timestamp(0) without time zone,
    ends_at timestamp(0) without time zone,
    cancelled_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: suppliers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suppliers (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    contact_person character varying(255),
    phone character varying(20) NOT NULL,
    email character varying(100),
    address text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: syncs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.syncs (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    table_name character varying(50) NOT NULL,
    record_id character varying(50) NOT NULL,
    action character varying(255) NOT NULL,
    payload jsonb,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    conflict_data jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    local_id character varying(64),
    device_id character varying(64),
    CONSTRAINT syncs_action_check CHECK (((action)::text = ANY ((ARRAY['create'::character varying, 'update'::character varying, 'delete'::character varying])::text[]))),
    CONSTRAINT syncs_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'synced'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: tax_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tax_profiles (
    id uuid NOT NULL,
    name character varying(100) NOT NULL,
    rate numeric(5,2) NOT NULL,
    type character varying(255) DEFAULT 'exclusive'::character varying NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT tax_profiles_type_check CHECK (((type)::text = ANY ((ARRAY['inclusive'::character varying, 'exclusive'::character varying])::text[])))
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    branch_id uuid,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    email_verified_at timestamp(0) without time zone,
    is_protected boolean DEFAULT false NOT NULL,
    secret_question character varying(255),
    secret_answer character varying(255),
    avatar_url character varying(255),
    is_default_account boolean DEFAULT false NOT NULL
);


--
-- Name: warehouses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.warehouses (
    id uuid NOT NULL,
    branch_id uuid NOT NULL,
    name character varying(100) NOT NULL,
    location character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Data for Name: activity_logs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.activity_logs (id, user_id, branch_id, auditable_type, auditable_id, event, old_values, new_values, url, ip_address, user_agent, description, created_at, updated_at, method, status_code) FROM stdin;
a743ced5-d2fb-4a7f-9649-edaf2d4cec88	\N	\N	App\\Models\\Landlord\\AdminUser	35546da5-f03f-4021-8c83-73903605a446	updated	{"last_login_at":null,"updated_at":"2026-07-13T09:56:59.000000Z"}	{"last_login_at":"2026-07-13 10:12:29","updated_at":"2026-07-13 10:12:29"}	https://localhost:9099/api/v1/admin/auth/login	172.19.0.1	curl/8.13.0	\N	2026-07-13 10:12:29	2026-07-13 10:12:29	\N	\N
98a23a10-e142-486f-b8fe-79307cade199	865f94df-5c67-49cc-b8ac-c7e11285b9d4	dd04d801-68c0-4eb7-86a8-f273e01c06f9	request	d3a789fa-0d04-45f0-9157-f348446f2d53	request	\N	{"method":"POST","path":"api\\/v1\\/auth\\/login","status_code":200}	https://localhost:9099/api/v1/auth/login	172.19.0.1	curl/8.13.0	POST api/v1/auth/login	2026-07-13 10:23:32	2026-07-13 10:23:32	POST	200
aa7ebb38-ef2d-4e17-b72f-324e73e5d943	\N	\N	App\\Models\\Landlord\\AdminUser	35546da5-f03f-4021-8c83-73903605a446	updated	{"last_login_at":"2026-07-13T10:12:29.000000Z","updated_at":"2026-07-13T10:12:29.000000Z"}	{"last_login_at":"2026-07-13 10:25:49","updated_at":"2026-07-13 10:25:49"}	https://localhost:9099/api/v1/admin/auth/login	172.19.0.1	curl/8.13.0	\N	2026-07-13 10:25:49	2026-07-13 10:25:49	\N	\N
d6660e5a-92b8-4f74-9449-2639d460465e	\N	\N	App\\Models\\Landlord\\AdminUser	35546da5-f03f-4021-8c83-73903605a446	updated	{"last_login_at":"2026-07-13T10:25:49.000000Z","updated_at":"2026-07-13T10:25:49.000000Z"}	{"last_login_at":"2026-07-13 16:02:08","updated_at":"2026-07-13 16:02:08"}	https://posapp.oakitsolutionsandsupplies.com/api/v1/admin/auth/login	102.209.109.178	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	\N	2026-07-13 16:02:08	2026-07-13 16:02:08	\N	\N
caf86852-3e51-4c97-9a88-babb88a6514a	\N	\N	App\\Models\\Landlord\\AdminUser	35546da5-f03f-4021-8c83-73903605a446	updated	{"last_login_at":"2026-07-13T16:02:08.000000Z","updated_at":"2026-07-13T16:02:08.000000Z"}	{"last_login_at":"2026-07-13 16:03:21","updated_at":"2026-07-13 16:03:21"}	https://posapp.oakitsolutionsandsupplies.com/api/v1/admin/auth/login	102.209.109.178	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	\N	2026-07-13 16:03:21	2026-07-13 16:03:21	\N	\N
7f191527-8d3b-416d-8a59-6bf8ba2ec219	\N	\N	App\\Models\\Landlord\\AdminUser	35546da5-f03f-4021-8c83-73903605a446	updated	{"last_login_at":"2026-07-13T16:03:21.000000Z","updated_at":"2026-07-13T16:03:21.000000Z"}	{"last_login_at":"2026-07-13 16:49:42","updated_at":"2026-07-13 16:49:42"}	https://posapp.oakitsolutionsandsupplies.com/api/v1/admin/auth/login	102.209.109.178	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	\N	2026-07-13 16:49:42	2026-07-13 16:49:42	\N	\N
\.


--
-- Data for Name: bank_reconciliations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.bank_reconciliations (id, branch_id, operating_account_id, statement_date, statement_balance, ledger_balance, difference, status, reconciled_at, notes, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: branch_user; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.branch_user (user_id, branch_id, created_at, updated_at) FROM stdin;
865f94df-5c67-49cc-b8ac-c7e11285b9d4	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2026-07-13 09:57:51	2026-07-13 09:57:51
d614fc8e-52c1-4bce-885e-77d6a687ef93	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2026-07-13 09:57:51	2026-07-13 09:57:51
bcb9dc14-6f37-4f5b-bff4-ffb17eaed1d7	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2026-07-13 09:57:52	2026-07-13 09:57:52
57d23c70-656b-4cb3-9f18-226a829d4a6c	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2026-07-13 09:57:52	2026-07-13 09:57:52
\.


--
-- Data for Name: branches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.branches (id, name, location, timezone, edge_device_id, cloud_sync_status, last_sync_at, created_at, updated_at, business_type) FROM stdin;
dd04d801-68c0-4eb7-86a8-f273e01c06f9	Nairobi HQ - Bar & Grill	Nairobi, Kenya	Africa/Nairobi	EDGE-NBO-001	synced	2026-07-13 09:57:50	2026-07-13 09:57:50	2026-07-13 09:57:50	bar_restaurant
de930bc6-a18b-44ac-919f-d42781db04b7	Mombasa Beach Lounge	Mombasa, Kenya	Africa/Nairobi	EDGE-MSA-001	synced	2026-07-13 09:57:50	2026-07-13 09:57:50	2026-07-13 09:57:50	bar_restaurant
\.


--
-- Data for Name: business_profiles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.business_profiles (id, branch_id, legal_business_name, trading_name, business_type, tax_id, vat_registered, currency, country, timezone, address_line1, address_line2, city, state_province, postal_code, phone, email, website, logo_url, registration_number, established_year, description, settings, onboarding_completed, created_at, updated_at, location) FROM stdin;
10d66c46-2965-44f9-aa82-30c5b392d420	dd04d801-68c0-4eb7-86a8-f273e01c06f9	Nairobi HQ - Bar & Grill	Nairobi HQ	bar_restaurant	\N	f	KES	KE	Africa/Nairobi	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	Nairobi, Kenya
067149d8-5a5b-4e95-bbe3-a3cdfa10ad18	de930bc6-a18b-44ac-919f-d42781db04b7	Mombasa Beach Lounge	Mombasa Beach	bar_restaurant	\N	f	KES	KE	Africa/Nairobi	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	Mombasa, Kenya
\.


--
-- Data for Name: cash_register_shifts; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cash_register_shifts (id, user_id, branch_id, opened_at, closed_at, opening_balance, cash_sales, expected_balance, actual_balance, variance, revenue_to_bank, status, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: categories; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.categories (id, name, created_at, updated_at, parent_id, returnable) FROM stdin;
f22f0922-f529-40d8-b28a-096a1f75107a	Beer	2026-07-13 09:57:50	2026-07-13 09:57:50	\N	f
7551b1b9-45da-4897-8fb6-157bea52fb64	Spirits	2026-07-13 09:57:50	2026-07-13 09:57:50	\N	f
8002151b-3841-4ad2-bda4-30d9cfe5ef42	Whisky	2026-07-13 09:57:50	2026-07-13 09:57:50	\N	f
7f4bb105-a025-4c67-9b90-de7bf362748e	Liqueur	2026-07-13 09:57:50	2026-07-13 09:57:50	\N	f
6ca3ce04-1fbd-4f2d-83b3-792bb4b55cb1	Soft Drinks	2026-07-13 09:57:50	2026-07-13 09:57:50	\N	f
c398f01c-eb0c-47cf-a1f9-84244e0d0a4d	Food	2026-07-13 09:57:50	2026-07-13 09:57:50	\N	f
2e6948c7-6b49-4a6a-b4dd-f24b34188f66	Snacks	2026-07-13 09:57:50	2026-07-13 09:57:50	\N	f
8350f142-2cdf-462f-af1b-625acbe6796f	Beverages	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
9d2cdb63-9e01-486d-b2dc-8f9d41e02ee0	Wine & Spirits	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
e8b946fb-a29f-442a-ab9a-3074074163cd	Tobacco	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
884c812b-8ef3-4ce2-a354-e849205571ef	Dairy	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
4ccfbc1d-bba7-4276-be53-7f172d951734	Bakery	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
873e5cd9-e536-45f5-9cdc-7c925839f297	Fresh Produce	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
9f550363-9958-4d08-8195-2f6165ab9fda	Meat & Seafood	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
f56d0b9d-b642-4781-b877-5a4a05101447	Frozen Foods	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
b3651a2c-eed6-487a-b499-efa1993423ce	Canned Goods	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
95b8d94b-c770-4b38-abba-5a042dd07e05	Condiments & Sauces	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
bfc11662-de3a-43b8-9738-715cb06cf667	Spices & Seasonings	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
32d437bb-8a82-4331-aacd-4a906c708f2e	Baking Supplies	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
048f42c5-a12a-407c-b8ec-a966fa20d25e	Breakfast Foods	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
6158c705-dc31-48b3-8165-e097c585a092	Household Essentials	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
6cf228cf-efb2-4194-bee6-2588a3404aa7	Personal Care	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
b9c3287e-c808-497f-b4ea-324c7fc8977b	Baby Care	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
d813e487-024a-4778-ad9a-b2c95d676495	Pet Supplies	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
25357acc-008b-4321-beeb-49dcaf4bfde9	Office Supplies	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
589090a3-1bef-44ab-88b2-4d489fd9beee	Electronics Accessories	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
245b8b46-c4b6-45ac-b667-2ea85e060d5c	Other	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	f
\.


--
-- Data for Name: chart_of_accounts; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.chart_of_accounts (id, branch_id, code, name, type, "group", normal_balance, description, is_system, is_active, created_at, updated_at) FROM stdin;
1c08080e-95a2-4508-bbd9-ba1ccac8eff4	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1100	Cash on Hand	asset	current_asset	debit	Physical cash at the business premises	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
c22fcda0-eb68-4ea1-b5e4-79ab4c912cc7	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1110	Petty Cash	asset	current_asset	debit	Small cash fund for minor expenses	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
a781a2a3-9dff-4932-8869-c334a6a0ecf6	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1120	Checking Account	asset	current_asset	debit	Business checking/current account	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
9451a9b9-b05f-4bbf-966b-39e9d74dbaac	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1130	Savings Account	asset	current_asset	debit	Business savings account	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
2d095a57-3ff1-419d-bc19-2c3fda72d35a	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1140	Mobile Money Account	asset	current_asset	debit	Mobile money wallet (M-Pesa, Airtel Money, etc.)	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
82bbb1f8-abba-42d7-ae1d-9b2147039033	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1200	Accounts Receivable	asset	current_asset	debit	Amounts owed by customers for credit sales	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
fe78e1f7-be58-468b-8850-44bd7d3fbe3a	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1210	Employee Advances	asset	current_asset	debit	Advances paid to employees	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
2bab7c5f-4a35-4909-b352-831ae7c594c8	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1300	Inventory - Raw Materials	asset	current_asset	debit	Raw materials used in production	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
d034d1e0-798d-44da-86e0-186b2ef461ff	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1310	Inventory - Work in Progress	asset	current_asset	debit	Partially completed goods	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
d7e8ed3f-df66-4a46-8466-b886fdd4728f	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1320	Inventory - Finished Goods	asset	current_asset	debit	Completed goods ready for sale	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
00240a5e-d88f-4c7d-8f1b-179d9fa218d2	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1330	Inventory - Trading Goods	asset	current_asset	debit	Goods purchased for resale	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
99d15ced-0bcc-477c-86eb-db4bab7894ed	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1340	Inventory - Supplies	asset	current_asset	debit	Office and operational supplies	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
dde20744-7930-49a5-8d09-b82a91015c93	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1400	Prepaid Expenses	asset	current_asset	debit	Expenses paid in advance	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
56cd8025-8253-4f29-9e70-b75c9f68d2fc	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1410	Prepaid Insurance	asset	current_asset	debit	Insurance premiums paid in advance	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
5d5b68b5-d978-4043-8e1e-207ee53c1a3a	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1500	Tax Receivable	asset	current_asset	debit	Input VAT and other recoverable taxes	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
68c273cd-bc88-4970-a126-a44acf747326	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1600	Land	asset	fixed_asset	debit	Land owned by the business	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
44e2f462-df76-4ea0-9abf-c1f55426e969	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1610	Buildings	asset	fixed_asset	debit	Buildings and structures owned by the business	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
1a86c835-2d3f-4c1c-b681-d32405915534	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1620	Furniture & Fixtures	asset	fixed_asset	debit	Office furniture, shelving, and fixtures	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
f704c3cc-fd7f-48a3-bada-81119dcc32f0	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1630	Office Equipment	asset	fixed_asset	debit	Office machinery and equipment	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
65ff6aae-bc8f-40aa-b533-f76a263c0e87	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1640	Computer Equipment	asset	fixed_asset	debit	Computers, servers, and IT hardware	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
13e75a8a-6044-408e-b58a-e473b84572d4	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1650	Motor Vehicles	asset	fixed_asset	debit	Company vehicles	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
54108161-0c8d-40b8-ae34-8f73dbd17843	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1660	Leasehold Improvements	asset	fixed_asset	debit	Improvements to leased premises	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
98739107-0fd0-41db-b91b-c3ff91b23170	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1670	Machinery & Equipment	asset	fixed_asset	debit	Production machinery and heavy equipment	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
83f15e8d-3374-47fa-bbb9-2a37f3135cde	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1700	Accum. Depreciation - Buildings	asset	accum_depreciation	credit	Accumulated depreciation on buildings	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
8fef73df-333d-454a-98b2-6e455c789f33	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1710	Accum. Depreciation - Furniture	asset	accum_depreciation	credit	Accumulated depreciation on furniture	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
49bd6e3d-cad2-40fc-9cba-2a564ad139c7	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1720	Accum. Depreciation - Equipment	asset	accum_depreciation	credit	Accumulated depreciation on equipment	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
1085ef71-4ffc-43b2-8bc0-557a750b6506	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1730	Accum. Depreciation - Vehicles	asset	accum_depreciation	credit	Accumulated depreciation on vehicles	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
cbd37f83-947c-4c67-afdf-8dfd6e78408a	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1800	Security Deposits	asset	other_asset	debit	Refundable deposits (rent, utilities, etc.)	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
31b15562-28a5-4974-88f0-14347b4be9b9	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1810	Goodwill	asset	other_asset	debit	Goodwill acquired in business acquisitions	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
afe4c33e-ecba-4f1b-a38a-eb7f7140ac56	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1820	Intangible Assets	asset	other_asset	debit	Patents, trademarks, copyrights, licenses	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
d67b3fe8-0948-4ee2-b017-990d21619d8d	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2100	Accounts Payable	liability	current_liability	credit	Amounts owed to suppliers	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
579f6a67-7f3a-4d40-90de-ae5dfe34fdb6	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2110	Accrued Expenses	liability	current_liability	credit	Expenses incurred but not yet paid	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
787ca7d1-7989-4506-a2b8-f0e8bd319b81	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2120	Accrued Salaries & Wages	liability	current_liability	credit	Salaries earned but not yet paid	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
481ca8b3-0307-4c49-a782-712eaaf8ea18	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2130	Accrued Taxes Payable	liability	current_liability	credit	Taxes incurred but not yet remitted	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
c473613e-a729-4957-b5c6-95ef5d177571	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2140	Sales Tax Payable	liability	current_liability	credit	Output VAT/sales tax collected from customers	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
05e647ce-4f7c-40f5-8d95-c981829e3872	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2150	Withholding Tax Payable	liability	current_liability	credit	Withholding tax to be remitted to tax authority	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
6f9b22aa-3007-4342-b2c4-17c84973917e	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2160	Customer Deposits	liability	current_liability	credit	Advance payments from customers	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
89b28013-d2c3-45dd-89b5-5bab4a5abf65	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2170	Short-term Loans	liability	current_liability	credit	Short-term borrowings due within 12 months	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
40753340-087d-4b17-acc6-3e599f547db2	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2200	Credit Cards Payable	liability	current_liability	credit	Outstanding credit card balances	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
7a989501-f196-4095-8a2d-12d6cb673b34	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2210	Gift Cards / Vouchers Payable	liability	current_liability	credit	Outstanding gift card and voucher liabilities	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
4cedef3e-3179-4977-b18b-c955f0bbb8ba	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2300	Long-term Loans	liability	long_term_liability	credit	Long-term borrowings due after 12 months	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
084b736d-6140-4f92-a14d-f2263226c657	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2310	Bank Loans	liability	long_term_liability	credit	Bank loans and overdrafts	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
ce7d4ec1-9583-419a-b4f7-6129dfde6cce	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2320	Mortgage Payable	liability	long_term_liability	credit	Mortgage loans secured by property	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
122280a6-ecb4-44c2-8a83-faa6c3bc53ef	dd04d801-68c0-4eb7-86a8-f273e01c06f9	2330	Shareholder Loans	liability	long_term_liability	credit	Loans from shareholders	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
fbf2daf8-af33-4a5f-ae20-143b04a23c0f	dd04d801-68c0-4eb7-86a8-f273e01c06f9	3100	Owner's Capital	equity	owner_equity	credit	Owner's initial and additional capital contributions	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
4cc5ea24-0cb4-470f-a6b1-119edef8dea8	dd04d801-68c0-4eb7-86a8-f273e01c06f9	3110	Owner's Drawings	equity	owner_equity	debit	Owner's personal withdrawals from the business	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
a95e4c4c-1c03-4eca-b2a6-1ae12e78102a	dd04d801-68c0-4eb7-86a8-f273e01c06f9	3200	Retained Earnings	equity	retained_earnings	credit	Accumulated earnings retained in the business	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
04011a1f-1d9e-409d-97c1-6d83f6e1a923	dd04d801-68c0-4eb7-86a8-f273e01c06f9	3300	Current Year Earnings	equity	retained_earnings	credit	Net profit/loss for the current fiscal year	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
408c57b0-7ded-4077-979d-f2804c1c3528	dd04d801-68c0-4eb7-86a8-f273e01c06f9	3400	Share Capital	equity	owner_equity	credit	Capital received from share issuance	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
47a34e8f-6b0d-4260-aa4d-36cee50feca1	dd04d801-68c0-4eb7-86a8-f273e01c06f9	3410	Share Premium	equity	owner_equity	credit	Amount received above par value of shares	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
f2a1c607-4804-43dd-84f0-b188314a9436	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4100	Sales Revenue - Products	revenue	operating_revenue	credit	Revenue from product sales	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
a3bbcc62-cde3-4ca8-b573-b6bbeae46eb9	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4110	Sales Revenue - Services	revenue	operating_revenue	credit	Revenue from services rendered	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
40e796a6-408b-406b-9e44-b10968c552de	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4120	Sales Revenue - Digital Goods	revenue	operating_revenue	credit	Revenue from digital product sales	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
07ce3adc-8e36-464b-9c87-67bc53fe04ec	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4200	Service Charges	revenue	operating_revenue	credit	Service fees and charges	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
b15872b3-6e49-4df3-881c-ab8c261ff598	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4210	Delivery & Shipping Income	revenue	operating_revenue	credit	Revenue from delivery and shipping services	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
b65317dd-8f31-4f2b-ab53-bd080c80f581	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4300	Sales Discounts	revenue	contra_revenue	debit	Discounts allowed to customers	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
088d3696-d761-47a5-bdcf-6883c1253b33	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4310	Sales Returns & Allowances	revenue	contra_revenue	debit	Returns and allowances granted to customers	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
28013f8a-c45f-4222-9fab-669c51fcac65	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4400	Interest Income	revenue	other_revenue	credit	Interest earned on bank deposits and investments	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
1dddda7e-823f-4826-9da0-5799046119ad	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4410	Rental Income	revenue	other_revenue	credit	Income from property rental	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
cc0e70f4-1acb-4c1a-9f95-463dc9d6698e	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4420	Commission Income	revenue	other_revenue	credit	Commission earned from third-party sales	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
fa0fa375-fbcb-410e-b823-cc586fa97a88	dd04d801-68c0-4eb7-86a8-f273e01c06f9	4430	Other Income	revenue	other_revenue	credit	Miscellaneous non-operating income	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
22968d45-5f5a-497a-8987-df1060c731c5	dd04d801-68c0-4eb7-86a8-f273e01c06f9	5100	COGS - Products	expense	cogs	debit	Cost of products sold	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
fd095422-3dc0-418e-af10-4961229fa9c6	dd04d801-68c0-4eb7-86a8-f273e01c06f9	5110	COGS - Services	expense	cogs	debit	Direct cost of services delivered	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
03a02860-e43e-4eeb-865b-fc5c1deef5f0	dd04d801-68c0-4eb7-86a8-f273e01c06f9	5120	COGS - Digital Goods	expense	cogs	debit	Cost of digital goods sold	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
7675e4bd-495b-4ce1-bd9b-3b0447339fef	dd04d801-68c0-4eb7-86a8-f273e01c06f9	5130	Freight & Shipping Costs	expense	cogs	debit	Freight and shipping costs on purchases	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
b773734d-c2b8-4470-aeb7-405a24c6981d	dd04d801-68c0-4eb7-86a8-f273e01c06f9	5140	Inventory Adjustments	expense	cogs	debit	Write-offs, shrinkage, and inventory adjustments	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
781fb288-612a-42c4-a2b1-840bd720cd2d	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6100	Rent Expense	expense	operating_expense	debit	Rent for business premises	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
b16d2123-6046-4260-ab00-f29ebc3f580b	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6110	Lease Expense	expense	operating_expense	debit	Equipment and vehicle lease payments	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
9bcc8d0c-fce2-48b4-9b2e-3786d1c0a6a0	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6200	Utilities Expense	expense	operating_expense	debit	General utilities	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
1cf751b3-7148-440c-a162-3350ffd47856	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6210	Electricity	expense	operating_expense	debit	Electricity bills	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
8d5b1bd4-b7ab-4ee5-ba56-c9c46e309866	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6220	Water & Sewer	expense	operating_expense	debit	Water and sewer bills	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
540bc07a-b4ed-433a-9de6-420af3275893	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6230	Internet & Telephone	expense	operating_expense	debit	Internet, phone, and communication costs	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
216c0ae8-cbff-4add-b8e2-ef28509e1624	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6300	Salaries & Wages	expense	operating_expense	debit	Employee salaries and wages	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
edb42190-73ca-49cb-b74d-18ec7c730d06	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6310	Employee Benefits	expense	operating_expense	debit	Health insurance, pension, and other benefits	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
d85cbe49-15d9-459f-9f1b-cab6ec8fa1b2	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6320	Payroll Taxes	expense	operating_expense	debit	Employer payroll taxes and contributions	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
b98afa0b-ea85-401a-b743-a72537b8aeb8	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6330	Commission Expense	expense	operating_expense	debit	Sales commissions paid to staff	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
683b9a63-5e9a-40a5-81e5-5953f4df177c	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6400	Office Supplies Expense	expense	operating_expense	debit	Office supplies and stationery	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
00180a68-2b7b-4dc5-be45-910246ae1018	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6410	Printing & Stationery	expense	operating_expense	debit	Printing, copying, and stationery costs	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
8ed6a888-dbe3-4a99-99f3-9f2bbeda2d60	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6500	Maintenance & Repairs	expense	operating_expense	debit	General maintenance and repair costs	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
2392dc2f-1dcf-482d-88ba-41f5c95eeee0	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6510	Building Maintenance	expense	operating_expense	debit	Building and premises maintenance	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
b92f9051-6ca2-4d24-a30f-3bd47f00829e	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6520	Equipment Maintenance	expense	operating_expense	debit	Equipment servicing and repair	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
0a9a345f-8f4e-43d1-bcf1-30e7dc6f03e9	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6600	Marketing & Advertising	expense	operating_expense	debit	Advertising and marketing costs	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
827cdff8-3348-4dbe-a09e-a6e0c2f9a88d	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6610	Promotional Expenses	expense	operating_expense	debit	Promotional events and materials	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
0475ad24-d62f-4fcf-b227-820a11e2ac22	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6620	Online Advertising	expense	operating_expense	debit	Digital and social media advertising	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
baaf311e-07d8-4bb1-88e1-75b65d3786cf	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6700	Transportation & Travel	expense	operating_expense	debit	Business travel and transportation costs	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
af2e589e-77ee-49c6-b149-bd45eb078968	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6710	Fuel Expense	expense	operating_expense	debit	Vehicle fuel costs	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
d2cccb0c-6bed-44a0-a042-396891639843	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6720	Travel & Accommodation	expense	operating_expense	debit	Travel and hotel accommodation	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
409321bb-2fb3-44a6-9113-82c8cd64c278	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6800	Insurance Expense	expense	operating_expense	debit	General insurance costs	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
baca0c6c-01c1-43d3-b489-e9c87b547faa	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6810	Health Insurance	expense	operating_expense	debit	Employee health insurance premiums	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
51a32b73-8360-42a5-9e4b-625dfb02c328	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6900	Professional Fees	expense	operating_expense	debit	Professional service fees	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
416142a4-dea0-490f-8e56-9fe183544f8b	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6910	Legal Fees	expense	operating_expense	debit	Legal and attorney fees	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
eaaa6c72-2cb5-444c-a51a-eb4c474a0633	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6920	Accounting & Audit Fees	expense	operating_expense	debit	Accounting, bookkeeping, and audit fees	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
afc6397c-a13c-4b89-ac41-9f1e051d8d2e	dd04d801-68c0-4eb7-86a8-f273e01c06f9	6930	Consulting Fees	expense	operating_expense	debit	Management and IT consulting fees	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
685bf354-ddcd-4f3d-b094-28f682a6a50a	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7000	Bank Charges	expense	operating_expense	debit	Bank service charges and fees	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
dec74c84-5172-498d-8592-7f29c59a6754	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7010	License & Permit Fees	expense	operating_expense	debit	Business licenses and permits	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
23fe7dff-0336-4d52-b70e-0f4815c86198	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7020	Taxes & Licenses	expense	operating_expense	debit	Business taxes and regulatory fees	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
ee01722a-5bab-4887-bf14-e89a3549dadb	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7030	Subscriptions & Memberships	expense	operating_expense	debit	Software subscriptions and professional memberships	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
f15cbd9b-3ce3-4b7f-8957-416b9a65900f	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7040	Training & Development	expense	operating_expense	debit	Employee training and professional development	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
ea21ba35-240b-4500-a22f-d1bd175fb1ff	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7100	Depreciation Expense	expense	operating_expense	debit	Depreciation of fixed assets	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
c0388295-9f9b-4324-b57f-1b56c56df528	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7110	Amortization Expense	expense	operating_expense	debit	Amortization of intangible assets	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
7b8c2fe6-5b1d-4454-92c6-605c00a8da1e	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7200	Loss on Disposal of Assets	expense	operating_expense	debit	Loss on sale or disposal of fixed assets	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
61f40d5b-ef73-4a49-8dd2-4461abf1fe59	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7210	Foreign Exchange Loss	expense	operating_expense	debit	Losses from foreign currency fluctuations	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
662463ea-ed1c-4ec8-9915-a0e8f61f8af9	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7220	Miscellaneous Expenses	expense	operating_expense	debit	Small miscellaneous expenses	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
1f4f748c-b088-4e2a-a152-bcd7c98ce54b	dd04d801-68c0-4eb7-86a8-f273e01c06f9	7230	Penalties & Fines	expense	operating_expense	debit	Regulatory penalties and late fees	t	t	2026-07-13 09:57:52	2026-07-13 09:57:52
40db6701-78e8-41e6-a113-2a5879d6d59e	de930bc6-a18b-44ac-919f-d42781db04b7	1100	Cash on Hand	asset	current_asset	debit	Physical cash at the business premises	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
6b25daaf-e35d-4405-9b11-2d8b80676cca	de930bc6-a18b-44ac-919f-d42781db04b7	1110	Petty Cash	asset	current_asset	debit	Small cash fund for minor expenses	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
a2afc4f8-d2b6-40f2-b336-1bd4b3fc97b9	de930bc6-a18b-44ac-919f-d42781db04b7	1120	Checking Account	asset	current_asset	debit	Business checking/current account	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
fc87edd9-d1ce-4f67-b57a-578cb2797a52	de930bc6-a18b-44ac-919f-d42781db04b7	1130	Savings Account	asset	current_asset	debit	Business savings account	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
1fe6259a-0a06-48f1-aee8-6a5c41fb2c25	de930bc6-a18b-44ac-919f-d42781db04b7	1140	Mobile Money Account	asset	current_asset	debit	Mobile money wallet (M-Pesa, Airtel Money, etc.)	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
09d0ce15-21a8-465e-9750-fc9cca551b19	de930bc6-a18b-44ac-919f-d42781db04b7	1200	Accounts Receivable	asset	current_asset	debit	Amounts owed by customers for credit sales	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
ac5126ec-03f3-4223-b46a-008574391f94	de930bc6-a18b-44ac-919f-d42781db04b7	1210	Employee Advances	asset	current_asset	debit	Advances paid to employees	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
2a614f6d-a664-451e-b457-228c69a73195	de930bc6-a18b-44ac-919f-d42781db04b7	1300	Inventory - Raw Materials	asset	current_asset	debit	Raw materials used in production	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
77cea523-8355-4972-84dd-86f93261ecdb	de930bc6-a18b-44ac-919f-d42781db04b7	1310	Inventory - Work in Progress	asset	current_asset	debit	Partially completed goods	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
ee9d12d5-c789-4b36-9bf2-74444b9da7ad	de930bc6-a18b-44ac-919f-d42781db04b7	1320	Inventory - Finished Goods	asset	current_asset	debit	Completed goods ready for sale	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
f8697360-8540-46a1-a2e8-797838aa800a	de930bc6-a18b-44ac-919f-d42781db04b7	1330	Inventory - Trading Goods	asset	current_asset	debit	Goods purchased for resale	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
e57f2a68-3cc2-4480-a9a3-0317ae186174	de930bc6-a18b-44ac-919f-d42781db04b7	1340	Inventory - Supplies	asset	current_asset	debit	Office and operational supplies	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
f94c75ce-b1cd-4406-95a5-6b25f44a24a9	de930bc6-a18b-44ac-919f-d42781db04b7	1400	Prepaid Expenses	asset	current_asset	debit	Expenses paid in advance	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
74b100c6-6761-4094-b49d-63611b55d97b	de930bc6-a18b-44ac-919f-d42781db04b7	1410	Prepaid Insurance	asset	current_asset	debit	Insurance premiums paid in advance	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
512c8b9b-da8d-4363-a0c0-4900d47c393e	de930bc6-a18b-44ac-919f-d42781db04b7	1500	Tax Receivable	asset	current_asset	debit	Input VAT and other recoverable taxes	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
aaa1e1d2-0525-4649-906a-cb92725d58b9	de930bc6-a18b-44ac-919f-d42781db04b7	1600	Land	asset	fixed_asset	debit	Land owned by the business	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
cb9f06f1-bbef-4bdd-baf1-781657a5c092	de930bc6-a18b-44ac-919f-d42781db04b7	1610	Buildings	asset	fixed_asset	debit	Buildings and structures owned by the business	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
71bab9e5-8570-4c65-a9f4-be68f2942ae9	de930bc6-a18b-44ac-919f-d42781db04b7	1620	Furniture & Fixtures	asset	fixed_asset	debit	Office furniture, shelving, and fixtures	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
9f304fa1-8f2e-4ae4-bc5d-15c52c73f47c	de930bc6-a18b-44ac-919f-d42781db04b7	1630	Office Equipment	asset	fixed_asset	debit	Office machinery and equipment	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
b940cc5d-255b-47ae-8462-4225a612d0dc	de930bc6-a18b-44ac-919f-d42781db04b7	1640	Computer Equipment	asset	fixed_asset	debit	Computers, servers, and IT hardware	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
a6593380-392f-480c-9d75-12dcd65a57f8	de930bc6-a18b-44ac-919f-d42781db04b7	1650	Motor Vehicles	asset	fixed_asset	debit	Company vehicles	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
802b2007-d643-40ec-83a2-a8b9e28f7909	de930bc6-a18b-44ac-919f-d42781db04b7	1660	Leasehold Improvements	asset	fixed_asset	debit	Improvements to leased premises	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
df41d5cb-573c-4aa6-b4ec-9a341fae05f9	de930bc6-a18b-44ac-919f-d42781db04b7	1670	Machinery & Equipment	asset	fixed_asset	debit	Production machinery and heavy equipment	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
1f9a0948-8ec7-474b-b51f-eba14af9c319	de930bc6-a18b-44ac-919f-d42781db04b7	1700	Accum. Depreciation - Buildings	asset	accum_depreciation	credit	Accumulated depreciation on buildings	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
4f785a46-459c-43d8-8fc7-f840381478c5	de930bc6-a18b-44ac-919f-d42781db04b7	1710	Accum. Depreciation - Furniture	asset	accum_depreciation	credit	Accumulated depreciation on furniture	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
a19c4adb-5ef5-4ccd-bf07-18593d93dab9	de930bc6-a18b-44ac-919f-d42781db04b7	1720	Accum. Depreciation - Equipment	asset	accum_depreciation	credit	Accumulated depreciation on equipment	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
3c6e8da8-26dd-497c-b94c-7ee0c0edac08	de930bc6-a18b-44ac-919f-d42781db04b7	1730	Accum. Depreciation - Vehicles	asset	accum_depreciation	credit	Accumulated depreciation on vehicles	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
5f294ba6-d6d6-47df-93fb-adf22cc2511c	de930bc6-a18b-44ac-919f-d42781db04b7	1800	Security Deposits	asset	other_asset	debit	Refundable deposits (rent, utilities, etc.)	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
fe567a13-1a6e-4600-9cd4-81ce7aad70cd	de930bc6-a18b-44ac-919f-d42781db04b7	1810	Goodwill	asset	other_asset	debit	Goodwill acquired in business acquisitions	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
46ae866a-5457-4853-8f1b-179d89784345	de930bc6-a18b-44ac-919f-d42781db04b7	1820	Intangible Assets	asset	other_asset	debit	Patents, trademarks, copyrights, licenses	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
611e90fe-5697-488d-91e3-943b17d5c634	de930bc6-a18b-44ac-919f-d42781db04b7	2100	Accounts Payable	liability	current_liability	credit	Amounts owed to suppliers	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
b82ce40e-c22f-419e-a9c1-96342f7a2527	de930bc6-a18b-44ac-919f-d42781db04b7	2110	Accrued Expenses	liability	current_liability	credit	Expenses incurred but not yet paid	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
208e9aab-a433-4e55-a10c-d62f22e6e858	de930bc6-a18b-44ac-919f-d42781db04b7	2120	Accrued Salaries & Wages	liability	current_liability	credit	Salaries earned but not yet paid	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
53312252-1db5-4391-8ac8-4ccc0e1313a7	de930bc6-a18b-44ac-919f-d42781db04b7	2130	Accrued Taxes Payable	liability	current_liability	credit	Taxes incurred but not yet remitted	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
7c9cc0fc-4193-42cc-b0c0-9e9ee0d31df9	de930bc6-a18b-44ac-919f-d42781db04b7	2140	Sales Tax Payable	liability	current_liability	credit	Output VAT/sales tax collected from customers	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
38a39153-3028-4c65-bb58-779b71a1f98f	de930bc6-a18b-44ac-919f-d42781db04b7	2150	Withholding Tax Payable	liability	current_liability	credit	Withholding tax to be remitted to tax authority	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
adc21e99-0fe5-4867-aa7a-a69cccf321fe	de930bc6-a18b-44ac-919f-d42781db04b7	2160	Customer Deposits	liability	current_liability	credit	Advance payments from customers	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
8116f73a-e81e-451e-9c57-8d943e282dff	de930bc6-a18b-44ac-919f-d42781db04b7	2170	Short-term Loans	liability	current_liability	credit	Short-term borrowings due within 12 months	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
940574d7-1c3b-4674-9105-736b95526f9f	de930bc6-a18b-44ac-919f-d42781db04b7	2200	Credit Cards Payable	liability	current_liability	credit	Outstanding credit card balances	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
7c1d3205-c72c-454b-8d37-074bb8f67d8b	de930bc6-a18b-44ac-919f-d42781db04b7	2210	Gift Cards / Vouchers Payable	liability	current_liability	credit	Outstanding gift card and voucher liabilities	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
30279986-1d7b-4ded-8947-daae56738708	de930bc6-a18b-44ac-919f-d42781db04b7	2300	Long-term Loans	liability	long_term_liability	credit	Long-term borrowings due after 12 months	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
143831d9-9ad8-4e20-b7a6-d30c17119dd3	de930bc6-a18b-44ac-919f-d42781db04b7	2310	Bank Loans	liability	long_term_liability	credit	Bank loans and overdrafts	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
f29b9850-86fb-49db-9819-44f0e50de48b	de930bc6-a18b-44ac-919f-d42781db04b7	2320	Mortgage Payable	liability	long_term_liability	credit	Mortgage loans secured by property	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
bb54d38b-79fd-461b-a72d-90be028e27ea	de930bc6-a18b-44ac-919f-d42781db04b7	2330	Shareholder Loans	liability	long_term_liability	credit	Loans from shareholders	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
50d094b0-fcf3-42fc-be74-7a84fadfe9d6	de930bc6-a18b-44ac-919f-d42781db04b7	3100	Owner's Capital	equity	owner_equity	credit	Owner's initial and additional capital contributions	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
0a8e9d36-f179-44e8-b98a-e406080571a7	de930bc6-a18b-44ac-919f-d42781db04b7	3110	Owner's Drawings	equity	owner_equity	debit	Owner's personal withdrawals from the business	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
8e0e1e5d-36ec-4664-a430-50100923f3f8	de930bc6-a18b-44ac-919f-d42781db04b7	3200	Retained Earnings	equity	retained_earnings	credit	Accumulated earnings retained in the business	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
d8238388-85e2-4223-8587-0e22d25b8117	de930bc6-a18b-44ac-919f-d42781db04b7	3300	Current Year Earnings	equity	retained_earnings	credit	Net profit/loss for the current fiscal year	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
0028bb9c-ab10-4867-be7d-17f3b0e08a0e	de930bc6-a18b-44ac-919f-d42781db04b7	3400	Share Capital	equity	owner_equity	credit	Capital received from share issuance	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
410a9bab-d53d-4ddc-829a-2e52969d84bd	de930bc6-a18b-44ac-919f-d42781db04b7	3410	Share Premium	equity	owner_equity	credit	Amount received above par value of shares	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
9b47c0ca-b86e-45d5-b02e-58f7d9eaf6ef	de930bc6-a18b-44ac-919f-d42781db04b7	4100	Sales Revenue - Products	revenue	operating_revenue	credit	Revenue from product sales	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
5306213a-c7b0-4d75-bf58-0c09b7fdd809	de930bc6-a18b-44ac-919f-d42781db04b7	4110	Sales Revenue - Services	revenue	operating_revenue	credit	Revenue from services rendered	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
7c8619a9-e6e5-4b90-9f8b-239b8dc5eaca	de930bc6-a18b-44ac-919f-d42781db04b7	4120	Sales Revenue - Digital Goods	revenue	operating_revenue	credit	Revenue from digital product sales	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
58c23196-d471-4b1b-92db-0c8c28120edd	de930bc6-a18b-44ac-919f-d42781db04b7	4200	Service Charges	revenue	operating_revenue	credit	Service fees and charges	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
59ef9a6c-ab66-4e14-b51e-75325c8d7e90	de930bc6-a18b-44ac-919f-d42781db04b7	4210	Delivery & Shipping Income	revenue	operating_revenue	credit	Revenue from delivery and shipping services	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
85064e2d-3ada-4719-a12d-7945126c6e88	de930bc6-a18b-44ac-919f-d42781db04b7	4300	Sales Discounts	revenue	contra_revenue	debit	Discounts allowed to customers	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
752c197d-c675-4b29-8539-8278ad5ca652	de930bc6-a18b-44ac-919f-d42781db04b7	4310	Sales Returns & Allowances	revenue	contra_revenue	debit	Returns and allowances granted to customers	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
34b210f4-db0d-42d7-88a8-5d2a7a518264	de930bc6-a18b-44ac-919f-d42781db04b7	4400	Interest Income	revenue	other_revenue	credit	Interest earned on bank deposits and investments	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
9334958e-8e45-47eb-a53d-5332daf05900	de930bc6-a18b-44ac-919f-d42781db04b7	4410	Rental Income	revenue	other_revenue	credit	Income from property rental	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
b3d6963d-7869-478f-8c44-ef29edb4e5ea	de930bc6-a18b-44ac-919f-d42781db04b7	4420	Commission Income	revenue	other_revenue	credit	Commission earned from third-party sales	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
5ea8c801-35b2-4d17-a0d8-95450b4af442	de930bc6-a18b-44ac-919f-d42781db04b7	4430	Other Income	revenue	other_revenue	credit	Miscellaneous non-operating income	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
4d0fb074-a19d-4d17-8872-740f3d4609b1	de930bc6-a18b-44ac-919f-d42781db04b7	5100	COGS - Products	expense	cogs	debit	Cost of products sold	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
044e5f9e-a061-426c-9405-c4ee929d2c2f	de930bc6-a18b-44ac-919f-d42781db04b7	5110	COGS - Services	expense	cogs	debit	Direct cost of services delivered	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
be571e10-4205-4f0a-9b8a-4b2d760a49a5	de930bc6-a18b-44ac-919f-d42781db04b7	5120	COGS - Digital Goods	expense	cogs	debit	Cost of digital goods sold	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
be0c4a00-efe8-4540-9c0d-45dd51e266d5	de930bc6-a18b-44ac-919f-d42781db04b7	5130	Freight & Shipping Costs	expense	cogs	debit	Freight and shipping costs on purchases	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
98c803dc-0c36-4f6c-b732-adf8625094ab	de930bc6-a18b-44ac-919f-d42781db04b7	5140	Inventory Adjustments	expense	cogs	debit	Write-offs, shrinkage, and inventory adjustments	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
204c93fd-74dd-49b9-b005-2c6f2b4b64d6	de930bc6-a18b-44ac-919f-d42781db04b7	6100	Rent Expense	expense	operating_expense	debit	Rent for business premises	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
e092bf2e-dbca-4a52-9d25-4f313d99afea	de930bc6-a18b-44ac-919f-d42781db04b7	6110	Lease Expense	expense	operating_expense	debit	Equipment and vehicle lease payments	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
67aba906-edaf-4a05-9cf5-ea584adc85e0	de930bc6-a18b-44ac-919f-d42781db04b7	6200	Utilities Expense	expense	operating_expense	debit	General utilities	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
9110a2e4-5189-4597-bf73-f2c035e2a794	de930bc6-a18b-44ac-919f-d42781db04b7	6210	Electricity	expense	operating_expense	debit	Electricity bills	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
00491cd0-1fdc-4bc6-991e-fc70a579f80c	de930bc6-a18b-44ac-919f-d42781db04b7	6220	Water & Sewer	expense	operating_expense	debit	Water and sewer bills	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
8294546f-dfc1-485e-a8c8-24981040320a	de930bc6-a18b-44ac-919f-d42781db04b7	6230	Internet & Telephone	expense	operating_expense	debit	Internet, phone, and communication costs	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
766de080-9229-489a-8fc6-97238a9ffb71	de930bc6-a18b-44ac-919f-d42781db04b7	6300	Salaries & Wages	expense	operating_expense	debit	Employee salaries and wages	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
9dcea3d5-c032-49c2-af45-08a2dca0619e	de930bc6-a18b-44ac-919f-d42781db04b7	6310	Employee Benefits	expense	operating_expense	debit	Health insurance, pension, and other benefits	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
72554c4a-872b-42d1-a09a-e393f5e80d7b	de930bc6-a18b-44ac-919f-d42781db04b7	6320	Payroll Taxes	expense	operating_expense	debit	Employer payroll taxes and contributions	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
5506dd4c-b17e-4943-938c-a9917b2a22de	de930bc6-a18b-44ac-919f-d42781db04b7	6330	Commission Expense	expense	operating_expense	debit	Sales commissions paid to staff	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
99ad03b6-bb20-4da5-99f6-43ab1d43fda8	de930bc6-a18b-44ac-919f-d42781db04b7	6400	Office Supplies Expense	expense	operating_expense	debit	Office supplies and stationery	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
8ecf8dee-f7f9-4a92-9f23-926c8ea5da29	de930bc6-a18b-44ac-919f-d42781db04b7	6410	Printing & Stationery	expense	operating_expense	debit	Printing, copying, and stationery costs	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
88e37c1e-2129-48b4-9cda-1ac36acf4d56	de930bc6-a18b-44ac-919f-d42781db04b7	6500	Maintenance & Repairs	expense	operating_expense	debit	General maintenance and repair costs	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
f5427cee-c221-4536-abaa-00a2a0930b85	de930bc6-a18b-44ac-919f-d42781db04b7	6510	Building Maintenance	expense	operating_expense	debit	Building and premises maintenance	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
2ec95657-4b5f-4ca0-8dd4-d438df9cbf41	de930bc6-a18b-44ac-919f-d42781db04b7	6520	Equipment Maintenance	expense	operating_expense	debit	Equipment servicing and repair	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
23933d2e-9cac-429c-8cc1-f1e115b6f886	de930bc6-a18b-44ac-919f-d42781db04b7	6600	Marketing & Advertising	expense	operating_expense	debit	Advertising and marketing costs	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
bba85974-ca38-4e08-8764-0da029dc43dd	de930bc6-a18b-44ac-919f-d42781db04b7	6610	Promotional Expenses	expense	operating_expense	debit	Promotional events and materials	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
5d861da7-c8cf-4b53-85ef-672cd43c0b19	de930bc6-a18b-44ac-919f-d42781db04b7	6620	Online Advertising	expense	operating_expense	debit	Digital and social media advertising	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
1802b1ae-aae1-46fc-b141-ad4469304392	de930bc6-a18b-44ac-919f-d42781db04b7	6700	Transportation & Travel	expense	operating_expense	debit	Business travel and transportation costs	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
49c018ed-f23f-49c2-9dc3-8a3e017f7613	de930bc6-a18b-44ac-919f-d42781db04b7	6710	Fuel Expense	expense	operating_expense	debit	Vehicle fuel costs	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
8388effb-9a07-4dc7-82d9-78e6100ef204	de930bc6-a18b-44ac-919f-d42781db04b7	6720	Travel & Accommodation	expense	operating_expense	debit	Travel and hotel accommodation	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
eb0dcfcf-fcfe-40d5-bbdb-427e33618423	de930bc6-a18b-44ac-919f-d42781db04b7	6800	Insurance Expense	expense	operating_expense	debit	General insurance costs	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
0ccf3df3-56df-4b49-bf49-decec855f285	de930bc6-a18b-44ac-919f-d42781db04b7	6810	Health Insurance	expense	operating_expense	debit	Employee health insurance premiums	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
9229288f-1155-41bb-8085-c428034a501d	de930bc6-a18b-44ac-919f-d42781db04b7	6900	Professional Fees	expense	operating_expense	debit	Professional service fees	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
3b495112-1819-42d5-9fac-4847d883570d	de930bc6-a18b-44ac-919f-d42781db04b7	6910	Legal Fees	expense	operating_expense	debit	Legal and attorney fees	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
4ade6ad2-aa19-445f-81ba-01dcc631a0a0	de930bc6-a18b-44ac-919f-d42781db04b7	6920	Accounting & Audit Fees	expense	operating_expense	debit	Accounting, bookkeeping, and audit fees	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
fde715bd-51f0-43f8-9ea6-b3e44aff2705	de930bc6-a18b-44ac-919f-d42781db04b7	6930	Consulting Fees	expense	operating_expense	debit	Management and IT consulting fees	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
0d4d6342-4e56-4ece-8aa6-616a2b057a72	de930bc6-a18b-44ac-919f-d42781db04b7	7000	Bank Charges	expense	operating_expense	debit	Bank service charges and fees	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
5c2fa90b-3435-4ca6-be69-f59d7d24a234	de930bc6-a18b-44ac-919f-d42781db04b7	7010	License & Permit Fees	expense	operating_expense	debit	Business licenses and permits	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
f9f0c006-8ccc-4400-a8bf-26442fcf405b	de930bc6-a18b-44ac-919f-d42781db04b7	7020	Taxes & Licenses	expense	operating_expense	debit	Business taxes and regulatory fees	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
53c71cd4-d890-465e-821c-b72dd58b9f9b	de930bc6-a18b-44ac-919f-d42781db04b7	7030	Subscriptions & Memberships	expense	operating_expense	debit	Software subscriptions and professional memberships	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
28cd2667-1330-4f07-975d-d148d84f734d	de930bc6-a18b-44ac-919f-d42781db04b7	7040	Training & Development	expense	operating_expense	debit	Employee training and professional development	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
9c69b408-a466-4094-8125-1aefd49bc439	de930bc6-a18b-44ac-919f-d42781db04b7	7100	Depreciation Expense	expense	operating_expense	debit	Depreciation of fixed assets	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
d2e90148-0892-42d3-9ce1-c5b7819c8f18	de930bc6-a18b-44ac-919f-d42781db04b7	7110	Amortization Expense	expense	operating_expense	debit	Amortization of intangible assets	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
182d924f-004c-4b81-92ca-e02d188c567a	de930bc6-a18b-44ac-919f-d42781db04b7	7200	Loss on Disposal of Assets	expense	operating_expense	debit	Loss on sale or disposal of fixed assets	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
71a76901-0140-4645-811b-d653c3aeb381	de930bc6-a18b-44ac-919f-d42781db04b7	7210	Foreign Exchange Loss	expense	operating_expense	debit	Losses from foreign currency fluctuations	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
e9177861-3b95-492e-aa25-b6a342c0d619	de930bc6-a18b-44ac-919f-d42781db04b7	7220	Miscellaneous Expenses	expense	operating_expense	debit	Small miscellaneous expenses	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
43c4bbe6-d36f-4b38-a7e2-529966276a0a	de930bc6-a18b-44ac-919f-d42781db04b7	7230	Penalties & Fines	expense	operating_expense	debit	Regulatory penalties and late fees	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
\.


--
-- Data for Name: customers; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.customers (id, phone, email, name, location, loyalty_points, member_level, created_at, updated_at, branch_id, deleted_at) FROM stdin;
\.


--
-- Data for Name: devices; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.devices (id, branch_id, name, device_id, type, status, description, firmware_version, ip_address, mac_address, os, enrollment_token, enrolled_at, last_seen_at, last_sync_at, capabilities, config, certificate_serial, certificate_expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: document_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.document_items (id, document_id, product_id, description, quantity, unit_price, discount, tax_rate, tax_amount, total, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: document_payments; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.document_payments (id, document_id, amount, method, reference, payment_date, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: documents; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.documents (id, document_number, document_type, status, customer_id, branch_id, issue_date, expiry_date, due_date, subtotal, discount, tax_amount, total_amount, paid_amount, notes, terms_conditions, converted_from_id, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: efris_configs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.efris_configs (id, integration_id, branch_id, tin, weaf_email, weaf_token, weaf_token_expires_at, weaf_environment, company_name, company_weaf_id, auto_fiscalize, fiscalize_receipts, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: efris_fiscal_logs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.efris_fiscal_logs (id, branch_id, sale_id, efris_invoice_no, efris_fdn, efris_qr_code, efris_verification_code, request_payload, response_payload, status, error_message, retry_count, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: expenses; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.expenses (id, branch_id, payee, amount, method, category, reference, expense_date, notes, purchase_order_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: grn; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.grn (id, purchase_order_id, received_by, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: grn_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.grn_items (id, grn_id, product_id, quantity, unit_cost, batch_number, expiry_date, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: hold_sales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.hold_sales (id, branch_id, user_id, customer_id, cart_data, promo_code, tax_profile_id, loyalty_points_redeemed, note, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: integrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.integrations (id, branch_id, type, name, status, config, last_sync_at, last_error, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: inventory; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.inventory (id, product_id, warehouse_id, quantity, batch_number, expiry_date, serial_number, created_at, updated_at, reserved_quantity, sync_status) FROM stdin;
3c4584f6-a003-42cb-95fe-352ecd787fed	50e153e7-1e4e-4c96-b2f5-5b11366fe324	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	15.000	BATCH-KSN1UL	2026-10-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
d9721680-4c6b-4caa-b0ee-391d0a61b29f	50e153e7-1e4e-4c96-b2f5-5b11366fe324	43be6d7e-f940-4876-8a30-c4c43926f5c1	84.000	BATCH-SQWRCX	2027-01-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
9d449f4d-2efb-4883-b953-85b519cb36a8	7e04f86d-708f-41e7-83ba-660e20f87b3b	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	32.000	BATCH-REEZMT	2027-07-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
8223db51-9dc2-4f51-a3c9-6099a3fdfa0a	7e04f86d-708f-41e7-83ba-660e20f87b3b	43be6d7e-f940-4876-8a30-c4c43926f5c1	29.000	BATCH-IFJFKF	2027-02-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
084eb646-e5e3-4aeb-bff5-c9622f385843	26e027ae-d1a7-48e1-9b55-86d197456438	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	71.000	BATCH-HVO9N8	2027-09-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
c7c9d469-29b8-40f3-9e30-6d6644e553d1	26e027ae-d1a7-48e1-9b55-86d197456438	43be6d7e-f940-4876-8a30-c4c43926f5c1	98.000	BATCH-VK91U7	2027-11-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
a3b185ca-2b0a-406b-b336-3ffe74d5ff63	1ef9c87f-ad9a-4f12-9e1c-b75e5c99b2de	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	77.000	BATCH-INDXBQ	2027-06-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
390086f0-f62d-46aa-aeea-c1b26abcdd7e	1ef9c87f-ad9a-4f12-9e1c-b75e5c99b2de	43be6d7e-f940-4876-8a30-c4c43926f5c1	71.000	BATCH-XN3SCK	2027-05-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
c20f6761-1d2e-4b97-91ed-98ed0e231e58	89495410-1544-42d3-a7e1-840d526c6cde	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	100.000	BATCH-MEYA0R	2026-10-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
a2cc97d9-3ba9-4690-b4c0-3603678b6269	89495410-1544-42d3-a7e1-840d526c6cde	43be6d7e-f940-4876-8a30-c4c43926f5c1	67.000	BATCH-OQTDE1	2026-11-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
dfb940cf-746e-4b88-b608-c941b1a7626b	aa48f0b6-ebde-45a3-8aa1-4917c9baf3b4	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	91.000	BATCH-HXRRQA	2027-12-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
b169c171-3039-4318-9a16-e0660c1e493b	aa48f0b6-ebde-45a3-8aa1-4917c9baf3b4	43be6d7e-f940-4876-8a30-c4c43926f5c1	79.000	BATCH-VDNS0N	2027-05-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
d88ab4de-52ba-43ed-afdb-2c06652b69f6	a48fcb7d-6019-44ae-b022-315bb882c549	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	84.000	BATCH-RI0JBZ	2026-11-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
4fec95cf-35ed-4708-a836-3d400d1e9296	a48fcb7d-6019-44ae-b022-315bb882c549	43be6d7e-f940-4876-8a30-c4c43926f5c1	40.000	BATCH-VQUFRA	2027-02-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
532d0a8b-788d-46cc-81f6-ebffe4f28605	c730c57f-3878-42cb-b7bd-27405c0e1402	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	20.000	BATCH-IBHBUJ	2026-12-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
948a9c04-8ecd-47bc-a590-2a5f76d96957	c730c57f-3878-42cb-b7bd-27405c0e1402	43be6d7e-f940-4876-8a30-c4c43926f5c1	76.000	BATCH-HUNMHB	2027-09-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
9671739a-bf0f-46e4-a077-65a412f561eb	81ca6528-2231-4b43-98f1-7ead20c1eae4	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	89.000	BATCH-TMAVOM	2027-03-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
7a3ed4bb-02ad-4472-af64-dd8912e40783	81ca6528-2231-4b43-98f1-7ead20c1eae4	43be6d7e-f940-4876-8a30-c4c43926f5c1	77.000	BATCH-BPJUV8	2027-07-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
5415072a-8551-49ef-b6bb-726ef4f22fc8	5a6b7554-ae63-4457-bfcb-e040e90e5301	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	17.000	BATCH-RQXOTJ	2027-02-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
81a46e37-57ad-4ebe-9859-781df115e5f1	5a6b7554-ae63-4457-bfcb-e040e90e5301	43be6d7e-f940-4876-8a30-c4c43926f5c1	54.000	BATCH-OMOKLN	2027-01-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
ca0769f3-fb6a-4d0c-9ba7-7620564979d4	497af3ce-44fb-450c-ab8b-20c89f360f7d	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	27.000	BATCH-OLCJAK	2026-12-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
36180aa5-ad52-4495-be5e-d1f7ebfa8670	497af3ce-44fb-450c-ab8b-20c89f360f7d	43be6d7e-f940-4876-8a30-c4c43926f5c1	40.000	BATCH-7WOLGA	2026-10-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
b40a7b7a-5aed-4724-8c59-9bb4a01b363f	f085ef50-7072-4fbe-8fa7-14894aa9e1c1	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	73.000	BATCH-E1NAVM	2027-10-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
729b9d8b-e51a-4a02-9ed8-752b1f708928	f085ef50-7072-4fbe-8fa7-14894aa9e1c1	43be6d7e-f940-4876-8a30-c4c43926f5c1	82.000	BATCH-HJMGM1	2027-08-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
0062e72b-4f1b-442b-bf40-d1206446afaf	15969fb1-47f3-453e-b32e-9e8cc2b2f642	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	59.000	BATCH-DR5HK3	2026-11-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
5d120f75-3980-4197-8b55-45792c2a075a	15969fb1-47f3-453e-b32e-9e8cc2b2f642	43be6d7e-f940-4876-8a30-c4c43926f5c1	59.000	BATCH-JUPXMD	2027-04-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
97b3548a-745b-4978-b448-2f0043090d3c	edcfcb6e-512f-47fa-98b1-cb8bd1850643	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	78.000	BATCH-VI16MC	2027-11-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
5be17fb2-07b5-44e5-aaaa-d2760e203aaa	edcfcb6e-512f-47fa-98b1-cb8bd1850643	43be6d7e-f940-4876-8a30-c4c43926f5c1	54.000	BATCH-YOMRPI	2027-08-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
cc2ddd78-abea-41f6-9553-b146030f8eb8	76603ead-e64b-4ca9-80d4-831c8775072f	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	17.000	BATCH-6XYLEO	2026-10-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
787fa5ec-1d61-49a4-940e-aef72f679980	76603ead-e64b-4ca9-80d4-831c8775072f	43be6d7e-f940-4876-8a30-c4c43926f5c1	80.000	BATCH-DMYDLA	2027-09-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
f87546d7-6f4c-49c2-bef5-d2fa966a3236	95c4475c-5a80-479e-97e3-ac363890fc6a	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	65.000	BATCH-7KCLWL	2027-08-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
d9cb16f9-53f7-45d4-b105-add051d5767f	95c4475c-5a80-479e-97e3-ac363890fc6a	43be6d7e-f940-4876-8a30-c4c43926f5c1	79.000	BATCH-1UL2US	2026-12-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
2731facc-9687-44ac-b1a9-01183c81bef7	959b1473-a7e3-473e-b152-a104adf06abd	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	32.000	BATCH-7608CH	2027-06-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
4e3402f9-9df3-4570-adda-4dc26f5c421d	959b1473-a7e3-473e-b152-a104adf06abd	43be6d7e-f940-4876-8a30-c4c43926f5c1	39.000	BATCH-KYKEKN	2026-11-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
49d36039-b606-41c0-a7ae-89f2a1772cb0	0bf06692-d8c6-4e88-92d0-c1b48e29a362	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	36.000	BATCH-IOEQND	2027-07-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
dae4b7b2-6921-4410-a035-1718caac2dfc	0bf06692-d8c6-4e88-92d0-c1b48e29a362	43be6d7e-f940-4876-8a30-c4c43926f5c1	59.000	BATCH-A8ECSZ	2027-04-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
1a49e4d4-68aa-4b1b-bb3f-571d09bca391	7dfb3088-a7f7-401b-84ab-fff8b14d7fba	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	18.000	BATCH-1BSYKS	2027-05-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
7549d731-0f6f-41c8-87c5-cac54b7474aa	7dfb3088-a7f7-401b-84ab-fff8b14d7fba	43be6d7e-f940-4876-8a30-c4c43926f5c1	87.000	BATCH-HQ1NDH	2027-09-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
7a9330c1-4248-4eed-9f11-69c931132ba4	3f4bfd59-0cc9-4fa4-b41e-cf96c809eead	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	80.000	BATCH-AKA6HR	2027-07-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
8f9e483c-a54a-4ef4-8432-2e51bcec01d0	3f4bfd59-0cc9-4fa4-b41e-cf96c809eead	43be6d7e-f940-4876-8a30-c4c43926f5c1	30.000	BATCH-P5GESX	2027-10-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
224d1f4a-5764-4e6b-b146-6e5c0affbeca	9a54e675-164d-42d9-b8c9-67082a813b1f	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	97.000	BATCH-IW2N3M	2026-10-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
ce0ec3a2-417f-43bd-aa97-3928300a14e9	9a54e675-164d-42d9-b8c9-67082a813b1f	43be6d7e-f940-4876-8a30-c4c43926f5c1	56.000	BATCH-BMPDLQ	2027-04-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
e26697a6-4255-4ff4-a1e4-5ce30992290f	5e137dd8-3429-4634-99de-e263b8261299	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	32.000	BATCH-AFZH4U	2027-06-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
a141f905-559f-4486-8845-7e92b950c961	5e137dd8-3429-4634-99de-e263b8261299	43be6d7e-f940-4876-8a30-c4c43926f5c1	32.000	BATCH-ER6POQ	2027-11-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
424d3fae-eda7-474a-b826-3adebd9ac659	f126ae8f-2f46-4b62-8977-96bb79245470	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	61.000	BATCH-EB1FSQ	2026-10-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
45a5ad12-ba23-4b50-a605-4d815a4ec928	f126ae8f-2f46-4b62-8977-96bb79245470	43be6d7e-f940-4876-8a30-c4c43926f5c1	80.000	BATCH-1WNQ7D	2027-05-13	\N	2026-07-13 09:57:50	2026-07-13 09:57:50	0.000	synced
\.


--
-- Data for Name: inventory_adjustments; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.inventory_adjustments (id, branch_id, product_id, warehouse_id, quantity, type, reason, reference, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: journal_entries; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.journal_entries (id, branch_id, entry_number, entry_date, description, reference_type, reference_id, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: journal_entry_lines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.journal_entry_lines (id, journal_entry_id, account_id, debit_amount, credit_amount, description, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: loyalty_rules; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.loyalty_rules (id, points_per_amount, points_earned, signup_bonus_points, member_levels, reward_thresholds, is_active, created_at, updated_at) FROM stdin;
f5f7d0e3-0c16-4741-a528-6f0b0474a3e7	10.00	1	50	[{"level":"bronze","min_points":0},{"level":"silver","min_points":100},{"level":"gold","min_points":500},{"level":"platinum","min_points":2000}]	[{"points":100,"discount":5},{"points":500,"discount":30},{"points":1000,"discount":75}]	t	2026-07-13 09:57:52	2026-07-13 09:57:52
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_branches_table	1
2	0001_01_01_000001_create_customers_table	1
3	0001_01_01_000002_create_inventory_tables	1
4	0001_01_01_000003_create_warehouses_table	1
5	0001_01_01_000004_create_suppliers_table	1
6	0001_01_01_000005_create_purchase_orders_table	1
7	0001_01_01_000006_create_sales_table	1
8	0001_01_01_000007_create_sale_items_table	1
9	0001_01_01_000008_create_payments_table	1
10	0001_01_01_000009_create_users_table	1
11	0001_01_01_000010_create_roles_table	1
12	0001_01_01_000011_create_permissions_table	1
13	0001_01_01_000012_create_role_user_table	1
14	0001_01_01_000013_create_permission_role_table	1
15	0001_01_01_000014_create_syncs_table	1
16	0001_01_01_000018_create_grn_table	1
17	0001_01_01_000019_create_grn_items_table	1
18	0001_01_01_000020_add_business_type_to_branches	1
19	0001_01_01_000021_create_business_profiles_table	1
20	2019_12_14_000001_create_personal_access_tokens_table	1
21	2026_06_22_162438_create_categories_table	1
22	2026_06_22_162440_add_category_id_to_products_table	1
23	2026_06_23_131732_drop_category_from_products_table	1
24	2026_06_23_132122_make_cost_nullable_in_products_table	1
25	2026_06_23_133000_add_location_to_business_profiles_table	1
26	2026_06_23_133001_create_subscriptions_table	1
27	2026_06_23_140000_add_branch_id_to_role_user_table	1
28	2026_06_23_140001_create_devices_table	1
29	2026_06_23_164326_create_stock_transfers_table	1
30	2026_06_23_164327_create_stock_transfer_items_table	1
31	2026_06_23_164510_create_returns_table	1
32	2026_06_23_164511_create_return_items_table	1
33	2026_06_23_165804_create_purchase_order_items_table	1
34	2026_06_23_170001_create_promotions_table	1
35	2026_06_23_170002_create_tax_profiles_table	1
36	2026_06_23_170003_create_loyalty_rules_table	1
37	2026_06_23_194723_add_image_to_products_table	1
38	2026_06_23_200000_add_parent_id_to_categories_table	1
39	2026_06_24_000001_create_password_reset_tokens_table	1
40	2026_06_24_000002_add_email_verified_at_to_users_table	1
41	2026_06_24_000003_add_reserved_quantity_to_inventory_table	1
42	2026_06_24_000004_add_refund_tracking_to_returns_table	1
43	2026_06_24_000005_fix_sales_status_enum	1
44	2026_06_24_000006_add_tax_columns_to_sale_items_table	1
45	2026_06_24_000007_create_hold_sales_table	1
46	2026_06_24_000010_fix_inventory_columns	1
47	2026_06_25_000001_add_soft_deletes_and_branch_id_to_customers_table	1
48	2026_06_26_000001_add_is_editable_to_roles_table	1
49	2026_06_26_000002_create_branch_user_table	1
50	2026_06_26_000003_schema_fixes_phase1	1
51	2026_06_26_000004_add_is_protected_to_users_table	1
52	2026_06_26_000005_create_stock_movements_table	1
53	2026_06_26_000006_create_expenses_table	1
54	2026_06_26_000007_add_condition_to_return_items	1
55	2026_06_26_000008_add_returnable_to_categories_and_products	1
56	2026_06_26_000009_create_documents_table	1
57	2026_06_26_000010_create_cash_register_shifts_table	1
58	2026_06_26_000011_add_secret_question_to_users	1
59	2026_06_27_000001_make_barcode_nullable	1
60	2026_06_27_000002_add_avatar_to_users	1
61	2026_06_28_000001_create_chart_of_accounts_table	1
62	2026_06_28_000002_create_journal_entries_table	1
63	2026_06_28_000003_create_journal_entry_lines_table	1
64	2026_06_28_000004_create_operating_accounts_table	1
65	2026_06_28_000005_create_bank_reconciliations_table	1
66	2026_06_28_000006_fix_normal_balance_column	1
67	2026_06_30_000001_add_is_default_account_to_users_table	1
68	2026_07_01_094003_create_notifications_table	1
69	2026_07_01_094004_fix_notifications_uuid	1
70	2026_07_07_000001_add_local_id_to_syncs_table	1
71	2026_07_07_000002_ensure_business_profiles_settings_column	1
72	2026_07_08_000001_create_integrations_table	1
73	2026_07_08_000002_create_efris_configs_table	1
74	2026_07_08_000003_create_efris_fiscal_logs_table	1
75	2026_07_08_000004_add_efris_columns_to_sales_table	1
76	2026_07_09_000001_create_failed_jobs_table	1
77	2026_07_09_000002_create_job_batches_table	1
78	2026_07_09_000003_create_activity_logs_table	1
79	2026_07_09_000004_add_request_columns_to_activity_logs	1
\.


--
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: operating_accounts; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.operating_accounts (id, branch_id, account_id, name, type, account_number, bank_name, currency, is_default, opening_balance, current_balance, is_system, is_active, created_at, updated_at) FROM stdin;
a99ea814-2fa9-4943-949a-4543b7afc89a	dd04d801-68c0-4eb7-86a8-f273e01c06f9	1c08080e-95a2-4508-bbd9-ba1ccac8eff4	Main Cash Drawer	cash	\N	\N	KES	t	0.00	0.00	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
dbd9eca6-eb45-41ff-951f-2c871b3f46fd	dd04d801-68c0-4eb7-86a8-f273e01c06f9	c22fcda0-eb68-4ea1-b5e4-79ab4c912cc7	Petty Cash Fund	petty_cash	\N	\N	KES	f	0.00	0.00	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
1013c569-cb64-4c92-8642-0837e82d39bf	dd04d801-68c0-4eb7-86a8-f273e01c06f9	a781a2a3-9dff-4932-8869-c334a6a0ecf6	Business Bank Account	bank	\N	\N	KES	f	0.00	0.00	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
c49e1695-67a1-421a-9cb7-f6400f6814d7	de930bc6-a18b-44ac-919f-d42781db04b7	40db6701-78e8-41e6-a113-2a5879d6d59e	Main Cash Drawer	cash	\N	\N	KES	t	0.00	0.00	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
e1474289-3a28-4b13-a77e-2c74837f783d	de930bc6-a18b-44ac-919f-d42781db04b7	6b25daaf-e35d-4405-9b11-2d8b80676cca	Petty Cash Fund	petty_cash	\N	\N	KES	f	0.00	0.00	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
c936333c-9cf3-41ba-ab81-b3b9c315921c	de930bc6-a18b-44ac-919f-d42781db04b7	a2afc4f8-d2b6-40f2-b336-1bd4b3fc97b9	Business Bank Account	bank	\N	\N	KES	f	0.00	0.00	t	t	2026-07-13 09:57:53	2026-07-13 09:57:53
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: payments; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.payments (id, sale_id, amount, method, gateway, txn_id, status, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: permission_role; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permission_role (permission_id, role_id) FROM stdin;
3819fb3e-2d9d-4560-b4af-349b8bf47517	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
ca911741-2dd8-4cb2-a265-e13eba71a2b7	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
b0b3dd6b-6736-40f2-a57f-1813dbfd938f	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
353ba2da-ea0f-40ea-b80d-0b6906faaa93	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
24ca097a-99bc-4e14-b0f6-5f51e4f85378	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
8f39adf5-6f89-4056-9525-0eeff3ef85c0	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
491648b9-7354-41a7-9b73-1f3d745efd76	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
b49761a4-c66c-4cab-aa99-5d4693bfd103	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
54acaba1-c86b-481d-b7d8-9c9545b0854a	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
bb4428ac-f573-4a37-a701-980c5065e5da	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
782560ed-f2dc-4911-b6de-5ce1016fed2e	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
bb7cb2c6-f1bc-44e3-a510-d4b2f1e19b43	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
936522a2-13d4-40eb-9a4f-00276a17114a	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
78fa4ead-346f-4375-9365-36a53b91d3f0	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
a9bad8c0-8e0d-46d7-9dfe-1a17e082f5d0	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
c127ab8c-18b2-42d7-8d93-e16cc022f191	2f38896f-53a7-4d3e-bbd8-b158891e2e1a
3819fb3e-2d9d-4560-b4af-349b8bf47517	05bac452-6b8b-4488-973f-ec9f3db4ce9c
ca911741-2dd8-4cb2-a265-e13eba71a2b7	05bac452-6b8b-4488-973f-ec9f3db4ce9c
b0b3dd6b-6736-40f2-a57f-1813dbfd938f	05bac452-6b8b-4488-973f-ec9f3db4ce9c
353ba2da-ea0f-40ea-b80d-0b6906faaa93	05bac452-6b8b-4488-973f-ec9f3db4ce9c
8f39adf5-6f89-4056-9525-0eeff3ef85c0	05bac452-6b8b-4488-973f-ec9f3db4ce9c
491648b9-7354-41a7-9b73-1f3d745efd76	05bac452-6b8b-4488-973f-ec9f3db4ce9c
b49761a4-c66c-4cab-aa99-5d4693bfd103	05bac452-6b8b-4488-973f-ec9f3db4ce9c
bb4428ac-f573-4a37-a701-980c5065e5da	05bac452-6b8b-4488-973f-ec9f3db4ce9c
782560ed-f2dc-4911-b6de-5ce1016fed2e	05bac452-6b8b-4488-973f-ec9f3db4ce9c
936522a2-13d4-40eb-9a4f-00276a17114a	05bac452-6b8b-4488-973f-ec9f3db4ce9c
78fa4ead-346f-4375-9365-36a53b91d3f0	05bac452-6b8b-4488-973f-ec9f3db4ce9c
a9bad8c0-8e0d-46d7-9dfe-1a17e082f5d0	05bac452-6b8b-4488-973f-ec9f3db4ce9c
c127ab8c-18b2-42d7-8d93-e16cc022f191	05bac452-6b8b-4488-973f-ec9f3db4ce9c
3819fb3e-2d9d-4560-b4af-349b8bf47517	32229581-9737-4689-8c8a-d9ac54aef1d6
491648b9-7354-41a7-9b73-1f3d745efd76	32229581-9737-4689-8c8a-d9ac54aef1d6
b0b3dd6b-6736-40f2-a57f-1813dbfd938f	32229581-9737-4689-8c8a-d9ac54aef1d6
ca911741-2dd8-4cb2-a265-e13eba71a2b7	257597e4-ff68-40cc-8213-349204925524
353ba2da-ea0f-40ea-b80d-0b6906faaa93	257597e4-ff68-40cc-8213-349204925524
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permissions (id, name, guard_name, created_at, updated_at) FROM stdin;
3819fb3e-2d9d-4560-b4af-349b8bf47517	manage_sales	web	2026-07-13 09:57:50	2026-07-13 09:57:50
ca911741-2dd8-4cb2-a265-e13eba71a2b7	manage_inventory	web	2026-07-13 09:57:50	2026-07-13 09:57:50
b0b3dd6b-6736-40f2-a57f-1813dbfd938f	manage_customers	web	2026-07-13 09:57:50	2026-07-13 09:57:50
353ba2da-ea0f-40ea-b80d-0b6906faaa93	manage_products	web	2026-07-13 09:57:50	2026-07-13 09:57:50
24ca097a-99bc-4e14-b0f6-5f51e4f85378	manage_branches	web	2026-07-13 09:57:50	2026-07-13 09:57:50
8f39adf5-6f89-4056-9525-0eeff3ef85c0	view_reports	web	2026-07-13 09:57:50	2026-07-13 09:57:50
491648b9-7354-41a7-9b73-1f3d745efd76	process_payments	web	2026-07-13 09:57:50	2026-07-13 09:57:50
b49761a4-c66c-4cab-aa99-5d4693bfd103	manage_users	web	2026-07-13 09:57:50	2026-07-13 09:57:50
54acaba1-c86b-481d-b7d8-9c9545b0854a	manage_devices	web	2026-07-13 09:57:50	2026-07-13 09:57:50
bb4428ac-f573-4a37-a701-980c5065e5da	void_sale	web	2026-07-13 09:57:50	2026-07-13 09:57:50
782560ed-f2dc-4911-b6de-5ce1016fed2e	approve_return	web	2026-07-13 09:57:50	2026-07-13 09:57:50
bb7cb2c6-f1bc-44e3-a510-d4b2f1e19b43	manage_subscription	web	2026-07-13 09:57:50	2026-07-13 09:57:50
936522a2-13d4-40eb-9a4f-00276a17114a	manage_business_profile	web	2026-07-13 09:57:50	2026-07-13 09:57:50
78fa4ead-346f-4375-9365-36a53b91d3f0	export_data	web	2026-07-13 09:57:50	2026-07-13 09:57:50
a9bad8c0-8e0d-46d7-9dfe-1a17e082f5d0	manage_accounting	web	2026-07-13 09:57:50	2026-07-13 09:57:50
c127ab8c-18b2-42d7-8d93-e16cc022f191	manage_integrations	web	2026-07-13 09:57:50	2026-07-13 09:57:50
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
1	App\\Models\\User	865f94df-5c67-49cc-b8ac-c7e11285b9d4	auth-token	48a73694c5a216849dedd496e6f94fb077bcff6672d914de0b4207a6f3e13d86	["*"]	\N	\N	2026-07-13 10:23:32	2026-07-13 10:23:32
\.


--
-- Data for Name: products; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.products (id, name, barcode, price, cost, stock_uom, min_stock, description, is_active, created_at, updated_at, category_id, image, returnable) FROM stdin;
50e153e7-1e4e-4c96-b2f5-5b11366fe324	Tusker Lager 500ml	BR001	3.00	1.80	pcs	50	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	f22f0922-f529-40d8-b28a-096a1f75107a	\N	\N
7e04f86d-708f-41e7-83ba-660e20f87b3b	White Cap Lager 500ml	BR002	3.00	1.80	pcs	50	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	f22f0922-f529-40d8-b28a-096a1f75107a	\N	\N
26e027ae-d1a7-48e1-9b55-86d197456438	Guinness Draught 500ml	BR003	4.00	2.50	pcs	30	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	f22f0922-f529-40d8-b28a-096a1f75107a	\N	\N
1ef9c87f-ad9a-4f12-9e1c-b75e5c99b2de	Heineken 500ml	BR004	4.00	2.50	pcs	30	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	f22f0922-f529-40d8-b28a-096a1f75107a	\N	\N
89495410-1544-42d3-a7e1-840d526c6cde	Smirnoff Vodka 750ml	BR005	15.00	9.00	pcs	10	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	7551b1b9-45da-4897-8fb6-157bea52fb64	\N	\N
aa48f0b6-ebde-45a3-8aa1-4917c9baf3b4	Johnnie Walker Red 750ml	BR006	25.00	16.00	pcs	5	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	8002151b-3841-4ad2-bda4-30d9cfe5ef42	\N	\N
a48fcb7d-6019-44ae-b022-315bb882c549	Jameson Irish 750ml	BR007	22.00	14.00	pcs	5	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	8002151b-3841-4ad2-bda4-30d9cfe5ef42	\N	\N
c730c57f-3878-42cb-b7bd-27405c0e1402	Baileys Irish Cream 750ml	BR008	28.00	18.00	pcs	5	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	7f4bb105-a025-4c67-9b90-de7bf362748e	\N	\N
81ca6528-2231-4b43-98f1-7ead20c1eae4	Coca Cola 330ml	BR009	1.50	0.70	pcs	100	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	6ca3ce04-1fbd-4f2d-83b3-792bb4b55cb1	\N	\N
5a6b7554-ae63-4457-bfcb-e040e90e5301	Fanta Orange 330ml	BR010	1.50	0.70	pcs	80	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	6ca3ce04-1fbd-4f2d-83b3-792bb4b55cb1	\N	\N
497af3ce-44fb-450c-ab8b-20c89f360f7d	Sprite 330ml	BR011	1.50	0.70	pcs	80	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	6ca3ce04-1fbd-4f2d-83b3-792bb4b55cb1	\N	\N
f085ef50-7072-4fbe-8fa7-14894aa9e1c1	Mineral Water 500ml	BR012	1.00	0.40	pcs	100	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	6ca3ce04-1fbd-4f2d-83b3-792bb4b55cb1	\N	\N
15969fb1-47f3-453e-b32e-9e8cc2b2f642	Fresh Orange Juice 300ml	BR013	2.50	1.20	pcs	30	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	6ca3ce04-1fbd-4f2d-83b3-792bb4b55cb1	\N	\N
edcfcb6e-512f-47fa-98b1-cb8bd1850643	Nyama Choma (1kg)	BR014	12.00	7.00	kg	10	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	c398f01c-eb0c-47cf-a1f9-84244e0d0a4d	\N	\N
76603ead-e64b-4ca9-80d4-831c8775072f	Grilled Tilapia	BR015	8.00	4.50	pcs	10	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	c398f01c-eb0c-47cf-a1f9-84244e0d0a4d	\N	\N
95c4475c-5a80-479e-97e3-ac363890fc6a	Beef Steak (300g)	BR016	10.00	6.00	pcs	15	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	c398f01c-eb0c-47cf-a1f9-84244e0d0a4d	\N	\N
959b1473-a7e3-473e-b152-a104adf06abd	Chips/Fries Portion	BR017	4.00	1.50	pcs	40	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	c398f01c-eb0c-47cf-a1f9-84244e0d0a4d	\N	\N
0bf06692-d8c6-4e88-92d0-c1b48e29a362	Chicken Wings (6pcs)	BR018	7.00	4.00	pcs	20	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	c398f01c-eb0c-47cf-a1f9-84244e0d0a4d	\N	\N
7dfb3088-a7f7-401b-84ab-fff8b14d7fba	Mushroom Burger	BR019	6.00	3.50	pcs	20	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	c398f01c-eb0c-47cf-a1f9-84244e0d0a4d	\N	\N
3f4bfd59-0cc9-4fa4-b41e-cf96c809eead	Chicken Samosa (Pc)	BR020	1.00	0.40	pcs	50	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	2e6948c7-6b49-4a6a-b4dd-f24b34188f66	\N	\N
9a54e675-164d-42d9-b8c9-67082a813b1f	Spring Rolls (3pcs)	BR021	3.00	1.50	pcs	30	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	2e6948c7-6b49-4a6a-b4dd-f24b34188f66	\N	\N
5e137dd8-3429-4634-99de-e263b8261299	Peanuts Roasted (100g)	BR022	1.50	0.60	pcs	40	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	2e6948c7-6b49-4a6a-b4dd-f24b34188f66	\N	\N
f126ae8f-2f46-4b62-8977-96bb79245470	Plantain Crisps (100g)	BR023	1.50	0.60	pcs	40	\N	t	2026-07-13 09:57:50	2026-07-13 09:57:50	2e6948c7-6b49-4a6a-b4dd-f24b34188f66	\N	\N
\.


--
-- Data for Name: promotions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.promotions (id, code, type, value, min_order_amount, max_discount_amount, usage_limit, used_count, valid_from, valid_until, is_active, description, created_at, updated_at) FROM stdin;
3f5ad629-f228-466a-92ad-f9f16b41473f	WELCOME10	percentage	10.00	20.00	50.00	1000	0	2026-06-13	2027-07-13	t	10% off your first order (max $50 discount)	2026-07-13 09:57:52	2026-07-13 09:57:52
af7f9658-81dd-407c-add6-27faa6907922	FLAT5	flat	5.00	15.00	\N	500	0	2026-06-13	2027-07-13	t	$5 off orders over $15	2026-07-13 09:57:52	2026-07-13 09:57:52
0c6dc18c-0100-4063-acce-285b8ecbbc06	HAPPYHOUR	percentage	15.00	10.00	30.00	200	0	2026-06-13	2027-07-13	t	Happy hour special — 15% off (max $30)	2026-07-13 09:57:52	2026-07-13 09:57:52
\.


--
-- Data for Name: purchase_order_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.purchase_order_items (id, purchase_order_id, product_id, quantity, unit_cost, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: purchase_orders; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.purchase_orders (id, supplier_id, branch_id, po_number, status, total_amount, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: reconciliation_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.reconciliation_items (id, reconciliation_id, journal_entry_id, amount, type, is_cleared, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: return_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.return_items (id, return_id, product_id, quantity, reason, created_at, updated_at, condition) FROM stdin;
f74a3e0a-6f38-48f1-bd50-a10c88f42afb	16789029-b24a-4328-81e1-ae179ec20b2d	50e153e7-1e4e-4c96-b2f5-5b11366fe324	1.000	Damaged on arrival	2026-07-13 09:57:52	2026-07-13 09:57:52	returnable
baa4ba78-9b89-4e31-9ab3-1f51988f8803	16789029-b24a-4328-81e1-ae179ec20b2d	7e04f86d-708f-41e7-83ba-660e20f87b3b	1.000	Damaged on arrival	2026-07-13 09:57:52	2026-07-13 09:57:52	returnable
\.


--
-- Data for Name: returns; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.returns (id, sale_id, branch_id, reason, status, refund_amount, created_at, updated_at, refund_payment_id, refunded_at) FROM stdin;
16789029-b24a-4328-81e1-ae179ec20b2d	8ee9a596-8681-4d3e-a38a-3e36a5a1426d	dd04d801-68c0-4eb7-86a8-f273e01c06f9	Customer returned damaged items	pending	0.00	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	\N
\.


--
-- Data for Name: role_user; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.role_user (user_id, role_id, branch_id) FROM stdin;
865f94df-5c67-49cc-b8ac-c7e11285b9d4	2f38896f-53a7-4d3e-bbd8-b158891e2e1a	dd04d801-68c0-4eb7-86a8-f273e01c06f9
d614fc8e-52c1-4bce-885e-77d6a687ef93	32229581-9737-4689-8c8a-d9ac54aef1d6	dd04d801-68c0-4eb7-86a8-f273e01c06f9
bcb9dc14-6f37-4f5b-bff4-ffb17eaed1d7	05bac452-6b8b-4488-973f-ec9f3db4ce9c	dd04d801-68c0-4eb7-86a8-f273e01c06f9
57d23c70-656b-4cb3-9f18-226a829d4a6c	257597e4-ff68-40cc-8213-349204925524	dd04d801-68c0-4eb7-86a8-f273e01c06f9
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, name, guard_name, created_at, updated_at, is_editable) FROM stdin;
2f38896f-53a7-4d3e-bbd8-b158891e2e1a	admin	web	2026-07-13 09:57:49	2026-07-13 09:57:49	f
05bac452-6b8b-4488-973f-ec9f3db4ce9c	branch_manager	web	2026-07-13 09:57:50	2026-07-13 09:57:50	f
32229581-9737-4689-8c8a-d9ac54aef1d6	cashier	web	2026-07-13 09:57:50	2026-07-13 09:57:50	f
257597e4-ff68-40cc-8213-349204925524	inventory_clerk	web	2026-07-13 09:57:50	2026-07-13 09:57:50	f
\.


--
-- Data for Name: sale_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sale_items (id, sale_id, product_id, quantity, price, subtotal, created_at, updated_at, tax_rate, tax_amount) FROM stdin;
a6a1c4ab-13e8-4664-8ae6-db433480b934	8ee9a596-8681-4d3e-a38a-3e36a5a1426d	50e153e7-1e4e-4c96-b2f5-5b11366fe324	1.000	10.00	10.00	2026-07-13 09:57:52	2026-07-13 09:57:52	0.00	0.00
a1087abe-691c-438b-97ae-078f833a659e	8ee9a596-8681-4d3e-a38a-3e36a5a1426d	7e04f86d-708f-41e7-83ba-660e20f87b3b	1.000	10.00	10.00	2026-07-13 09:57:52	2026-07-13 09:57:52	0.00	0.00
\.


--
-- Data for Name: sales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sales (id, branch_id, customer_id, invoice_number, total_amount, tax_amount, discount, payment_method, status, sync_status, created_at, updated_at, efris_fdn, efris_qr_code, efris_verification_code, efris_fiscal_status) FROM stdin;
8ee9a596-8681-4d3e-a38a-3e36a5a1426d	dd04d801-68c0-4eb7-86a8-f273e01c06f9	\N	INV-DEMO-R9ZKJINB	20.00	0.00	0.00	cash	completed	pending	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	\N	\N	\N
\.


--
-- Data for Name: stock_movements; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_movements (id, inventory_id, product_id, warehouse_id, quantity_change, running_balance, reference_type, reference_id, reason, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: stock_transfer_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_transfer_items (id, stock_transfer_id, product_id, quantity, created_at, updated_at) FROM stdin;
d5b1f1ca-721f-4142-9cbe-29dc91cf40ff	ff0d9188-4e69-455f-94ee-3f28c308f579	50e153e7-1e4e-4c96-b2f5-5b11366fe324	10.000	2026-07-13 09:57:52	2026-07-13 09:57:52
c55c6a09-ac5b-453f-a9ee-a6db5ac9e7a1	ff0d9188-4e69-455f-94ee-3f28c308f579	7e04f86d-708f-41e7-83ba-660e20f87b3b	10.000	2026-07-13 09:57:52	2026-07-13 09:57:52
c14f9fbc-9f69-48d5-8aca-0d06c3d958be	ff0d9188-4e69-455f-94ee-3f28c308f579	26e027ae-d1a7-48e1-9b55-86d197456438	10.000	2026-07-13 09:57:52	2026-07-13 09:57:52
\.


--
-- Data for Name: stock_transfers; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_transfers (id, from_warehouse_id, to_warehouse_id, status, notes, transferred_at, created_at, updated_at) FROM stdin;
ff0d9188-4e69-455f-94ee-3f28c308f579	12151c8d-e0b4-4be3-baf1-50dd3887f1f8	43be6d7e-f940-4876-8a30-c4c43926f5c1	completed	Initial stock replenishment	2026-07-13 09:57:52	2026-07-13 09:57:52	2026-07-13 09:57:52
\.


--
-- Data for Name: subscriptions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.subscriptions (id, branch_id, plan_type, billing_cycle, status, trial_ends_at, starts_at, ends_at, cancelled_at, created_at, updated_at) FROM stdin;
30742cc8-5013-41db-9112-6e9455afd724	dd04d801-68c0-4eb7-86a8-f273e01c06f9	standard	monthly	trial	2026-08-12 09:57:51	2026-07-13 09:57:51	\N	\N	2026-07-13 09:57:51	2026-07-13 09:57:51
7c367849-534b-47ae-a15c-1331e424c78f	de930bc6-a18b-44ac-919f-d42781db04b7	standard	monthly	trial	2026-08-12 09:57:51	2026-07-13 09:57:51	\N	\N	2026-07-13 09:57:51	2026-07-13 09:57:51
\.


--
-- Data for Name: suppliers; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.suppliers (id, name, contact_person, phone, email, address, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: syncs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.syncs (id, branch_id, table_name, record_id, action, payload, status, conflict_data, created_at, updated_at, local_id, device_id) FROM stdin;
\.


--
-- Data for Name: tax_profiles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.tax_profiles (id, name, rate, type, is_default, is_active, description, created_at, updated_at) FROM stdin;
04c28782-cf90-4388-8f9e-b3f5a4f6d2bd	VAT 16%	16.00	exclusive	t	t	Standard VAT at 16% (exclusive)	2026-07-13 09:57:52	2026-07-13 09:57:52
ed2db143-ea2e-4ca7-a444-6877ac535481	VAT 8%	8.00	exclusive	f	t	Reduced VAT at 8% (exclusive)	2026-07-13 09:57:52	2026-07-13 09:57:52
003d09cb-8763-4a1c-bf18-58780405403a	Sales Tax 5%	5.00	inclusive	f	t	Sales tax included in price	2026-07-13 09:57:52	2026-07-13 09:57:52
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, password, branch_id, is_active, created_at, updated_at, email_verified_at, is_protected, secret_question, secret_answer, avatar_url, is_default_account) FROM stdin;
865f94df-5c67-49cc-b8ac-c7e11285b9d4	Admin User	admin@classicpos.app	$2y$12$xYlSr9RKQ6hrKNNRmBbC6OezxhosxTTXQmKrVf//WL97Em91BxJtq	dd04d801-68c0-4eb7-86a8-f273e01c06f9	t	2026-07-13 09:57:51	2026-07-13 09:57:51	\N	t	\N	\N	\N	f
d614fc8e-52c1-4bce-885e-77d6a687ef93	Cashier User	cashier@classicpos.app	$2y$12$rntMNgLvYs3qZakXWUz6wecT.X/gsdMUlRiJ9GNCqRDMF0sCHlGZ6	dd04d801-68c0-4eb7-86a8-f273e01c06f9	t	2026-07-13 09:57:51	2026-07-13 09:57:51	\N	f	\N	\N	\N	f
bcb9dc14-6f37-4f5b-bff4-ffb17eaed1d7	Branch Manager	branch_manager@classicpos.app	$2y$12$TXsE.RiAFL6BJymSyBYmWeevHNPjbLRv31LYzhEZlBjlZp5HdbWIC	dd04d801-68c0-4eb7-86a8-f273e01c06f9	f	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	t	\N	\N	\N	t
57d23c70-656b-4cb3-9f18-226a829d4a6c	Inventory Clerk	inventory_clerk@classicpos.app	$2y$12$Fu4JJtNAv6D7ALqf.bf8B.HUdwNKqbLEqL3aIPqcW.UqNVjj54v/i	dd04d801-68c0-4eb7-86a8-f273e01c06f9	f	2026-07-13 09:57:52	2026-07-13 09:57:52	\N	t	\N	\N	\N	t
\.


--
-- Data for Name: warehouses; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.warehouses (id, branch_id, name, location, is_active, created_at, updated_at) FROM stdin;
12151c8d-e0b4-4be3-baf1-50dd3887f1f8	dd04d801-68c0-4eb7-86a8-f273e01c06f9	Nairobi Main Warehouse	Nairobi Industrial Area	t	2026-07-13 09:57:50	2026-07-13 09:57:50
43be6d7e-f940-4876-8a30-c4c43926f5c1	de930bc6-a18b-44ac-919f-d42781db04b7	Mombasa Main Warehouse	Mombasa Port Area	t	2026-07-13 09:57:50	2026-07-13 09:57:50
\.


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 79, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, true);


--
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (id);


--
-- Name: bank_reconciliations bank_reconciliations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_reconciliations
    ADD CONSTRAINT bank_reconciliations_pkey PRIMARY KEY (id);


--
-- Name: branch_user branch_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branch_user
    ADD CONSTRAINT branch_user_pkey PRIMARY KEY (user_id, branch_id);


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- Name: business_profiles business_profiles_branch_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.business_profiles
    ADD CONSTRAINT business_profiles_branch_id_unique UNIQUE (branch_id);


--
-- Name: business_profiles business_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.business_profiles
    ADD CONSTRAINT business_profiles_pkey PRIMARY KEY (id);


--
-- Name: cash_register_shifts cash_register_shifts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cash_register_shifts
    ADD CONSTRAINT cash_register_shifts_pkey PRIMARY KEY (id);


--
-- Name: categories categories_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_name_unique UNIQUE (name);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: chart_of_accounts chart_of_accounts_branch_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_branch_id_code_unique UNIQUE (branch_id, code);


--
-- Name: chart_of_accounts chart_of_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_pkey PRIMARY KEY (id);


--
-- Name: customers customers_phone_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_phone_unique UNIQUE (phone);


--
-- Name: customers customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (id);


--
-- Name: devices devices_device_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devices
    ADD CONSTRAINT devices_device_id_unique UNIQUE (device_id);


--
-- Name: devices devices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devices
    ADD CONSTRAINT devices_pkey PRIMARY KEY (id);


--
-- Name: document_items document_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_items
    ADD CONSTRAINT document_items_pkey PRIMARY KEY (id);


--
-- Name: document_payments document_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_payments
    ADD CONSTRAINT document_payments_pkey PRIMARY KEY (id);


--
-- Name: documents documents_document_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_document_number_unique UNIQUE (document_number);


--
-- Name: documents documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_pkey PRIMARY KEY (id);


--
-- Name: efris_configs efris_configs_integration_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.efris_configs
    ADD CONSTRAINT efris_configs_integration_id_unique UNIQUE (integration_id);


--
-- Name: efris_configs efris_configs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.efris_configs
    ADD CONSTRAINT efris_configs_pkey PRIMARY KEY (id);


--
-- Name: efris_fiscal_logs efris_fiscal_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.efris_fiscal_logs
    ADD CONSTRAINT efris_fiscal_logs_pkey PRIMARY KEY (id);


--
-- Name: expenses expenses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: grn_items grn_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grn_items
    ADD CONSTRAINT grn_items_pkey PRIMARY KEY (id);


--
-- Name: grn grn_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grn
    ADD CONSTRAINT grn_pkey PRIMARY KEY (id);


--
-- Name: hold_sales hold_sales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hold_sales
    ADD CONSTRAINT hold_sales_pkey PRIMARY KEY (id);


--
-- Name: integrations integrations_branch_id_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integrations
    ADD CONSTRAINT integrations_branch_id_type_unique UNIQUE (branch_id, type);


--
-- Name: integrations integrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integrations
    ADD CONSTRAINT integrations_pkey PRIMARY KEY (id);


--
-- Name: inventory_adjustments inventory_adjustments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustments
    ADD CONSTRAINT inventory_adjustments_pkey PRIMARY KEY (id);


--
-- Name: inventory inventory_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory
    ADD CONSTRAINT inventory_pkey PRIMARY KEY (id);


--
-- Name: inventory inventory_product_id_warehouse_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory
    ADD CONSTRAINT inventory_product_id_warehouse_id_unique UNIQUE (product_id, warehouse_id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: journal_entries journal_entries_branch_id_entry_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_branch_id_entry_number_unique UNIQUE (branch_id, entry_number);


--
-- Name: journal_entries journal_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_pkey PRIMARY KEY (id);


--
-- Name: journal_entry_lines journal_entry_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_entry_lines
    ADD CONSTRAINT journal_entry_lines_pkey PRIMARY KEY (id);


--
-- Name: loyalty_rules loyalty_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.loyalty_rules
    ADD CONSTRAINT loyalty_rules_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: operating_accounts operating_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operating_accounts
    ADD CONSTRAINT operating_accounts_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: permission_role permission_role_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permission_role
    ADD CONSTRAINT permission_role_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: permissions permissions_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_unique UNIQUE (name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: promotions promotions_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotions
    ADD CONSTRAINT promotions_code_unique UNIQUE (code);


--
-- Name: promotions promotions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.promotions
    ADD CONSTRAINT promotions_pkey PRIMARY KEY (id);


--
-- Name: purchase_order_items purchase_order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_pkey PRIMARY KEY (id);


--
-- Name: purchase_orders purchase_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_pkey PRIMARY KEY (id);


--
-- Name: purchase_orders purchase_orders_po_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_po_number_unique UNIQUE (po_number);


--
-- Name: reconciliation_items reconciliation_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reconciliation_items
    ADD CONSTRAINT reconciliation_items_pkey PRIMARY KEY (id);


--
-- Name: return_items return_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.return_items
    ADD CONSTRAINT return_items_pkey PRIMARY KEY (id);


--
-- Name: returns returns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.returns
    ADD CONSTRAINT returns_pkey PRIMARY KEY (id);


--
-- Name: role_user role_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_user
    ADD CONSTRAINT role_user_pkey PRIMARY KEY (user_id, role_id, branch_id);


--
-- Name: roles roles_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_unique UNIQUE (name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sale_items sale_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sale_items
    ADD CONSTRAINT sale_items_pkey PRIMARY KEY (id);


--
-- Name: sales sales_invoice_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_invoice_number_unique UNIQUE (invoice_number);


--
-- Name: sales sales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_pkey PRIMARY KEY (id);


--
-- Name: stock_movements stock_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_pkey PRIMARY KEY (id);


--
-- Name: stock_transfer_items stock_transfer_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_transfer_items
    ADD CONSTRAINT stock_transfer_items_pkey PRIMARY KEY (id);


--
-- Name: stock_transfers stock_transfers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_transfers
    ADD CONSTRAINT stock_transfers_pkey PRIMARY KEY (id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: suppliers suppliers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_pkey PRIMARY KEY (id);


--
-- Name: syncs syncs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.syncs
    ADD CONSTRAINT syncs_pkey PRIMARY KEY (id);


--
-- Name: tax_profiles tax_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_profiles
    ADD CONSTRAINT tax_profiles_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: warehouses warehouses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouses
    ADD CONSTRAINT warehouses_pkey PRIMARY KEY (id);


--
-- Name: activity_logs_auditable_type_auditable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_auditable_type_auditable_id_index ON public.activity_logs USING btree (auditable_type, auditable_id);


--
-- Name: activity_logs_branch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_branch_id_index ON public.activity_logs USING btree (branch_id);


--
-- Name: activity_logs_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_created_at_index ON public.activity_logs USING btree (created_at);


--
-- Name: activity_logs_event_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_event_index ON public.activity_logs USING btree (event);


--
-- Name: activity_logs_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_user_id_index ON public.activity_logs USING btree (user_id);


--
-- Name: bank_reconciliations_branch_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bank_reconciliations_branch_id_status_index ON public.bank_reconciliations USING btree (branch_id, status);


--
-- Name: cash_register_shifts_branch_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cash_register_shifts_branch_id_status_index ON public.cash_register_shifts USING btree (branch_id, status);


--
-- Name: chart_of_accounts_branch_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chart_of_accounts_branch_id_is_active_index ON public.chart_of_accounts USING btree (branch_id, is_active);


--
-- Name: chart_of_accounts_branch_id_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chart_of_accounts_branch_id_type_index ON public.chart_of_accounts USING btree (branch_id, type);


--
-- Name: customers_branch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customers_branch_id_index ON public.customers USING btree (branch_id);


--
-- Name: devices_branch_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX devices_branch_id_status_index ON public.devices USING btree (branch_id, status);


--
-- Name: devices_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX devices_status_index ON public.devices USING btree (status);


--
-- Name: documents_branch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_branch_id_index ON public.documents USING btree (branch_id);


--
-- Name: documents_document_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_document_type_status_index ON public.documents USING btree (document_type, status);


--
-- Name: efris_configs_branch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX efris_configs_branch_id_index ON public.efris_configs USING btree (branch_id);


--
-- Name: efris_fiscal_logs_branch_id_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX efris_fiscal_logs_branch_id_status_created_at_index ON public.efris_fiscal_logs USING btree (branch_id, status, created_at);


--
-- Name: efris_fiscal_logs_sale_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX efris_fiscal_logs_sale_id_index ON public.efris_fiscal_logs USING btree (sale_id);


--
-- Name: expenses_branch_id_expense_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expenses_branch_id_expense_date_index ON public.expenses USING btree (branch_id, expense_date);


--
-- Name: expenses_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expenses_category_index ON public.expenses USING btree (category);


--
-- Name: grn_items_grn_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX grn_items_grn_id_idx ON public.grn_items USING btree (grn_id);


--
-- Name: grn_items_product_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX grn_items_product_id_idx ON public.grn_items USING btree (product_id);


--
-- Name: grn_purchase_order_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX grn_purchase_order_id_idx ON public.grn USING btree (purchase_order_id);


--
-- Name: grn_received_by_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX grn_received_by_idx ON public.grn USING btree (received_by);


--
-- Name: hold_sales_branch_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hold_sales_branch_id_index ON public.hold_sales USING btree (branch_id);


--
-- Name: hold_sales_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hold_sales_created_at_index ON public.hold_sales USING btree (created_at);


--
-- Name: hold_sales_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hold_sales_user_id_index ON public.hold_sales USING btree (user_id);


--
-- Name: integrations_branch_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX integrations_branch_id_status_index ON public.integrations USING btree (branch_id, status);


--
-- Name: inventory_adjustments_branch_id_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventory_adjustments_branch_id_type_index ON public.inventory_adjustments USING btree (branch_id, type);


--
-- Name: journal_entries_branch_id_entry_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX journal_entries_branch_id_entry_date_index ON public.journal_entries USING btree (branch_id, entry_date);


--
-- Name: journal_entries_reference_type_reference_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX journal_entries_reference_type_reference_id_index ON public.journal_entries USING btree (reference_type, reference_id);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: operating_accounts_branch_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX operating_accounts_branch_id_is_active_index ON public.operating_accounts USING btree (branch_id, is_active);


--
-- Name: payments_sale_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_sale_id_idx ON public.payments USING btree (sale_id);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: purchase_order_items_product_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_order_items_product_id_idx ON public.purchase_order_items USING btree (product_id);


--
-- Name: purchase_order_items_purchase_order_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_order_items_purchase_order_id_idx ON public.purchase_order_items USING btree (purchase_order_id);


--
-- Name: return_items_product_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX return_items_product_id_idx ON public.return_items USING btree (product_id);


--
-- Name: return_items_return_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX return_items_return_id_idx ON public.return_items USING btree (return_id);


--
-- Name: sale_items_product_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sale_items_product_id_idx ON public.sale_items USING btree (product_id);


--
-- Name: sale_items_sale_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sale_items_sale_id_idx ON public.sale_items USING btree (sale_id);


--
-- Name: sales_efris_fiscal_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_efris_fiscal_status_index ON public.sales USING btree (efris_fiscal_status);


--
-- Name: stock_movements_inventory_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_movements_inventory_id_created_at_index ON public.stock_movements USING btree (inventory_id, created_at);


--
-- Name: stock_movements_product_id_warehouse_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_movements_product_id_warehouse_id_index ON public.stock_movements USING btree (product_id, warehouse_id);


--
-- Name: stock_movements_reference_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_movements_reference_type_index ON public.stock_movements USING btree (reference_type);


--
-- Name: stock_transfer_items_product_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_transfer_items_product_id_idx ON public.stock_transfer_items USING btree (product_id);


--
-- Name: stock_transfer_items_stock_transfer_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_transfer_items_stock_transfer_id_idx ON public.stock_transfer_items USING btree (stock_transfer_id);


--
-- Name: subscriptions_branch_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_branch_id_status_index ON public.subscriptions USING btree (branch_id, status);


--
-- Name: subscriptions_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_status_index ON public.subscriptions USING btree (status);


--
-- Name: syncs_local_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX syncs_local_id_index ON public.syncs USING btree (local_id);


--
-- Name: bank_reconciliations bank_reconciliations_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_reconciliations
    ADD CONSTRAINT bank_reconciliations_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: bank_reconciliations bank_reconciliations_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_reconciliations
    ADD CONSTRAINT bank_reconciliations_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: bank_reconciliations bank_reconciliations_operating_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bank_reconciliations
    ADD CONSTRAINT bank_reconciliations_operating_account_id_foreign FOREIGN KEY (operating_account_id) REFERENCES public.operating_accounts(id);


--
-- Name: branch_user branch_user_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branch_user
    ADD CONSTRAINT branch_user_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: branch_user branch_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branch_user
    ADD CONSTRAINT branch_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: business_profiles business_profiles_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.business_profiles
    ADD CONSTRAINT business_profiles_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: cash_register_shifts cash_register_shifts_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cash_register_shifts
    ADD CONSTRAINT cash_register_shifts_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: cash_register_shifts cash_register_shifts_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cash_register_shifts
    ADD CONSTRAINT cash_register_shifts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: categories categories_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.categories(id) ON DELETE SET NULL;


--
-- Name: chart_of_accounts chart_of_accounts_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: customers customers_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: devices devices_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.devices
    ADD CONSTRAINT devices_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: document_items document_items_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_items
    ADD CONSTRAINT document_items_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: document_items document_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_items
    ADD CONSTRAINT document_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE SET NULL;


--
-- Name: document_payments document_payments_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_payments
    ADD CONSTRAINT document_payments_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: documents documents_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: documents documents_converted_from_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_converted_from_id_foreign FOREIGN KEY (converted_from_id) REFERENCES public.documents(id) ON DELETE SET NULL;


--
-- Name: documents documents_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: documents documents_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: efris_configs efris_configs_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.efris_configs
    ADD CONSTRAINT efris_configs_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: efris_configs efris_configs_integration_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.efris_configs
    ADD CONSTRAINT efris_configs_integration_id_foreign FOREIGN KEY (integration_id) REFERENCES public.integrations(id) ON DELETE CASCADE;


--
-- Name: efris_fiscal_logs efris_fiscal_logs_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.efris_fiscal_logs
    ADD CONSTRAINT efris_fiscal_logs_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: efris_fiscal_logs efris_fiscal_logs_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.efris_fiscal_logs
    ADD CONSTRAINT efris_fiscal_logs_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE SET NULL;


--
-- Name: expenses expenses_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: expenses expenses_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE SET NULL;


--
-- Name: grn_items grn_items_grn_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grn_items
    ADD CONSTRAINT grn_items_grn_id_foreign FOREIGN KEY (grn_id) REFERENCES public.grn(id) ON DELETE CASCADE;


--
-- Name: grn_items grn_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grn_items
    ADD CONSTRAINT grn_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: grn grn_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grn
    ADD CONSTRAINT grn_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE CASCADE;


--
-- Name: grn grn_received_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grn
    ADD CONSTRAINT grn_received_by_foreign FOREIGN KEY (received_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: hold_sales hold_sales_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hold_sales
    ADD CONSTRAINT hold_sales_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: hold_sales hold_sales_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hold_sales
    ADD CONSTRAINT hold_sales_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: integrations integrations_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.integrations
    ADD CONSTRAINT integrations_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: inventory_adjustments inventory_adjustments_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustments
    ADD CONSTRAINT inventory_adjustments_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: inventory_adjustments inventory_adjustments_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustments
    ADD CONSTRAINT inventory_adjustments_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: inventory_adjustments inventory_adjustments_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_adjustments
    ADD CONSTRAINT inventory_adjustments_warehouse_id_foreign FOREIGN KEY (warehouse_id) REFERENCES public.warehouses(id);


--
-- Name: inventory inventory_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory
    ADD CONSTRAINT inventory_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: inventory inventory_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory
    ADD CONSTRAINT inventory_warehouse_id_foreign FOREIGN KEY (warehouse_id) REFERENCES public.warehouses(id) ON DELETE CASCADE;


--
-- Name: journal_entries journal_entries_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: journal_entries journal_entries_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: journal_entry_lines journal_entry_lines_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_entry_lines
    ADD CONSTRAINT journal_entry_lines_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: journal_entry_lines journal_entry_lines_journal_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.journal_entry_lines
    ADD CONSTRAINT journal_entry_lines_journal_entry_id_foreign FOREIGN KEY (journal_entry_id) REFERENCES public.journal_entries(id) ON DELETE CASCADE;


--
-- Name: operating_accounts operating_accounts_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operating_accounts
    ADD CONSTRAINT operating_accounts_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: operating_accounts operating_accounts_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operating_accounts
    ADD CONSTRAINT operating_accounts_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: payments payments_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE CASCADE;


--
-- Name: permission_role permission_role_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permission_role
    ADD CONSTRAINT permission_role_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: permission_role permission_role_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permission_role
    ADD CONSTRAINT permission_role_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: products products_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE SET NULL;


--
-- Name: purchase_order_items purchase_order_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE RESTRICT;


--
-- Name: purchase_order_items purchase_order_items_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE CASCADE;


--
-- Name: purchase_orders purchase_orders_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: purchase_orders purchase_orders_supplier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES public.suppliers(id) ON DELETE CASCADE;


--
-- Name: reconciliation_items reconciliation_items_journal_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reconciliation_items
    ADD CONSTRAINT reconciliation_items_journal_entry_id_foreign FOREIGN KEY (journal_entry_id) REFERENCES public.journal_entries(id);


--
-- Name: reconciliation_items reconciliation_items_reconciliation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reconciliation_items
    ADD CONSTRAINT reconciliation_items_reconciliation_id_foreign FOREIGN KEY (reconciliation_id) REFERENCES public.bank_reconciliations(id) ON DELETE CASCADE;


--
-- Name: return_items return_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.return_items
    ADD CONSTRAINT return_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE RESTRICT;


--
-- Name: return_items return_items_return_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.return_items
    ADD CONSTRAINT return_items_return_id_foreign FOREIGN KEY (return_id) REFERENCES public.returns(id) ON DELETE CASCADE;


--
-- Name: returns returns_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.returns
    ADD CONSTRAINT returns_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: returns returns_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.returns
    ADD CONSTRAINT returns_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE CASCADE;


--
-- Name: role_user role_user_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_user
    ADD CONSTRAINT role_user_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: role_user role_user_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_user
    ADD CONSTRAINT role_user_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: role_user role_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_user
    ADD CONSTRAINT role_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: sale_items sale_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sale_items
    ADD CONSTRAINT sale_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: sale_items sale_items_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sale_items
    ADD CONSTRAINT sale_items_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE CASCADE;


--
-- Name: sales sales_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: sales sales_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: stock_movements stock_movements_inventory_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_inventory_id_foreign FOREIGN KEY (inventory_id) REFERENCES public.inventory(id) ON DELETE CASCADE;


--
-- Name: stock_movements stock_movements_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: stock_movements stock_movements_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_warehouse_id_foreign FOREIGN KEY (warehouse_id) REFERENCES public.warehouses(id);


--
-- Name: stock_transfer_items stock_transfer_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_transfer_items
    ADD CONSTRAINT stock_transfer_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE RESTRICT;


--
-- Name: stock_transfer_items stock_transfer_items_stock_transfer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_transfer_items
    ADD CONSTRAINT stock_transfer_items_stock_transfer_id_foreign FOREIGN KEY (stock_transfer_id) REFERENCES public.stock_transfers(id) ON DELETE CASCADE;


--
-- Name: stock_transfers stock_transfers_from_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_transfers
    ADD CONSTRAINT stock_transfers_from_warehouse_id_foreign FOREIGN KEY (from_warehouse_id) REFERENCES public.warehouses(id) ON DELETE RESTRICT;


--
-- Name: stock_transfers stock_transfers_to_warehouse_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_transfers
    ADD CONSTRAINT stock_transfers_to_warehouse_id_foreign FOREIGN KEY (to_warehouse_id) REFERENCES public.warehouses(id) ON DELETE RESTRICT;


--
-- Name: subscriptions subscriptions_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: syncs syncs_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.syncs
    ADD CONSTRAINT syncs_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: users users_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: warehouses warehouses_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.warehouses
    ADD CONSTRAINT warehouses_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict KB0erUvLw9EAqPJrMNf2RT1wcKlgMfk8DxpaIaGuNOkCxOPQfJU7588wQTep5UD

