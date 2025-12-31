/**
 * Command Palette
 *
 * Fuzzy search command palette with keyboard navigation
 *
 * @package SagaManagerTheme
 */

(function() {
    'use strict';

    class CommandPalette {
        constructor() {
            this.container = null;
            this.input = null;
            this.results = null;
            this.commands = [];
            this.filteredCommands = [];
            this.selectedIndex = 0;
            this.isOpen = false;

            this.init();
        }

        /**
         * Initialize command palette
         */
        init() {
            this.loadCommands();
            this.createDOM();
            this.attachEventListeners();

            // Expose to window
            window.sagaPalette = this;
        }

        /**
         * Load commands from registry
         */
        loadCommands() {
            if (typeof sagaCommands === 'undefined' || !sagaCommands.commands) {
                console.warn('Saga commands not loaded');
                return;
            }

            this.commands = sagaCommands.commands;
        }

        /**
         * Create DOM structure
         */
        createDOM() {
            // Create container
            this.container = document.createElement('div');
            this.container.className = 'saga-command-palette';
            this.container.setAttribute('role', 'dialog');
            this.container.setAttribute('aria-modal', 'true');
            this.container.setAttribute('aria-labelledby', 'command-palette-label');
            this.container.hidden = true;

            // Create backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'command-palette__backdrop';
            backdrop.addEventListener('click', () => this.close());

            // Create main container
            const main = document.createElement('div');
            main.className = 'command-palette__container';

            // Create header
            const header = document.createElement('div');
            header.className = 'command-palette__header';
            header.innerHTML = `
                <h2 id="command-palette-label" class="sr-only">Command Palette</h2>
            `;

            // Create search input
            const searchWrapper = document.createElement('div');
            searchWrapper.className = 'command-palette__search';

            this.input = document.createElement('input');
            this.input.type = 'text';
            this.input.className = 'command-palette__input';
            this.input.placeholder = 'Type a command or search...';
            this.input.setAttribute('aria-label', 'Command search');
            this.input.setAttribute('autocomplete', 'off');
            this.input.setAttribute('spellcheck', 'false');

            searchWrapper.appendChild(this.input);

            // Create results container
            this.results = document.createElement('div');
            this.results.className = 'command-palette__results';
            this.results.setAttribute('role', 'listbox');

            // Assemble
            main.appendChild(header);
            main.appendChild(searchWrapper);
            main.appendChild(this.results);

            this.container.appendChild(backdrop);
            this.container.appendChild(main);

            document.body.appendChild(this.container);
        }

        /**
         * Attach event listeners
         */
        attachEventListeners() {
            // Input events
            this.input.addEventListener('input', this.handleInput.bind(this));
            this.input.addEventListener('keydown', this.handleKeyDown.bind(this));

            // Global keyboard listener (Ctrl+K)
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    this.toggle();
                }
            });
        }

        /**
         * Handle input changes
         */
        handleInput(e) {
            const query = e.target.value.trim();

            if (query === '') {
                this.showDefaultCommands();
            } else {
                this.search(query);
            }
        }

        /**
         * Handle keyboard navigation
         */
        handleKeyDown(e) {
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.selectNext();
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    this.selectPrevious();
                    break;

                case 'Enter':
                    e.preventDefault();
                    this.executeSelected();
                    break;

                case 'Escape':
                    e.preventDefault();
                    this.close();
                    break;

                case 'Home':
                    e.preventDefault();
                    this.selectFirst();
                    break;

                case 'End':
                    e.preventDefault();
                    this.selectLast();
                    break;
            }
        }

        /**
         * Toggle palette open/close
         */
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }

        /**
         * Open palette
         */
        open() {
            this.container.hidden = false;
            this.isOpen = true;

            // Show default commands
            this.showDefaultCommands();

            // Focus input
            this.input.value = '';
            this.input.focus();

            // Prevent body scroll
            document.body.style.overflow = 'hidden';

            // Announce to screen readers
            this.announce('Command palette opened');
        }

        /**
         * Close palette
         */
        close() {
            this.container.hidden = true;
            this.isOpen = false;

            // Restore body scroll
            document.body.style.overflow = '';

            // Clear input
            this.input.value = '';
            this.results.innerHTML = '';

            // Announce to screen readers
            this.announce('Command palette closed');
        }

        /**
         * Show default commands (recent + all categories)
         */
        showDefaultCommands() {
            const recentCommands = window.sagaShortcuts?.getRecentCommands() || [];

            this.filteredCommands = this.commands;
            this.selectedIndex = 0;

            this.renderResults(recentCommands);
        }

        /**
         * Search commands with fuzzy matching
         */
        search(query) {
            const normalizedQuery = query.toLowerCase();

            // Fuzzy search
            const matches = this.commands
                .map(command => ({
                    command,
                    score: this.fuzzyScore(normalizedQuery, command)
                }))
                .filter(match => match.score > 0)
                .sort((a, b) => b.score - a.score)
                .map(match => match.command);

            this.filteredCommands = matches;
            this.selectedIndex = 0;

            this.renderResults();
        }

        /**
         * Calculate fuzzy match score
         */
        fuzzyScore(query, command) {
            const searchText = `${command.label} ${command.description || ''} ${command.category}`.toLowerCase();

            // Exact match bonus
            if (searchText.includes(query)) {
                return 100 + query.length;
            }

            // Fuzzy match
            let score = 0;
            let lastIndex = -1;
            let consecutiveMatches = 0;

            for (const char of query) {
                const index = searchText.indexOf(char, lastIndex + 1);

                if (index === -1) {
                    return 0; // No match
                }

                // Score based on position and consecutiveness
                if (index === lastIndex + 1) {
                    consecutiveMatches++;
                    score += 5 + consecutiveMatches;
                } else {
                    consecutiveMatches = 0;
                    score += 1;
                }

                lastIndex = index;
            }

            return score;
        }

        /**
         * Render search results
         */
        renderResults(recentCommands = []) {
            this.results.innerHTML = '';

            // Show recent commands if no query
            if (recentCommands.length > 0 && this.input.value.trim() === '') {
                this.renderSection('Recent', recentCommands);
            }

            // Group filtered commands by category
            const grouped = this.groupByCategory(this.filteredCommands);

            Object.entries(grouped).forEach(([category, commands]) => {
                this.renderSection(this.formatCategoryName(category), commands);
            });

            // No results message
            if (this.filteredCommands.length === 0) {
                this.renderNoResults();
            }

            // Update ARIA
            this.results.setAttribute('aria-label', `${this.filteredCommands.length} commands found`);
        }

        /**
         * Render a section of commands
         */
        renderSection(title, commands) {
            const section = document.createElement('div');
            section.className = 'command-section';

            const heading = document.createElement('h3');
            heading.className = 'command-section__title';
            heading.textContent = title;

            const list = document.createElement('ul');
            list.className = 'command-section__list';
            list.setAttribute('role', 'group');
            list.setAttribute('aria-label', title);

            commands.forEach((command, index) => {
                const item = this.createCommandItem(command, index);
                list.appendChild(item);
            });

            section.appendChild(heading);
            section.appendChild(list);

            this.results.appendChild(section);
        }

        /**
         * Create command item element
         */
        createCommandItem(command, index) {
            const item = document.createElement('li');
            item.className = 'command-item';
            item.setAttribute('role', 'option');
            item.setAttribute('data-index', this.getGlobalIndex(command));

            if (this.getGlobalIndex(command) === this.selectedIndex) {
                item.classList.add('selected');
                item.setAttribute('aria-selected', 'true');
            }

            // Icon
            const icon = document.createElement('span');
            icon.className = 'command-item__icon';
            icon.textContent = command.icon || '⌘';

            // Content
            const content = document.createElement('div');
            content.className = 'command-item__content';

            const label = document.createElement('span');
            label.className = 'command-item__label';
            label.textContent = command.label;

            const description = document.createElement('span');
            description.className = 'command-item__description';
            description.textContent = command.description || '';

            content.appendChild(label);
            if (command.description) {
                content.appendChild(description);
            }

            // Shortcut
            const shortcut = document.createElement('kbd');
            shortcut.className = 'command-item__shortcut';
            shortcut.textContent = this.formatShortcut(command.keys);

            // Assemble
            item.appendChild(icon);
            item.appendChild(content);
            item.appendChild(shortcut);

            // Click handler
            item.addEventListener('click', () => {
                this.executeCommand(command);
            });

            // Hover handler
            item.addEventListener('mouseenter', () => {
                this.selectedIndex = this.getGlobalIndex(command);
                this.updateSelection();
            });

            return item;
        }

        /**
         * Get global index for command
         */
        getGlobalIndex(targetCommand) {
            return this.filteredCommands.findIndex(cmd => cmd.id === targetCommand.id);
        }

        /**
         * Render no results message
         */
        renderNoResults() {
            const message = document.createElement('div');
            message.className = 'command-palette__no-results';
            message.textContent = 'No commands found';
            message.setAttribute('role', 'status');

            this.results.appendChild(message);
        }

        /**
         * Group commands by category
         */
        groupByCategory(commands) {
            const grouped = {};

            commands.forEach(command => {
                const category = command.category || 'other';

                if (!grouped[category]) {
                    grouped[category] = [];
                }

                grouped[category].push(command);
            });

            return grouped;
        }

        /**
         * Format category name for display
         */
        formatCategoryName(category) {
            return category.charAt(0).toUpperCase() + category.slice(1);
        }

        /**
         * Format keyboard shortcut for display
         */
        formatShortcut(keys) {
            if (!keys) return '';

            // Replace Ctrl/Cmd with symbols
            let formatted = keys;

            if (sagaCommands.isMac) {
                formatted = formatted.replace(/Cmd/gi, '⌘');
                formatted = formatted.replace(/Alt/gi, '⌥');
                formatted = formatted.replace(/Shift/gi, '⇧');
                formatted = formatted.replace(/Ctrl/gi, '⌃');
            }

            return formatted.toUpperCase();
        }

        /**
         * Select next command
         */
        selectNext() {
            if (this.filteredCommands.length === 0) return;

            this.selectedIndex = (this.selectedIndex + 1) % this.filteredCommands.length;
            this.updateSelection();
        }

        /**
         * Select previous command
         */
        selectPrevious() {
            if (this.filteredCommands.length === 0) return;

            this.selectedIndex = this.selectedIndex === 0
                ? this.filteredCommands.length - 1
                : this.selectedIndex - 1;

            this.updateSelection();
        }

        /**
         * Select first command
         */
        selectFirst() {
            if (this.filteredCommands.length === 0) return;

            this.selectedIndex = 0;
            this.updateSelection();
        }

        /**
         * Select last command
         */
        selectLast() {
            if (this.filteredCommands.length === 0) return;

            this.selectedIndex = this.filteredCommands.length - 1;
            this.updateSelection();
        }

        /**
         * Update visual selection
         */
        updateSelection() {
            const items = this.results.querySelectorAll('.command-item');

            items.forEach((item, index) => {
                const itemIndex = parseInt(item.getAttribute('data-index'));

                if (itemIndex === this.selectedIndex) {
                    item.classList.add('selected');
                    item.setAttribute('aria-selected', 'true');

                    // Scroll into view
                    item.scrollIntoView({
                        block: 'nearest',
                        behavior: 'smooth'
                    });
                } else {
                    item.classList.remove('selected');
                    item.setAttribute('aria-selected', 'false');
                }
            });
        }

        /**
         * Execute selected command
         */
        executeSelected() {
            if (this.filteredCommands.length === 0) return;

            const command = this.filteredCommands[this.selectedIndex];
            this.executeCommand(command);
        }

        /**
         * Execute a command
         */
        executeCommand(command) {
            // Close palette
            this.close();

            // Execute via shortcuts handler
            if (window.sagaShortcuts) {
                window.sagaShortcuts.executeCommand(command);
            }
        }

        /**
         * Announce to screen readers
         */
        announce(message) {
            const announcer = document.querySelector('[aria-live="polite"]') ||
                            this.createAnnouncer();

            announcer.textContent = message;

            // Clear after announcement
            setTimeout(() => {
                announcer.textContent = '';
            }, 1000);
        }

        /**
         * Create ARIA live region for announcements
         */
        createAnnouncer() {
            const announcer = document.createElement('div');
            announcer.className = 'sr-only';
            announcer.setAttribute('aria-live', 'polite');
            announcer.setAttribute('aria-atomic', 'true');

            document.body.appendChild(announcer);

            return announcer;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new CommandPalette();
        });
    } else {
        new CommandPalette();
    }
})();
