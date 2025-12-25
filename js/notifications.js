// Lightweight notifications loader
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('notificationsContainer');
  const refreshBtn = document.getElementById('refreshNotificationsBtn');

  async function loadNotifications() {
    if (!container) return;
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</div>';
    try {
      const res = await fetch('/dbb-frontend/backend/api/notifications.php', {
        method: 'GET',
        credentials: 'include'
      });
      if (!res.ok) throw new Error('Failed to load notifications');
      const data = await res.json();
      renderNotifications(data.data || []);
    } catch (err) {
      container.innerHTML = `<div class="error">Error loading notifications: ${err.message}</div>`;
    }
  }

  function renderNotifications(items) {
    if (!container) return;
    if (!items || items.length === 0) {
      container.innerHTML = `
        <div class="no-notifications">
          <h3>No Notifications</h3>
          <p>You're all caught up! There are no notifications to display.</p>
        </div>`;
      return;
    }

    const list = document.createElement('ul');
    list.className = 'notification-list';
    items.forEach(n => {
      const li = document.createElement('li');
      li.className = 'notification-item';
      const title = document.createElement('div');
      title.className = 'notification-title';
      title.textContent = n.title || n.type || 'Notification';
      const body = document.createElement('div');
      body.className = 'notification-body';
      body.textContent = n.message || n.body || JSON.stringify(n);
      const meta = document.createElement('div');
      meta.className = 'notification-meta';
      meta.textContent = n.created_at ? new Date(n.created_at).toLocaleString() : '';
      li.appendChild(title);
      li.appendChild(body);
      li.appendChild(meta);
      list.appendChild(li);
    });

    container.innerHTML = '';
    container.appendChild(list);
  }

  if (refreshBtn) refreshBtn.addEventListener('click', loadNotifications);
  loadNotifications();
});