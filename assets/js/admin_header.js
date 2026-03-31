/**
 * Admin shell interactions with websocket-aware notifications.
 * Keeps the existing dropdown, drawer and theme behaviors
 * while upgrading notifications from polling-only to realtime-first.
 */

document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const header = document.querySelector('.admin-header');
  const baseUrl = body.dataset.base || '/';

  const nav = document.querySelector('.admin-nav');
  const underline = document.querySelector('.nav-underline');
  const profile = document.querySelector('.admin-profile');
  const hamburger = document.querySelector('.hamburger');
  const drawer = document.querySelector('.drawer');
  const overlay = document.querySelector('.drawer-overlay');
  const themeBtn = document.querySelector('.theme-toggle');
  const notifyWrap = document.querySelector('.notify');
  const THEME_KEY = 'ktx_theme';

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function applyTheme(theme) {
    body.dataset.theme = theme;
    localStorage.setItem(THEME_KEY, theme);
  }

  function moveUnderline(activeLink) {
    if (!nav || !underline || !activeLink) {
      if (underline) {
        underline.style.width = '0';
      }
      return;
    }

    const navRect = nav.getBoundingClientRect();
    const linkRect = activeLink.getBoundingClientRect();
    underline.style.left = `${linkRect.left - navRect.left}px`;
    underline.style.width = `${linkRect.width}px`;
  }

  if (nav && underline) {
    const links = [...nav.querySelectorAll('a[data-path]')];
    const currentPath = window.location.pathname.replace(/^\/+/, '');
    const activeLink = links.find((link) => currentPath.endsWith(link.dataset.path || ''));

    if (activeLink) {
      activeLink.classList.add('active');
      moveUnderline(activeLink);
    }

    window.addEventListener('resize', () => moveUnderline(activeLink));
    links.forEach((link) => {
      link.addEventListener('mouseenter', () => moveUnderline(link));
    });
    nav.addEventListener('mouseleave', () => moveUnderline(activeLink));
  }

  if (profile) {
    const btn = profile.querySelector('.profile-btn');
    const menu = profile.querySelector('.dropdown-menu');
    if (btn && menu) {
      btn.addEventListener('click', (event) => {
        event.stopPropagation();
        const show = !menu.classList.contains('show');
        menu.classList.toggle('show', show);
        btn.setAttribute('aria-expanded', String(show));
      });

      window.addEventListener('click', (event) => {
        if (!profile.contains(event.target)) {
          menu.classList.remove('show');
          btn.setAttribute('aria-expanded', 'false');
        }
      });
    }
  }

  if (hamburger && drawer) {
    const setDrawerOpen = (open) => {
      hamburger.classList.toggle('active', open);
      drawer.classList.toggle('open', open);
      overlay?.classList.toggle('open', open);
      body.classList.toggle('no-scroll', open);
    };

    hamburger.addEventListener('click', (event) => {
      event.stopPropagation();
      setDrawerOpen(!drawer.classList.contains('open'));
    });

    overlay?.addEventListener('click', () => setDrawerOpen(false));
    window.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('open')) {
        setDrawerOpen(false);
      }
    });
    window.addEventListener('click', (event) => {
      if (drawer.classList.contains('open') && !drawer.contains(event.target) && !hamburger.contains(event.target)) {
        setDrawerOpen(false);
      }
    });
  }

  applyTheme(localStorage.getItem(THEME_KEY) || 'light');
  themeBtn?.addEventListener('click', () => {
    applyTheme(body.dataset.theme === 'dark' ? 'light' : 'dark');
  });

  if (header) {
    window.addEventListener('scroll', () => {
      header.classList.toggle('scrolled', window.scrollY > 10);
    });
  }

  if (!notifyWrap) {
    return;
  }

  const notifyBtn = notifyWrap.querySelector('.notify-btn');
  const notifyMenu = notifyWrap.querySelector('.notify-menu');
  const notifyList = notifyWrap.querySelector('.notify-list');
  const notifyDot = notifyWrap.querySelector('.dot');
  const markReadBtn = notifyWrap.querySelector('.mark-read');

  let isOpen = false;
  let socket = null;
  let reconnectTimer = null;

  function formatTime(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return value || '';
    }
    return date.toLocaleString('vi-VN', {
      hour: '2-digit',
      minute: '2-digit',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  }

  function normalizeResponse(payload) {
    if (Array.isArray(payload)) {
      const unreadCount = payload.filter((item) => Number(item.IsRead) === 0).length;
      return { items: payload, unreadCount };
    }

    return {
      items: Array.isArray(payload?.items) ? payload.items : [],
      unreadCount: Number(payload?.unreadCount || 0),
    };
  }

  function renderNotifications(items, unreadCount) {
    if (!items.length) {
      notifyList.innerHTML = '<li class="empty">Chưa có thông báo mới</li>';
      notifyDot.hidden = true;
      return;
    }

    notifyList.innerHTML = items.slice(0, 8).map((item) => {
      const unread = Number(item.IsRead) === 0;
      const linkButton = item.Link
        ? `<button class="notify-view" type="button" data-link="${escapeHtml(item.Link)}">Xem</button>`
        : '';

      return `
        <li class="notify-item${unread ? ' unread' : ''}" data-id="${item.NotificationID}">
          <div class="notify-icon"><i class="fa-regular fa-bell"></i></div>
          <div class="notify-content">
            <div class="notify-title">${escapeHtml(item.Title)}</div>
            <div class="notify-message">${escapeHtml(item.Message)}</div>
            <div class="notify-meta">
              <span class="notify-time">${escapeHtml(formatTime(item.CreatedAt))}</span>
              ${linkButton}
            </div>
          </div>
        </li>
      `;
    }).join('');

    notifyDot.hidden = unreadCount === 0;

    notifyList.querySelectorAll('.notify-view').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.stopPropagation();
        const link = button.dataset.link;
        if (link) {
          window.location.href = link;
        }
      });
    });
  }

  async function fetchNotifications() {
    try {
      const response = await fetch(`${baseUrl}modules/api/get_notifications.php`, {
        cache: 'no-store',
        credentials: 'same-origin',
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const payload = await response.json();
      const { items, unreadCount } = normalizeResponse(payload);
      renderNotifications(items, unreadCount);
    } catch (error) {
      notifyList.innerHTML = '<li class="empty">Không tải được thông báo</li>';
      console.warn('wave1 admin notifications failed:', error);
    }
  }

  async function markNotificationsRead(notificationId = null) {
    const response = await fetch(`${baseUrl}modules/api/mark_read_notifications.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify(notificationId ? { notification_id: notificationId } : { mark_all: true }),
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    await response.json();
    await fetchNotifications();
  }

  async function connectRealtime() {
    try {
      const response = await fetch(`${baseUrl}modules/api/realtime_token.php`, {
        cache: 'no-store',
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !payload?.ok || !payload?.token || !payload?.wsUrl) {
        throw new Error(payload?.error || 'Realtime unavailable');
      }

      socket = new WebSocket(`${payload.wsUrl}?token=${encodeURIComponent(payload.token)}`);
      socket.addEventListener('message', () => {
        fetchNotifications().catch(console.warn);
      });
      socket.addEventListener('close', () => {
        window.clearTimeout(reconnectTimer);
        reconnectTimer = window.setTimeout(connectRealtime, 5000);
      });
      socket.addEventListener('error', () => {
        socket?.close();
      });
    } catch (error) {
      window.clearTimeout(reconnectTimer);
      reconnectTimer = window.setTimeout(connectRealtime, 5000);
      console.warn('wave1 admin realtime unavailable:', error);
    }
  }

  notifyBtn?.addEventListener('click', (event) => {
    event.stopPropagation();
    isOpen = !isOpen;
    notifyMenu?.classList.toggle('show', isOpen);
    if (isOpen) {
      markNotificationsRead().catch(console.warn);
    }
  });

  markReadBtn?.addEventListener('click', (event) => {
    event.stopPropagation();
    markNotificationsRead().catch(console.warn);
  });

  document.addEventListener('click', (event) => {
    if (isOpen && !notifyWrap.contains(event.target)) {
      isOpen = false;
      notifyMenu?.classList.remove('show');
    }
  });

  window.addEventListener('wave1:notification-refresh', () => {
    fetchNotifications().catch(console.warn);
  });

  fetchNotifications().catch(console.warn);
  connectRealtime().catch(console.warn);
  window.setInterval(() => {
    fetchNotifications().catch(console.warn);
  }, 60000);
});
