/**
 * Bitversity - Main JavaScript File
 * Interactive functionality for the learning platform
 */

// Global variables
let isLoggedIn = false;
let csrfToken = '';

// Determine base path for API calls based on current location
const getBasePath = () => {
    const path = window.location.pathname;
    if (path.includes('/public/')) {
        return '../';
    } else if (path.includes('/admin/')) {
        return '../';
    } else if (path.includes('/user/')) {
        return '../';
    } else {
        return './';
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Initialize the application
 */
function initializeApp() {
    // Check login status
    checkLoginStatus();
    
    // Initialize components
    initializeSearch();
    initializeCart();
    initializeWishlist();
    initializeForms();
    initializeTooltips();
    
    console.log('Bitversity application initialized');
}

/**
 * Check if user is logged in
 */
function checkLoginStatus() {
    // This would be set by PHP in the template
    isLoggedIn = document.body.dataset.loggedIn === 'true';
    csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/**
 * Initialize search functionality
 */
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    const suggestionsContainer = document.getElementById('searchSuggestions');
    
    if (searchInput && suggestionsContainer) {
        let searchTimeout;
        let currentFocus = -1;
        
        // Handle input events
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    fetchAutocomplete(query);
                }, 300);
            } else {
                hideSuggestions();
            }
            currentFocus = -1;
        });
        
        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
            const suggestions = suggestionsContainer.querySelectorAll('.search-suggestion');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentFocus++;
                if (currentFocus >= suggestions.length) currentFocus = 0;
                setActiveSuggestion(suggestions, currentFocus);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentFocus--;
                if (currentFocus < 0) currentFocus = suggestions.length - 1;
                setActiveSuggestion(suggestions, currentFocus);
            } else if (e.key === 'Enter' && currentFocus > -1) {
                e.preventDefault();
                if (suggestions[currentFocus]) {
                    suggestions[currentFocus].click();
                }
            } else if (e.key === 'Escape') {
                hideSuggestions();
                currentFocus = -1;
            }
        });
        
        // Handle focus events
        searchInput.addEventListener('focus', function() {
            const query = this.value.trim();
            if (query.length >= 2) {
                fetchAutocomplete(query);
            }
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                hideSuggestions();
                currentFocus = -1;
            }
        });
    }
}

/**
 * Fetch autocomplete suggestions
 */
async function fetchAutocomplete(query) {
    try {
        const response = await fetch(`${getBasePath()}api/search.php?action=autocomplete&q=${encodeURIComponent(query)}&limit=8`);
        const data = await response.json();
        
        if (data.success && data.suggestions) {
            displaySuggestions(data.suggestions);
        }
    } catch (error) {
        console.error('Error fetching autocomplete:', error);
        hideSuggestions();
    }
}

/**
 * Display search suggestions
 */
function displaySuggestions(suggestions) {
    const container = document.getElementById('searchSuggestions');
    if (!container) return;
    
    if (suggestions.length === 0) {
        hideSuggestions();
        return;
    }
    
    container.innerHTML = '';
    
    suggestions.forEach((suggestion, index) => {
        const item = document.createElement('div');
        item.className = 'search-suggestion';
        item.innerHTML = `
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center flex-grow-1">
                    <i class="fas fa-${getItemIcon(suggestion.type)} me-3 text-muted"></i>
                    <div class="flex-grow-1">
                        <div class="search-suggestion-title">${escapeHtml(suggestion.title)}</div>
                        <div class="search-suggestion-subtitle">${escapeHtml(suggestion.subtitle)}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="search-suggestion-type me-2">${suggestion.type}</span>
                    <small class="text-success fw-bold">${suggestion.price}</small>
                </div>
            </div>
        `;
        
        item.addEventListener('click', () => {
            document.getElementById('searchInput').value = suggestion.title;
            window.location.href = suggestion.url;
        });
        
        container.appendChild(item);
    });
    
    container.style.display = 'block';
}

/**
 * Set active suggestion for keyboard navigation
 */
function setActiveSuggestion(suggestions, index) {
    suggestions.forEach((item, i) => {
        if (i === index) {
            item.style.backgroundColor = '#f8f9fa';
            item.style.color = '#0d6efd';
        } else {
            item.style.backgroundColor = '';
            item.style.color = '';
        }
    });
}

/**
 * Hide search suggestions
 */
