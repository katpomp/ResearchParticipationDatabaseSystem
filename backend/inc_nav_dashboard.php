<?php
/**
 * Shared "Back to Dashboard" link + button styles.
 * Only shows the link when user is logged in (dashboard requires auth).
 * Always outputs CSS so pages can rely on .nav-dashboard if needed.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>
    .nav-dashboard {
        display: inline-block;
        margin-bottom: 1em;
        padding: 8px 16px;
        background: #333;
        color: #fff !important;
        text-decoration: none;
        border-radius: 4px;
        font-size: 14px;
    }
    .nav-dashboard:hover {
        background: #555;
        color: #fff !important;
    }
    .nav-login { display: inline-block; margin-bottom: 1em; }
</style>
<?php if (isset($_SESSION['user_id'])): ?>
<p><a href="dashboard.php" class="nav-dashboard">&larr; Back to Dashboard</a></p>
<?php else: ?>
<p class="nav-login"><a href="login.php">Log in</a> to access the dashboard.</p>
<?php endif; ?>
