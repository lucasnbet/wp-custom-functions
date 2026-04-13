# WordPress Custom Functions Plugin

A comprehensive WordPress plugin with advanced geolocation, HubSpot & WhatsApp integrations, performance optimizations, and accessibility improvements.

## ✨ Features

### 🌍 Advanced Geolocation
- Automatic country detection using multiple sources
- Intelligent caching for improved performance
- CDN compatibility (Cloudflare, Fastly, Azure, Qwilt)
- Browser locale fallback
- Admin debug tools

### 📞 HubSpot Integration
- Dynamic forms by country
- Lazy loading of scripts
- Automatic phone input synchronization
- Easy-to-use shortcodes
### 💬 WhatsApp Integration
- Popup with integrated HubSpot forms
- Customizable trigger buttons
- Multi-language support (ES, EN, PT)
- Configurable auto-close

### ⚡ Performance Optimizations
- LCP (Largest Contentful Paint) improvements
- jQuery Migrate removal
- Image optimization
- Critical resource preloading
- Emoji cleanup

### ♿ Accessibility
- ARIA roles correction
- Keyboard navigation improvements
- WPML/SmartMenus menu fixes
- High contrast styles

### 🔒 Security
- Optimized security headers
- Secure Permissions-Policy
- Consistent robots.txt
- Version number removal

### 🔗 UTM Manager
- UTM parameter persistence
- Cross-domain tracking
- Form field auto-population
- Link parameter propagation

### 🌐 WPML Integration
- Automatic language redirection by country
- Language selector for undetected countries
- Complete logging system
- Debug tools for administrators

## 📦 Installation

1. Upload the `wp-custom-functions` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin panel
3. No initial configuration required

## 🛠️ Usage

### Available Shortcodes

**Geolocation:**
- `[show_country]` - Display detected country
- `[show_country_flag]` - Display country flag
- `[show_country_info]` - Display country information

**HubSpot:**
- `[hubspot_form form_id="123"]` - Embed HubSpot form
- `[hubspot_cta guid="abc"]` - Display HubSpot CTA

**WhatsApp:**
- `[whatsapp_button]` - Display WhatsApp button
- `[whatsapp_float]` - Display floating WhatsApp button

**UTM Management:**
- `[utm_debug]` - Display UTM debugging information

### Configuration

The plugin includes a configuration module that allows you to enable/disable specific features:

