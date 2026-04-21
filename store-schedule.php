<?php
/**
 * Plugin Name: Store Schedule Manager
 * Description: Professional weekly schedule control for WooCommerce with live frontend countdown.
 * Version: 3.0
 * Author: Mazhar Ali
 */

if (!defined('ABSPATH')) exit;

/* ================================================================
   CONSTANTS
================================================================ */
define('SSM_TIMEZONE',   'America/Los_Angeles');
define('SSM_OPTION',     'ssm_store_schedule');
define('SSM_CUTOFF_MIN', 20); // minutes before close: orders stop

/* ================================================================
   1. REGISTER SETTINGS
================================================================ */
add_action('admin_init', 'ssm_register_settings');
function ssm_register_settings() {
    register_setting(
        'ssm_group',
        SSM_OPTION,
        ['sanitize_callback' => 'ssm_sanitize_schedule']
    );
}

function ssm_sanitize_schedule($input) {
    $days  = ssm_days();
    $clean = [];
    foreach ($days as $day) {
        $enabled = !empty($input[$day]['enabled']) ? 1 : 0;
        $open    = sanitize_text_field($input[$day]['open']  ?? '09:00');
        $close   = sanitize_text_field($input[$day]['close'] ?? '21:00');
        if (!preg_match('/^\d{2}:\d{2}$/', $open))  $open  = '09:00';
        if (!preg_match('/^\d{2}:\d{2}$/', $close)) $close = '21:00';
        $clean[$day] = ['enabled' => $enabled, 'open' => $open, 'close' => $close];
    }
    return $clean;
}

/* ================================================================
   2. ADMIN MENU & PAGE
================================================================ */
add_action('admin_menu', 'ssm_admin_menu');
function ssm_admin_menu() {
    add_menu_page(
        'Store Schedule',
        'Store Schedule',
        'manage_options',
        'store-schedule',
        'ssm_admin_page',
        'dashicons-clock',
        56
    );
}

