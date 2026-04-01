document.addEventListener('DOMContentLoaded', () => {
  const notifyWrap = document.querySelector('.notify');
  if (!notifyWrap) {
    return;
  }

  const baseUrl = document.body.dataset.base || '/';
  const notifyBtn = notifyWrap.querySelector('.notify-btn');
  const notifyMenu = notifyWrap.querySelector('.notify-menu');
  const notifyList = notifyWrap.querySelector('.notify-list');
  const notifyDot = notifyWrap.querySelector('.dot');
  const markReadBtn = notifyWrap.querySelector('.mark-read');

  let isOpen = false;
  let socket = null;
  let reconnectTimer = null;

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

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
      console.warn('wave1 user notifications failed:', error);
    }
  }

  function renderNotifications(items, unreadCount) {
    if (!items.length) {
      notifyList.innerHTML = '<li class="empty">Chưa có thông báo mới</li>';
      notifyDot.hidden = true;
      return;
    }

    notifyList.innerHTML = items.slice(0, 5).map((item) => {
      const unread = Number(item.IsRead) === 0;
      const linkButton = item.Link
        ? `<button class="notify-view" type="button" data-link="${escapeHtml(item.Link)}">Xem</button>`
        : '';

      return `
        <li class="notify-item${unread ? ' unread' : ''}" data-id="${item.NotificationID}">
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
      if (!response.ok || !payload.ok || !payload.token || !payload.wsUrl) {
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
      console.warn('wave1 user realtime unavailable:', error);
    }
  }

  notifyBtn?.addEventListener('click', (event) => {
    event.stopPropagation();
    isOpen = !isOpen;
    notifyMenu.classList.toggle('show', isOpen);
    if (isOpen) {
      markNotificationsRead().catch(console.warn);
    }
  });

  markReadBtn?.addEventListener('click', (event) => {
    event.stopPropagation();
    markNotificationsRead().catch(console.warn);
  });

  window.addEventListener('wave1:notification-refresh', () => {
    fetchNotifications().catch(console.warn);
  });

  document.addEventListener('click', (event) => {
    if (isOpen && !notifyWrap.contains(event.target)) {
      isOpen = false;
      notifyMenu.classList.remove('show');
    }
  });

  fetchNotifications().catch(console.warn);
  connectRealtime().catch(console.warn);
  window.setInterval(() => {
    fetchNotifications().catch(console.warn);
  }, 60000);
});
