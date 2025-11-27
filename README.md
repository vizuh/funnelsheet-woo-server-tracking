# Funnelsheet – WooCommerce Server-Side Tracking (GA4 & GTM-SS)

**Capture 100% of your WooCommerce purchases using bulletproof server-side tracking.**

Google Analytics is missing 20-30% of your purchases thanks to ad blockers. This plugin captures every single transaction server-side, finally giving you the real numbers on what's actually working.

## 🎯 Features

- **100% Order Capture** - Server-side tracking bypasses all ad blockers and browser restrictions
- **Dual Destination Support** - Send events to GA4 Measurement Protocol or server-side GTM (sGTM)
- **Reliable Queue System** - Events are queued and retried with exponential backoff
- **Attribution Preservation** - Captures and sends UTM parameters, client IDs, and campaign data
- **Enhanced Conversions** - Automatically hashes and sends user data for GA4 enhanced conversions
- **Event Log Dashboard** - Monitor all events with status tracking and manual retry
- **Refund Tracking** - Automatically captures refund events
- **Action Scheduler Integration** - Uses WooCommerce's built-in scheduler for reliable processing

## 📋 Requirements

- WordPress 5.8 or higher
- WooCommerce 3.5 or higher
- PHP 7.4 or higher

## 🚀 Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **WooCommerce → Server Tracking** to configure settings

## ⚙️ Configuration

### GA4 Measurement Protocol Setup

1. Go to **WooCommerce → Server Tracking**
2. Select **GA4 Measurement Protocol** as destination type
3. Enter your GA4 Measurement ID (format: `G-XXXXXXXXXX`)
4. Get your API Secret from GA4:
   - Go to GA4 Admin → Data Streams → Choose your stream
   - Scroll to "Measurement Protocol API secrets"
   - Create a new secret or copy existing one
5. Click **Send Test Event** to verify configuration

### Server-Side GTM (sGTM) Setup

1. Go to **WooCommerce → Server Tracking**
2. Select **Server-Side GTM** as destination type
3. Enter your sGTM endpoint URL
4. (Optional) Add authorization header if required
5. Click **Send Test Event** to verify

### Advanced Settings

- **Track on Processing** - Send purchase event when order status changes to Processing
- **Track on Completed** - Send purchase event when order status changes to Completed
- **Max Retry Attempts** - Number of times to retry failed events (1-10)
- **Queue Processing Interval** - How often to process the queue (5/10/15 minutes)
- **Debug Mode** - Enable detailed logging for troubleshooting

## 📊 Event Log

View all tracked events at **WooCommerce → Tracking Events**:

- Filter by status (All, Pending, Sent, Failed)
- View event details and error messages
- Manually retry failed events
- Export events to CSV

## 🔌 Attribution Integration

This plugin automatically detects and sends attribution data stored in order meta:

- **ClickTrail Plugin** - Reads `_clicktrail_attribution` meta
- **Standard UTM Parameters** - Reads `_utm_source`, `_utm_medium`, `_utm_campaign`, etc.
- **GA4 Client ID** - Reads `_ga_client_id` meta
- **Facebook Click IDs** - Reads `_fbp` and `_fbc` meta

If no attribution data is found, the plugin generates a fallback client ID from the customer email.

## 🔧 How It Works

1. **Order Created** - WooCommerce processes an order
2. **Event Captured** - Plugin hooks into order status change and extracts all data
3. **Queued** - Event is stored in database queue with status "pending"
4. **Processed** - Action Scheduler runs every 5 minutes and processes pending events
5. **Sent** - Events are sent to GA4 or sGTM via HTTP request
6. **Retry** - Failed events are retried with exponential backoff (2^attempts minutes)
7. **Logged** - Final status (sent/failed) is logged for monitoring

## 📦 What's Tracked

### Purchase Events

- Transaction ID, value, currency
- Tax, shipping, coupon codes
- Line items with product details
- Customer information (hashed for privacy)
- Attribution data (UTMs, client IDs, etc.)

### Refund Events

- Transaction ID, refund value, currency
- Attribution data

## 🛠️ Developer Hooks

### Filters

```php
// Modify event data before queuing
add_filter('wc_sst_event_data', function($event_data, $order) {
    // Add custom data
    $event_data['custom_field'] = 'custom_value';
    return $event_data;
}, 10, 2);

// Modify whether order should be tracked
add_filter('wc_sst_should_track_order', function($should_track, $order) {
    // Custom logic
    return $should_track;
}, 10, 2);
```

### Actions

```php
// After event is queued
add_action('wc_sst_event_queued', function($event_id, $order_id) {
    // Custom logic
}, 10, 2);

// After event is sent successfully
add_action('wc_sst_event_sent', function($event_id) {
    // Custom logic
}, 10);
```

## ❓ FAQ

### Does this replace client-side tracking?

This plugin is designed to complement client-side tracking, not replace it. For best results, use both:
- **Client-side GA4** - Captures user behavior, pageviews, add-to-cart, etc.
- **Server-side purchases** - Ensures 100% purchase capture even with ad blockers

### Will this cause duplicate purchases in GA4?

The plugin uses the same transaction ID as your order number. GA4 automatically deduplicates events with the same transaction ID. However, you should:
1. Use server-side tracking for purchases **OR** client-side, not both
2. Configure your setup to avoid sending the same event twice

### How do I verify events are being sent?

1. Go to **WooCommerce → Tracking Events** to see event status
2. Use GA4 DebugView to see events in real-time
3. Enable Debug Mode in plugin settings for detailed logs

### What happens if GA4/sGTM is down?

Events are queued and will be retried automatically with exponential backoff. Failed events remain in the queue for manual retry.

## 📝 License

GPL v2 or later

## 🤝 Support

For issues or questions, please create an issue on GitHub or contact support.

## 🔄 Changelog

### 1.0.0
- Initial release
- GA4 Measurement Protocol support
- Server-side GTM support
- Event queue with retry logic
- Event log dashboard
- Attribution data capture
