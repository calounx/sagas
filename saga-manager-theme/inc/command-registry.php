<?php
/**
 * Command Registry for Keyboard Shortcuts
 *
 * @package SagaManagerTheme
 */

declare(strict_types=1);

namespace SagaManagerTheme\Commands;

/**
 * Command Registry
 *
 * Defines all available commands for the command palette and keyboard shortcuts
 */
class CommandRegistry {
    /**
     * Get all registered commands
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function get_commands(): array {
        $commands = [
            'navigation' => [
                [
                    'id' => 'go-home',
                    'label' => 'Go to Home',
                    'description' => 'Navigate to homepage',
                    'keys' => 'G H',
                    'sequence' => true,
                    'action' => home_url('/'),
                    'icon' => '🏠',
                    'category' => 'navigation'
                ],
                [
                    'id' => 'go-search',
                    'label' => 'Go to Search',
                    'description' => 'Open advanced search',
                    'keys' => 'G S',
                    'sequence' => true,
                    'action' => home_url('/search/'),
                    'icon' => '🔍',
                    'category' => 'navigation'
                ],
                [
                    'id' => 'go-collections',
                    'label' => 'My Collections',
                    'description' => 'View your saved collections',
                    'keys' => 'G C',
                    'sequence' => true,
                    'action' => home_url('/my-collections/'),
                    'icon' => '📚',
                    'category' => 'navigation'
                ],
                [
                    'id' => 'go-annotations',
                    'label' => 'My Annotations',
                    'description' => 'View your annotations',
                    'keys' => 'G A',
                    'sequence' => true,
                    'action' => home_url('/my-annotations/'),
                    'icon' => '📝',
                    'category' => 'navigation'
                ],
                [
                    'id' => 'go-timeline',
                    'label' => 'Timeline Viewer',
                    'description' => 'View saga timeline',
                    'keys' => 'G T',
                    'sequence' => true,
                    'action' => home_url('/timeline/'),
                    'icon' => '📅',
                    'category' => 'navigation'
                ],
                [
                    'id' => 'go-graph',
                    'label' => 'Relationship Graph',
                    'description' => 'Explore entity connections',
                    'keys' => 'G R',
                    'sequence' => true,
                    'action' => home_url('/relationship-graph/'),
                    'icon' => '🕸️',
                    'category' => 'navigation'
                ],
            ],
            'actions' => [
                [
                    'id' => 'new-annotation',
                    'label' => 'New Annotation',
                    'description' => 'Create a new annotation',
                    'keys' => 'N',
                    'sequence' => false,
                    'action' => 'openAnnotationModal',
                    'icon' => '✏️',
                    'category' => 'actions',
                    'requiresAuth' => true
                ],
                [
                    'id' => 'bookmark',
                    'label' => 'Bookmark Entity',
                    'description' => 'Add current entity to bookmarks',
                    'keys' => 'B',
                    'sequence' => false,
                    'action' => 'toggleBookmark',
                    'icon' => '⭐',
                    'category' => 'actions',
                    'requiresAuth' => true,
                    'requiresEntity' => true
                ],
                [
                    'id' => 'reading-mode',
                    'label' => 'Reading Mode',
                    'description' => 'Toggle distraction-free reading',
                    'keys' => 'R',
                    'sequence' => false,
                    'action' => 'toggleReadingMode',
                    'icon' => '📖',
                    'category' => 'actions'
                ],
                [
                    'id' => 'focus-search',
                    'label' => 'Focus Search Bar',
                    'description' => 'Jump to search input',
                    'keys' => 'S',
                    'sequence' => false,
                    'action' => 'focusSearch',
                    'icon' => '🔎',
                    'category' => 'actions'
                ],
                [
                    'id' => 'share',
                    'label' => 'Share Entity',
                    'description' => 'Share current entity',
                    'keys' => 'Shift+S',
                    'sequence' => false,
                    'action' => 'shareEntity',
                    'icon' => '🔗',
                    'category' => 'actions',
                    'requiresEntity' => true
                ],
            ],
            'appearance' => [
                [
                    'id' => 'dark-mode',
                    'label' => 'Toggle Dark Mode',
                    'description' => 'Switch between light and dark theme',
                    'keys' => 'D',
                    'sequence' => false,
                    'action' => 'toggleDarkMode',
                    'icon' => '🌙',
                    'category' => 'appearance'
                ],
                [
                    'id' => 'increase-font',
                    'label' => 'Increase Font Size',
                    'description' => 'Make text larger',
                    'keys' => 'Ctrl+=',
                    'sequence' => false,
                    'action' => 'increaseFontSize',
                    'icon' => '🔤',
                    'category' => 'appearance'
                ],
                [
                    'id' => 'decrease-font',
                    'label' => 'Decrease Font Size',
                    'description' => 'Make text smaller',
                    'keys' => 'Ctrl+-',
                    'sequence' => false,
                    'action' => 'decreaseFontSize',
                    'icon' => '🔡',
                    'category' => 'appearance'
                ],
            ],
            'filters' => [
                [
                    'id' => 'filter-characters',
                    'label' => 'Filter: Characters',
                    'description' => 'Show only characters',
                    'keys' => '1',
                    'sequence' => false,
                    'action' => 'filterByType:character',
                    'icon' => '👤',
                    'category' => 'filters',
                    'requiresSearch' => true
                ],
                [
                    'id' => 'filter-locations',
                    'label' => 'Filter: Locations',
                    'description' => 'Show only locations',
                    'keys' => '2',
                    'sequence' => false,
                    'action' => 'filterByType:location',
                    'icon' => '📍',
                    'category' => 'filters',
                    'requiresSearch' => true
                ],
                [
                    'id' => 'filter-events',
                    'label' => 'Filter: Events',
                    'description' => 'Show only events',
                    'keys' => '3',
                    'sequence' => false,
                    'action' => 'filterByType:event',
                    'icon' => '⚡',
                    'category' => 'filters',
                    'requiresSearch' => true
                ],
                [
                    'id' => 'filter-factions',
                    'label' => 'Filter: Factions',
                    'description' => 'Show only factions',
                    'keys' => '4',
                    'sequence' => false,
                    'action' => 'filterByType:faction',
                    'icon' => '⚔️',
                    'category' => 'filters',
                    'requiresSearch' => true
                ],
                [
                    'id' => 'filter-artifacts',
                    'label' => 'Filter: Artifacts',
                    'description' => 'Show only artifacts',
                    'keys' => '5',
                    'sequence' => false,
                    'action' => 'filterByType:artifact',
                    'icon' => '💎',
                    'category' => 'filters',
                    'requiresSearch' => true
                ],
                [
                    'id' => 'filter-concepts',
                    'label' => 'Filter: Concepts',
                    'description' => 'Show only concepts',
                    'keys' => '6',
                    'sequence' => false,
                    'action' => 'filterByType:concept',
                    'icon' => '💡',
                    'category' => 'filters',
                    'requiresSearch' => true
                ],
                [
                    'id' => 'clear-filters',
                    'label' => 'Clear All Filters',
                    'description' => 'Reset search filters',
                    'keys' => '0',
                    'sequence' => false,
                    'action' => 'clearFilters',
                    'icon' => '🔄',
                    'category' => 'filters',
                    'requiresSearch' => true
                ],
            ],
            'help' => [
                [
                    'id' => 'show-shortcuts',
                    'label' => 'Show Keyboard Shortcuts',
                    'description' => 'View all available shortcuts',
                    'keys' => '?',
                    'sequence' => false,
                    'action' => 'showShortcutsHelp',
                    'icon' => '❓',
                    'category' => 'help'
                ],
                [
                    'id' => 'show-help',
                    'label' => 'Show Help',
                    'description' => 'Open help documentation',
                    'keys' => 'Shift+?',
                    'sequence' => false,
                    'action' => home_url('/help/'),
                    'icon' => '📚',
                    'category' => 'help'
                ],
            ],
            'system' => [
                [
                    'id' => 'command-palette',
                    'label' => 'Open Command Palette',
                    'description' => 'Access all commands',
                    'keys' => 'Ctrl+K',
                    'sequence' => false,
                    'action' => 'toggleCommandPalette',
                    'icon' => '⌘',
                    'category' => 'system'
                ],
                [
                    'id' => 'escape',
                    'label' => 'Close/Escape',
                    'description' => 'Close modals and palettes',
                    'keys' => 'Esc',
                    'sequence' => false,
                    'action' => 'closeModals',
                    'icon' => '❌',
                    'category' => 'system'
                ],
            ],
        ];

        return apply_filters('saga_keyboard_commands', $commands);
    }

    /**
     * Get commands formatted for JavaScript
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_commands_for_js(): array {
        $all_commands = self::get_commands();
        $flat_commands = [];

        foreach ($all_commands as $category => $commands) {
            foreach ($commands as $command) {
                // Add visibility check
                if (isset($command['requiresAuth']) && $command['requiresAuth'] && !is_user_logged_in()) {
                    continue;
                }

                $flat_commands[] = $command;
            }
        }

        return $flat_commands;
    }

    /**
     * Get command by ID
     *
     * @param string $command_id Command ID
     * @return array<string, mixed>|null
     */
    public static function get_command(string $command_id): ?array {
        $all_commands = self::get_commands();

        foreach ($all_commands as $category => $commands) {
            foreach ($commands as $command) {
                if ($command['id'] === $command_id) {
                    return $command;
                }
            }
        }

        return null;
    }

    /**
     * Enqueue command registry for JavaScript
     */
    public static function enqueue_commands(): void {
        wp_localize_script('saga-keyboard-shortcuts', 'sagaCommands', [
            'commands' => self::get_commands_for_js(),
            'isMac' => self::is_mac_platform(),
            'isLoggedIn' => is_user_logged_in(),
            'nonce' => wp_create_nonce('saga_shortcuts'),
        ]);
    }

    /**
     * Detect if user is on Mac platform
     *
     * @return bool
     */
    private static function is_mac_platform(): bool {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return stripos($user_agent, 'Mac') !== false;
    }
}