function ssm_admin_page() {
    if (!current_user_can('manage_options')) return;
    $schedule = ssm_get_schedule();
    $days     = ssm_days();

    $tz  = new DateTimeZone(SSM_TIMEZONE);
    $now = new DateTime('now', $tz);
    $today_name = $now->format('l');
    $today_time = $now->format('H:i');
    ?>
    <div class="ssm-wrap">
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@600;700&display=swap" rel="stylesheet">
        <style>
        :root{
            --ink:#0f1117;--ink2:#3d4151;--ink3:#7a7f94;
            --bg:#f5f6fa;--card:#ffffff;--border:#e2e4ec;
            --accent:#1a56db;--accent-lite:#eef3ff;
            --green:#0f9d58;--green-lite:#e6f4ea;
            --red:#d93025;--red-lite:#fce8e6;
            --yellow:#f4a900;--yellow-lite:#fef9e7;
            --radius:14px;--shadow:0 2px 16px rgba(15,17,23,.07);
        }
        .ssm-wrap *{box-sizing:border-box;margin:0;padding:0;}
        .ssm-wrap{font-family:'DM Sans',sans-serif;color:var(--ink);padding:28px 0 60px;max-width:860px;}
        .ssm-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:32px;gap:20px;flex-wrap:wrap;}
        .ssm-header-left h1{font-family:'Syne',sans-serif;font-size:26px;font-weight:700;letter-spacing:-.4px;color:var(--ink);}
        .ssm-header-left p{color:var(--ink3);font-size:13.5px;margin-top:4px;}
        .ssm-clock-card{background:var(--ink);color:#fff;border-radius:var(--radius);padding:14px 22px;min-width:200px;text-align:center;}
        .ssm-clock-card .ssm-clock-time{font-family:'Syne',sans-serif;font-size:28px;font-weight:700;letter-spacing:1px;line-height:1;}
        .ssm-clock-card .ssm-clock-label{font-size:11px;letter-spacing:.8px;text-transform:uppercase;opacity:.55;margin-top:5px;}
        .ssm-clock-card .ssm-clock-day{font-size:13px;opacity:.75;margin-top:3px;}
        .ssm-status-bar{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;margin-bottom:28px;font-size:13.5px;font-weight:500;}
        .ssm-status-bar.open{background:var(--green-lite);color:var(--green);}
        .ssm-status-bar.closed{background:var(--red-lite);color:var(--red);}
        .ssm-status-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
        .ssm-status-bar.open .ssm-status-dot{background:var(--green);animation:ssm-pulse 1.6s infinite;}
        .ssm-status-bar.closed .ssm-status-dot{background:var(--red);}
        @keyframes ssm-pulse{0%,100%{opacity:1}50%{opacity:.3}}
        .ssm-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:24px;}
        .ssm-card-head{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
        .ssm-card-head h2{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;letter-spacing:.2px;text-transform:uppercase;color:var(--ink2);}
        .ssm-table{width:100%;border-collapse:collapse;}
        .ssm-table th{padding:10px 20px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--ink3);background:var(--bg);}
        .ssm-table td{padding:14px 20px;border-top:1px solid var(--border);vertical-align:middle;}
        .ssm-table tr.ssm-today{background:var(--accent-lite);}
        .ssm-table tr.ssm-today td:first-child{border-left:3px solid var(--accent);}
        .ssm-day-name{font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px;}
        .ssm-today-badge{font-size:9px;font-weight:700;letter-spacing:.7px;text-transform:uppercase;background:var(--accent);color:#fff;padding:2px 7px;border-radius:20px;}
        .ssm-toggle{position:relative;display:inline-block;width:42px;height:24px;}
        .ssm-toggle input{opacity:0;width:0;height:0;}
        .ssm-slider{position:absolute;cursor:pointer;inset:0;background:#d1d5e0;border-radius:24px;transition:.25s;}
        .ssm-slider:before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 1px 4px rgba(0,0,0,.2);}
        .ssm-toggle input:checked + .ssm-slider{background:var(--green);}
        .ssm-toggle input:checked + .ssm-slider:before{transform:translateX(18px);}
        .ssm-status-text{font-size:12px;color:var(--ink3);font-weight:500;margin-left:4px;transition:.2s;}
        .ssm-times{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .ssm-time-group{display:flex;flex-direction:column;gap:4px;}
        .ssm-time-group label{font-size:10px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--ink3);}
        .ssm-time-input{border:1.5px solid var(--border);border-radius:8px;padding:7px 11px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:500;color:var(--ink);background:#fff;transition:.2s;outline:none;width:105px;}
        .ssm-time-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,86,219,.12);}
        .ssm-time-sep{color:var(--ink3);font-size:18px;font-weight:300;padding-top:18px;}
        .ssm-closed-row td.ssm-times-cell{opacity:.35;pointer-events:none;}
        .ssm-cutoff-row td{padding:0 20px 16px;}
        .ssm-cutoff-note{background:var(--yellow-lite);border:1px solid #fde899;border-radius:8px;padding:10px 14px;font-size:12.5px;color:#7a5c00;display:flex;align-items:center;gap:8px;}
        .ssm-actions{display:flex;align-items:center;gap:14px;padding:0 2px;}
        .ssm-btn-save{background:var(--accent);color:#fff;border:none;border-radius:10px;padding:12px 30px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:.2s;letter-spacing:.1px;}
        .ssm-btn-save:hover{background:#1447bf;transform:translateY(-1px);box-shadow:0 4px 14px rgba(26,86,219,.3);}
        .ssm-btn-save:active{transform:translateY(0);}
        .ssm-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:20px 24px;}
        .ssm-info-item{display:flex;flex-direction:column;gap:3px;}
        .ssm-info-item .label{font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--ink3);}
        .ssm-info-item .value{font-size:14px;font-weight:500;color:var(--ink);}
        @media(max-width:640px){
            .ssm-table th:nth-child(3),.ssm-table td:nth-child(3){display:none;}
            .ssm-info-grid{grid-template-columns:1fr;}
        }
        </style>

        <div class="ssm-header">
            <div class="ssm-header-left">
                <h1>Store Schedule</h1>
                <p>Manage weekly opening hours — changes apply instantly to checkout.</p>
            </div>
            <div class="ssm-clock-card">
                <div class="ssm-clock-time" id="ssm-admin-clock"><?php echo esc_html($today_time); ?></div>
                <div class="ssm-clock-day"><?php echo esc_html($now->format('l, F j')); ?></div>
                <div class="ssm-clock-label">Los Angeles Time</div>
            </div>
        </div>

        <?php
        $today_data  = $schedule[$today_name] ?? [];
        $store_open  = false;
        $status_label = 'Closed today';
        if (!empty($today_data['enabled'])) {
            $date_str  = $now->format('Y-m-d');
            $open_dt   = new DateTime($date_str . ' ' . ($today_data['open']  ?? '09:00') . ':00', $tz);
            $close_dt  = new DateTime($date_str . ' ' . ($today_data['close'] ?? '21:00') . ':00', $tz);
            $cutoff_dt = clone $close_dt;
            $cutoff_dt->modify('-' . SSM_CUTOFF_MIN . ' minutes');

            if ($now >= $open_dt && $now <= $cutoff_dt) {
                $store_open   = true;
                $status_label = 'Open now · Closes ' . $close_dt->format('g:i A');
            } elseif ($now < $open_dt) {
                $diff_sec = $open_dt->getTimestamp() - $now->getTimestamp();
                $diff_h   = floor($diff_sec / 3600);
                $diff_m   = floor(($diff_sec % 3600) / 60);
                $opens_in = $diff_h > 0 ? "{$diff_h}h {$diff_m}m" : "{$diff_m}m";
                $status_label = 'Closed now · Opens today at ' . $open_dt->format('g:i A') . ' (in ' . $opens_in . ')';
            } else {
                $status_label = 'Closed · Order cutoff passed (' . $cutoff_dt->format('g:i A') . ')';
            }
        }
        ?>

        <div class="ssm-status-bar <?php echo $store_open ? 'open' : 'closed'; ?>">
            <div class="ssm-status-dot"></div>
            <?php echo esc_html($status_label); ?> &nbsp;·&nbsp;
            Timezone: <strong>&nbsp;America/Los_Angeles</strong>
            &nbsp;·&nbsp; Checkout cutoff: <strong>&nbsp;<?php echo SSM_CUTOFF_MIN; ?> min before close</strong>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields('ssm_group'); ?>

            <div class="ssm-card">
                <div class="ssm-card-head">
                    <h2>Weekly Opening Hours</h2>
                </div>
                <table class="ssm-table">
                    <thead>
                        <tr>
                            <th style="width:170px">Day</th>
                            <th style="width:140px">Status</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($days as $day):
                        $d        = $schedule[$day] ?? [];
                        $enabled  = !empty($d['enabled']);
                        $open_v   = $d['open']  ?? '09:00';
                        $close_v  = $d['close'] ?? '21:00';
                        $is_today = ($day === $today_name);
                        $row_class = $is_today ? 'ssm-today' : '';
                        if (!$enabled) $row_class .= ' ssm-closed-row';
                        // Cutoff time for display
                        $cutoff_display = date('g:i A', strtotime($close_v) - (SSM_CUTOFF_MIN * 60));
                    ?>
                        <tr class="<?php echo esc_attr(trim($row_class)); ?>" data-day="<?php echo esc_attr($day); ?>">
                            <td>
                                <div class="ssm-day-name">
                                    <?php echo esc_html($day); ?>
                                    <?php if ($is_today): ?><span class="ssm-today-badge">Today</span><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <label class="ssm-toggle">
                                    <input
                                        type="checkbox"
                                        name="<?php echo SSM_OPTION; ?>[<?php echo $day; ?>][enabled]"
                                        value="1"
                                        <?php checked(1, $enabled); ?>
                                        onchange="ssmToggleRow(this)"
                                    >
                                    <span class="ssm-slider"></span>
                                </label>
                                <span class="ssm-status-text"><?php echo $enabled ? 'Open' : 'Closed'; ?></span>
                            </td>
                            <td class="ssm-times-cell">
                                <div class="ssm-times">
                                    <div class="ssm-time-group">
                                        <label>Opens</label>
                                        <input class="ssm-time-input" type="time"
                                            name="<?php echo SSM_OPTION; ?>[<?php echo $day; ?>][open]"
                                            value="<?php echo esc_attr($open_v); ?>">
                                    </div>
                                    <span class="ssm-time-sep">→</span>
                                    <div class="ssm-time-group">
                                        <label>Closes</label>
                                        <input class="ssm-time-input" type="time"
                                            name="<?php echo SSM_OPTION; ?>[<?php echo $day; ?>][close]"
                                            value="<?php echo esc_attr($close_v); ?>">
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="ssm-cutoff-row">
                            <td colspan="3">
                                <?php if ($enabled): ?>
                                <div class="ssm-cutoff-note">
                                    ⚠️ Last order accepted at
                                    <strong><?php echo esc_html($cutoff_display); ?></strong>
                                    (<?php echo SSM_CUTOFF_MIN; ?> min before close)
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="ssm-card">
                <div class="ssm-card-head"><h2>Configuration</h2></div>
                <div class="ssm-info-grid">
                    <div class="ssm-info-item">
                        <span class="label">Timezone</span>
                        <span class="value">America/Los_Angeles (Pacific)</span>
                    </div>
                    <div class="ssm-info-item">
                        <span class="label">Checkout Cutoff</span>
                        <span class="value"><?php echo SSM_CUTOFF_MIN; ?> minutes before closing</span>
                    </div>
                    <div class="ssm-info-item">
                        <span class="label">Frontend Sync</span>
                        <span class="value">Auto — live countdown on product pages</span>
                    </div>
                    <div class="ssm-info-item">
                        <span class="label">Backend Validation</span>
                        <span class="value">WooCommerce add-to-cart + cart page hook</span>
                    </div>
                </div>
            </div>

            <div class="ssm-actions">
                <?php submit_button('Save Schedule', 'primary', 'submit', false, ['class' => 'ssm-btn-save']); ?>
            </div>
        </form>
    </div>

    <script>
    function ssmToggleRow(cb){
        const row   = cb.closest('tr');
        const label = row.querySelector('.ssm-status-text');
        if(cb.checked){
            row.classList.remove('ssm-closed-row');
            if(label) label.textContent = 'Open';
        } else {
            row.classList.add('ssm-closed-row');
            if(label) label.textContent = 'Closed';
        }
    }
    function ssmAdminClock(){
        const el = document.getElementById('ssm-admin-clock');
        if(!el) return;
        const t = new Date().toLocaleTimeString('en-US',{timeZone:'America/Los_Angeles',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false});
        el.textContent = t;
    }
    setInterval(ssmAdminClock, 1000);
    </script>
    <?php
}


