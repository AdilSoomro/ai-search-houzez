# AI Search for Houzez

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://houzi-ai.booleanbites.com)
[![WordPress](https://img.shields.io/badge/WordPress-5.9%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-8892BF.svg)](https://php.net)
[![Theme](https://img.shields.io/badge/Theme-Houzez-orange.svg)](https://themeforest.net/item/houzez-real-estate-wordpress-theme/15752549)

**AI Search for Houzez** transforms your real estate website into an intelligent, conversational platform powered by cutting-edge natural language AI. Visitors can discover properties simply by describing what they want in plain words (e.g. *"Modern 4-bedroom villa with a pool in Coconut Grove under $2.5M"*), instantly translating conversational intent into structured Houzez taxonomy queries.

🌐 **Live Demo:** [houzi-ai.booleanbites.com](https://houzi-ai.booleanbites.com/)  
📱 **Mobile App Ecosystem:** [Houzi Flutter App](https://houzi.booleanbites.com)  
🏢 **Compatible Theme:** [Houzez Real Estate Theme](https://themeforest.net/item/houzez-real-estate-wordpress-theme/15752549)

---

## ✨ Features

- **Natural Language Search (NLP):** Parses price ranges, bedroom/bathroom counts, property types, features, square footage, locations, and lease terms from plain English or natural queries.
- **Bring Your Own Key (BYOK):** Direct native integration with:
  - **OpenAI:** GPT-5-mini, GPT-4o-mini, GPT-4o
  - **Anthropic:** Claude 3.5 Haiku, Claude 3.5 Sonnet, Claude 4.5 Haiku
  - **Google:** Gemini 3 Flash, Gemini 2.0 Flash, Gemini Flash-Lite
  *No recurring middleman fees — pay only direct provider costs.*
- **Dual-Model Architecture:** Uses a high-reasoning Primary model for complex search extraction and an ultra-fast Lite model for rapid sub-tasks.
- **1-Click Elementor Homepage Installer:** Generates an "AI Smart Home" page featuring the AI Search Hero widget, customizable suggestion prompt pills, and an option to preserve existing homepage sections.
- **Intelligent Location Resolver:** Automatically maps colloquial area nicknames, landmarks, and abbreviations (e.g., *"SoHo"* → *"Manhattan"*, *"NYC"* → *"New York"*) to WordPress taxonomy terms.
- **Location Misses Log:** Tracks unmapped location queries entered by real users so admins can add instant aliases with one click.
- **Interactive Filter Tag Chips:** Displays active filters as clickable pills. Users can adjust parameters via direct dropdowns or remove them without re-calling the AI.
- **Live Split-Screen Results Panel:** Instant AJAX property cards with dynamic pagination, match counts, and a direct link to the Houzez full search page.
- **Rate Limiting & Safety Caps:** Per-user hourly search limits and site-wide daily limits to protect against scraping bots and keep API costs predictable.
- **5-Minute Transient Caching:** Fast responses for repeated queries while optimizing token usage.
- **100% Native Houzez Compatibility:** Directly queries Houzez types, statuses, cities, areas, states, countries, features, labels, and custom fields.

---

## 📋 Requirements

- **WordPress:** 5.9 or higher
- **PHP:** 7.4 or higher (8.0+ recommended)
- **Theme:** [Houzez Real Estate WordPress Theme](https://themeforest.net/item/houzez-real-estate-wordpress-theme/15752549)
- **Page Builder (Optional):** [Elementor](https://elementor.com/)
- **AI Provider API Key:** OpenAI, Anthropic, or Google Gemini

---

## 🚀 Installation & Setup

1. **Upload & Activate:**
   - In WordPress Admin, go to **Plugins → Add New → Upload Plugin**.
   - Choose `ai-search-houzez.zip` and click **Install Now**, then **Activate**.

2. **Configure AI Provider & API Key:**
   - Navigate to **AI Search → Configurations** in your WordPress sidebar.
   - Select your provider (**OpenAI**, **Anthropic**, or **Google Gemini**).
   - Enter your API Key and click **Test Connection** to verify credentials.

3. **Install AI Search Homepage:**
   - Click the **1-Click Homepage Installer** button in the settings panel.
   - Optionally check *"Preserve current homepage sections"* to keep your existing Elementor layout while prepending the AI Search Hero widget.

4. **Customize & Refine:**
   - Add custom location aliases or adjust rate-limiting thresholds as needed.

---

## 📁 Repository Structure

```
ai-search-houzez/
├── admin/
│   ├── class-ai-search-admin.php    # Admin dashboard controller
│   ├── css/ai-search-admin.css      # Admin styling
│   ├── js/ai-search-admin.js        # Admin AJAX interactions
│   └── partials/                    # Admin view templates
├── docs/
│   └── index.html                   # Offline documentation & guide
├── includes/
│   ├── ai/
│   │   ├── class-ai-gateway.php     # AI orchestration engine
│   │   ├── class-ai-rate-limiter.php# Safety & rate limiting
│   │   ├── class-location-resolver.php# Taxonomy alias resolver
│   │   └── providers/               # OpenAI, Claude, Gemini integrations
│   ├── class-ai-search-ajax.php     # Public & admin AJAX handlers
│   ├── class-ai-search-page-installer.php # 1-Click homepage installer
│   ├── class-ai-search-settings.php # Option schema & settings manager
│   └── elementor/
│       └── widgets/
│           └── class-widget-ai-search-hero.php # Elementor AI Hero widget
├── public/
│   ├── css/ai-search-public.css     # Frontend layout & split-panel styles
│   ├── js/ai-search-public.js       # Frontend query runner & chip handlers
│   └── templates/                   # Public template parts
├── templates/
│   └── default-homepage-elementor.json # 1-Click Elementor layout template
├── ai-search-houzez.php             # Main plugin bootstrap
├── description.html                 # Product description
└── uninstall.php                    # Clean uninstall routine
```

---

## 📝 Changelog

### [1.0.0] - 2026-08-22
- **Initial Release:** Initial public release of AI Search for Houzez plugin.
- **Natural Language Search:** Conversational property search parsing price ranges, bed/bath counts, locations, property types, and features.
- **Multi-Provider AI Gateway:** Native support for OpenAI (ChatGPT), Anthropic (Claude), and Google (Gemini) with Bring-Your-Own-Key (BYOK) architecture.
- **1-Click Elementor Homepage Installer:** Instant setup of "AI Smart Home" page with dynamic prompt pills and active section preservation.
- **Location Resolver & Aliases:** Intelligent mapping for city/area nicknames and abbreviations with a location misses log.
- **Interactive Filter Chips:** Clickable tag chips with direct dropdown adjustments and live split-screen property results.
- **Dual-Model Architecture:** Fast sub-tasks routing with Primary and Lite model configurations.
- **Rate Limiting & Safety:** Per-user hourly caps, site-wide daily limits, and smart 5-minute query caching.
- **Houzi Mobile App Ready:** Full compatibility with Houzez theme and the Houzi Flutter Mobile App.

---

## 📄 License & Support

- Developed by **[BooleanBites Ltd.](https://booleanbites.com/)**
- Product Support: [houzi.booleanbites.com](https://houzi.booleanbites.com)
- Documentation: Available offline in `/docs/index.html` or online at [houzi-docs.booleanbites.com](https://houzi-docs.booleanbites.com)
