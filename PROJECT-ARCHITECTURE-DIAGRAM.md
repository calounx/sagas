# Saga Manager - Project Architecture Diagram
**Date:** 2026-01-03
**Version:** Phase 2 Complete (Weeks 1-9)

---

## 1. System Overview - High Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Saga Manager WordPress Theme                      │
│                     (Multi-tenant Fictional Universe Manager)             │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                ┌───────────────────┼───────────────────┐
                │                   │                   │
                ▼                   ▼                   ▼
        ┌───────────────┐   ┌─────────────┐   ┌──────────────┐
        │   Frontend    │   │   Backend   │   │   Database   │
        │   (Browser)   │   │   (PHP)     │   │   (MariaDB)  │
        └───────────────┘   └─────────────┘   └──────────────┘
                │                   │                   │
                └───────────────────┴───────────────────┘
```

---

## 2. Detailed Architecture - Hexagonal Design

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              PRESENTATION LAYER                           │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐  ┌─────────────┐ │
│  │   Admin UI  │  │  REST API    │  │  Shortcodes   │  │  AJAX      │ │
│  │  (WordPress)│  │  (WP REST)   │  │  (Frontend)   │  │  Handlers  │ │
│  └──────┬──────┘  └──────┬───────┘  └───────┬───────┘  └──────┬──────┘ │
└─────────┼────────────────┼──────────────────┼─────────────────┼────────┘
          │                │                  │                 │
┌─────────┼────────────────┼──────────────────┼─────────────────┼────────┐
│         │          APPLICATION SERVICE LAYER (Use Cases)                 │
│         │                │                  │                 │          │
│  ┌──────▼────────────────▼──────────────────▼─────────────────▼──────┐  │
│  │                    WordPress Hooks & Filters                       │  │
│  │   - save_post  - rest_api_init  - wp_ajax_*  - init               │  │
│  └─────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│  ┌─────────────────────────────────────────────────────────────────────┐ │
│  │                      AI Services (Phase 2)                          │ │
│  │  ┌──────────────┐ ┌────────────────┐ ┌────────────────────────┐   │ │
│  │  │ Consistency  │ │ Entity         │ │ Predictive             │   │ │
│  │  │ Guardian     │ │ Extractor      │ │ Relationships          │   │ │
│  │  └──────┬───────┘ └────────┬───────┘ └───────────┬────────────┘   │ │
│  │         │                  │                      │                │ │
│  │  ┌──────▼──────────────────▼──────────────────────▼────────────┐  │ │
│  │  │           Summary Generator (Bilingual Support)             │  │ │
│  │  └─────────────────────────────────────────────────────────────┘  │ │
│  └─────────────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────┘
                                   │
┌────────────────────────────────────────────────────────────────────────┐
│                           DOMAIN LAYER (Core)                            │
│                                                                            │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                        Value Objects                              │   │
│  │  • EntityId  • SagaId  • ImportanceScore                         │   │
│  │  • ConsistencyIssue  • RelationshipSuggestion                    │   │
│  │  • ExtractionJob  • ExtractedEntity                              │   │
│  │  • SummaryRequest  • GeneratedSummary                            │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                            │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                      Repository Interfaces (Ports)                │   │
│  │  • EntityRepositoryInterface                                      │   │
│  │  • ConsistencyRepositoryInterface                                 │   │
│  │  • SuggestionRepositoryInterface                                  │   │
│  │  • ExtractionRepositoryInterface                                  │   │
│  │  • SummaryRepositoryInterface                                     │   │
│  └──────────────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────────┘
                                   │
┌────────────────────────────────────────────────────────────────────────┐
│                      INFRASTRUCTURE LAYER (Adapters)                     │
│                                                                            │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                    Repository Implementations                      │   │
│  │                                                                    │   │
│  │  ┌──────────────────┐        ┌─────────────────────────────┐     │   │
│  │  │ MariaDB          │        │ WordPress Integration       │     │   │
│  │  │ Repositories     │◄───────┤ • $wpdb global              │     │   │
│  │  │                  │        │ • wp_cache (Redis)          │     │   │
│  │  │ • EntityRepo     │        │ • wp_posts sync             │     │   │
│  │  │ • SummaryRepo    │        │ • Table prefix handling     │     │   │
│  │  │ • SuggestionRepo │        └─────────────────────────────┘     │   │
│  │  │ • ExtractionRepo │                                             │   │
│  │  │ • ConsistencyRepo│                                             │   │
│  │  └──────────────────┘                                             │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                            │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                      External Services                             │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌────────────────────┐     │   │
│  │  │   OpenAI     │  │   Anthropic  │  │   Embedding        │     │   │
│  │  │   GPT-4      │  │   Claude     │  │   Service          │     │   │
│  │  │   API        │  │   API        │  │   (sentence-trans) │     │   │
│  │  └──────────────┘  └──────────────┘  └────────────────────┘     │   │
│  └──────────────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────────┘
                                   │
┌────────────────────────────────────────────────────────────────────────┐
│                          PERSISTENCE LAYER                               │
│                                                                            │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                    MariaDB 11.4+ Database                         │   │
│  │                                                                    │   │
│  │  Core Tables:                  AI Tables (Phase 2):               │   │
│  │  • wp_saga_sagas               • wp_saga_consistency_issues       │   │
│  │  • wp_saga_entities            • wp_saga_extraction_jobs          │   │
│  │  • wp_saga_entity_relationships• wp_saga_extracted_entities       │   │
│  │  • wp_saga_timeline_events     • wp_saga_relationship_suggestions │   │
│  │  • wp_saga_attribute_definitions• wp_saga_suggestion_features     │   │
│  │  • wp_saga_attribute_values    • wp_saga_suggestion_feedback      │   │
│  │  • wp_saga_content_fragments   • wp_saga_learning_weights         │   │
│  │  • wp_saga_quality_metrics     • wp_saga_summary_requests         │   │
│  │                                • wp_saga_generated_summaries      │   │
│  │                                                                    │   │
│  │  WordPress Core Tables:                                           │   │
│  │  • wp_posts (synced with saga_entities)                           │   │
│  │  • wp_users (referenced by foreign keys)                          │   │
│  │  • wp_postmeta                                                     │   │
│  │  • wp_options (settings, transients)                              │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                            │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                      Cache Layer (Redis)                          │   │
│  │  • Entity cache (5 min TTL)                                       │   │
│  │  • Query results cache                                            │   │
│  │  • Embedding cache                                                │   │
│  │  • Session data                                                   │   │
│  └──────────────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Data Flow Diagram - Request Lifecycle

### 3.1 Entity Creation Flow

```
User (Browser)
    │
    │ 1. Submit Form
    ▼
