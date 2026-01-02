# Files Review - Candidates for Deletion

This document lists files and folders that may be unused or unnecessary for the VNMAP project.

## 🗑️ DEFINITELY SAFE TO DELETE (Personal/Temp Files) delete 

### Root Level Temp Files
- `temp.cpp` - Temporary C++ file
- `temp.out` - Compiled output file
- `error_log` - Error log file (can regenerate)

### Archive/ZIP Files (Backups - safe to delete if you have backups elsewhere) delete 
- `api.vnmap-safeschool.net.zip` - ZIP backup of api.vnmap-safeschool.net folder
- `downloaded_site.zip` - Downloaded archive
- `map_app.zip` - Archive
- `map_exact_no_touch.zip` - Archive

### Personal Workspace (NOT part of VNMAP project) dont delete 
- `workspace/` - Entire folder (contains personal C++, Java, Lua files)
  - `app.js`, `app.out`, `c_prog.out`, `program.out`
  - `main.c`, `main.cpp`, `Main.java`
  - `script.lua`
  - `input.txt`
  - `neofetch/` folder

### Temporary Admin Folder dont delete 
- `tmp_admin/` - Entire folder (temp admin files)
  - `code.cpp`, `code.py`, `in.txt`

### Storage Folder (Test/Temp Files) delete 
- `storage/` - Entire folder (contains test HTML, images, duplicate tinh.json)
  - `1.html`, `test1.html` - Test HTML files
  - `1.png`, `2_1683333441-1683336141.webp`, `tancodien_cc1.jpg`, `unnamed (1).jpg`, `unnamed.jpg` - Test images
  - `tinh.json` - Duplicate (original is in data/)

### Empty Folders delete 
- `cgi-bin/` - Empty folder
- `tssp/` - Empty folder

---

## ⚠️ NEEDS REVIEW (May or May Not Be Used)

### Questionable Files/Folders (REVIEW NEEDED) dont delete 
- `abc.php` - File manager/editor (Station-X Omega) - NOT part of VNMAP core, might be used for admin
- `config.html` - ✅ ADMIN PAGE - Used for updating province data (calls update_handler.php)
- `update_handler.php` - ✅ USED by config.html - Backend for updating province data
- `docs.html` - Documentation page (referenced in api.vnmap-safeschool.net/index.html but NOT in main site)
- `map_embed.html` - Embed version of map - Uses different Leaflet controls, may be unused/old version

### Separate Projects (Keep if they're separate projects, delete if not) dont delete except api.vnmap-safeschool.net and add
- `cashio/` - Calculator hacking utilities (separate project)
- `safegps/` - GPS tracking system (separate project)
- `add/` - Unknown folder (contains cwqr.js, index.html)
- `api/` - Separate API folder (different from api.php)
- `api.vnmap-safeschool.net/` - Duplicate/depl oyment folder?

### Leaflet Control Files (Used by map_embed.html, but may be unused) delete all(delete map-embed too!)
- `L.Control.MousePosition.css` - Used by map_embed.html
- `L.Control.MousePosition2.js` - Used by map_embed.html
- `L.Control.OSMGeocoder.css` - Used by map_embed.html (references geocoder.png)
- `L.Control.OSMGeocoder.js` - Used by map_embed.html
- `geocoder.png` - Referenced by L.Control.OSMGeocoder.css
  - *Note: index.html has custom inline mouse position control, these files are only for map_embed.html*

---

## ✅ KEEP (Core VNMAP Files)

### Essential Files
- `index.html` - Main landing page
- `map.html` - Detailed province map
- `about.html` - About page
- `api.php` - Backend API
- `db_connect.php` - Database connection
- `tts.php` - Text-to-Speech backend
- `css/style_1.css` - Map styles
- `css/style_2.css` - Google Translate styles
- `js/script_1.js` - Main map script
- `js/mapUtils.js` - Map utilities
- `js/mapMinimap.js` - Minimap functionality
- `data/` - All JSON files and images (essential)
- `databando/` - Province map images (used by minimap)
- `geoserver/` - Image search proxy server

---

## 📋 RECOMMENDATION

### Immediate Safe Deletes:
1. All `.zip` files (if you have backups)
2. `workspace/` folder
3. `tmp_admin/` folder
4. `storage/` folder
5. `temp.cpp`, `temp.out`
6. `error_log`
7. Empty folders: `cgi-bin/`, `tssp/`

### Review Before Deleting:
- `abc.php` - File manager (admin tool?)
- `config.html`, `docs.html`, `map_embed.html`
- `update_handler.php`
- `cashio/`, `safegps/` - Separate projects?
- `add/`, `api/`, `api.vnmap-safeschool.net/` folders
- Leaflet control files (L.Control.*)

---

**Total estimated space that can be freed:** ~50-100MB+ (depending on workspace size)

