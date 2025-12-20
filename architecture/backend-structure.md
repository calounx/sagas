# Saga Manager Core - Backend Plugin Structure

```
saga-manager-core/
├── saga-manager-core.php              # Main plugin file
├── uninstall.php                      # Cleanup on uninstall
├── composer.json
├── phpcs.xml                          # Coding standards
├── phpunit.xml
│
├── assets/
│   └── admin/
│       ├── css/
│       │   ├── admin.css              # Admin page styles
│       │   └── list-tables.css        # WP_List_Table customizations
│       └── js/
│           ├── admin.js               # General admin functionality
│           ├── entity-editor.js       # Entity form handling
│           └── relationship-graph.js  # Visual relationship editor
│
├── includes/
│   ├── class-activator.php            # Activation logic
│   ├── class-deactivator.php          # Deactivation logic
│   └── class-loader.php               # Hook/filter registration
│
├── src/
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── Saga.php
│   │   │   ├── SagaEntity.php
│   │   │   ├── Relationship.php
│   │   │   ├── TimelineEvent.php
│   │   │   ├── AttributeDefinition.php
│   │   │   └── ContentFragment.php
│   │   │
│   │   ├── ValueObject/
│   │   │   ├── EntityId.php
│   │   │   ├── SagaId.php
│   │   │   ├── ImportanceScore.php
│   │   │   ├── RelationshipType.php
│   │   │   ├── EntityType.php
│   │   │   └── CanonDate.php
│   │   │
│   │   ├── Repository/
│   │   │   ├── SagaRepositoryInterface.php
│   │   │   ├── EntityRepositoryInterface.php
│   │   │   ├── RelationshipRepositoryInterface.php
│   │   │   ├── TimelineRepositoryInterface.php
│   │   │   └── SearchRepositoryInterface.php
│   │   │
│   │   ├── Service/
│   │   │   ├── EntityValidatorInterface.php
│   │   │   ├── QualityAnalyzerInterface.php
│   │   │   └── EmbeddingServiceInterface.php
│   │   │
│   │   └── Exception/
│   │       ├── DomainException.php
│   │       ├── EntityNotFoundException.php
│   │       ├── ValidationException.php
│   │       ├── DuplicateEntityException.php
│   │       └── RelationshipConstraintException.php
│   │
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── CreateSagaCommand.php
│   │   │   ├── CreateEntityCommand.php
│   │   │   ├── UpdateEntityCommand.php
│   │   │   ├── DeleteEntityCommand.php
│   │   │   ├── CreateRelationshipCommand.php
│   │   │   └── BulkImportCommand.php
│   │   │
│   │   ├── Query/
│   │   │   ├── GetEntityQuery.php
│   │   │   ├── ListEntitiesQuery.php
│   │   │   ├── SearchEntitiesQuery.php
│   │   │   ├── GetRelationshipsQuery.php
│   │   │   └── GetTimelineQuery.php
│   │   │
│   │   ├── Handler/
│   │   │   ├── CreateSagaHandler.php
│   │   │   ├── CreateEntityHandler.php
│   │   │   ├── UpdateEntityHandler.php
│   │   │   ├── DeleteEntityHandler.php
│   │   │   ├── CreateRelationshipHandler.php
│   │   │   ├── GetEntityHandler.php
│   │   │   ├── ListEntitiesHandler.php
│   │   │   ├── SearchEntitiesHandler.php
│   │   │   └── GetTimelineHandler.php
│   │   │
│   │   ├── DTO/
│   │   │   ├── EntityDTO.php
│   │   │   ├── SagaDTO.php
│   │   │   ├── RelationshipDTO.php
│   │   │   ├── TimelineEventDTO.php
│   │   │   └── PaginatedResultDTO.php
│   │   │
│   │   └── Service/
│   │       ├── EntityService.php
│   │       ├── RelationshipService.php
│   │       ├── TimelineService.php
│   │       ├── SearchService.php
│   │       └── QualityService.php
│   │
│   ├── Infrastructure/
│   │   ├── Repository/
│   │   │   ├── WordPressTablePrefixAware.php
│   │   │   ├── MariaDBSagaRepository.php
│   │   │   ├── MariaDBEntityRepository.php
│   │   │   ├── MariaDBRelationshipRepository.php
│   │   │   ├── MariaDBTimelineRepository.php
│   │   │   ├── MariaDBAttributeRepository.php
│   │   │   └── MariaDBSearchRepository.php
│   │   │
│   │   ├── Database/
│   │   │   ├── Schema.php             # Table definitions
│   │   │   ├── Migrator.php           # Migration runner
│   │   │   └── migrations/
│   │   │       ├── Migration001_InitialSchema.php
│   │   │       ├── Migration002_AddQualityMetrics.php
│   │   │       └── Migration003_AddEmbeddings.php
│   │   │
│   │   ├── Cache/
│   │   │   ├── CacheInterface.php
│   │   │   ├── WordPressCacheAdapter.php
│   │   │   └── RedisCacheAdapter.php
│   │   │
│   │   ├── Service/
│   │   │   ├── HttpEmbeddingService.php
│   │   │   ├── EntityValidator.php
│   │   │   └── QualityAnalyzer.php
│   │   │
│   │   └── WordPress/
│   │       ├── OptionsManager.php     # Plugin settings
│   │       ├── CapabilityManager.php  # Custom capabilities
│   │       └── CronManager.php        # Background jobs
│   │
│   ├── Presentation/
│   │   ├── Admin/
│   │   │   ├── AdminMenuManager.php   # Menu registration
│   │   │   ├── Pages/
│   │   │   │   ├── DashboardPage.php
│   │   │   │   ├── SagaListPage.php
│   │   │   │   ├── SagaEditPage.php
│   │   │   │   ├── EntityListPage.php
│   │   │   │   ├── EntityEditPage.php
│   │   │   │   ├── RelationshipListPage.php
│   │   │   │   ├── TimelinePage.php
│   │   │   │   ├── SearchPage.php
│   │   │   │   └── SettingsPage.php
│   │   │   │
│   │   │   ├── ListTable/
│   │   │   │   ├── SagaListTable.php
│   │   │   │   ├── EntityListTable.php
│   │   │   │   ├── RelationshipListTable.php
│   │   │   │   └── TimelineListTable.php
│   │   │   │
│   │   │   ├── Form/
│   │   │   │   ├── SagaForm.php
│   │   │   │   ├── EntityForm.php
│   │   │   │   ├── AttributeFieldRenderer.php
│   │   │   │   └── RelationshipForm.php
│   │   │   │
│   │   │   └── Widget/
│   │   │       ├── DashboardStatsWidget.php
│   │   │       └── QualityAlertsWidget.php
│   │   │
│   │   └── Rest/
│   │       ├── RestApiManager.php     # Route registration
│   │       ├── Middleware/
│   │       │   ├── AuthenticationMiddleware.php
│   │       │   ├── RateLimitMiddleware.php
│   │       │   └── SanitizationMiddleware.php
│   │       │
│   │       └── Controller/
│   │           ├── SagaController.php
│   │           ├── EntityController.php
│   │           ├── RelationshipController.php
│   │           ├── TimelineController.php
│   │           ├── SearchController.php
│   │           └── HealthController.php
│   │
│   └── Contract/                      # Shared interfaces for frontend
│       ├── ApiEndpoints.php           # Endpoint constants
│       ├── EntityTypeContract.php     # Entity type definitions
│       └── ResponseFormat.php         # Response structure
│
├── templates/
│   └── admin/
│       ├── dashboard.php
│       ├── saga-list.php
│       ├── saga-edit.php
│       ├── entity-list.php
│       ├── entity-edit.php
│       ├── relationship-list.php
│       ├── timeline.php
│       └── settings.php
│
├── languages/
│   └── saga-manager-core.pot
│
└── tests/
    ├── Unit/
    │   ├── Domain/
    │   │   ├── ValueObject/
    │   │   └── Entity/
    │   └── Application/
    │       └── Handler/
    │
    ├── Integration/
    │   ├── Repository/
    │   └── Rest/
    │
    └── fixtures/
        └── test-data.php
```