WordPress Admin UI (Presentation)
    │
    │ 2. save_post hook fires
    ▼
Entity Creation Service (Application)
    │
    │ 3. Validate input
    ▼
Domain Validation (Value Objects)
    │
    │ 4. Create SagaEntity
    ▼
Repository (Infrastructure)
    │
    │ 5. START TRANSACTION
    │ 6. INSERT into wp_saga_entities
    │ 7. INSERT into wp_saga_attribute_values
    │ 8. COMMIT
    ▼
Database (MariaDB)
    │
    │ 9. Sync to wp_posts
    ◄─┘
    │ 10. Invalidate cache
    ▼
Cache (Redis)
    │
    │ 11. Return entity ID
    ▼
Response to User
```

### 3.2 AI Summary Generation Flow

```
User clicks "Generate Summary"
    │
    │ 1. AJAX request
    ▼
AJAX Handler (inc/ajax/summaries-ajax.php)
    │
    │ 2. Verify nonce + capability check
    ▼
Summary Generator Service
    │
    │ 3. Create SummaryRequest (value object)
    ▼
Summary Repository
    │
    │ 4. INSERT wp_saga_summary_requests
    │    (status: pending)
    ▼
Database
    │
    │ 5. Trigger background job (WP-Cron)
    ▼
Summary Processing Job
    │
    │ 6. Collect entity data
    ├──► Data Collection Service
    │       │
    │       │ 7. Query relationships, attributes, events
    │       ▼
    │    Database (MariaDB)
    │       │
    │       │ 8. Return structured data
    │       ◄─┘
    │
    │ 9. Call AI Provider (OpenAI/Anthropic)
    ▼
External AI API
    │
    │ 10. Generate summary (English + French)
    │     Token usage tracked
    ▼
Summary Response
    │
    │ 11. Validate + sanitize
    ▼
Generated Summary (value object)
    │
    │ 12. INSERT wp_saga_generated_summaries
    │ 13. UPDATE wp_saga_summary_requests
    │     (status: completed)
    ▼
Database
    │
    │ 14. Cache result (5 min TTL)
    ▼
Cache (Redis)
    │
    │ 15. Notify user (transient)
    ▼
User Dashboard
```

### 3.3 Predictive Relationships Flow

```
Background Job (Daily Cron)
    │
    │ 1. Find entity pairs without suggestions
    ▼
Suggestion Repository
    │
    │ 2. SELECT entities WHERE no suggestions
    ▼
Database
    │
    │ 3. Return entity pairs (batch of 100)
    ▼