function hideSuggestions() {
    const container = document.getElementById('searchSuggestions');
    if (container) {
        container.style.display = 'none';
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Get icon for item type
 */
function getItemIcon(type) {
    const icons = {
        'book': 'book',
        'project': 'code',
        'game': 'gamepad'
    };
    return icons[type] || 'search';
}

/**
 * Initialize cart functionality
 */
function initializeCart() {
    // Add to cart buttons - handle both class variations
    document.addEventListener('click', function(e) {
        if (e.target.matches('.btn-add-cart, .btn-add-cart *, .add-to-cart, .add-to-cart *')) {
            const button = e.target.closest('.btn-add-cart, .add-to-cart');
            if (button) {
                e.preventDefault();
                console.log('Cart button clicked:', button.dataset);
                addToCart(button.dataset.itemId, button.dataset.itemType);
            }
        }
    });
}

/**
 * Add item to cart
 */
async function addToCart(itemId, itemType) {
    console.log('addToCart called with:', { itemId, itemType, isLoggedIn });
    
    if (!isLoggedIn) {
        showNotification('Please log in to add items to cart', 'error');
        return;
    }
    
    if (!itemId || !itemType) {
        showNotification('Invalid item data', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${getBasePath()}api/cart.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                action: 'add',
                item_id: itemId,
                item_type: itemType
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`${itemType.charAt(0).toUpperCase() + itemType.slice(1)} added to cart successfully!`, 'success');
            updateCartCount(data.cart_count);
            
            // Update button state to show item is in cart
            const buttons = document.querySelectorAll(`[data-item-id="${itemId}"][data-item-type="${itemType}"]`);
            buttons.forEach(button => {
                if (button.classList.contains('btn-add-cart') || button.classList.contains('add-to-cart')) {
                    button.innerHTML = '<i class="fas fa-check"></i> In Cart';
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-success');
                    button.disabled = true;
                }
            });
        } else {
            showNotification(data.message || 'Error adding item to cart', 'error');
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showNotification('Error adding item to cart', 'error');
    }
}

/**
 * Update cart count in navigation
 */
function updateCartCount(count) {
    console.log('Updating cart count to:', count);
    
    // Update badge in navbar
    const cartBadge = document.querySelector('.navbar .badge');
    if (cartBadge) {
        cartBadge.textContent = count;
        cartBadge.style.display = count > 0 ? 'inline' : 'none';
        console.log('Cart badge updated');
    } else {
        console.log('Cart badge not found - creating one');
        // If badge doesn't exist and count > 0, create it
        if (count > 0) {
            const cartLink = document.querySelector('a[href*="/user/cart.php"]');
            if (cartLink && !cartLink.querySelector('.badge')) {
                const badge = document.createElement('span');
                badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                badge.textContent = count;
                cartLink.appendChild(badge);
                console.log('Cart badge created');
            }
        }
    }
    
    // Also update any other cart count displays
    const cartCounts = document.querySelectorAll('.cart-count');
    cartCounts.forEach(el => {
        el.textContent = count;
        el.style.display = count > 0 ? 'inline' : 'none';
    });
}

/**
 * Initialize wishlist functionality
 */
function initializeWishlist() {
    document.addEventListener('click', function(e) {
        if (e.target.matches('.btn-wishlist, .btn-wishlist *')) {
            const button = e.target.closest('.btn-wishlist');
            if (button) {
                e.preventDefault();
                toggleWishlist(button.dataset.itemId, button.dataset.itemType, button);
            }
        }
    });
}

/**
 * Toggle wishlist item
 */
async function toggleWishlist(itemId, itemType, button) {
    if (!isLoggedIn) {
        showLoginModal();
        return;
    }
    
    try {
        const isActive = button.classList.contains('active');
        const action = isActive ? 'remove' : 'add';
        
        const response = await fetch(`${getBasePath()}api/wishlist.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                action: action,
                item_id: itemId,
                item_type: itemType
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            button.classList.toggle('active');
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('fas');
                icon.classList.toggle('far');
            }
            
            const message = action === 'add' ? 'Added to wishlist!' : 'Removed from wishlist!';
            showNotification(message, 'success');
        } else {
            showNotification(data.message || 'Error updating wishlist', 'error');
        }
    } catch (error) {
        console.error('Error updating wishlist:', error);
        showNotification('Error updating wishlist', 'error');
    }
}

/**
 * Initialize form enhancements
 */
function initializeForms() {
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // File upload preview
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', handleFilePreview);
    });
}

/**
 * Handle file upload preview
 */
function handleFilePreview(event) {
    const file = event.target.files[0];
    const preview = document.getElementById(event.target.dataset.preview);
    
    if (file && preview) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (file.type.startsWith('image/')) {
                preview.innerHTML = `<img src="${e.target.result}" class="img-fluid" alt="Preview">`;
            } else {
                preview.innerHTML = `<p class="text-muted">File selected: ${file.name}</p>`;
            }
        };
        reader.readAsDataURL(file);
    }
}

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    console.log('Showing notification:', message, type);
    
    // Remove existing notifications
    const existing = document.querySelectorAll('.notification-alert');
    existing.forEach(alert => alert.remove());
    
    // Create simple alert notification
    const alert = document.createElement('div');
    alert.className = `notification-alert alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    
    const iconClass = {
        'success': 'fa-check-circle text-success',
        'error': 'fa-exclamation-circle text-danger',
        'warning': 'fa-exclamation-triangle text-warning',
        'info': 'fa-info-circle text-info'
    }[type] || 'fa-info-circle text-info';
    
    alert.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${iconClass} me-2"></i>
            <div class="flex-grow-1"><strong>${message}</strong></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.appendChild(alert);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        if (alert && alert.parentNode) {
            alert.classList.remove('show');
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 300);
        }
    }, 4000);
}

/**
 * Get notification icon
 */
function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

/**
 * Show login modal
 */
function showLoginModal() {
    const modal = document.getElementById('loginModal');
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    } else {
        // Redirect to login page if modal doesn't exist
        window.location.href = '/auth/login.php';
    }
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Smooth scroll to element
 */
function scrollToElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

/**
 * Alias for showNotification for compatibility
 */
function showAlert(type, message) {
    showNotification(message, type);
}

// Export functions for use in other scripts
window.BitversityApp = {
    addToCart,
    toggleWishlist,
    showNotification,
    showAlert,
    formatCurrency,
    debounce,
};

// Make functions globally accessible
window.updateCartCount = updateCartCount;
window.showNotification = showNotification;
