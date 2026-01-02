# VNMAP - Vietnamese Administrative Map Application

> An interactive web application for exploring Vietnam's administrative divisions with the new 2025 administrative structure (63 → 34 provinces).

**Developed by:** Nguyễn Hải Long & Vũ Nguyễn Quang Anh  
**School:** Trường THCS và THPT Nguyễn Tất Thành, Đại học Sư phạm Hà Nội  
**Year:** 2025

---

## 📋 Table of Contents

- [Features](#features)
- [Project Structure](#project-structure)
- [Tech Stack](#tech-stack)
- [Installation](#installation)
- [Usage](#usage)
- [File Organization](#file-organization)
- [Development](#development)

---

## ✨ Features

### Main Features
- 🗺️ **Interactive Province Maps** - Detailed SVG-based maps for each province/city
- 📍 **Commune/Ward Exploration** - Click on administrative units to see details
- 🔊 **Text-to-Speech (TTS)** - Multi-language support (Vietnamese, English, Japanese, French, Chinese)
- 🔍 **Search Functionality** - Search for communes/wards by name
- 🖼️ **Image Gallery** - Auto-fetched images for provinces and communes
- 🌍 **Multi-language Support** - Google Translate integration
- 📱 **Responsive Design** - Works on desktop and mobile devices
- 🎯 **Minimap Preview** - Overview map with click-to-reset zoom feature

### Technical Features
- Real-time data fetching from MySQL database
- SVG-based interactive map rendering
- Zoom and pan controls
- Hover tooltips
- Modern glassmorphism UI design

---

## 📁 Project Structure

```
VNMAP/
│
├── 📄 Core Files
│   ├── index.html              # Main landing page
│   ├── map.html                # Detailed province map view
│   └── about.html              # About page
│
├── 🎨 Styles
│   └── css/
│       ├── style_1.css         # Map & UI styles
│       └── style_2.css         # Google Translate styles
│
├── 💻 JavaScript
│   └── js/
│       ├── script_1.js         # Main map application logic
│       ├── mapUtils.js         # Utility functions
│       └── mapMinimap.js       # Minimap functionality
│
├── 🔌 Backend
│   ├── api.php                 # Main API endpoint
│   ├── db_connect.php          # Database connection
│   ├── tts.php                 # Text-to-Speech service
│   └── update_handler.php      # Data update handler (admin)
│
├── 📊 Data
│   ├── data/                   # GeoJSON files & province data
│   │   ├── *.json              # Province boundary data
│   │   ├── tinh.json           # Province list
│   │   ├── xa.json             # Commune/ward data
│   │   └── *.jpg/png           # Map images & logos
│   └── databando/              # Province overview map images
│
├── 🔍 Services
│   └── geoserver/              # Node.js proxy for image search
│       └── geoview-proxy-server/
│           └── server.js       # Google Custom Search API proxy
│
└── 🔧 Admin Tools
    └── config.html             # Province data configuration page
```

---

## 🛠️ Tech Stack

### Frontend
- **HTML5** - Structure
- **Tailwind CSS** - Utility-first CSS framework
- **JavaScript (ES6+)** - Core functionality
- **Leaflet.js** - Map library (for overview map)
- **SVG** - Interactive map rendering

### Backend
- **PHP 7.4+** - Server-side logic
- **MySQL** - Database
- **Node.js + Express** - Proxy server for image search

### APIs & Services
- **Google Text-to-Speech API** - Multi-language TTS
- **Google Custom Search API** - Image search
- **Google Translate API** - Language translation

---

## 🚀 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Node.js 14+ (for geoserver proxy)
- Web server (Apache/Nginx)

### Setup Steps

1. **Clone/Download the project**
   ```bash
   cd /path/to/vnmap
   ```

2. **Configure Database**
   - Update `db_connect.php` with your database credentials
   - Import database schema (if provided)
   - Ensure database has `provinces` table with required columns

3. **Configure API Keys**
   - Update `tts.php` with Google TTS API key
   - Update `geoserver/geoview-proxy-server/.env` with Google Custom Search API key

4. **Start Proxy Server (Optional)**
   ```bash
   cd geoserver/geoview-proxy-server
   npm install
   node server.js
   ```

5. **Deploy to Web Server**
   - Upload files to web server
   - Ensure PHP has proper permissions
   - Configure web server to serve PHP files

---

## 📖 Usage

### For Users

1. **View Provinces**
   - Visit `index.html` to see the list of all provinces
   - Click on any province to view its detailed map

2. **Explore Map**
   - Click on communes/wards to see details
   - Use zoom controls (+/-) or mouse wheel
   - Drag to pan around the map
   - Click minimap to reset zoom

3. **Search**
   - Use the search bar to find communes/wards
   - Click on search results to highlight on map

4. **Text-to-Speech**
   - Click the play button next to province/commune info
   - Text will be read in the current language

### For Administrators

1. **Update Province Data**
   - Visit `config.html`
   - Select a province
   - Update fields (geography, economy, culture, etc.)
   - Save changes

---

## 📂 File Organization

### JavaScript Modules

| File | Purpose |
|------|---------|
| `script_1.js` | Main application logic (map initialization, interactions) |
| `mapUtils.js` | Utility functions (name cleaning, image fetching, gallery rendering) |
| `mapMinimap.js` | Minimap overlay functionality |

### CSS Files

| File | Purpose |
|------|---------|
| `style_1.css` | Map container styles, SVG path styles, tooltips, accordions, TTS buttons |
| `style_2.css` | Google Translate widget customization |

### Data Files

| Directory | Contents |
|-----------|----------|
| `data/` | GeoJSON files for province boundaries, commune data (xa.json), province list (tinh.json) |
| `databando/` | Province overview map images (used by minimap) |

---

## 🔧 Development

### Adding a New Province

1. Add GeoJSON file to `data/` folder (e.g., `tỉnh X.json`)
2. Add overview map image to `databando/` folder (e.g., `Bản đồ tỉnh X.jpg`)
3. Add province data to database via `config.html` or directly

### Customization

- **Styles**: Modify `css/style_1.css` and `css/style_2.css`
- **Map Colors**: Update SVG path styles in `style_1.css`
- **API Endpoints**: Modify `api.php` for custom data structure

---

## 🗂️ Related Projects

This repository also contains:
- **cashio/** - Calculator hacking utilities (separate project)
- **safegps/** - GPS tracking system (separate project)

---

## 📝 Notes

- The project addresses Vietnam's 2025 administrative reform (63 → 34 provinces)
- Designed for educational use (Geography, History, Civic Education)
- Uses UTF-8 encoding for Vietnamese characters
- All images are fetched dynamically from Google Custom Search API

---

## 📄 License

© 2025 VNMAP. All Rights Reserved.

---

## 👥 Credits

- **Data Source**: SafeSchool Vietnam
- **Map Data**: Administrative boundary data (GeoJSON format)
- **Images**: Google Custom Search API
- **TTS**: Google Cloud Text-to-Speech API

---

## 🔗 Links

- **Live Site**: [vnmap-safeschool.net](https://vnmap-safeschool.net)
- **API Endpoint**: `https://vnmap-safeschool.net/api.php`

---

**Last Updated:** January 2025