Feature Extraction Service
    │
    │ 4. Extract features for each pair:
    ├──► Name Similarity (Levenshtein distance)
    ├──► Type Match (same entity type?)
    ├──► Temporal Proximity (timeline events)
    ├──► Attribute Similarity (shared attributes)
    ├──► Content Co-occurrence (same text fragments)
    └──► Existing Relationship Count
    │
    │ 5. Calculate confidence scores
    ▼
Learning Weights Repository
    │
    │ 6. Retrieve ML weights
    ▼
Database
    │
    │ 7. weighted_score = Σ(feature × weight)
    ▼
Relationship Suggestion (value object)
    │
    │ 8. If score > threshold (0.7):
    │    INSERT wp_saga_relationship_suggestions
    ▼
Database
    │
    │ 9. Store features for learning
    │    INSERT wp_saga_suggestion_features
    ▼
Database
    │
    │ 10. User reviews suggestion (accept/reject)
    ▼
Feedback Service
    │
    │ 11. INSERT wp_saga_suggestion_feedback
    │ 12. UPDATE learning weights (backpropagation)
    ▼
Database
    │
    │ 13. If accepted: CREATE relationship
    │     INSERT wp_saga_entity_relationships
    ▼
Database
```

---

## 4. Component Dependencies Map

### 4.1 Dependency Graph (Upstream → Downstream)

```
┌─────────────────────────────────────────────────────────────────┐
│                    EXTERNAL DEPENDENCIES                         │
│                                                                   │
│  Upstream (Required by Saga Manager):                           │
│  • WordPress 6.0+ Core                                           │
│  • MariaDB 11.4+ with JSON functions                            │
│  • PHP 8.2+ with extensions (pdo_mysql, mysqli)                 │
│  • Composer (dependency management)                              │
│  • Redis 7+ (optional, for caching)                             │
│                                                                   │
│  External Services (Optional):                                   │
│  • OpenAI API (GPT-4) for summaries                             │
│  • Anthropic API (Claude) for summaries                         │
│  • Sentence Transformers API (embeddings)                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PHP COMPOSER DEPENDENCIES                     │
│                                                                   │
│  Production (composer.json):                                     │
│  • No production dependencies (WordPress-native)                 │
│                                                                   │
│  Development (composer.json require-dev):                        │
│  • phpunit/phpunit: ^10.5                                        │
│  • yoast/phpunit-polyfills: ^2.0                                 │
│  • mockery/mockery: ^1.6                                         │
│  • phpstan/phpstan: ^1.10                                        │
│  • squizlabs/php_codesniffer: ^3.7                               │
│  • wp-coding-standards/wpcs: ^3.0                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    INTERNAL COMPONENTS                           │
│                                                                   │
│  Core Theme Files:                                               │
│  ┌────────────────┐           ┌──────────────────────┐          │
│  │ functions.php  │──requires─►│ inc/autoload.php     │          │
│  └────────────────┘           └──────────────────────┘          │
│         │                              │                         │
│         │                              ▼                         │
│         │                     ┌─────────────────────┐           │
│         │                     │  inc/class-*.php    │           │
│         │                     │  (Core Classes)     │           │
│         │                     └─────────────────────┘           │
│         │                                                        │
│         ├──► inc/ai/                                            │
│         │    └─► Phase 2 AI Services                            │
│         │                                                        │
│         ├──► inc/ajax/                                          │
│         │    └─► AJAX Handlers                                  │
│         │                                                        │
│         ├──► inc/admin/                                         │
│         │    └─► Admin UI Components                            │
│         │                                                        │
│         ├──► inc/shortcodes/                                    │
│         │    └─► Frontend Shortcodes                            │
│         │                                                        │
│         └──► assets/                                            │
│              └─► JS/CSS/Images                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Module Dependency Matrix

| Module | Depends On | Used By |
|--------|------------|---------|
| **Domain (Value Objects)** | None | All other layers |
| **Repository Interfaces** | Domain | Repositories, Services |
| **MariaDB Repositories** | Domain, WordPress $wpdb | Application Services |
| **AI Services** | Repositories, Domain | AJAX Handlers, Background Jobs |
| **AJAX Handlers** | AI Services, Repositories | WordPress AJAX system |
| **Admin UI** | AI Services | WordPress admin_menu |
| **Shortcodes** | Repositories | WordPress shortcode API |
| **Background Jobs** | AI Services, Repositories | WP-Cron |

---

## 5. Database Schema - Entity Relationship Diagram