/* ================================================================
   3. BACKEND VALIDATION — ADD TO CART
================================================================ */
add_filter('woocommerce_add_to_cart_validation', 'ssm_validate_cart', 10, 3);
function ssm_validate_cart($passed, $product_id, $qty) {
    $result = ssm_check_store_open();
    if ($result !== true) {
        wc_add_notice($result, 'error');
        return false;
    }
    return $passed;
}

/* ================================================================
   4. BACKEND VALIDATION — CART PAGE (block checkout if closed)
      Hooks into cart totals — shows notice & empties proceed button
================================================================ */
add_action('woocommerce_before_cart', 'ssm_cart_page_check');
add_action('woocommerce_before_checkout_form', 'ssm_cart_page_check');
function ssm_cart_page_check() {
    $result = ssm_check_store_open();
    if ($result !== true) {
        wc_add_notice($result, 'error');
    }
}

// Also block the "Proceed to Checkout" button on cart page
add_filter('woocommerce_order_button_html', 'ssm_block_checkout_button');
add_filter('woocommerce_proceed_to_checkout', 'ssm_maybe_hide_checkout_button');

function ssm_maybe_hide_checkout_button() {
    $result = ssm_check_store_open();
    if ($result !== true) {
        // Replace proceed button with closed notice
        echo '<div style="background:#fce8e6;border:1px solid #f5bfbb;color:#a0231a;padding:12px 16px;border-radius:8px;font-weight:600;text-align:center;margin-top:10px;">🔒 ' . esc_html($result) . '</div>';
        return; // do not proceed
    }
    // Store is open — show normal button
    wc_get_template('cart/proceed-to-checkout-button.php');
}

