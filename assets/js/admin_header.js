/**
 * Admin Header Logic
 * Handles navigation, notifications, theme toggle, and UI interactions.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Globals
    const body = document.body;
    const header = document.querySelector('.admin-header');
    
    // Get Base URL from a data attribute on the header or body, fallback to '/'
    const baseUrl = document.body.dataset.base || '/';

    /* =========================================
       1. NAVIGATION ACTIVE STATE & UNDERLINE
       ========================================= */
    const nav = document.querySelector('.admin-nav');
    const underline = document.querySelector('.nav-underline');

    if (nav && underline) {
        const links = [...nav.querySelectorAll('a[data-path]')];
        // Normalize current path by removing leading slash
        const currentPath = window.location.pathname.replace(/^\/+/, '');
        
        // Find active link: either exact match or starts with (for nested pages)
        // Adjust logic to be robust for subdirectory deployments
        let activeLink = links.find(a => {
            const path = a.dataset.path; // e.g., modules/staff/dashboard.php
            return currentPath.endsWith(path);
        });

        // Fallback: Check if any part of the path matches (simplified)
        if (!activeLink && links.length > 0) {
            // Default checking logic if strict match fails (optional)
        }

        if (activeLink) {
            activeLink.classList.add('active');
            moveUnderline(activeLink);
        }

        function moveUnderline(el) {
            if (!el) {
                underline.style.width = '0';
                return;
            }
            const linkRect = el.getBoundingClientRect();
            const navRect = nav.getBoundingClientRect();
            
            const left = linkRect.left - navRect.left;
            const width = linkRect.width;

            underline.style.left = `${left}px`;
            underline.style.width = `${width}px`;
        }

        // Update on resize
        window.addEventListener('resize', () => {
            if (activeLink) moveUnderline(activeLink);
        });
        
        // Hover effect (optional: move underline on hover)
        links.forEach(link => {
            link.addEventListener('mouseenter', () => moveUnderline(link));
        });
        nav.addEventListener('mouseleave', () => {
            if (activeLink) moveUnderline(activeLink);
            else underline.style.width = '0';
        });
    }

    /* =========================================
       2. USER DROPDOWN
       ========================================= */
    const profile = document.querySelector('.admin-profile');
    if (profile) {
        const btn = profile.querySelector('.profile-btn');
        const menu = profile.querySelector('.dropdown-menu');

        if (btn && menu) {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('show');
                const isExpanded = menu.classList.contains('show');
                btn.setAttribute('aria-expanded', isExpanded);
            });

            // Close on click outside
            window.addEventListener('click', (e) => {
                if (!profile.contains(e.target)) {
                    menu.classList.remove('show');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }

    /* =========================================
       3. MOBILE DRAWER / HAMBURGER
       ========================================= */
    const hamburger = document.querySelector('.hamburger');
    const drawer = document.querySelector('.drawer');
    const overlay = document.querySelector('.drawer-overlay'); // Optional overlay

    if (hamburger && drawer) {
        const toggleDrawer = () => {
            hamburger.classList.toggle('active');
            drawer.classList.toggle('open');
            body.classList.toggle('no-scroll', drawer.classList.contains('open'));
        };

        hamburger.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleDrawer();
        });

        // Close when clicking outside (window click)
        window.addEventListener('click', (e) => {
            if (drawer.classList.contains('open') && 
                !drawer.contains(e.target) && 
                !hamburger.contains(e.target)) {
                toggleDrawer();
            }
        });
        
        // Close on clean escape
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && drawer.classList.contains('open')) {
                toggleDrawer();
            }
        });
    }

    /* =========================================
       4. THEME TOGGLE
       ========================================= */
    // Logic: Check localStorage -> Apply class/attribute -> Save
    const themeBtn = document.querySelector('.theme-toggle');
    const THEME_KEY = 'ktx_theme';
    
    function applyTheme(theme) {
        body.dataset.theme = theme;
        localStorage.setItem(THEME_KEY, theme);
        
        // Update icons if needed (handled by CSS usually via opacity)
    }

    // Init
    const savedTheme = localStorage.getItem(THEME_KEY) || 'light';
    applyTheme(savedTheme);

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const current = body.dataset.theme === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });
    }

    /* =========================================
       5. HEADER SHADOW ON SCROLL
       ========================================= */
    if (header) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    /* =========================================
       6. REAL-TIME NOTIFICATIONS
       ========================================= */
    const notifyWrap = document.querySelector('.notify');
    if (notifyWrap) {
        const notifyBtn = notifyWrap.querySelector('.notify-btn');
        const notifyMenu = notifyWrap.querySelector('.notify-menu');
        const notifyList = notifyWrap.querySelector('.notify-list');
        const notifyDot = notifyWrap.querySelector('.dot');
        const markReadBtn = notifyWrap.querySelector('.mark-read');

        let isNotifyOpen = false;

        // Toggle Menu
        notifyBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            isNotifyOpen = !isNotifyOpen;
            notifyMenu.classList.toggle('show', isNotifyOpen);
            
            if (isNotifyOpen) {
                // Determine logic: Mark read immediately or on explicit action?
                // User requirement: usually explicit or "Mark All Read" button.
                // Current logic from previous code: Mark all read when opened?
                // Improved: Keep 'unread' state until clicked or 'Mark all read' action.
                // But for simplicity of UX, usually opening = checked.
                markAllAsRead(); 
            }
        });

        // Close on outside click
        window.addEventListener('click', (e) => {
            if (isNotifyOpen && !notifyWrap.contains(e.target)) {
                isNotifyOpen = false;
                notifyMenu.classList.remove('show');
            }
        });

        if (markReadBtn) {
            markReadBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                markAllAsRead();
            });
        }

        // Fetch Logic
        async function fetchNotifications() {
            try {
                // Adjust path based on baseUrl
                const apiUrl = `${baseUrl}modules/api/get_notifications.php`;
                const res = await fetch(apiUrl, { cache: 'no-store' });
                if (!res.ok) throw new Error('Network response was not ok');
                
                const data = await res.json();
                renderNotifications(data);
            } catch (err) {
                console.warn('Failed to fetch notifications:', err);
                notifyList.innerHTML = '<li class="empty"><i class="fa-solid fa-triangle-exclamation"></i> Lỗi tải thông báo</li>';
            }
        }

        async function markAllAsRead() {
            try {
                const apiUrl = `${baseUrl}modules/api/mark_read_notifications.php`;
                await fetch(apiUrl, { method: 'POST' });
                // Re-fetch to update UI (remove unread classes)
                fetchNotifications();
            } catch (err) {
                console.error('Error marking read:', err);
            }
        }

        function renderNotifications(data) {
            if (!data || data.length === 0) {
                notifyList.innerHTML = '<li class="empty">Chưa có thông báo mới</li>';
                notifyDot.hidden = true;
                return;
            }

            // Deduplicate logic
            const unique = [];
            const seen = new Set();
            data.forEach(item => {
                // Robust ID checking
                const id = item.NotificationID || item.id || item.Id || `${item.Title}_${item.CreatedAt}`;
                if (!seen.has(id)) {
                    seen.add(id);
                    unique.push(item);
                }
            });

            let unreadCount = 0;
            const fragment = document.createDocumentFragment();

            unique.forEach(n => {
                const isUnread = (Number(n.IsRead) === 0);
                if (isUnread) unreadCount++;

                const li = document.createElement('li');
                li.className = `notify-item ${isUnread ? 'unread' : ''}`;
                
                // Format relative time or absolute
                const timeStr = new Date(n.CreatedAt).toLocaleString('vi-VN', {
                    hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit'
                });

                li.innerHTML = `
                    <div class="notify-icon"><i class="fa-regular fa-bell"></i></div>
                    <div class="notify-content">
                        <div class="notify-title">${escapeHtml(n.Title)}</div>
                        <div class="notify-message">${escapeHtml(n.Message)}</div>
                        <div class="notify-meta">
                            <span class="notify-time">${timeStr}</span>
                        </div>
                    </div>
                `;
                fragment.appendChild(li);
            });

            notifyList.innerHTML = '';
            notifyList.appendChild(fragment);
            notifyDot.hidden = (unreadCount === 0);
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;")
                       .replace(/</g, "&lt;")
                       .replace(/>/g, "&gt;")
                       .replace(/"/g, "&quot;")
                       .replace(/'/g, "&#039;");
        }

        // Init
        fetchNotifications();
        // Poll every 60s
        setInterval(fetchNotifications, 60000);
    }
});