```
┌──────────────────────┐
│   wp_saga_sagas      │ ◄────────────┐
│  (Multi-tenant)      │              │
│                      │              │
│  • id (PK)           │              │
│  • name (UNIQUE)     │              │
│  • universe          │              │ 1:N
│  • calendar_type     │              │
│  • calendar_config   │              │
└──────────────────────┘              │
           │                          │
           │ 1:N                      │
           ▼                          │
┌──────────────────────┐         ┌───┴──────────────────┐
│  wp_saga_entities    │         │ wp_saga_timeline_    │
│  (Core Entity Data)  │         │ events               │
│                      │         │                      │
│  • id (PK)           │         │  • id (PK)           │
│  • saga_id (FK) ─────┼────────►│  • saga_id (FK)      │
│  • entity_type       │         │  • event_entity_id   │
│  • canonical_name    │         │  • canon_date        │
│  • slug              │         │  • normalized_ts     │
│  • importance_score  │         │  • participants JSON │
│  • wp_post_id (FK)   │         └──────────────────────┘
└───────┬──────────────┘
        │ 1:N                   ┌──────────────────────┐
        ├──────────────────────►│ wp_saga_attribute_   │
        │                       │ values (EAV)         │
        │                       │                      │
        │                       │  • entity_id (FK,PK) │
        │                       │  • attribute_id(FK,PK│
        │                       │  • value_string      │
        │                       │  • value_int         │
        │                       │  • value_float       │
        │                       └──────────────────────┘
        │                               ▲
        │                               │ N:1
        │                       ┌───────┴──────────────┐
        │                       │ wp_saga_attribute_   │
        │                       │ definitions          │
        │                       │                      │
        │                       │  • id (PK)           │
        │                       │  • entity_type       │
        │                       │  • attribute_key     │
        │                       │  • display_name      │
        │                       │  • data_type         │
        │                       └──────────────────────┘
        │
        │ N:N (Self-referencing)
        ├──────────────────────┐
        ▼                      ▼
┌───────────────────────────────────────┐
│ wp_saga_entity_relationships          │
│ (Typed, Weighted, Temporal)           │
│                                       │
│  • id (PK)                            │
│  • source_entity_id (FK) ─────────────┼─► wp_saga_entities
│  • target_entity_id (FK) ─────────────┼─► wp_saga_entities
│  • relationship_type                  │
│  • strength (0-100)                   │
│  • valid_from (DATE)                  │
│  • valid_until (DATE)                 │
│  • metadata JSON                      │
└───────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│              PHASE 2: AI ENHANCEMENT TABLES                  │
└─────────────────────────────────────────────────────────────┘

┌──────────────────────┐      ┌──────────────────────┐
│ wp_saga_consistency_ │      │ wp_saga_extraction_  │
│ issues               │      │ jobs                 │
│                      │      │                      │
│  • id (PK)           │      │  • id (PK)           │
│  • saga_id (FK)      │      │  • saga_id (FK)      │
│  • entity_id (FK)    │      │  • user_id (FK)      │
│  • issue_type        │      │  • source_text       │
│  • severity          │      │  • ai_provider       │
│  • status            │      │  • status            │
│  • confidence        │      │  • total_entities    │
│  • detected_at       │      │  • processed_count   │
└──────────────────────┘      └──────────────────────┘
                                      │ 1:N
                                      ▼
                              ┌──────────────────────┐
                              │ wp_saga_extracted_   │
                              │ entities             │
                              │                      │
                              │  • id (PK)           │
                              │  • job_id (FK)       │
                              │  • entity_name       │
                              │  • entity_type       │
                              │  • confidence        │
                              │  • created_entity_id │
                              └──────────────────────┘

┌──────────────────────┐      ┌──────────────────────┐
│ wp_saga_relationship_│      │ wp_saga_summary_     │
│ suggestions          │      │ requests             │
│ (ML Predictions)     │      │                      │
│                      │      │  • id (PK)           │
│  • id (PK)           │      │  • saga_id (FK)      │
│  • saga_id (FK)      │      │  • user_id (FK)      │
│  • source_id (FK)    │      │  • summary_type ENUM │
│  • target_id (FK)    │      │  • entity_id (FK)    │
│  • rel_type          │      │  • status ENUM       │
│  • confidence        │      │  • ai_provider       │
│  • status ENUM       │      │  • ai_model          │
└──────┬───────────────┘      │  • priority          │
       │ 1:N                  └──────┬───────────────┘
       ▼                             │ 1:1
┌──────────────────────┐             ▼
│ wp_saga_suggestion_  │      ┌──────────────────────┐
│ features             │      │ wp_saga_generated_   │
│ (ML Feature Storage) │      │ summaries            │
│                      │      │                      │
│  • suggestion_id(FK,PK)     │  • id (PK)           │
│  • feature_name      │      │  • request_id (FK)   │
│  • feature_value     │      │  • content_en        │
│  • feature_weight    │      │  • content_fr        │
└──────────────────────┘      │  • output_format     │
                              │  • quality_score     │
┌──────────────────────┐      │  • token_usage       │
│ wp_saga_suggestion_  │      └──────────────────────┘
│ feedback             │
│ (User Actions)       │
│                      │
│  • id (PK)           │
│  • suggestion_id(FK) │
│  • user_id (FK)      │
│  • action ENUM       │
│  • actioned_at       │
└──────────────────────┘

┌──────────────────────┐
│ wp_saga_learning_    │
│ weights              │
│ (ML Model Params)    │
│                      │
│  • id (PK)           │
│  • saga_id (FK)      │
│  • feature_name      │
│  • weight            │
│  • sample_size       │
│  • updated_at        │
└──────────────────────┘

┌──────────────────────────────────────────────────────┐
│       WORDPRESS CORE TABLE INTEGRATION                │
└──────────────────────────────────────────────────────┘

┌──────────────────────┐      ┌──────────────────────┐
│    wp_posts          │      │    wp_users          │
│ (Synced with entities│      │ (Referenced by FK)   │
│                      │      │                      │
│  • ID                │◄─────│  • ID                │◄─── user_id in AI tables
│  • post_title        │ sync │  • user_login        │
│  • post_type         │      │  • user_email        │
│  • post_status       │      └──────────────────────┘
└──────────────────────┘
         ▲
         │ sync (bidirectional)
         │
    wp_saga_entities.wp_post_id
```