function ssm_block_checkout_button($button_html) {
    $result = ssm_check_store_open();
    if ($result !== true) {
        return '<button type="submit" class="button alt" disabled style="opacity:.5;cursor:not-allowed;pointer-events:none;">🔒 Store Closed</button>';
    }
    return $button_html;
}

/* ================================================================
   5. CORE STORE OPEN CHECK (shared by all hooks)
================================================================ */
function ssm_check_store_open() {
    $schedule = ssm_get_schedule();
    $tz       = new DateTimeZone(SSM_TIMEZONE);
    $now      = new DateTime('now', $tz);
    $day      = $now->format('l');
    $date_str = $now->format('Y-m-d');
    $day_data = $schedule[$day] ?? [];

    // Day closed
    if (empty($day_data['enabled'])) {
        return sprintf('Sorry, we are closed on %s. Please check our weekly schedule.', $day);
    }

    $open_dt   = new DateTime($date_str . ' ' . ($day_data['open']  ?? '09:00') . ':00', $tz);
    $close_dt  = new DateTime($date_str . ' ' . ($day_data['close'] ?? '21:00') . ':00', $tz);
    $cutoff_dt = clone $close_dt;
    $cutoff_dt->modify('-' . SSM_CUTOFF_MIN . ' minutes');

    if ($now < $open_dt) {
        return sprintf(
            "We're not open yet! Orders accepted from %s today.",
            $open_dt->format('g:i A')
        );
    }

    if ($now > $cutoff_dt) {
        return sprintf(
            'Sorry, order cutoff was %s (%d min before closing at %s). Please order tomorrow!',
            $cutoff_dt->format('g:i A'),
            SSM_CUTOFF_MIN,
            $close_dt->format('g:i A')
        );
    }

    return true; // store is open
}


