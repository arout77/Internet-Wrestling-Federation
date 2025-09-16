document.addEventListener('DOMContentLoaded', () => {
    const notificationBadge = document.getElementById('notification-badge');

    // Function to fetch the notification count from the server
    async function checkNotifications() {
        // Only run if the notification badge element exists on the page
        if (!notificationBadge) {
            return;
        }

        try {
            const response = await fetch(`${baseUrl}notification/check`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                // Don't show errors for background tasks
                console.error('Failed to fetch notifications. Status:', response.status);
                return;
            }

            const data = await response.json();

            if (data.count > 0) {
                notificationBadge.textContent = data.count;
                notificationBadge.classList.remove('hidden');
            } else {
                notificationBadge.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }

    // Check for notifications immediately on page load
    checkNotifications();

    // Then check every 30 seconds
    setInterval(checkNotifications, 30000); 
});