---

## 6. Technology Stack - Layer Dependencies

```
┌──────────────────────────────────────────────────────────┐
│                   CLIENT SIDE                             │
│                                                            │
│  JavaScript/TypeScript:                                   │
│  • Vanilla JS (no jQuery dependency)                     │
│  • ES6+ modules                                           │
│  • Fetch API for AJAX                                     │
│                                                            │
│  CSS:                                                     │
│  • Custom CSS (no framework)                              │
│  • WordPress admin styles                                 │
│  • Responsive design                                      │
└──────────────────────────────────────────────────────────┘
                         │
                         │ HTTP/AJAX
                         ▼
┌──────────────────────────────────────────────────────────┐
│                   SERVER SIDE (PHP 8.2+)                  │
│                                                            │
│  WordPress Core:                                          │
│  • Hooks & Filters system                                │
│  • REST API framework                                     │
│  • WP-Cron (background jobs)                             │
│  • Transient API (caching)                                │
│  • Nonce verification (security)                          │
│                                                            │
│  PHP Extensions Required:                                 │
│  • pdo_mysql (database)                                   │
│  • mysqli (WordPress core)                                │
│  • json (data serialization)                              │
│  • mbstring (UTF-8 support)                               │
│                                                            │
│  Composer Packages (dev only):                            │
│  • PHPUnit (testing)                                      │
│  • PHPStan (static analysis)                              │
│  • PHPCS (code standards)                                 │
└──────────────────────────────────────────────────────────┘
                         │
                         │ PDO/MySQLi
                         ▼
┌──────────────────────────────────────────────────────────┐
│                  DATABASE (MariaDB 11.4+)                 │
│                                                            │
│  Required Features:                                       │
│  • JSON functions (JSON_EXTRACT, JSON_CONTAINS)          │
│  • Window functions (ROW_NUMBER, RANK)                   │
│  • Foreign key constraints                                │
│  • Full-text search (MyISAM or InnoDB FTS)               │
│  • Transactions (InnoDB engine)                           │
│                                                            │
│  Optional:                                                │
│  • Vector UDF for semantic search                        │
│  • Partitioning for large datasets                       │
└──────────────────────────────────────────────────────────┘
                         │
                         │ TCP/IP
                         ▼
┌──────────────────────────────────────────────────────────┐
│                     CACHE LAYER (Optional)                │
│                                                            │
│  Redis 7+:                                                │
│  • wp_cache backend (object caching)                     │
│  • Session storage                                        │
│  • Rate limiting counters                                 │
│  • Embedding cache (vector storage)                      │
│                                                            │
│  Fallback:                                                │
│  • WordPress transients (database)                        │
│  • PHP APCu (in-memory)                                   │
└──────────────────────────────────────────────────────────┘
                         │
                         │ HTTPS
                         ▼
┌──────────────────────────────────────────────────────────┐
│              EXTERNAL SERVICES (Optional)                 │
│                                                            │
│  AI Providers:                                            │
│  • OpenAI API (GPT-4, GPT-3.5-turbo)                     │
│  • Anthropic API (Claude 3 Opus, Sonnet)                 │
│                                                            │
│  Embedding Service:                                       │
│  • sentence-transformers (FastAPI server)                │
│  • all-MiniLM-L6-v2 model (384 dimensions)               │
│                                                            │
│  Monitoring (Future):                                     │
│  • Sentry (error tracking)                                │
│  • New Relic (APM)                                        │
└──────────────────────────────────────────────────────────┘
```