/* ================================================================
   6. FRONTEND — PRODUCT PAGE live widget + button disable
================================================================ */
add_action('wp_footer', 'ssm_frontend_script');
function ssm_frontend_script() {

    if (!is_product() && !is_shop() && !is_cart()) return;

    $schedule = ssm_get_schedule();
    $cutoff   = SSM_CUTOFF_MIN;
    ?>
<style>
/* ── Closed button states ── */
button.ome-atc-btn.single_add_to_cart_button.ssm-btn-closed,
button.ome-atc-btn.single_add_to_cart_button[disabled]{
    opacity:0.45 !important;
    cursor:not-allowed !important;
    pointer-events:none !important;
    background:#888 !important;
    border-color:#888 !important;
    box-shadow:none !important;
    transform:none !important;
    color:#fff !important;
    filter:grayscale(40%) !important;
}
/* Cart page checkout block */
.ssm-cart-closed-notice{
    background:#fce8e6;
    border:1px solid #f5bfbb;
    color:#a0231a;
    padding:14px 18px;
    border-radius:10px;
    font-weight:600;
    text-align:center;
    margin:12px 0;
    font-size:14px;
}
/* Status widget */
.ssm-status-widget{
    display:flex;align-items:center;gap:10px;
    margin-top:12px;padding:11px 16px;
    border-radius:10px;font-family:inherit;
    font-size:13.5px;font-weight:500;transition:background .3s;
}
.ssm-status-widget.open{background:#e6f4ea;color:#0f5c2e;border:1px solid #b7dfcb;}
.ssm-status-widget.closed{background:#fce8e6;color:#8b1c13;border:1px solid #f5c6c2;}
.ssm-wdot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.ssm-status-widget.open .ssm-wdot{background:#0f9d58;animation:ssmPulse 1.5s infinite;}
.ssm-status-widget.closed .ssm-wdot{background:#d93025;}
.ssm-wtxt{flex:1;line-height:1.3;}
.ssm-wclock{font-family:monospace;font-size:11px;font-weight:700;opacity:.6;white-space:nowrap;}
@keyframes ssmPulse{0%,100%{opacity:1}50%{opacity:.2}}
</style>

<script>
(function(){
'use strict';

var SCH    = <?php echo wp_json_encode($schedule); ?>;
var CUTOFF = <?php echo (int)$cutoff; ?>;
var IS_CART = <?php echo (is_cart() ? 'true' : 'false'); ?>;

/* ── LA time ── */
function getNow(){
    var now=new Date();
    var fmt=new Intl.DateTimeFormat('en-US',{
        timeZone:'America/Los_Angeles',
        weekday:'long',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false
    });
    var day='',H=0,M=0,S=0;
    fmt.formatToParts(now).forEach(function(p){
        if(p.type==='weekday') day=p.value;
        if(p.type==='hour')    H=parseInt(p.value,10);
        if(p.type==='minute')  M=parseInt(p.value,10);
        if(p.type==='second')  S=parseInt(p.value,10);
    });
    return {day:day,H:H,M:M,S:S,sec:H*3600+M*60+S};
}

function toSec(hhmm){
    var p=hhmm.split(':');
    return parseInt(p[0],10)*3600+parseInt(p[1],10)*60;
}

function fmt12(hhmm){
    var p=hhmm.split(':'),h=parseInt(p[0],10),m=parseInt(p[1],10);
    return (h%12||12)+':'+('0'+m).slice(-2)+(h>=12?' PM':' AM');
}

function fmtDur(sec){
    if(sec<=0) return '0s';
    var h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),s=sec%60;
    return (h>0?h+'h ':'')+((h>0||m>0)?m+'m ':'')+s+'s';
}

/* ── Status ── */
function getStatus(){
    var n=getNow(), data=SCH[n.day];
    if(!data||data.enabled!=1){
        return {open:false,reason:'day_closed',next:findNext(n.day,n.sec,false)};
    }
    var os=toSec(data.open), cs=toSec(data.close), cut=cs-CUTOFF*60;
    // Accept orders starting exactly at open time (no early cutoff for opening)
    if(n.sec<os)  return {open:false,reason:'before_open',diff:os-n.sec,openTime:data.open};
    // Stop orders 20 min before close
    if(n.sec>cut) return {open:false,reason:'after_cutoff',next:findNext(n.day,n.sec,false)};
    return {open:true,remaining:cut-n.sec,closeTime:data.close};
}

function findNext(today,nowSec,sameDay){
    var order=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var idx=order.indexOf(today);
    for(var i=sameDay?0:1;i<8;i++){
        var d=order[(idx+i)%7], data=SCH[d];
        if(data&&data.enabled==1){
            var os=toSec(data.open);
            var cut=toSec(data.close)-CUTOFF*60;
            // Skip today if we're already past the order cutoff
            if(i===0&&nowSec>cut) continue;
            return {day:d,fmt:fmt12(data.open),inDays:i};
        }
    }
    return null;
}

function getMsg(st){
    if(st.open) return 'Open \u00b7 Closes '+fmt12(st.closeTime)+' \u00b7 Last order in '+fmtDur(st.remaining);
    if(st.reason==='before_open') return 'Closed \u00b7 Opens at '+fmt12(st.openTime)+' \u00b7 In '+fmtDur(st.diff);
    if(st.next){
        if(st.next.inDays===0) return 'Closed \u00b7 Opens today at '+st.next.fmt;
        if(st.next.inDays===1) return 'Closed \u00b7 Opens tomorrow at '+st.next.fmt;
        return 'Closed \u00b7 Opens '+st.next.day+' at '+st.next.fmt;
    }
    return 'Store Closed';
}

/* ── Product page: Add to Cart button ── */
var _widget = null;

function getBtn(){
    // Exact selector matching: <button type="submit" class="ome-atc-btn single_add_to_cart_button">
    return document.querySelector('button.ome-atc-btn.single_add_to_cart_button');
}

function getWidget(btn){
    if(!_widget){
        _widget=document.createElement('div');
        _widget.className='ssm-status-widget closed';
        _widget.innerHTML='<span class="ssm-wdot"></span><span class="ssm-wtxt"></span><span class="ssm-wclock"></span>';
        // Insert ABOVE the button (before btn, not after)
        btn.parentNode.insertBefore(_widget, btn);
    }
    return _widget;
}

function updateProductPage(){
    var btn=getBtn();
    if(!btn) return;
    var st=getStatus(), w=getWidget(btn), n=getNow();

    // Live clock in widget
    w.querySelector('.ssm-wclock').textContent=
        ('0'+n.H).slice(-2)+':'+('0'+n.M).slice(-2)+':'+('0'+n.S).slice(-2)+' LA';

    if(st.open){
        // OPEN — restore button
        btn.classList.remove('ssm-btn-closed');
        btn.removeAttribute('disabled');
        btn.style.pointerEvents='';
        btn.style.opacity='';
        btn.style.cursor='';
        if(btn.dataset.origHtml) btn.innerHTML=btn.dataset.origHtml;
        w.className='ssm-status-widget open';
        w.querySelector('.ssm-wtxt').textContent=getMsg(st);
    } else {
        // CLOSED — fully block button
        if(!btn.dataset.origHtml) btn.dataset.origHtml=btn.innerHTML;
        btn.classList.add('ssm-btn-closed');
        btn.setAttribute('disabled','disabled');
        btn.style.pointerEvents='none';
        btn.style.opacity='0.45';
        btn.style.cursor='not-allowed';
        btn.innerHTML='\uD83D\uDD12 Store Closed';
        w.className='ssm-status-widget closed';
        w.querySelector('.ssm-wtxt').textContent=getMsg(st);
    }
}

/* ── Cart page: block proceed to checkout ── */
var _cartNotice = null;

function updateCartPage(){
    var st=getStatus();

    // Find checkout button — WooCommerce default
    var checkoutBtn = document.querySelector(
        '.wc-proceed-to-checkout .checkout-button, '+
        'a.checkout-button, '+
        '.checkout-button'
    );

    if(!checkoutBtn) return;

    if(!st.open){
        // Disable checkout button
        checkoutBtn.style.pointerEvents='none';
        checkoutBtn.style.opacity='0.45';
        checkoutBtn.style.cursor='not-allowed';
        checkoutBtn.style.filter='grayscale(40%)';
        checkoutBtn.setAttribute('aria-disabled','true');

        // Show/update notice above button
        if(!_cartNotice){
            _cartNotice=document.createElement('div');
            _cartNotice.className='ssm-cart-closed-notice';
            checkoutBtn.parentNode.insertBefore(_cartNotice, checkoutBtn);
        }
        _cartNotice.textContent='\uD83D\uDD12 '+getMsg(st);

        // Block click on checkout button
        checkoutBtn.onclick=function(e){
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        };
    } else {
        // OPEN — restore
        checkoutBtn.style.pointerEvents='';
        checkoutBtn.style.opacity='';
        checkoutBtn.style.cursor='';
        checkoutBtn.style.filter='';
        checkoutBtn.removeAttribute('aria-disabled');
        checkoutBtn.onclick=null;
        if(_cartNotice){ _cartNotice.remove(); _cartNotice=null; }
    }
}

/* ── Block form submit — triple safety ── */
document.addEventListener('click', function(e){
    var st=getStatus();
    if(st.open) return;

    var btn=getBtn();
    if(btn && (e.target===btn || btn.contains(e.target))){
        e.preventDefault(); e.stopImmediatePropagation();
        return false;
    }

    // Cart checkout button click
    var cb=document.querySelector('.checkout-button');
    if(cb && (e.target===cb || cb.contains(e.target))){
        e.preventDefault(); e.stopImmediatePropagation();
        return false;
    }
}, true);

document.addEventListener('submit', function(e){
    var st=getStatus();
    if(st.open) return;
    var form=e.target;
    if(form && (
        form.classList.contains('cart') ||
        form.querySelector('button.ome-atc-btn') ||
        form.querySelector('.checkout-button')
    )){
        e.preventDefault(); e.stopImmediatePropagation();
        return false;
    }
}, true);

/* ── Init & tick ── */
function tick(){
    if(!IS_CART) updateProductPage();
    if(IS_CART)  updateCartPage();
}

if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', function(){ tick(); setInterval(tick,1000); });
} else {
    tick();
    setInterval(tick, 1000);
}

})();
</script>
    <?php
}


/* ================================================================
   7. HELPERS
================================================================ */
function ssm_days() {
    return ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
}

function ssm_get_schedule() {
    $saved = get_option(SSM_OPTION, []);
    $days  = ssm_days();

    // ── Default hours per your actual schedule ──
    // Monday    : CLOSED
    // Tuesday   : 9:00 AM – 5:00 PM
    // Wednesday : 9:00 AM – 5:00 PM
    // Thursday  : 9:00 AM – 7:00 PM
    // Friday    : 9:00 AM – 8:00 PM
    // Saturday  : 7:00 AM – 8:00 PM
    // Sunday    : 7:00 AM – 7:00 PM
    $day_defaults = [
        'Monday'    => ['enabled' => 0, 'open' => '09:00', 'close' => '17:00'],
        'Tuesday'   => ['enabled' => 1, 'open' => '09:00', 'close' => '17:00'],
        'Wednesday' => ['enabled' => 1, 'open' => '09:00', 'close' => '17:00'],
        'Thursday'  => ['enabled' => 1, 'open' => '09:00', 'close' => '19:00'],
        'Friday'    => ['enabled' => 1, 'open' => '09:00', 'close' => '20:00'],
        'Saturday'  => ['enabled' => 1, 'open' => '07:00', 'close' => '20:00'],
        'Sunday'    => ['enabled' => 1, 'open' => '07:00', 'close' => '19:00'],
    ];

    $schedule = [];
    foreach ($days as $day) {
        $default       = $day_defaults[$day] ?? ['enabled' => 0, 'open' => '09:00', 'close' => '21:00'];
        $schedule[$day] = array_merge($default, $saved[$day] ?? []);
    }
    return $schedule;
}
