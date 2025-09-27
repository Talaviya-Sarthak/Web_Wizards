/**
 * Dashboard JavaScript - Drag & Drop, Accessibility, and Interactive Features
 * React Beats Compatible Dark Theme
 */

class Dashboard {
    constructor() {
        this.draggedElement = null;
        this.dropZones = [];
        this.widgets = [];
        this.init();
    }

    init() {
        this.setupAccessibility();
        this.setupDragAndDrop();
        this.setupKeyboardNavigation();
        this.setupNotifications();
        this.setupThemeToggle();
        this.setupCharts();
    }

    /**
     * Accessibility Setup
     */
    setupAccessibility() {
        // Add skip links
        this.addSkipLinks();
        
        // Setup ARIA live regions
        this.setupAriaLiveRegions();
        
        // Setup focus management
        this.setupFocusManagement();
        
        // Setup screen reader announcements
        this.setupScreenReaderSupport();
        
        // Setup high contrast mode detection
        this.setupHighContrastMode();
    }

    addSkipLinks() {
        const skipLinks = document.createElement('div');
        skipLinks.innerHTML = `
            <a href="#main-content" class="skip-link">Skip to main content</a>
            <a href="#navigation" class="skip-link">Skip to navigation</a>
            <a href="#dashboard" class="skip-link">Skip to dashboard</a>
        `;
        document.body.insertBefore(skipLinks, document.body.firstChild);
    }

    setupAriaLiveRegions() {
        const liveRegion = document.createElement('div');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.id = 'aria-live-region';
        liveRegion.className = 'sr-only';
        document.body.appendChild(liveRegion);
    }