---

## 7. Deployment Architecture

### 7.1 Single Server Deployment

```
┌──────────────────────────────────────────────────────────┐
│                   PRODUCTION SERVER                       │
│                                                            │
│  ┌────────────────────────────────────────────────────┐  │
│  │               Apache/Nginx (Web Server)            │  │
│  │  • SSL/TLS termination                             │  │
│  │  • Reverse proxy                                   │  │
│  │  • Static file serving                             │  │
│  └────────────────────────────────────────────────────┘  │
│                         │                                 │
│                         ▼                                 │
│  ┌────────────────────────────────────────────────────┐  │
│  │            PHP-FPM 8.2+ (Application)              │  │
│  │  • WordPress + Saga Manager Theme                  │  │
│  │  • OPcache enabled                                 │  │
│  │  • APCu for object cache                           │  │
│  └────────────────────────────────────────────────────┘  │
│                         │                                 │
│                         ▼                                 │
│  ┌────────────────────────────────────────────────────┐  │
│  │           MariaDB 11.4+ (Database)                 │  │
│  │  • InnoDB storage engine                           │  │
│  │  • Query cache enabled                             │  │
│  │  • Automated backups (daily)                       │  │
│  └────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
                         │
                         │ External API calls
                         ▼
                ┌─────────────────┐
                │  OpenAI/Claude  │
                │  External APIs  │
                └─────────────────┘
```

### 7.2 Scalable Deployment (High Traffic)

```
┌──────────────────────────────────────────────────────────┐
│                     LOAD BALANCER                         │
│  (HAProxy / AWS ALB / Cloudflare)                        │
└────────────┬───────────────────────┬─────────────────────┘
             │                       │
             ▼                       ▼
┌─────────────────────┐    ┌─────────────────────┐
│   Web Server 1      │    │   Web Server 2      │
│   (Apache/Nginx)    │    │   (Apache/Nginx)    │
│   + PHP-FPM         │    │   + PHP-FPM         │
│   + WordPress       │    │   + WordPress       │
└──────────┬──────────┘    └──────────┬──────────┘
           │                          │
           └────────┬─────────────────┘
                    │
                    ▼
          ┌─────────────────────┐
          │   Redis Cluster     │
          │  (Object Cache)     │
          └─────────────────────┘
                    │
                    ▼
          ┌─────────────────────┐
          │  MariaDB Primary    │
          │  (Read/Write)       │
          └──────────┬──────────┘
                     │
              ┌──────┴──────┐
              ▼             ▼
     ┌──────────────┐  ┌──────────────┐
     │  Replica 1   │  │  Replica 2   │
     │  (Read Only) │  │  (Read Only) │
     └──────────────┘  └──────────────┘
                     │
                     ▼
          ┌─────────────────────┐
          │  External Services  │
          │  • OpenAI API       │
          │  • Anthropic API    │
          │  • Embedding Svc    │
          └─────────────────────┘
```

---

## 8. Security Architecture

```
┌──────────────────────────────────────────────────────────┐
│                  SECURITY LAYERS                          │
└──────────────────────────────────────────────────────────┘

Layer 1: Network Security
┌────────────────────────────────────────────────────────┐
│  • Firewall rules (allow only 80/443)                  │
│  • DDoS protection (Cloudflare/AWS Shield)            │
│  • IP whitelisting for admin (optional)               │
│  • VPN access for database (production)               │
└────────────────────────────────────────────────────────┘

Layer 2: Application Security
┌────────────────────────────────────────────────────────┐
│  • HTTPS enforced (SSL/TLS 1.3)                       │
│  • WordPress nonce verification (CSRF)                │
│  • Capability checks (Authorization)                  │
│  • Input sanitization (XSS prevention)                │
│  • SQL injection prevention ($wpdb->prepare)          │
│  • Rate limiting (API endpoints)                      │
│  • Content Security Policy headers                    │
└────────────────────────────────────────────────────────┘

Layer 3: Database Security
┌────────────────────────────────────────────────────────┐
│  • Least privilege principle (user permissions)       │
│  • Prepared statements only                           │
│  • Foreign key constraints                            │
│  • Encrypted connections (SSL)                        │
│  • Audit logging enabled                              │
└────────────────────────────────────────────────────────┘

Layer 4: Data Security
┌────────────────────────────────────────────────────────┐
│  • No plain text passwords                            │
│  • API keys in environment variables                  │
│  • Sensitive data not logged                          │
│  • User data encrypted at rest (optional)             │
│  • GDPR compliance (data retention policies)          │
└────────────────────────────────────────────────────────┘
```

---

## 9. File Structure - Codebase Organization

