<?php

declare(strict_types=1);

namespace SagaManagerCore\Presentation\Admin;

/**
 * Manages WordPress admin menu for Saga Manager
 *
 * Uses custom admin pages with WP_List_Table instead of Custom Post Types
 */
final class AdminMenuManager
{
    private const CAPABILITY = 'edit_posts';
    private const MENU_SLUG = 'saga-manager';

    public function register(): void
    {
        // Main menu
        add_menu_page(
            __('Saga Manager', 'saga-manager-core'),
            __('Saga Manager', 'saga-manager-core'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-book-alt',
            25
        );

        // Dashboard (same as main)
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'saga-manager-core'),
            __('Dashboard', 'saga-manager-core'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'renderDashboard']
        );

        // Sagas
        add_submenu_page(
            self::MENU_SLUG,
            __('Sagas', 'saga-manager-core'),
            __('Sagas', 'saga-manager-core'),
            self::CAPABILITY,
            self::MENU_SLUG . '-sagas',
            [$this, 'renderSagasPage']
        );

        // Entities
        add_submenu_page(
            self::MENU_SLUG,
            __('Entities', 'saga-manager-core'),
            __('Entities', 'saga-manager-core'),
            self::CAPABILITY,
            self::MENU_SLUG . '-entities',
            [$this, 'renderEntitiesPage']
        );

        // Relationships
        add_submenu_page(
            self::MENU_SLUG,
            __('Relationships', 'saga-manager-core'),
            __('Relationships', 'saga-manager-core'),
            self::CAPABILITY,
            self::MENU_SLUG . '-relationships',
            [$this, 'renderRelationshipsPage']
        );

        // Timeline
        add_submenu_page(
            self::MENU_SLUG,
            __('Timeline', 'saga-manager-core'),
            __('Timeline', 'saga-manager-core'),
            self::CAPABILITY,
            self::MENU_SLUG . '-timeline',
            [$this, 'renderTimelinePage']
        );

        // Attribute Definitions
        add_submenu_page(
            self::MENU_SLUG,
            __('Attributes', 'saga-manager-core'),
            __('Attributes', 'saga-manager-core'),
            'manage_options',
            self::MENU_SLUG . '-attributes',
            [$this, 'renderAttributesPage']
        );

        // Settings
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'saga-manager-core'),
            __('Settings', 'saga-manager-core'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function renderDashboard(): void
    {
        $page = new Pages\DashboardPage();
        $page->render();
    }

    public function renderSagasPage(): void
    {
        $action = sanitize_key($_GET['action'] ?? 'list');

        match ($action) {
            'new', 'edit' => (new Pages\SagaEditPage())->render(),
            default => (new Pages\SagaListPage())->render(),
        };
    }

    public function renderEntitiesPage(): void
    {
        $action = sanitize_key($_GET['action'] ?? 'list');

        match ($action) {
            'new', 'edit' => (new Pages\EntityEditPage())->render(),
            default => (new Pages\EntityListPage())->render(),
        };
    }

    public function renderRelationshipsPage(): void
    {
        $action = sanitize_key($_GET['action'] ?? 'list');

        match ($action) {
            'new', 'edit' => (new Pages\RelationshipEditPage())->render(),
            default => (new Pages\RelationshipListPage())->render(),
        };
    }

    public function renderTimelinePage(): void
    {
        $page = new Pages\TimelinePage();
        $page->render();
    }

    public function renderAttributesPage(): void
    {
        $page = new Pages\AttributeDefinitionsPage();
        $page->render();
    }

    public function renderSettingsPage(): void
    {
        $page = new Pages\SettingsPage();
        $page->render();
    }

    /**
     * Get admin URL for a saga manager page
     */
    public static function getUrl(string $page, array $params = []): string
    {
        $slug = $page === 'dashboard' ? self::MENU_SLUG : self::MENU_SLUG . '-' . $page;
        $url = admin_url('admin.php?page=' . $slug);

        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }

        return $url;
    }
}