    setupFocusManagement() {
        // Trap focus in modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                const modal = document.querySelector('.modal.show');
                if (modal) {
                    this.trapFocus(e, modal);
                }
            }
        });
    }

    trapFocus(e, container) {
        const focusableElements = container.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (e.shiftKey) {
            if (document.activeElement === firstElement) {
                lastElement.focus();
                e.preventDefault();
            }
        } else {
            if (document.activeElement === lastElement) {
                firstElement.focus();
                e.preventDefault();
            }
        }
    }

    setupScreenReaderSupport() {
        // Announce page changes
        this.announceToScreenReader = (message) => {
            const liveRegion = document.getElementById('aria-live-region');
            if (liveRegion) {
                liveRegion.textContent = message;
                setTimeout(() => {
                    liveRegion.textContent = '';
                }, 1000);
            }
        };
    }

    setupHighContrastMode() {
        // Detect system high contrast preference
        if (window.matchMedia && window.matchMedia('(prefers-contrast: high)').matches) {
            document.body.classList.add('high-contrast');
        }

        // Listen for changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-contrast: high)').addEventListener('change', (e) => {
                if (e.matches) {
                    document.body.classList.add('high-contrast');
                } else {
                    document.body.classList.remove('high-contrast');
                }
            });
        }
    }

    /**
     * Drag and Drop Setup
     */
    setupDragAndDrop() {
        this.widgets = document.querySelectorAll('.dashboard-widget, .card');
        
        this.widgets.forEach(widget => {
            this.makeDraggable(widget);
        });

        // Setup drop zones
        this.setupDropZones();
    }

    makeDraggable(element) {
        element.setAttribute('draggable', 'true');
        element.setAttribute('role', 'button');
        element.setAttribute('tabindex', '0');
        element.setAttribute('aria-label', 'Draggable widget');

        element.addEventListener('dragstart', (e) => {
            this.draggedElement = element;
            element.classList.add('dragging');
            element.setAttribute('aria-grabbed', 'true');
            
            // Set drag effect
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', element.outerHTML);
            
            this.announceToScreenReader('Widget grabbed, looking for drop zone');
        });

        element.addEventListener('dragend', (e) => {
            element.classList.remove('dragging');
            element.setAttribute('aria-grabbed', 'false');
            this.draggedElement = null;
            
            // Remove drop zone highlights
            document.querySelectorAll('.drop-target').forEach(zone => {
                zone.classList.remove('drop-target');
            });
        });

        // Keyboard drag and drop
        element.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.startKeyboardDrag(element);
            }
        });
    }

    startKeyboardDrag(element) {
        element.classList.add('dragging');
        element.setAttribute('aria-grabbed', 'true');
        this.announceToScreenReader('Keyboard drag mode activated. Use arrow keys to move, Enter to drop, Escape to cancel');
        
        // Highlight available drop zones
        document.querySelectorAll('.dropzone').forEach(zone => {
            zone.classList.add('drop-target');
        });
    }

    setupDropZones() {
        const dashboard = document.querySelector('.dashboard-grid');
        if (dashboard) {
            dashboard.classList.add('dropzone');
            dashboard.addEventListener('dragover', this.handleDragOver.bind(this));
            dashboard.addEventListener('drop', this.handleDrop.bind(this));
            dashboard.addEventListener('dragenter', this.handleDragEnter.bind(this));
            dashboard.addEventListener('dragleave', this.handleDragLeave.bind(this));
        }
    }

    handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    handleDragEnter(e) {
        e.preventDefault();
        e.target.closest('.dropzone')?.classList.add('drop-target');
    }

    handleDragLeave(e) {
        if (!e.target.closest('.dropzone')?.contains(e.relatedTarget)) {
            e.target.closest('.dropzone')?.classList.remove('drop-target');
        }
    }

    handleDrop(e) {
        e.preventDefault();
        const dropZone = e.target.closest('.dropzone');
        
        if (dropZone && this.draggedElement) {
            // Calculate new position
            const rect = dropZone.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            // Move element
            this.moveWidget(this.draggedElement, x, y);
            this.announceToScreenReader('Widget moved successfully');
        }
        
        dropZone?.classList.remove('drop-target');
    }

    moveWidget(widget, x, y) {
        // Update widget position
        widget.style.position = 'absolute';
        widget.style.left = x + 'px';
        widget.style.top = y + 'px';
        
        // Save position to localStorage
        this.saveWidgetPositions();
    }

    saveWidgetPositions() {
        const positions = {};
        this.widgets.forEach((widget, index) => {
            const rect = widget.getBoundingClientRect();
            positions[widget.id || `widget-${index}`] = {
                x: rect.left,
                y: rect.top
            };
        });
        localStorage.setItem('dashboard-positions', JSON.stringify(positions));
    }

    loadWidgetPositions() {
        const saved = localStorage.getItem('dashboard-positions');
        if (saved) {
            const positions = JSON.parse(saved);
            Object.keys(positions).forEach(widgetId => {
                const widget = document.getElementById(widgetId);
                if (widget && positions[widgetId]) {
                    widget.style.position = 'absolute';
                    widget.style.left = positions[widgetId].x + 'px';
                    widget.style.top = positions[widgetId].y + 'px';
                }
            });
        }
    }

    /**
     * Keyboard Navigation
     */
    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            // Global keyboard shortcuts
            if (e.altKey) {
                switch(e.key) {
                    case '1':
                        e.preventDefault();
                        document.getElementById('main-content')?.focus();
                        break;
                    case '2':
                        e.preventDefault();
                        document.getElementById('navigation')?.focus();
                        break;
                    case '3':
                        e.preventDefault();
                        document.getElementById('dashboard')?.focus();
                        break;
                }
            }

            // Escape key handling
            if (e.key === 'Escape') {
                this.handleEscape();
            }
        });
    }

    handleEscape() {
        // Close modals
        const openModal = document.querySelector('.modal.show');
        if (openModal) {
            this.closeModal(openModal);
            return;
        }

        // Cancel drag operations
        if (this.draggedElement) {
            this.draggedElement.classList.remove('dragging');
            this.draggedElement.setAttribute('aria-grabbed', 'false');
            this.draggedElement = null;
            document.querySelectorAll('.drop-target').forEach(zone => {
                zone.classList.remove('drop-target');
            });
        }
    }

    /**
     * Notification System
     */
    setupNotifications() {
        this.notificationContainer = document.createElement('div');
        this.notificationContainer.id = 'notification-container';
        document.body.appendChild(this.notificationContainer);
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.setAttribute('role', 'alert');
        notification.setAttribute('aria-live', 'assertive');
        
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close" aria-label="Close notification">&times;</button>
            </div>
        `;

        this.notificationContainer.appendChild(notification);

        // Show notification
        setTimeout(() => notification.classList.add('show'), 100);

        // Auto hide
        if (duration > 0) {
            setTimeout(() => this.hideNotification(notification), duration);
        }

        // Manual close
        notification.querySelector('.notification-close').addEventListener('click', () => {
            this.hideNotification(notification);
        });

        return notification;
    }

    hideNotification(notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    /**
     * Theme Toggle
     */
    setupThemeToggle() {
        const themeToggle = document.createElement('button');
        themeToggle.className = 'theme-toggle';
        themeToggle.innerHTML = '🌙';
        themeToggle.setAttribute('aria-label', 'Toggle theme');
        themeToggle.setAttribute('title', 'Toggle dark/light theme');

        // Add to navigation or create floating button
        const nav = document.querySelector('.navbar') || document.body;
        nav.appendChild(themeToggle);

        themeToggle.addEventListener('click', () => {
            this.toggleTheme();
        });
    }

    toggleTheme() {
        const currentTheme = document.body.getAttribute('data-theme');
        const newTheme = currentTheme === 'high-contrast' ? 'default' : 'high-contrast';
        
        document.body.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        const themeToggle = document.querySelector('.theme-toggle');
        themeToggle.innerHTML = newTheme === 'high-contrast' ? '☀️' : '🌙';
        
        this.showNotification(`Switched to ${newTheme} theme`, 'success', 2000);
    }

    /**
     * Charts Setup
     */
    setupCharts() {
        // Initialize charts with accessibility features
        const charts = document.querySelectorAll('.chart-canvas');
        charts.forEach(canvas => {
            this.initializeChart(canvas);
        });
    }

    initializeChart(canvas) {
        // Add ARIA labels and descriptions
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', 'Interactive chart');
        
        // Add keyboard navigation
        canvas.setAttribute('tabindex', '0');
        
        canvas.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                // Navigate through data points
                this.navigateChart(canvas, e.key === 'ArrowRight' ? 1 : -1);
            }
        });
    }

    navigateChart(canvas, direction) {
        // Implement chart navigation
        this.announceToScreenReader(`Chart navigation: ${direction > 0 ? 'next' : 'previous'} data point`);
    }

    /**
     * Modal Management
     */
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            
            // Focus first focusable element
            const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (firstFocusable) {
                firstFocusable.focus();
            }
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Logout Confirmation
     */
    showLogoutConfirmation() {
        const modal = document.createElement('div');
        modal.className = 'modal logout-modal show';
        modal.id = 'logout-modal';
        modal.setAttribute('aria-hidden', 'false');
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Confirm Logout</h2>
                    <button type="button" class="modal-close" aria-label="Close modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout?</p>
                    <p class="text-muted">You will need to login again to access your account.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancel-logout">Cancel</button>
                    <button type="button" class="btn btn-confirm-logout" id="confirm-logout">Yes, Logout</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        
        // Focus first button
        modal.querySelector('#cancel-logout').focus();
        
        // Event listeners
        modal.querySelector('#cancel-logout').addEventListener('click', () => {
            this.closeModal(modal);
        });
        
        modal.querySelector('#confirm-logout').addEventListener('click', () => {
            this.performLogout();
        });
        
        modal.querySelector('.modal-close').addEventListener('click', () => {
            this.closeModal(modal);
        });
        
        // Close on escape
        const escapeHandler = (e) => {
            if (e.key === 'Escape') {
                this.closeModal(modal);
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal(modal);
            }
        });
    }

    performLogout() {
        // Show loading state
        const confirmBtn = document.getElementById('confirm-logout');
        const originalText = confirmBtn.textContent;
        confirmBtn.innerHTML = '<span class="loading"></span> Logging out...';
        confirmBtn.disabled = true;
        
        // Announce to screen reader
        this.announceToScreenReader('Logging out...');
        
        // Redirect to logout
        window.location.href = 'logout.php';
    }

    closeModal(modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        
        // Return focus to trigger element
        const trigger = document.querySelector(`[data-modal="${modal.id}"]`);
        if (trigger) {
            trigger.focus();
        }
    }

    /**
     * Utility Methods
     */
    announceToScreenReader(message) {
        if (this.announceToScreenReader) {
            this.announceToScreenReader(message);
        }
    }

    // Public API
    getWidgets() {
        return this.widgets;
    }

    addWidget(widgetElement) {
        this.makeDraggable(widgetElement);
        this.widgets.push(widgetElement);
    }

    removeWidget(widgetElement) {
        const index = this.widgets.indexOf(widgetElement);
        if (index > -1) {
            this.widgets.splice(index, 1);
        }
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new Dashboard();
    
    // Load saved positions
    window.dashboard.loadWidgetPositions();
    
    // Load saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.body.setAttribute('data-theme', savedTheme);
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            themeToggle.innerHTML = savedTheme === 'high-contrast' ? '☀️' : '🌙';
        }
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Dashboard;
}