```
saga-manager-theme/
│
├── functions.php                 # Theme entry point, hooks registration
├── style.css                     # Theme metadata
├── composer.json                 # PHP dependencies
├── phpunit.xml                   # PHPUnit configuration
├── docker-compose.test.yml       # Test environment
├── Makefile                      # Development commands
│
├── inc/                          # Backend PHP code
│   ├── autoload.php              # PSR-4 autoloader
│   │
│   ├── class-saga*.php           # Core classes (legacy naming)
│   │   ├── class-sagatheme.php
│   │   ├── class-sagaqueries.php
│   │   ├── class-sagahelpers.php
│   │   ├── class-sagacache.php
│   │   └── ...
│   │
│   ├── ai/                       # Phase 2: AI Services
│   │   ├── entities/             # Value Objects (Domain)
│   │   │   ├── ConsistencyIssue.php
│   │   │   ├── ExtractionJob.php
│   │   │   ├── ExtractedEntity.php
│   │   │   ├── RelationshipSuggestion.php
│   │   │   ├── SuggestionFeature.php
│   │   │   ├── SummaryRequest.php
│   │   │   └── GeneratedSummary.php
│   │   │
│   │   ├── *Repository.php       # Infrastructure (Data Access)
│   │   │   ├── ConsistencyRepository.php
│   │   │   ├── ExtractionRepository.php
│   │   │   ├── SuggestionRepository.php
│   │   │   └── SummaryRepository.php
│   │   │
│   │   ├── *Service.php          # Application Services
│   │   │   ├── DataCollectionService.php
│   │   │   ├── FeatureExtractionService.php
│   │   │   ├── DuplicateDetectionService.php
│   │   │   └── ...
│   │   │
│   │   ├── *-migrator.php        # Database migrations
│   │   │   ├── entity-extractor-migrator.php
│   │   │   ├── predictive-relationships-migrator.php
│   │   │   └── summary-generator-migrator.php
│   │   │
│   │   └── *-loader.php          # Module initialization
│   │       ├── consistency-guardian-loader.php
│   │       ├── entity-extractor-loader.php
│   │       └── ...
│   │
│   ├── ajax/                     # AJAX Handlers
│   │   ├── consistency-ajax.php
│   │   ├── extraction-ajax.php
│   │   ├── suggestions-ajax.php
│   │   ├── summaries-ajax.php
│   │   ├── quick-create-handler.php
│   │   ├── search-handler.php
│   │   └── timeline-data-handler.php
│   │
│   ├── admin/                    # Admin UI
│   │   ├── admin-analytics-dashboard.php
│   │   ├── consistency-admin-init.php
│   │   ├── entity-templates.php
│   │   └── quick-create.php
│   │
│   ├── shortcodes/               # Frontend Shortcodes
│   │   ├── timeline-shortcode.php
│   │   └── search-shortcode.php
│   │
│   ├── helpers/                  # Utilities
│   │   └── calendar-converter.php
│   │
│   └── widgets/                  # WordPress Widgets
│       └── search-widget.php
│
├── assets/                       # Frontend Assets
│   ├── js/
│   │   ├── admin/
│   │   │   ├── consistency-guardian.js
│   │   │   ├── entity-extractor.js
│   │   │   ├── predictive-relationships.js
│   │   │   └── summary-generator.js
│   │   └── public/
│   │       ├── timeline.js
│   │       └── search.js
│   │
│   ├── css/
│   │   ├── admin/
│   │   │   └── admin-styles.css
│   │   └── public/
│   │       └── theme-styles.css
│   │
│   └── images/
│       └── icons/
│
├── templates/                    # Page Templates
│   └── page-timeline.php
│
├── template-parts/               # Reusable Template Parts
│   ├── entity/
│   │   ├── entity-card.php
│   │   └── entity-detail.php
│   └── relationship/
│       └── relationship-graph.php
│
├── tests/                        # Test Suite
│   ├── bootstrap.php             # WordPress test framework
│   ├── includes/
│   │   ├── TestCase.php          # Base test class
│   │   └── FactoryTrait.php      # Test data factories
│   │
│   ├── unit/                     # Unit Tests (95 tests)
│   │   ├── ConsistencyGuardian/
│   │   ├── EntityExtractor/
│   │   ├── PredictiveRelationships/
│   │   └── SummaryGenerator/
│   │
│   └── integration/              # Integration Tests (86 tests)
│       ├── ConsistencyGuardian/
│       ├── EntityExtractor/
│       ├── PredictiveRelationships/
│       └── SummaryGenerator/
│
└── vendor/                       # Composer dependencies (dev only)
    └── autoload.php
```

---

## 10. Upstream & Downstream Dependencies

### 10.1 Upstream (Saga Manager depends on)

