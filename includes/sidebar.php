<?php
/**
 * Shared sidebar include — Certanity Robotics Dashboard
 * Usage: require 'includes/sidebar.php';
 * Expects $sidebar_active to be set before including:
 *   'dashboard' | 'new-simulation' | 'simulations' | 'billing' | 'settings'
 */
$_active = $sidebar_active ?? '';

$_user_name = htmlspecialchars($_SESSION['name'] ?? 'User');
$_initial   = strtoupper(substr(trim($_SESSION['name'] ?? 'U'), 0, 1));

// Map plan_id → display label (matches DB plan_id values)
$_plan_id_map = [1 => 'BASIS', 2 => 'PRO', 3 => 'MAX'];
$_plan_id     = $_SESSION['user_sub']['plan_id'] ?? null;
$_plan_label  = isset($_plan_id, $_plan_id_map[$_plan_id])
    ? $_plan_id_map[$_plan_id]
    : htmlspecialchars($_SESSION['user_sub']['plan_name'] ?? 'Free');

if (!function_exists('sb_simulator_launch_info')) {
    require_once __DIR__ . '/simulator_launch.php';
}
$_sim_launch_url = htmlspecialchars(sb_simulator_launch_info()['url'], ENT_QUOTES, 'UTF-8');

function _nav_item(string $href, string $label, string $icon_svg, string $key, string $active): string {
    $cls = $key === $active ? 'nav-item active' : 'nav-item';
    return "<a class=\"{$cls}\" href=\"{$href}\">{$icon_svg}<span>{$label}</span></a>";
}

// Icon SVGs — defined once here, reused below
$_ic_dashboard = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
$_ic_new       = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
$_ic_sims      = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="2"/><line x1="2" y1="12" x2="8" y2="12"/><line x1="16" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="8"/><line x1="12" y1="16" x2="12" y2="22"/><circle cx="4" cy="4" r="2"/><circle cx="20" cy="4" r="2"/><circle cx="4" cy="20" r="2"/><circle cx="20" cy="20" r="2"/></svg>';
$_ic_billing   = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
?>
<aside class="sidebar">

  <!-- Logo — single definition, no duplication across pages -->
  <div class="sidebar-logo">
    <svg width="28" height="28" viewBox="0 0 32 32" fill="none" aria-hidden="true">
      <!-- central hub -->
      <circle cx="16" cy="16" r="4" fill="#10256D"/>
      <!-- arms -->
      <rect x="6"  y="14" width="8"  height="4" rx="2" fill="#10256D" opacity=".75"/>
      <rect x="18" y="14" width="8"  height="4" rx="2" fill="#10256D" opacity=".75"/>
      <rect x="14" y="6"  width="4"  height="8" rx="2" fill="#10256D" opacity=".75"/>
      <rect x="14" y="18" width="4"  height="8" rx="2" fill="#10256D" opacity=".75"/>
      <!-- rotor hubs -->
      <circle cx="7"  cy="7"  r="3" fill="#EE9346"/>
      <circle cx="25" cy="7"  r="3" fill="#EE9346"/>
      <circle cx="7"  cy="25" r="3" fill="#EE9346"/>
      <circle cx="25" cy="25" r="3" fill="#EE9346"/>
    </svg>
    <span class="sidebar-logo-text">CERTANITY</span>
  </div>

  <!-- Nav links -->
  <?= _nav_item('dashboard.php',   'Dashboard',          $_ic_dashboard, 'dashboard',      $_active) ?>
  <?= _nav_item($_sim_launch_url . '" target="_blank', 'New Simulation',     $_ic_new,       'new-simulation', $_active) ?>
  <?= _nav_item('simulations.php', 'Simulations',        $_ic_sims,      'simulations',    $_active) ?>
  <?= _nav_item('billing.php',     'My Plan &amp; Billing', $_ic_billing, 'billing',        $_active) ?>

  <!-- Bottom user chip -->
  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?= $_initial ?></div>
      <div class="user-info">
        <div class="user-name"><?= $_user_name ?></div>
        <div class="user-role"><?= $_plan_label ?> plan</div>
      </div>
      <div class="user-actions">
        <a href="settings.php"
           class="user-action-btn <?= $_active === 'settings' ? 'active-icon' : '' ?>"
           title="Settings">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
          </svg>
        </a>
        <a href="auth/logout.php" class="user-action-btn logout" title="Logout">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </a>
      </div>
    </div>
  </div>
</aside>