**Critical Dependencies:**
```
WordPress Core 6.0+
    │
    ├─► Hooks & Filters API
    ├─► REST API Framework
    ├─► WP-Cron System
    ├─► Transient API
    ├─► Nonce System
    ├─► User Capabilities
    └─► Post System (wp_posts)

MariaDB 11.4+
    │
    ├─► JSON Functions
    ├─► Window Functions
    ├─► Foreign Keys
    ├─► Full-Text Search
    └─► Transactions

PHP 8.2+
    │
    ├─► pdo_mysql
    ├─► mysqli
    ├─► json
    ├─► mbstring
    └─► opcache

Redis 7+ (Optional)
    │
    └─► Object Cache Backend
```

**External Service Dependencies:**
```
OpenAI API (Optional)
    │
    └─► GPT-4, GPT-3.5-turbo models

Anthropic API (Optional)
    │
    └─► Claude 3 Opus, Sonnet models

Sentence Transformers (Optional)
    │
    └─► all-MiniLM-L6-v2 embedding model
```

### 10.2 Downstream (Things that depend on Saga Manager)

**Direct Consumers:**
```
WordPress Admin Users
    │
    ├─► Create/Edit Entities
    ├─► Generate Summaries
    ├─► Review Relationships
    └─► Monitor Consistency

Website Visitors
    │
    ├─► Browse Entity Pages
    ├─► Search Sagas
    ├─► View Timelines
    └─► Read Summaries

Third-party Plugins (Potential)
    │
    ├─► Access REST API
    ├─► Filter saga data
    └─► Extend entity types

Developers
    │
    ├─► Custom entity types
    ├─► Custom shortcodes
    ├─► Theme child themes
    └─► API integrations
```

**Integration Points:**
```
WordPress Ecosystem
    │
    ├─► wp_posts (bidirectional sync)
    ├─► wp_users (foreign keys)
    ├─► wp_options (settings)
    └─► wp_postmeta (custom fields)
```

---

## 11. API Endpoints - REST Architecture

```
WordPress REST API Namespace: /wp-json/saga/v1/

Entities:
    GET    /entities                      # List all entities
    GET    /entities/{id}                 # Get single entity
    POST   /entities                      # Create entity
    PUT    /entities/{id}                 # Update entity
    DELETE /entities/{id}                 # Delete entity

Relationships:
    GET    /relationships                 # List relationships
    GET    /relationships/{id}            # Get single relationship
    POST   /relationships                 # Create relationship
    DELETE /relationships/{id}            # Delete relationship

Sagas:
    GET    /sagas                         # List all sagas
    GET    /sagas/{id}                    # Get single saga
    POST   /sagas                         # Create saga
    PUT    /sagas/{id}                    # Update saga

Timeline:
    GET    /timeline/{saga_id}            # Get saga timeline
    POST   /timeline/events               # Create event

AI Services (Phase 2):
    POST   /consistency/analyze           # Analyze consistency
    GET    /consistency/issues            # List issues
    PUT    /consistency/resolve/{id}      # Resolve issue

    POST   /extraction/start              # Start extraction job
    GET    /extraction/status/{job_id}    # Check job status
    POST   /extraction/confirm            # Confirm extracted entity

    GET    /suggestions/pending           # Get pending suggestions
    POST   /suggestions/feedback          # Accept/reject suggestion
    GET    /suggestions/accuracy          # Get accuracy metrics

    POST   /summaries/generate            # Generate summary
    GET    /summaries/{id}                # Get summary
    GET    /summaries/request/{id}        # Get request status

Search:
    GET    /search?q={query}&saga={id}    # Search entities
```

---

## 12. Event Flow - WordPress Hooks Integration

```
WordPress Lifecycle → Saga Manager Hooks

init
  ├─► Register custom post types (saga_entity)
  ├─► Register taxonomies (saga_type)
  ├─► Initialize AI services
  └─► Load migrations if needed

rest_api_init
  ├─► Register REST routes
  └─► Add authentication callbacks

admin_menu
  ├─► Add Analytics Dashboard
  ├─► Add AI Tools menu
  └─► Add Settings page

save_post (saga_entity)
  ├─► Sync to wp_saga_entities
  ├─► Update attribute values
  ├─► Invalidate cache
  └─► Trigger consistency check

wp_ajax_* (AJAX handlers)
  ├─► verify nonce
  ├─► check capabilities
  ├─► process request
  └─► return JSON response

wp_cron (background jobs)
  ├─► Generate pending summaries
  ├─► Process extraction jobs
  ├─► Analyze relationships (daily)
  └─► Cleanup old data (weekly)
```

---

**Document Version:** 1.0.0
**Last Updated:** 2026-01-03
**Maintained By:** Development Team
**Review Schedule:** Quarterly or with major changes
